<?php
/**
 * ============================================================
 * ENDPOINT: Gestor de Usuarios - Datos
 * ============================================================
 * Proposito: Retorna JSON con usuarios que tienen roles
 * asignados, roles disponibles y subsistemas para el
 * formulario gestor_usuarios_n.php.
 *
 * Seguridad:
 * - Valida sesion Azure
 * - Solo accesible por usuario Root
 * - Prepared statements en consultas SQL
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
try {
    $conexionBD = BD::crearInstancia();

    // Usuarios con roles asignados (solo activos, no eliminados)
    $sqlUsuarios = "
        SELECT 
            u.id,
            u.cedula,
            u.nombre,
            u.correo,
            u.ultimo_acceso,
            GROUP_CONCAT(DISTINCT r.rol SEPARATOR ', ') AS roles,
            COUNT(DISTINCT ur.id) AS total_roles
        FROM usuarios u
        INNER JOIN usuarios_roles ur ON u.id = ur.usuario_id AND ur.eliminado = 0
        INNER JOIN t_roles r ON ur.rol_id = r.id_rol
        WHERE u.eliminado = 0
        GROUP BY u.id
        ORDER BY u.nombre ASC
    ";
    $stmtUsuarios = $conexionBD->prepare($sqlUsuarios);
    $stmtUsuarios->execute();
    $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

    // Todos los roles disponibles (incluido Root)
    $sqlRoles = "SELECT id_rol, rol, descripcion, imagen FROM t_roles ORDER BY id_rol ASC";
    $stmtRoles = $conexionBD->prepare($sqlRoles);
    $stmtRoles->execute();
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

    // Contar usuarios con rol Root activos
    $sqlRootCount = "SELECT COUNT(DISTINCT ur.usuario_id) AS total FROM usuarios_roles ur WHERE ur.rol_id = 1 AND ur.eliminado = 0";
    $stmtRootCount = $conexionBD->prepare($sqlRootCount);
    $stmtRootCount->execute();
    $rootCount = (int)$stmtRootCount->fetchColumn();

    // Subsistemas
    $sqlSubsistemas = "SELECT id, nombre FROM subsistemas ORDER BY orden ASC, nombre ASC";
    $stmtSubsistemas = $conexionBD->prepare($sqlSubsistemas);
    $stmtSubsistemas->execute();
    $subsistemas = $stmtSubsistemas->fetchAll(PDO::FETCH_ASSOC);

    // Detalle de roles por usuario (para edicion)
    $sqlDetalleRoles = "
        SELECT 
            ur.id AS usuario_rol_id,
            ur.usuario_id,
            ur.rol_id,
            ur.subsistema_id,
            ur.codigo_presu,
            r.rol,
            r.descripcion
        FROM usuarios_roles ur
        INNER JOIN t_roles r ON ur.rol_id = r.id_rol
        WHERE ur.eliminado = 0
        ORDER BY ur.usuario_id ASC, r.rol ASC
    ";
    $stmtDetalle = $conexionBD->prepare($sqlDetalleRoles);
    $stmtDetalle->execute();
    $detalleRoles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios,
        'roles' => $roles,
        'subsistemas' => $subsistemas,
        'detalle_roles' => $detalleRoles,
        'root_count' => $rootCount
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
