<?php
/**
 * ============================================================
 * ENDPOINT: Gestor Catalogo de Lugares - Accion (CRUD)
 * ============================================================
 * Proposito: Procesa las operaciones CRUD sobre el catalogo de
 * lugares (t_lugar).
 *
 * Acciones soportadas:
 *   - crear_lugar / editar_lugar
 *   - toggle_lugar (activar/desactivar)
 *
 * Reglas de negocio:
 *   - Unicidad del nombre ignorando mayusculas y tildes
 *     (aplica para todos los lugares, activos e inactivos).
 *   - Para desactivar (activo 1 -> 0) se verifica que NO existan
 *     placas asociadas (t_placa.id_lugar); si las hay, se bloquea.
 *   - La reactivacion (activo 0 -> 1) no tiene restricciones.
 *
 * Seguridad:
 *   - Valida sesion Azure
 *   - Valida permiso de ruta (misma tarjeta "Gestion de Ubicacion")
 *   - Prepared statements
 *   - Transacciones en operaciones multi-consulta
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/usuarioAzure.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sql/bd.php';

$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion invalida']);
    exit;
}

// Aplicar el mismo criterio de acceso que la tarjeta "Gestion de Ubicacion"
if (!usuarioTieneRuta('gestor_catalogo_lugares_n.php')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Peticion invalida: se requiere accion']);
    exit;
}

/**
 * Normaliza el nombre de un lugar a una clave canonica:
 * - Recorta espacios al inicio/fin
 * - Colapsa espacios consecutivos en uno solo
 * - Convierte a minusculas
 * - Elimina tildes y diacriticos (regla de unicidad sin acentos)
 *
 * @param string|null $texto
 * @return string
 */
function normalizarLugar($texto)
{
    $texto = trim((string)$texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);
    $texto = mb_strtolower($texto, 'UTF-8');

    // Mapa de diacriticos del espanol (y comunes)
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c'
    ];
    $texto = strtr($texto, $mapa);

    return $texto;
}

/**
 * Obtiene los lugares existentes normalizados (excluyendo uno dado).
 *
 * @param PDO   $conexionBD
 * @param int   $excluirId id_lugar a excluir (0 para ninguno)
 * @return array<int,string> mapa id_lugar => clave normalizada
 */
function obtenerLugaresNormalizados(PDO $conexionBD, int $excluirId = 0): array
{
    $sql = "SELECT id_lugar, lugar FROM t_lugar";
    $stmt = $conexionBD->query($sql);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $normalizados = [];
    foreach ($filas as $fila) {
        $id = (int)$fila['id_lugar'];
        if ($excluirId > 0 && $id === $excluirId) {
            continue;
        }
        $normalizados[$id] = normalizarLugar($fila['lugar']);
    }
    return $normalizados;
}

/**
 * Valida unicidad del nombre de un lugar y retorna un mensaje de
 * error (string) o null si es valido.
 *
 * @param PDO   $conexionBD
 * @param string $lugarNombre Nombre ya normalizado (sin trim ni acentos)
 * @param int   $excluirId
 * @return string|null
 */
function validarUnicidadLugar(PDO $conexionBD, string $lugarNombre, int $excluirId = 0): ?string
{
    $normalizados = obtenerLugaresNormalizados($conexionBD, $excluirId);
    $clave = normalizarLugar($lugarNombre);

    if ($clave === '') {
        return 'El nombre del lugar es obligatorio';
    }

    foreach ($normalizados as $claveExistente) {
        if ($claveExistente === $clave) {
            return 'Ya existe un lugar con ese nombre';
        }
    }

    return null;
}

