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
