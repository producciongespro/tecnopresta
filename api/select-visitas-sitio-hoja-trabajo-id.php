<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
    
    $id_visita = $_GET['id_visita'];    

    if ($id_visita == null OR $id_visita === null OR $id_visita == '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener el id de visita'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT visitas_sitio_hoja_trabajo_id,
                         visitas_sitio_hoja_trabajo_resultado,
                         visitas_sitio_hoja_trabajo_indicaciones
            FROM visitas_sitio_hoja_trabajo
            WHERE id_visita = :id_visita";

    $params = [':id_visita' => $id_visita];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $visitas_sitio_hoja_trabajo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
                'success' => true,
                'visitas' => $visitas_sitio_hoja_trabajo
                ]);

    exit;        

    } catch (PDOException $e) {
    error_log("Error en select-visitas_sitio_hoja_trabajo_id.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar visitas'
    ]);
}
?>