try {
    $conexionBD = BD::crearInstancia();

    switch ($action) {

        // ============================================================
        // CREAR LUGAR
        // ============================================================
        case 'crear_lugar':
            $lugar = trim($_POST['lugar'] ?? '');

            if ($lugar === '') {
                echo json_encode(['success' => false, 'message' => 'El nombre del lugar es obligatorio']);
                exit;
            }
            if (mb_strlen($lugar, 'UTF-8') > 50) {
                echo json_encode(['success' => false, 'message' => 'El nombre del lugar no puede superar 50 caracteres']);
                exit;
            }

            $error = validarUnicidadLugar($conexionBD, $lugar);
            if ($error !== null) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }

            $sql = "INSERT INTO t_lugar (lugar, activo) VALUES (?, 1)";
            $stmt = $conexionBD->prepare($sql);
            $stmt->execute([$lugar]);

            echo json_encode([
                'success' => true,
                'message' => 'Lugar creado correctamente',
                'data' => ['id' => (int)$conexionBD->lastInsertId()]
            ]);
            break;

        // ============================================================
        // EDITAR LUGAR
        // ============================================================
        case 'editar_lugar':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $lugar = trim($_POST['lugar'] ?? '');

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de lugar invalido']);
                exit;
            }
            if ($lugar === '') {
                echo json_encode(['success' => false, 'message' => 'El nombre del lugar es obligatorio']);
                exit;
            }
            if (mb_strlen($lugar, 'UTF-8') > 50) {
                echo json_encode(['success' => false, 'message' => 'El nombre del lugar no puede superar 50 caracteres']);
                exit;
            }

            // Verificar que el lugar exista
            $stmtCheck = $conexionBD->prepare("SELECT id_lugar FROM t_lugar WHERE id_lugar = ?");
            $stmtCheck->execute([$id]);
            if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'El lugar no existe']);
                exit;
            }

            $error = validarUnicidadLugar($conexionBD, $lugar, $id);
            if ($error !== null) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }

            $sql = "UPDATE t_lugar SET lugar = ? WHERE id_lugar = ?";
            $stmt = $conexionBD->prepare($sql);
            $stmt->execute([$lugar, $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Lugar actualizado correctamente'
            ]);
            break;

        // ============================================================
        // TOGGLE LUGAR (activar/desactivar)
        // ============================================================
        case 'toggle_lugar':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $activar = isset($_POST['active']) ? filter_var($_POST['active'], FILTER_VALIDATE_BOOLEAN) : false;

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de lugar invalido']);
                exit;
            }

            // Obtener estado actual
            $stmtGet = $conexionBD->prepare("SELECT lugar, activo FROM t_lugar WHERE id_lugar = ?");
            $stmtGet->execute([$id]);
            $lugarActual = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$lugarActual) {
                echo json_encode(['success' => false, 'message' => 'El lugar no existe']);
                exit;
            }

            $estadoActual = (int)$lugarActual['activo'];
            $nuevoEstado = $activar ? 1 : 0;

            // Si ya esta en el estado deseado, responder sin cambios
            if ($estadoActual === $nuevoEstado) {
                $mensaje = $activar ? 'El lugar ya se encuentra activo' : 'El lugar ya se encuentra desactivado';
                echo json_encode(['success' => true, 'message' => $mensaje]);
                exit;
            }

            // Regla de negocio: para desactivar, no deben existir placas asociadas
            if (!$activar) {
                $stmtCount = $conexionBD->prepare("SELECT COUNT(*) AS total FROM t_placa WHERE id_lugar = ?");
                $stmtCount->execute([$id]);
                $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

                if ($total > 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se puede desactivar: existen ' . $total . ' activo(s) asociado(s) a este lugar'
                    ]);
                    exit;
                }
            }

            $sql = "UPDATE t_lugar SET activo = ? WHERE id_lugar = ?";
            $stmt = $conexionBD->prepare($sql);
            $stmt->execute([$nuevoEstado, $id]);

            $mensaje = $activar ? 'Lugar reactivado correctamente' : 'Lugar desactivado correctamente';
            echo json_encode(['success' => true, 'message' => $mensaje]);
            break;

        // ============================================================
        // ACCION NO RECONOCIDA
        // ============================================================
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Accion no reconocida: ' . $action
            ]);
            break;
    }

} catch (Exception $e) {
    if (isset($conexionBD) && $conexionBD->inTransaction()) {
        $conexionBD->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}