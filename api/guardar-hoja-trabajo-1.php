<?php
/**
 * API: Guardar Hoja de Trabajo
 * Ruta: /api/guardar-hoja-trabajo.php
 *
 * Flujo (dentro de una transacción):
 *  1. Actualizar visitas_sitio.estado si chk_cerrado = true
 *  2. INSERT o UPDATE en visitas_sitio_hoja_trabajo
 *  3. Obtener visitas_sitio_hoja_trabajo_id
 *  4. DELETE artículos previos de visitas_sitio_hoja_trabajo_activos
 *  5. INSERT artículos actuales
 */

header('Content-Type: application/json; charset=utf-8');

require_once("db_config.php");

// ── 1. Solo acepta POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

// ── 2. Leer y validar body JSON ───────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || $body === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Cuerpo JSON inválido.']);
    exit;
}

// Campos obligatorios
$id_visita   = isset($body['id_visita'])   ? (int) $body['id_visita']  : 0;
$chk_cerrado = isset($body['chk_cerrado']) ? (bool) $body['chk_cerrado'] : false;
$articulos   = isset($body['articulos'])   && is_array($body['articulos'])
               ? $body['articulos']
               : [];

if ($id_visita <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'id_visita inválido.']);
    exit;
}

// ── 3. Ejecutar flujo dentro de una transacción ───────────────────────────────
try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // ── Paso 1: Actualizar estado si caso cerrado ─────────────────────────────
    if ($chk_cerrado === true) {
        $stmt = $pdo->prepare(
            'UPDATE visitas_sitio SET estado = 3 WHERE id_visita = :id_visita'
        );
        $stmt->execute([':id_visita' => $id_visita]);
    }

    // ── Paso 2: ¿Existe registro en visitas_sitio_hoja_trabajo? ───────────────
    $stmt = $pdo->prepare(
        'SELECT visitas_sitio_hoja_trabajo_id
           FROM visitas_sitio_hoja_trabajo
          WHERE id_visita = :id_visita
          LIMIT 1'
    );
    $stmt->execute([':id_visita' => $id_visita]);
    $registro = $stmt->fetch();

    // ── Paso 3: INSERT o UPDATE; obtener el ID ────────────────────────────────
    if ($registro) {
        // Ya existe → UPDATE (no hay campos adicionales que cambiar según spec)
        $visitas_sitio_hoja_trabajo_id = (int) $registro['visitas_sitio_hoja_trabajo_id'];

        $stmt = $pdo->prepare(
            'UPDATE visitas_sitio_hoja_trabajo
                SET id_visita = :id_visita
              WHERE visitas_sitio_hoja_trabajo_id = :hoja_id'
        );
        $stmt->execute([
            ':id_visita' => $id_visita,
            ':hoja_id'   => $visitas_sitio_hoja_trabajo_id,
        ]);
    } else {
        // No existe → INSERT
        $stmt = $pdo->prepare(
            'INSERT INTO visitas_sitio_hoja_trabajo (id_visita)
             VALUES (:id_visita)'
        );
        $stmt->execute([':id_visita' => $id_visita]);
        $visitas_sitio_hoja_trabajo_id = (int) $pdo->lastInsertId();
    }

    // ── Paso 4: Eliminar artículos previos ────────────────────────────────────
    $stmt = $pdo->prepare(
        'DELETE FROM visitas_sitio_hoja_trabajo_activos
          WHERE visitas_sitio_hoja_trabajo_id = :hoja_id'
    );
    $stmt->execute([':hoja_id' => $visitas_sitio_hoja_trabajo_id]);

    // ── Paso 5: Insertar artículos actuales ───────────────────────────────────
    if (!empty($articulos)) {
        $stmt = $pdo->prepare(
            'INSERT INTO visitas_sitio_hoja_trabajo_activos
                (visitas_sitio_hoja_trabajo_id,
                 visitas_sitio_hoja_trabajo_activos_id_activo,
                 visitas_sitio_hoja_trabajo_id_placa,
                 visitas_sitio_hoja_trabajo_comentario)
             VALUES
                (:hoja_id, :id_activo, :id_placa, :comentario)'
        );

        foreach ($articulos as $art) {
            $id_activo  = isset($art['id_activo'])  ? (int)    $art['id_activo']  : 0;
            $id_placa   = isset($art['id_placa'])   ? (int)    $art['id_placa']   : 0;
            $comentario = isset($art['comentario']) ? (string) $art['comentario'] : '';

            if ($id_activo <= 0 || $id_placa <= 0) {
                // Artículo con datos incompletos: abortar transacción
                throw new \InvalidArgumentException(
                    "Artículo con id_activo o id_placa inválido."
                );
            }

            $stmt->execute([
                ':hoja_id'    => $visitas_sitio_hoja_trabajo_id,
                ':id_activo'  => $id_activo,
                ':id_placa'   => $id_placa,
                ':comentario' => $comentario,
            ]);
        }
    }

    // ── Confirmar transacción ─────────────────────────────────────────────────
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Hoja de trabajo guardada correctamente.',
        'visitas_sitio_hoja_trabajo_id' => $visitas_sitio_hoja_trabajo_id,
    ]);

} catch (\InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);

} catch (\PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('guardar-hoja-trabajo.php PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error en base de datos.']);

} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('guardar-hoja-trabajo.php Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error interno del servidor.']);
}
