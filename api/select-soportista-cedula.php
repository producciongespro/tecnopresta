<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
    
    $cedula = $_GET['cedula'];

    if ($cedula == null OR $cedula === null OR $cedula == '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener la cédula'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT * FROM soportistas WHERE cedula = :cedula";

    $params = [':cedula' => $cedula];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $soportista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
                'success' => true,
                'soportista' => $soportista
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