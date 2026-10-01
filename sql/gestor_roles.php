<?php
/**
 * ============================================================
 * ENDPOINT: Gestor de Roles - Datos
 * ============================================================
 * Proposito: Retorna JSON con roles, subsistemas, modulos,
 * formularios, acciones, permisos y roles_permisos para el
 * formulario gestor_roles_permisos_n.php (arbol de permisos).
 *
 * Soft delete:
 * - Excluye subsistemas, modulos, formularios y acciones con
 *   eliminado = 1, igual que el menu en sql/formulario_principal.php
 * - Los permisos se restringen a formularios no eliminados para que
 *   el contador de permisos asignados no incluya formularios caidos
 * - No modifica datos: solo SELECT
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

if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $conexionBD = BD::crearInstancia();

    // Roles
    $sqlRoles = "SELECT id_rol, rol, descripcion FROM t_roles ORDER BY id_rol ASC";
    $stmtRol = $conexionBD->prepare($sqlRoles);
    $stmtRol->execute();
    $roles = $stmtRol->fetchAll(PDO::FETCH_ASSOC);

    // Subsistemas (solo los no eliminados, para el arbol de permisos)
    $sqlSub = "SELECT id, nombre, descripcion FROM subsistemas WHERE eliminado = 0 ORDER BY orden ASC, nombre ASC";
    $stmtSub = $conexionBD->prepare($sqlSub);
    $stmtSub->execute();
    $subsistemas = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    // Modulos (solo los no eliminados, para el arbol de permisos)
    $sqlMod = "SELECT id, nombre, subsistema_id, descripcion FROM modulos WHERE eliminado = 0 ORDER BY subsistema_id ASC, orden ASC, nombre ASC";
    $stmtMod = $conexionBD->prepare($sqlMod);
    $stmtMod->execute();
    $modulos = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

    // Formularios (solo los no eliminados, para el arbol de permisos)
    $sqlForm = "SELECT id, modulo_id, nombre, descripcion FROM formularios WHERE eliminado = 0 ORDER BY modulo_id ASC, orden ASC";
    $stmtForm = $conexionBD->prepare($sqlForm);
    $stmtForm->execute();
    $formularios = $stmtForm->fetchAll(PDO::FETCH_ASSOC);

    // Acciones (solo las no eliminadas)
    $sqlAcc = "SELECT id, nombre, descripcion FROM acciones WHERE eliminado = 0 ORDER BY id ASC";
    $stmtAcc = $conexionBD->prepare($sqlAcc);
    $stmtAcc->execute();
    $acciones = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

    // Permisos (combinaciones formulario x accion de formularios no eliminados)
    // El JOIN con formularios.eliminado = 0 omite los permisos de formularios
    // desactivados para que el contador de permisos del rol no los incluya
    $sqlPerm = "SELECT p.id, p.formulario_id, p.accion_id
                FROM permisos p
                INNER JOIN formularios f ON f.id = p.formulario_id AND f.eliminado = 0
                ORDER BY p.formulario_id ASC, p.accion_id ASC";
    $stmtPerm = $conexionBD->prepare($sqlPerm);
    $stmtPerm->execute();
    $permisos = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);

    // roles_permisos (toda la asignacion actual)
    $sqlRP = "SELECT rol_id, permiso_id FROM roles_permisos ORDER BY rol_id ASC, permiso_id ASC";
    $stmtRP = $conexionBD->prepare($sqlRP);
    $stmtRP->execute();
    $roles_permisos = $stmtRP->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'roles' => $roles,
        'subsistemas' => $subsistemas,
        'modulos' => $modulos,
        'formularios' => $formularios,
        'acciones' => $acciones,
        'permisos' => $permisos,
        'roles_permisos' => $roles_permisos
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
