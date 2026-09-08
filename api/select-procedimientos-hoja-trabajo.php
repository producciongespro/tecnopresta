<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
   
    $pdo = getDBConnection();

        $sql = "SELECT visitas_procedimiento_id,
                visitas_procedimiento_descripcion
                FROM visitas_procedimiento ORDER BY visitas_procedimiento_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
                    'success' => true,
                    'procedimientos' => $procedimientos
                    ]);

        exit;

    } catch (PDOException $e) {
    error_log("Error en select-procedimientos-hoja-trabajo.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar procedimientos: ' . $e->getMessage()
    ]);
}
?>