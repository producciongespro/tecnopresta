<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $visitas_sitio_hoja_trabajo_id = $_GET['visitas_sitio_hoja_trabajo_id'] ?? null;

    if ($visitas_sitio_hoja_trabajo_id === null || $visitas_sitio_hoja_trabajo_id === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener el id de hoja de trabajo'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT 
            t_placa.id_activo, 
            clase, marca, modelo, 
            placa, serial AS serie, 
            imagen, id_placa,
            visitas_sitio_hoja_trabajo_comentario AS comentario
            FROM visitas_sitio_hoja_trabajo_activos
            INNER JOIN t_placa ON visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_id_placa=t_placa.id_placa
            INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
            INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
            INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag
            WHERE visitas_sitio_hoja_trabajo_id = :visitas_sitio_hoja_trabajo_id";

    $params = [':visitas_sitio_hoja_trabajo_id' => $visitas_sitio_hoja_trabajo_id];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'articulos' => $articulos
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en select-activos-hoja-de-trabajo.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar los artículos'
    ]);
}

?>
