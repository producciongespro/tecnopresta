<?php
require_once('db_config.php');

header('Content-Type: application/json; charset=utf-8');

// Parámetro esperado: visitas_sitio_hoja_trabajo_id (GET)
$hoja_id = isset($_GET['visitas_sitio_hoja_trabajo_id']) ? (int) $_GET['visitas_sitio_hoja_trabajo_id'] : 0;

if ($hoja_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'visitas_sitio_hoja_trabajo_id inválido.']);
    exit;
}

try {
    $pdo = getDBConnection();

    $sql = "SELECT
                vshp.visitas_sitio_hoja_trabajo_id,
                vshp.visitas_procedimiento_id,
                vp.visitas_procedimiento_descripcion,
                vshp.visitas_sitio_hoja_trabajo_procedimiento_comentario
            FROM visitas_sitio_hoja_trabajo_procedimiento vshp
            INNER JOIN visitas_procedimiento vp
              ON vshp.visitas_procedimiento_id = vp.visitas_procedimiento_id
            WHERE vshp.visitas_sitio_hoja_trabajo_id = :hoja_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hoja_id' => $hoja_id]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $procedimientos = array_map(function($r) {
        return [
            'id' => isset($r['visitas_procedimiento_id']) ? (int) $r['visitas_procedimiento_id'] : 0,
            'descripcion' => isset($r['visitas_procedimiento_descripcion']) ? $r['visitas_procedimiento_descripcion'] : '',
            'comentario' => isset($r['visitas_sitio_hoja_trabajo_procedimiento_comentario']) ? $r['visitas_sitio_hoja_trabajo_procedimiento_comentario'] : ''
        ];
    }, $rows);

    echo json_encode(['success' => true, 'procedimientos' => $procedimientos]);

} catch (PDOException $e) {
    error_log('select-procedimientos-visita.php PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en base de datos.']);
}

?>
