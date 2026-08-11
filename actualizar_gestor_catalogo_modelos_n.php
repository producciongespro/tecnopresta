<?php
/**
 * ============================================================
 * ENDPOINT: Gestor Catalogo de Modelos - Accion (CRUD)
 * ============================================================
 * Proposito: Procesa las operaciones CRUD sobre el catalogo de
 * modelos (t_modelos) y la relacion modelo-fondos (t_modelo_fondos).
 *
 * Acciones soportadas:
 *   - crear_modelo / editar_modelo
 *   - toggle_modelo (activar/desactivar)
 *   - guardar_fondos (asignacion 0..N de fuentes presupuestarias)
 *
 * Seguridad:
 *   - Valida sesion Azure
 *   - Solo Root
 *   - Prepared statements
 *   - Transacciones en operaciones multi-tabla
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/usuarioAzure.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sql/bd.php';
require_once __DIR__ . '/funciones_modelos_n.php';

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

$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Peticion invalida: se requiere accion']);
    exit;
}

try {
    $conexionBD = BD::crearInstancia();

    switch ($action) {

        // ============================================================
        // CREAR MODELO
        // ============================================================
        case 'crear_modelo':
            $modelo = trim($_POST['modelo'] ?? '');
            $created_by = trim($_POST['created_by'] ?? '');

            if ($modelo === '') {
                echo json_encode(['success' => false, 'message' => 'El nombre del modelo es obligatorio']);
                exit;
            }

            $sql = "INSERT INTO t_modelos (modelo, mdl_elm, created_by, created_at, updated_at)
                    VALUES (?, 0, ?, NOW(), NOW())";
            $stmt = $conexionBD->prepare($sql);
            $stmt->execute([$modelo, $created_by]);

            echo json_encode([
                'success' => true,
                'message' => 'Modelo creado correctamente',
                'data' => ['id' => (int)$conexionBD->lastInsertId()]
            ]);
            break;

        // ============================================================
        // EDITAR MODELO
        // ============================================================
        case 'editar_modelo':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $modelo = trim($_POST['modelo'] ?? '');

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de modelo invalido']);
                exit;
            }
            if ($modelo === '') {
                echo json_encode(['success' => false, 'message' => 'El nombre del modelo es obligatorio']);
                exit;
            }

            $sql = "UPDATE t_modelos SET modelo = ?, updated_at = NOW() WHERE id_modelo = ?";
            $stmt = $conexionBD->prepare($sql);
            $stmt->execute([$modelo, $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Modelo actualizado correctamente'
            ]);
            break;

        // ============================================================
        // TOGGLE MODELO (activar/desactivar)
        // ============================================================
        case 'toggle_modelo':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $activo = isset($_POST['active']) ? filter_var($_POST['active'], FILTER_VALIDATE_BOOLEAN) : false;

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de modelo invalido']);
                exit;
            }

            $nuevoEstado = $activo ? 0 : 1;
            $sql = "UPDATE t_modelos SET mdl_elm = ?, updated_at = NOW() WHERE id_modelo = ?";
            $stmt = $conexionBD->prepare($sql);
            $stmt->execute([$nuevoEstado, $id]);

            $mensaje = $activo ? 'Modelo reactivado correctamente' : 'Modelo desactivado correctamente';
            echo json_encode(['success' => true, 'message' => $mensaje]);
            break;

        // ============================================================
        // GUARDAR FONDOS DE UN MODELO (asignacion 0..N)
        // ============================================================
        case 'guardar_fondos':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $fondos = isset($_POST['fondos']) ? $_POST['fondos'] : [];

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de modelo invalido']);
                exit;
            }

            // Normalizar a array de enteros
            $fondosIds = [];
            if (is_array($fondos)) {
                foreach ($fondos as $f) {
                    $fondosIds[] = (int)$f;
                }
            }
            $fondosIds = array_unique(array_filter($fondosIds, function ($v) { return $v > 0; }));

            $conexionBD->beginTransaction();

            // Eliminar asociaciones actuales
            $sqlDel = "DELETE FROM t_modelo_fondos WHERE id_modelo = ?";
            $stmtDel = $conexionBD->prepare($sqlDel);
            $stmtDel->execute([$id]);

            // Insertar las nuevas
            if (!empty($fondosIds)) {
                $sqlIns = "INSERT INTO t_modelo_fondos (id_modelo, id_fondos) VALUES (?, ?)";
                $stmtIns = $conexionBD->prepare($sqlIns);
                foreach ($fondosIds as $idFondo) {
                    $stmtIns->execute([$id, $idFondo]);
                }
            }

            $conexionBD->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Fondos asignados correctamente'
            ]);
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
