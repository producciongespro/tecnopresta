<?php
/**
 * ============================================================
 * ENDPOINT: Gestor de Usuarios - Acciones CRUD
 * ============================================================
 * Proposito: Procesa asignar, editar y eliminar roles de usuarios
 * desde el formulario gestor_usuarios_n.php.
 *
 * Acciones soportadas:
 *   - asignar: INSERT INTO usuarios_roles
 *   - editar: UPDATE usuarios_roles SET rol_id = ?
 *   - eliminar: UPDATE usuarios_roles SET eliminado = 1
 *
 * Seguridad:
 *   - Valida sesion Azure
 *   - Solo Root
 *   - Prepared statements
 *   - Transaccion completa
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

if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['accion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Peticion invalida: se requiere accion']);
    exit;
}

$accion = $input['accion'];

try {
    $conexionBD = BD::crearInstancia();
    $conexionBD->beginTransaction();

    switch ($accion) {

        case 'asignar':
            $usuario_id = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;
            $rol_id = isset($input['rol_id']) ? (int)$input['rol_id'] : 0;
            $subsistema_id = isset($input['subsistema_id']) ? (int)$input['subsistema_id'] : 0;
            $codigo_presu = isset($input['codigo_presu']) ? trim($input['codigo_presu']) : '';

            if ($usuario_id <= 0 || $rol_id <= 0 || $subsistema_id <= 0) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requieren usuario_id, rol_id y subsistema_id']);
                exit;
            }

            // Verificar que no exista ya la asignacion
            $sqlCheck = "SELECT id FROM usuarios_roles WHERE usuario_id = ? AND rol_id = ? AND subsistema_id = ? AND codigo_presu = ? AND eliminado = 0";
            $stmtCheck = $conexionBD->prepare($sqlCheck);
            $stmtCheck->execute([$usuario_id, $rol_id, $subsistema_id, $codigo_presu]);
            if ($stmtCheck->fetch()) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Este usuario ya tiene este rol asignado en este subsistema']);
                exit;
            }

            // Verificar si existe pero eliminado (reactivar)
            $sqlCheckEliminado = "SELECT id FROM usuarios_roles WHERE usuario_id = ? AND rol_id = ? AND subsistema_id = ? AND codigo_presu = ? AND eliminado = 1";
            $stmtCheckElim = $conexionBD->prepare($sqlCheckEliminado);
            $stmtCheckElim->execute([$usuario_id, $rol_id, $subsistema_id, $codigo_presu]);
            $rowElim = $stmtCheckElim->fetch();

            if ($rowElim) {
                // Reactivar registro eliminado
                $sqlReactivar = "UPDATE usuarios_roles SET eliminado = 0, updated_at = NOW() WHERE id = ?";
                $stmtReactivar = $conexionBD->prepare($sqlReactivar);
                $stmtReactivar->execute([$rowElim['id']]);
                $nuevo_id = $rowElim['id'];
            } else {
                // Obtener created_by del usuario actual
                $sqlCreatedBy = "SELECT id FROM usuarios WHERE cedula = ? LIMIT 1";
                $stmtCreatedBy = $conexionBD->prepare($sqlCreatedBy);
                $stmtCreatedBy->execute([$usuario_azure['cedula'] ?? '']);
                $rowCreatedBy = $stmtCreatedBy->fetch();
                $created_by = $rowCreatedBy ? $rowCreatedBy['id'] : null;

                // Insertar nueva asignacion
                $sqlInsert = "INSERT INTO usuarios_roles (usuario_id, rol_id, subsistema_id, codigo_presu, eliminado, created_by, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())";
                $stmtInsert = $conexionBD->prepare($sqlInsert);
                $stmtInsert->execute([$usuario_id, $rol_id, $subsistema_id, $codigo_presu, $created_by]);
                $nuevo_id = $conexionBD->lastInsertId();
            }

            $conexionBD->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Rol asignado correctamente',
                'data' => ['id' => $nuevo_id]
            ]);
            break;

        case 'editar':
            $usuario_rol_id = isset($input['usuario_rol_id']) ? (int)$input['usuario_rol_id'] : 0;
            $nuevo_rol_id = isset($input['nuevo_rol_id']) ? (int)$input['nuevo_rol_id'] : 0;

            if ($usuario_rol_id <= 0 || $nuevo_rol_id <= 0) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requieren usuario_rol_id y nuevo_rol_id']);
                exit;
            }

            // Verificar que el registro exista
            $sqlExist = "SELECT id, usuario_id, rol_id, subsistema_id, codigo_presu FROM usuarios_roles WHERE id = ? AND eliminado = 0";
            $stmtExist = $conexionBD->prepare($sqlExist);
            $stmtExist->execute([$usuario_rol_id]);
            $rowExist = $stmtExist->fetch();

            if (!$rowExist) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Registro de rol no encontrado']);
                exit;
            }

            // Verificar que no exista ya el nuevo rol para este usuario/subsistema
            $sqlDup = "SELECT id FROM usuarios_roles WHERE usuario_id = ? AND rol_id = ? AND subsistema_id = ? AND codigo_presu = ? AND eliminado = 0 AND id != ?";
            $stmtDup = $conexionBD->prepare($sqlDup);
            $stmtDup->execute([$rowExist['usuario_id'], $nuevo_rol_id, $rowExist['subsistema_id'], $rowExist['codigo_presu'], $usuario_rol_id]);
            if ($stmtDup->fetch()) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Este usuario ya tiene el nuevo rol asignado en este subsistema']);
                exit;
            }

            // Actualizar
            $sqlUpdate = "UPDATE usuarios_roles SET rol_id = ?, updated_at = NOW() WHERE id = ?";
            $stmtUpdate = $conexionBD->prepare($sqlUpdate);
            $stmtUpdate->execute([$nuevo_rol_id, $usuario_rol_id]);

            $conexionBD->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Rol actualizado correctamente'
            ]);
            break;

        case 'eliminar':
            $usuario_rol_id = isset($input['usuario_rol_id']) ? (int)$input['usuario_rol_id'] : 0;

            if ($usuario_rol_id <= 0) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requiere usuario_rol_id']);
                exit;
            }

            // Verificar que exista
            $sqlExist2 = "SELECT id FROM usuarios_roles WHERE id = ? AND eliminado = 0";
            $stmtExist2 = $conexionBD->prepare($sqlExist2);
            $stmtExist2->execute([$usuario_rol_id]);
            if (!$stmtExist2->fetch()) {
                $conexionBD->rollBack();
                echo json_encode(['success' => false, 'message' => 'Registro de rol no encontrado']);
                exit;
            }

            // Eliminado logico
            $sqlDelete = "UPDATE usuarios_roles SET eliminado = 1, updated_at = NOW() WHERE id = ?";
            $stmtDelete = $conexionBD->prepare($sqlDelete);
            $stmtDelete->execute([$usuario_rol_id]);

            $conexionBD->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Rol eliminado correctamente'
            ]);
            break;

        default:
            $conexionBD->rollBack();
            echo json_encode(['success' => false, 'message' => 'Accion no reconocida: ' . $accion]);
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
