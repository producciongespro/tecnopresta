<?php
/**
 * ============================================================
 * ENDPOINT: Buscar usuario por cedula
 * ============================================================
 * Proposito: Retorna datos de un usuario por su cedula
 * para el formulario de asignacion de roles.
 *
 * Flujo:
 * 1) Busca la cedula en la tabla local usuarios.
 * 2) Consulta una sola vez el WS del MEP (ConsultaFuncionario).
 * 3) Si el usuario ya existe, sincroniza el nombre completo.
 * 4) Si no existe, lo crea (o reactiva) en usuarios con los
 *    datos del WS (nombre, correo, sexo, azure_id).
 * 5) Resuelve los centros educativos/codigos presupuestarios:
 *    primero el WS del MEP; si no hay, el rol activo; luego
 *    t_lista_blanca. Con varios centros exige seleccion manual.
 *
 * Seguridad:
 * - Valida sesion Azure
 * - Prepared statements
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../usuarioAzure.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/ws_mep.php';

$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion invalida']);
    exit;
}
/*
if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}
*/
$cedula = (isset($_GET['cedula']) && is_scalar($_GET['cedula'])) ? trim((string) $_GET['cedula']) : '';

if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debe proporcionar una cedula']);
    exit;
}

try {
    $conexionBD = BD::crearInstancia();

    // === 1. Buscar el usuario activo en la tabla local ===
    $sql = "SELECT id, cedula, nombre, correo FROM usuarios WHERE cedula = ? AND eliminado = 0 LIMIT 1";
    $stmt = $conexionBD->prepare($sql);
    $stmt->execute([$cedula]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // === 2. Consultar el WS del MEP una sola vez ===
    $cedulaWs = preg_replace('/\D/', '', $cedula);
    $registrosWs = [];
    $centrosWs = [];
    $ws = null;
    $wsError = '';
    try {
        $registrosWs = consultarFuncionarioWS($cedulaWs !== '' ? $cedulaWs : $cedula);
        $centrosWs = normalizarCentrosMep($registrosWs);
        $ws = $registrosWs[0] ?? null;
    } catch (Throwable $e) {
        $wsError = $e->getMessage();
    }

    $creado = false;

    if ($usuario) {
        // === Rama A: el usuario existe -> sincronizar nombre completo ===
        $nombreWs = '';
        if ($ws) {
            $nombreWs = trim(
                (string) ($ws['Nombre'] ?? '') . ' ' .
                (string) ($ws['Apellido1'] ?? '') . ' ' .
                (string) ($ws['Apellido2'] ?? '')
            );
        }

        $usuario['nombre_completo'] = $nombreWs !== '' ? $nombreWs : trim((string) $usuario['nombre']);

        // Sincroniza usuarios.nombre con el nombre completo del WS del MEP.
        // Solo se actualiza si difiere (comparacion normalizada) y si el WS
        // trae tantas palabras como el guardado, para nunca pisar apellidos
        // ya registrados con un nombre mas corto.
        $nombreBd = preg_replace('/\s+/', ' ', trim((string) $usuario['nombre']));
        $nombreWsNorm = preg_replace('/\s+/', ' ', $usuario['nombre_completo']);
        if ($nombreWsNorm !== ''
            && strcasecmp($nombreBd, $nombreWsNorm) !== 0
            && count(explode(' ', $nombreWsNorm)) >= count(explode(' ', $nombreBd))
        ) {
            try {
                $stmtUpd = $conexionBD->prepare(
                    'UPDATE usuarios SET nombre = ?, updated_at = NOW() WHERE id = ?'
                );
                $stmtUpd->execute([$nombreWsNorm, $usuario['id']]);
                $usuario['nombre'] = $nombreWsNorm;
            } catch (Exception $e) {
                // Sin sincronizar: se responde igual con el nombre del WS.
            }
        }
    } else {
        // === Rama B: el usuario no existe -> crear/reactivar con datos del WS ===
        if ($ws === null) {
            if ($wsError !== '') {
                http_response_code(503);
                echo json_encode([
                    'success' => false,
                    'message' => 'No se pudo consultar el servicio del MEP. Intente de nuevo.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado con cedula: ' . $cedula
                ]);
            }
            exit;
        }

        $nombreWs = preg_replace('/\s+/', ' ', trim(
            (string) ($ws['Nombre'] ?? '') . ' ' .
            (string) ($ws['Apellido1'] ?? '') . ' ' .
            (string) ($ws['Apellido2'] ?? '')
        ));

        if ($nombreWs === '') {
            echo json_encode([
                'success' => false,
                'message' => 'El servicio del MEP no devolvio el nombre para la cedula: ' . $cedula
            ]);
            exit;
        }

        $correoWs = trim((string) ($ws['Correo_Electronico_Oficial'] ?? ''));
        if ($correoWs === '') {
            echo json_encode([
                'success' => false,
                'message' => 'El funcionario no tiene correo registrado en el servicio del MEP'
            ]);
            exit;
        }

        $sexoWs = strtolower(trim((string) ($ws['Sexo'] ?? '')));
        $sexo = null;
        if ($sexoWs === 'm' || $sexoWs === 'masculino' || $sexoWs === 'hombre') {
            $sexo = 1;
        } elseif ($sexoWs === 'f' || $sexoWs === 'femenino' || $sexoWs === 'mujer') {
            $sexo = 2;
        }

        $azureId = (string) ($ws['Id_Empleado'] ?? '');

        // Registro inactivo previo (eliminado = 1) para reactivar.
        $stmtInactivo = $conexionBD->prepare(
            "SELECT id FROM usuarios WHERE cedula = ? AND eliminado = 1 LIMIT 1"
        );
        $stmtInactivo->execute([$cedula]);
        $rowInactivo = $stmtInactivo->fetch(PDO::FETCH_ASSOC);

        $nuevoId = 0;
        try {
            if ($rowInactivo) {
                $stmtReactivar = $conexionBD->prepare(
                    "UPDATE usuarios SET eliminado = 0, nombre = ?, correo = ?, sexo = ?, azure_id = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmtReactivar->execute([$nombreWs, $correoWs, $sexo, $azureId, $rowInactivo['id']]);
                $nuevoId = (int) $rowInactivo['id'];
            } else {
                $stmtInsert = $conexionBD->prepare(
                    "INSERT INTO usuarios (cedula, nombre, correo, azure_id, sexo, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
                );
                $stmtInsert->execute([$cedula, $nombreWs, $correoWs, $azureId, $sexo]);
                $nuevoId = (int) $conexionBD->lastInsertId();
            }
            $creado = true;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            // Conflicto de unicidad: cedula duplicada (carrera) o correo ya asociado.
            $stmtRe = $conexionBD->prepare(
                "SELECT id, eliminado FROM usuarios WHERE cedula = ? LIMIT 1"
            );
            $stmtRe->execute([$cedula]);
            $rowRe = $stmtRe->fetch(PDO::FETCH_ASSOC);

            if ($rowRe && (int) $rowRe['eliminado'] === 0) {
                $nuevoId = (int) $rowRe['id'];
            } else {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'El correo ' . $correoWs . ' ya esta asociado a otro usuario'
                ]);
                exit;
            }
        }

        $usuario = [
            'id' => $nuevoId,
            'cedula' => $cedula,
            'nombre' => $nombreWs,
            'correo' => $correoWs,
            'nombre_completo' => $nombreWs,
        ];
    }

    // === 3. Codigo presupuestario asociado al usuario ===
    // 1) Centros del WS del MEP.
    // 2) Si el WS falla o no devuelve centros: rol activo.
    // 3) Si no hay rol: t_lista_blanca.
    // Si falla cualquier consulta, se responde igual sin el dato (no se rompe la busqueda).
    $usuario['codigo_presu'] = '';
    $usuario['codigos_presu'] = [];
    $usuario['requiere_seleccion'] = false;
    $usuario['codigo_origen'] = 'ninguno';
    $usuario['ws_disponible'] = $wsError === '';
    $usuario['ws_sin_centros'] = $wsError === '' && empty($centrosWs);

    if (!empty($centrosWs)) {
        $usuario['codigos_presu'] = $centrosWs;
        $usuario['codigo_origen'] = 'ws';

        if (count($centrosWs) === 1) {
            $usuario['codigo_presu'] = $centrosWs[0]['codigo'];
        } else {
            // Con varios centros no se auto-selecciona ninguno: el
            // administrador debe elegir el centro en el formulario.
            $usuario['requiere_seleccion'] = true;
        }
    } else {
        try {
            $sqlCode = "SELECT codigo_presu FROM usuarios_roles
                        WHERE usuario_id = ? AND eliminado = 0 AND codigo_presu <> ''
                        ORDER BY id DESC LIMIT 1";
            $stmtCode = $conexionBD->prepare($sqlCode);
            $stmtCode->execute([$usuario['id']]);
            $codigoPresu = $stmtCode->fetchColumn();
            if ($codigoPresu !== false && $codigoPresu !== null) {
                $usuario['codigo_presu'] = trim((string) $codigoPresu);
                $usuario['codigo_origen'] = 'rol';
            }

            if ($usuario['codigo_presu'] === '') {
                $sqlLb = "SELECT codigo FROM t_lista_blanca
                          WHERE cedula = ? AND codigo <> ''
                          ORDER BY id_lista_blanca DESC LIMIT 1";
                $stmtLb = $conexionBD->prepare($sqlLb);
                $stmtLb->execute([$usuario['cedula']]);
                $codigoLb = $stmtLb->fetchColumn();
                if ($codigoLb !== false && $codigoLb !== null) {
                    $usuario['codigo_presu'] = trim((string) $codigoLb);
                    $usuario['codigo_origen'] = 'lista_blanca';
                }
            }
        } catch (Exception $e) {
            // Se omite el codigo presupuestario; la busqueda del usuario no se afecta.
        }

        if ($usuario['codigo_presu'] !== '') {
            $usuario['codigos_presu'] = [[
                'codigo' => $usuario['codigo_presu'],
                'dependencia' => '',
            ]];
        }
    }

    echo json_encode([
        'success' => true,
        'creado' => $creado,
        'usuario' => $usuario
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
