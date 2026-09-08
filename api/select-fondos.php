<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $pdo = getDBConnection();

    $sql = "SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $fondos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'fondos' => $fondos
    ]);

} catch (PDOException $e) {
    error_log("Error en select-fondos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar fondos'
    ]);
}
?>
