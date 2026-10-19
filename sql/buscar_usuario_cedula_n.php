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

const WS_CONSULTA_FUNCIONARIO = 'https://apps.mep.go.cr/wstecnopresta/servicio.asmx?WSDL';

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
$cedula = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';

if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debe proporcionar una cedula']);
    exit;
}

/**
 * Consulta el WS del MEP y devuelve el primer registro del funcionario.
 * - Retorna null si el servicio responde vacio o no encuentra la cedula.
 * - Lanza excepcion si el transporte/SOAP falla (WS caido, sin extension, etc.).
 */
function consultarFuncionarioWS(string $cedula): ?array {
    if (!class_exists('SoapClient')) {
        throw new RuntimeException('La extension SOAP de PHP no esta habilitada');
    }

    $client = new SoapClient(WS_CONSULTA_FUNCIONARIO, [
        'connection_timeout' => 15,
        'exceptions' => true,
    ]);

    $resWs = $client->ConsultaFuncionario(['str_identificacion' => $cedula]);
    $datosWs = json_decode((string) ($resWs->ConsultaFuncionarioResult ?? ''), true);

    if (!is_array($datosWs) || empty($datosWs)) {
        return null;
    }

    $fWs = (isset($datosWs[0]) && is_array($datosWs[0])) ? $datosWs[0] : $datosWs;

    return is_array($fWs) ? $fWs : null;
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
    $ws = null;
    $wsError = '';
    try {
        $ws = consultarFuncionarioWS($cedulaWs !== '' ? $cedulaWs : $cedula);
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
    // 1) Asignaciones vigentes (usuarios_roles).
    // 2) Respaldo historico por cedula (t_lista_blanca).
    // 3) Datos del WS del MEP.
    // Si falla cualquier consulta, se responde igual sin el dato (no se rompe la busqueda).
    $usuario['codigo_presu'] = '';
    try {
        $sqlCode = "SELECT codigo_presu FROM usuarios_roles
                    WHERE usuario_id = ? AND eliminado = 0 AND codigo_presu <> ''
                    ORDER BY id DESC LIMIT 1";
        $stmtCode = $conexionBD->prepare($sqlCode);
        $stmtCode->execute([$usuario['id']]);
        $codigoPresu = $stmtCode->fetchColumn();
        if ($codigoPresu !== false && $codigoPresu !== null) {
            $usuario['codigo_presu'] = trim((string) $codigoPresu);
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
            }
        }
    } catch (Exception $e) {
        // Se omite el codigo presupuestario; la busqueda del usuario no se afecta.
    }

    if ($usuario['codigo_presu'] === '' && $ws) {
        $codigoWs = trim((string) ($ws['CentrosEducativosDondeTrabaja'] ?? ''));
        if ($codigoWs === '') {
            $codigoWs = trim((string) ($ws['Codigo_Presupuestario'] ?? ''));
        }
        if ($codigoWs !== '') {
            $usuario['codigo_presu'] = $codigoWs;
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
