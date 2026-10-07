<?php
/**
 * ============================================================
 * ENDPOINT: Buscar usuario por cedula
 * ============================================================
 * Proposito: Retorna datos de un usuario por su cedula
 * para el formulario de asignacion de roles.
 *
 * Seguridad:
 * - Valida sesion Azure
 * - Solo Root
 * - Prepared statements
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../usuarioAzure.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/bd.php';

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

try {
    $conexionBD = BD::crearInstancia();

    $sql = "SELECT id, cedula, nombre, correo FROM usuarios WHERE cedula = ? AND eliminado = 0 LIMIT 1";
    $stmt = $conexionBD->prepare($sql);
    $stmt->execute([$cedula]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado con cedula: ' . $cedula]);
        exit;
    }

    // Codigo presupuestario asociado al usuario.
    // 1) Asignaciones vigentes (usuarios_roles).
    // 2) Respaldo historico por cedula (t_lista_blanca), para usuarios aun sin rol asignado.
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

    // Nombre completo (nombre + los 2 apellidos) desde el WS del MEP.
    // Si el WS no responde o no devuelve datos, se conserva el nombre de la BD.
    $usuario['nombre_completo'] = trim((string) $usuario['nombre']);
    if (class_exists('SoapClient')) {
        try {
            $client = new SoapClient('https://apps.mep.go.cr/wstecnopresta/servicio.asmx?WSDL', ['connection_timeout' => 10]);
            $resWs = $client->ConsultaFuncionario(['str_identificacion' => $cedula]);
            $datosWs = json_decode((string) ($resWs->ConsultaFuncionarioResult ?? ''), true);
            if (is_array($datosWs)) {
                $fWs = (isset($datosWs[0]) && is_array($datosWs[0])) ? $datosWs[0] : $datosWs;
                $nWs = trim((string) ($fWs['Nombre'] ?? ''));
                $a1Ws = trim((string) ($fWs['Apellido1'] ?? ''));
                $a2Ws = trim((string) ($fWs['Apellido2'] ?? ''));
                $fullWs = trim($nWs . ' ' . $a1Ws . ' ' . $a2Ws);
                if ($fullWs !== '') {
                    $usuario['nombre_completo'] = $fullWs;
                }
            }
        } catch (Throwable $e) {
            // Sin WS: se usa el nombre registrado en la BD.
        }
    }

    // Sincroniza usuarios.nombre con el nombre completo del WS del MEP.
    // Solo se actualiza si difiere (comparacion normalizada, sin escrituras
    // cuando ya coincide) y si el WS trae tantas palabras como el guardado,
    // para nunca pisar apellidos ya registrados con un nombre mas corto.
    // Si el UPDATE falla, la busqueda responde igual (no se rompe nada).
    $nombreBd = preg_replace('/\s+/', ' ', trim((string) $usuario['nombre']));
    $nombreWs = preg_replace('/\s+/', ' ', trim((string) $usuario['nombre_completo']));
    if ($nombreWs !== ''
        && strcasecmp($nombreBd, $nombreWs) !== 0
        && count(explode(' ', $nombreWs)) >= count(explode(' ', $nombreBd))
    ) {
        try {
            $stmtUpd = $conexionBD->prepare(
                'UPDATE usuarios SET nombre = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmtUpd->execute([$nombreWs, $usuario['id']]);
            $usuario['nombre'] = $nombreWs;
        } catch (Exception $e) {
            // Sin sincronizar: se responde igual con el nombre del WS.
        }
    }

    echo json_encode([
        'success' => true,
        'usuario' => $usuario
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
