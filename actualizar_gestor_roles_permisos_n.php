<?php
/**
 * ============================================================
 * ENDPOINT: Gestor de Roles - Accion (Asignar/Revocar Permisos)
 * ============================================================
 * Proposito: Procesa la asignacion o revocacion de permisos
 * a roles desde el formulario gestor_roles_permisos_n.php.
 *
 * Recibe una lista diferencial de cambios:
 * {
 *   rol_id: 10,
 *   cambios: [
 *     { formulario_id: 25, accion_id: 1, activo: true },
 *     { formulario_id: 25, accion_id: 3, activo: false }
 *   ]
 * }
 *
 * Logica por cambio:
 *   activo=true:  valida cadena activa (formulario/modulo/subsistema/accion)
 *                 INSERT IGNORE INTO permisos + INSERT IGNORE INTO roles_permisos
 *   activo=false: DELETE FROM roles_permisos + DELETE FROM permisos (si huerfano)
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
//require_once __DIR__ . '/../usuarioAzure.php';
//require_once __DIR__ . '/../auth.php';
//require_once __DIR__ . '/bd.php';

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

if (!$input || !isset($input['rol_id']) || !isset($input['cambios'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Peticion invalida: se requieren rol_id y cambios']);
    exit;
}

$rol_id = (int)$input['rol_id'];
$cambios = $input['cambios'];

if ($rol_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de rol invalido']);
    exit;
}

try {
    $conexionBD = BD::crearInstancia();
    $conexionBD->beginTransaction();

    $procesados = 0;
    $errores = 0;
    $detalleErrores = [];

    // Prepared statements reutilizables
    $sqlBuscarPermiso = "SELECT id FROM permisos WHERE formulario_id = ? AND accion_id = ?";
    $stmtBuscar = $conexionBD->prepare($sqlBuscarPermiso);

    $sqlInsertPermiso = "INSERT IGNORE INTO permisos (formulario_id, accion_id) VALUES (?, ?)";
    $stmtInsertPermiso = $conexionBD->prepare($sqlInsertPermiso);

    $sqlInsertRP = "INSERT IGNORE INTO roles_permisos (rol_id, permiso_id) VALUES (?, ?)";
    $stmtInsertRP = $conexionBD->prepare($sqlInsertRP);

    $sqlDeleteRP = "DELETE FROM roles_permisos WHERE rol_id = ? AND permiso_id = ?";
    $stmtDeleteRP = $conexionBD->prepare($sqlDeleteRP);

    // Valida que el formulario, su modulo y su subsistema esten activos
    // (y que la accion exista y no este eliminada) antes de asignar
    $sqlValidarCadena = "SELECT f.id
        FROM formularios f
        INNER JOIN modulos m     ON m.id = f.modulo_id
        INNER JOIN subsistemas s ON s.id = m.subsistema_id
        INNER JOIN acciones    a ON a.id = ?
        WHERE f.id = ?
          AND f.eliminado = 0
          AND m.eliminado = 0
          AND s.eliminado = 0
          AND a.eliminado = 0";
    $stmtValidarCadena = $conexionBD->prepare($sqlValidarCadena);

    $sqlCheckPermisoUsado = "SELECT COUNT(*) AS total FROM roles_permisos WHERE permiso_id = ?";
    $stmtCheckUsado = $conexionBD->prepare($sqlCheckPermisoUsado);

    $sqlDeletePermiso = "DELETE FROM permisos WHERE id = ?";
    $stmtDeletePerm = $conexionBD->prepare($sqlDeletePermiso);

    foreach ($cambios as $cambio) {
        $formulario_id = isset($cambio['formulario_id']) ? (int)$cambio['formulario_id'] : 0;
        $accion_id = isset($cambio['accion_id']) ? (int)$cambio['accion_id'] : 0;
        $activo = isset($cambio['activo']) ? (bool)$cambio['activo'] : false;

        if ($formulario_id <= 0 || $accion_id <= 0) {
            $errores++;
            continue;
        }

        if ($activo) {
            // ASIGNAR permiso
            // 0. Verificar que el formulario (y su modulo/subsistema/accion) no este eliminado
            $stmtValidarCadena->execute([$accion_id, $formulario_id]);
            if (!$stmtValidarCadena->fetchColumn()) {
                $errores++;
                $detalleErrores[] = 'formulario ' . $formulario_id . ' / accion ' . $accion_id . ': formulario, modulo, subsistema o accion desactivado o inexistente, no se asigno';
                continue;
            }

            // 1. Asegurar que existe el registro en permisos
            $stmtInsertPermiso->execute([$formulario_id, $accion_id]);

            // 2. Obtener el permiso_id
            $stmtBuscar->execute([$formulario_id, $accion_id]);
            $row = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $permiso_id = (int)$row['id'];
                // 3. Asignar al rol
                $stmtInsertRP->execute([$rol_id, $permiso_id]);
                $procesados++;
            }

        } else {
            // REVOCAR permiso
            // 1. Obtener el permiso_id
            $stmtBuscar->execute([$formulario_id, $accion_id]);
            $row = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $permiso_id = (int)$row['id'];

                // 2. Eliminar la asignacion del rol
                $stmtDeleteRP->execute([$rol_id, $permiso_id]);

                // 3. Si ningun otro rol usa este permiso, limpiarlo
                $stmtCheckUsado->execute([$permiso_id]);
                $usado = (int)$stmtCheckUsado->fetch(PDO::FETCH_ASSOC)['total'];

                if ($usado === 0) {
                    $stmtDeletePerm->execute([$permiso_id]);
                }

                $procesados++;
            }
        }
    }

    $conexionBD->commit();

    $mensaje = 'Cambios aplicados correctamente (' . $procesados . ' procesados, ' . $errores . ' errores)';
    if (!empty($detalleErrores)) {
        $mensaje .= ' - ' . implode(' | ', $detalleErrores);
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'data' => [
            'procesados' => $procesados,
            'errores' => $errores,
            'detalle_errores' => $detalleErrores
        ]
    ]);

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
