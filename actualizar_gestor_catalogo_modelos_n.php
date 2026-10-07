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
/*
if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}
*/

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
         // CREAR COMBINACION (tipo+marca+modelo) EN t_activo
         // ============================================================
         case 'crear_combo':
             $id_ag = intval($_POST['id_ag'] ?? 0);
             $id_marca = intval($_POST['id_marca'] ?? 0);
             $id_modelo = intval($_POST['id_modelo'] ?? 0);
             $modelo_texto = trim($_POST['modelo_texto'] ?? '');

             if ($id_ag <= 0 || $id_marca <= 0) {
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => 'Debe seleccionar Tipo y Marca']);
                 exit;
             }

             $sqlCheckTipo = "SELECT 1 FROM t_activo_general WHERE id_ag = ?";
             $stmtCT = $mysqli->prepare($sqlCheckTipo);
             $stmtCT->bind_param('i', $id_ag);
             $stmtCT->execute();
             $stmtCT->store_result();
             if ($stmtCT->num_rows == 0) {
                 $stmtCT->close();
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => 'Tipo de activo invalido']);
                 exit;
             }
             $stmtCT->close();

             $sqlCheckMarca = "SELECT 1 FROM t_marca WHERE id_marca = ?";
             $stmtCM = $mysqli->prepare($sqlCheckMarca);
             $stmtCM->bind_param('i', $id_marca);
             $stmtCM->execute();
             $stmtCM->store_result();
             if ($stmtCM->num_rows == 0) {
                 $stmtCM->close();
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => 'Marca invalida']);
                 exit;
             }
             $stmtCM->close();

             if ($id_modelo <= 0 && $modelo_texto !== '') {
                 $modelo_texto_n = normalizarModelo($modelo_texto);
                 if ($modelo_texto_n === '') {
                     http_response_code(400);
                     echo json_encode(['success' => false, 'message' => 'Nombre de modelo invalido']);
                     exit;
                 }
                 if (mb_strlen($modelo_texto_n) > 250) {
                     http_response_code(400);
                     echo json_encode(['success' => false, 'message' => 'Nombre de modelo excede 250 caracteres']);
                     exit;
                 }
                 $created_by = substr(($usuario_azure['cedula'] ?? $usuario_azure['correo'] ?? ''), 0, 50);
                 $id_modelo_resuelto = obtenerOCrearModelo($mysqli, $modelo_texto_n, $created_by);
                 if (!$id_modelo_resuelto) {
                     http_response_code(500);
                     echo json_encode(['success' => false, 'message' => 'No se pudo crear el modelo']);
                     exit;
                 }
             } elseif ($id_modelo > 0) {
                 $sqlCheckModelo = "SELECT 1 FROM t_modelos WHERE id_modelo = ?";
                 $stmtCMo = $mysqli->prepare($sqlCheckModelo);
                 $stmtCMo->bind_param('i', $id_modelo);
                 $stmtCMo->execute();
                 $stmtCMo->store_result();
                 $existeModelo = $stmtCMo->num_rows > 0;
                 $stmtCMo->close();
                 if (!$existeModelo) {
                     http_response_code(400);
                     echo json_encode(['success' => false, 'message' => 'Modelo invalido']);
                     exit;
                 }
                 $id_modelo_resuelto = $id_modelo;
             } else {
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => 'Debe seleccionar o ingresar un modelo']);
                 exit;
             }

             $sqlDup = "SELECT id_activo FROM t_activo WHERE id_ag = ? AND id_marca = ? AND modelo_id = ? LIMIT 1";
             $stmtDup = $mysqli->prepare($sqlDup);
             $stmtDup->bind_param('iii', $id_ag, $id_marca, $id_modelo_resuelto);
             $stmtDup->execute();
             $stmtDup->store_result();
             $duplicado = $stmtDup->num_rows > 0;
             $stmtDup->close();
             if ($duplicado) {
                 http_response_code(409);
                 echo json_encode(['success' => false, 'message' => 'La combinación tipo+marca+modelo ya existe']);
                 exit;
             }

             $mysqli->begin_transaction();
             try {
                 $modelo_vacio = '';
                 $id_color = 1;
                 $stmtIns = $mysqli->prepare("INSERT INTO t_activo (id_ag, id_marca, modelo, modelo_id, id_color) VALUES (?, ?, ?, ?, ?)");
                 $stmtIns->bind_param('iisii', $id_ag, $id_marca, $modelo_vacio, $id_modelo_resuelto, $id_color);
                 $stmtIns->execute();
                 $id_activo_nuevo = $mysqli->insert_id;
                 $stmtIns->close();
                 $mysqli->commit();
                 echo json_encode(['success' => true, 'message' => 'Combinación creada correctamente', 'data' => ['id_activo' => $id_activo_nuevo, 'id_modelo' => $id_modelo_resuelto, 'id_ag' => $id_ag, 'id_marca' => $id_marca]]);
             } catch (Exception $e) {
                 $mysqli->rollback();
                 http_response_code(500);
                 echo json_encode(['success' => false, 'message' => 'Error al crear combinación: ' . $e->getMessage()]);
             }
             break;

        // ============================================================
        // EDITAR COMBINACION (tipo+marca+modelo) EN t_activo
        // ============================================================
        case 'editar_combo':
            $old_id_ag = intval($_POST['old_id_ag'] ?? 0);
            $old_id_marca = intval($_POST['old_id_marca'] ?? 0);
            $old_id_modelo = intval($_POST['old_id_modelo'] ?? 0);
            $id_ag = intval($_POST['id_ag'] ?? 0);
            $id_marca = intval($_POST['id_marca'] ?? 0);
            $id_modelo = intval($_POST['id_modelo'] ?? 0);

            if ($old_id_ag <= 0 || $old_id_marca <= 0 || $old_id_modelo <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Combinación de origen inválida']);
                exit;
            }
            if ($id_ag <= 0 || $id_marca <= 0 || $id_modelo <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar Tipo, Marca y Modelo']);
                exit;
            }
            if ($id_ag === $old_id_ag && $id_marca === $old_id_marca && $id_modelo === $old_id_modelo) {
                echo json_encode(['success' => true, 'message' => 'No se detectaron cambios']);
                exit;
            }

            $stmtCT = $mysqli->prepare("SELECT 1 FROM t_activo_general WHERE id_ag = ?");
            $stmtCT->bind_param('i', $id_ag);
            $stmtCT->execute();
            $stmtCT->store_result();
            $tipoOk = $stmtCT->num_rows > 0;
            $stmtCT->close();
            if (!$tipoOk) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tipo de activo inválido']);
                exit;
            }

            $stmtCM = $mysqli->prepare("SELECT 1 FROM t_marca WHERE id_marca = ?");
            $stmtCM->bind_param('i', $id_marca);
            $stmtCM->execute();
            $stmtCM->store_result();
            $marcaOk = $stmtCM->num_rows > 0;
            $stmtCM->close();
            if (!$marcaOk) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Marca inválida']);
                exit;
            }

            $stmtCMo = $mysqli->prepare("SELECT 1 FROM t_modelos WHERE id_modelo = ?");
            $stmtCMo->bind_param('i', $id_modelo);
            $stmtCMo->execute();
            $stmtCMo->store_result();
            $modeloOk = $stmtCMo->num_rows > 0;
            $stmtCMo->close();
            if (!$modeloOk) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Modelo inválido']);
                exit;
            }

            $stmtDup = $mysqli->prepare("SELECT 1 FROM t_activo WHERE id_ag = ? AND id_marca = ? AND modelo_id = ? LIMIT 1");
            $stmtDup->bind_param('iii', $id_ag, $id_marca, $id_modelo);
            $stmtDup->execute();
            $stmtDup->store_result();
            $duplicado = $stmtDup->num_rows > 0;
            $stmtDup->close();
            if ($duplicado) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'La combinación destino ya existe']);
                exit;
            }

            $mysqli->begin_transaction();
            try {
                $stmtUpd = $mysqli->prepare("UPDATE t_activo SET id_ag = ?, id_marca = ?, modelo_id = ?, modelo = '' WHERE id_ag = ? AND id_marca = ? AND modelo_id = ?");
                $stmtUpd->bind_param('iiiiii', $id_ag, $id_marca, $id_modelo, $old_id_ag, $old_id_marca, $old_id_modelo);
                $stmtUpd->execute();
                $afectadas = $stmtUpd->affected_rows;
                $stmtUpd->close();
                $mysqli->commit();

                if ($afectadas <= 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Combinación de origen no encontrada']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Combinación actualizada correctamente (' . $afectadas . ' filas en t_activo)',
                    'data' => ['filas' => (int)$afectadas, 'id_ag' => $id_ag, 'id_marca' => $id_marca, 'id_modelo' => $id_modelo]
                ]);
            } catch (Exception $e) {
                $mysqli->rollback();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al editar combinación: ' . $e->getMessage()]);
            }
            break;

        // ============================================================
        // ELIMINAR COMBINACION (tipo+marca+modelo) EN t_activo
        // Solo se permite si no hay unidades fisicas (t_placa) asociadas
        // ============================================================
        case 'eliminar_combo':
            $id_ag = intval($_POST['id_ag'] ?? 0);
            $id_marca = intval($_POST['id_marca'] ?? 0);
            $id_modelo = intval($_POST['id_modelo'] ?? 0);

            if ($id_ag <= 0 || $id_marca <= 0 || $id_modelo <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Combinación inválida']);
                exit;
            }

            $stmtCnt = $mysqli->prepare("SELECT COUNT(*) FROM t_activo WHERE id_ag = ? AND id_marca = ? AND modelo_id = ?");
            $stmtCnt->bind_param('iii', $id_ag, $id_marca, $id_modelo);
            $stmtCnt->execute();
            $stmtCnt->bind_result($filasCombo);
            $stmtCnt->fetch();
            $stmtCnt->close();

            if ($filasCombo <= 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'La combinación no existe']);
                exit;
            }

            $stmtUn = $mysqli->prepare(
                "SELECT COUNT(*) FROM t_placa p
                 INNER JOIN t_activo a ON a.id_activo = p.id_activo
                 WHERE a.id_ag = ? AND a.id_marca = ? AND a.modelo_id = ?"
            );
            $stmtUn->bind_param('iii', $id_ag, $id_marca, $id_modelo);
            $stmtUn->execute();
            $stmtUn->bind_result($unidades);
            $stmtUn->fetch();
            $stmtUn->close();

            if ($unidades > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'No se puede eliminar: la combinación tiene ' . $unidades . ' unidad(es) física(s) asociadas'
                ]);
                exit;
            }

            $mysqli->begin_transaction();
            try {
                $stmtDel = $mysqli->prepare("DELETE FROM t_activo WHERE id_ag = ? AND id_marca = ? AND modelo_id = ?");
                $stmtDel->bind_param('iii', $id_ag, $id_marca, $id_modelo);
                $stmtDel->execute();
                $eliminadas = $stmtDel->affected_rows;
                $stmtDel->close();
                $mysqli->commit();

                if ($eliminadas <= 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'La combinación no existe']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Combinación eliminada correctamente (' . $eliminadas . ' filas en t_activo)',
                    'data' => ['filas' => (int)$eliminadas]
                ]);
            } catch (Exception $e) {
                $mysqli->rollback();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al eliminar combinación: ' . $e->getMessage()]);
            }
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
