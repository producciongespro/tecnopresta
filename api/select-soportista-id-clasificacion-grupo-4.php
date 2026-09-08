<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
    
    $id_soportista = $_GET['id_soportista'];

    if ($id_soportista == null OR $id_soportista === null OR $id_soportista == '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id_soportista'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT * FROM soportistas_clasificaciones WHERE id_soportista = :id_soportista 
            AND activo = 1 AND grupo = 4";

    $params = [':id_soportista' => $id_soportista];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $soportista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
                'success' => true,
                'soportistaclasificacion' => $soportista
                ]);

    exit;

    } catch (PDOException $e) {
    error_log("Error en select-soportista-cedula.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar soportista'
    ]);
}
?>