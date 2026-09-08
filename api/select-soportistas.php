<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $pdo = getDBConnection();

    $sql = "SELECT s.id_soportista, s.nombre
            FROM soportistas s
            INNER JOIN soportistas_clasificaciones sc ON sc.id_soportista = s.id_soportista
            WHERE sc.activo = 1 AND sc.grupo = 3
            ORDER BY s.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $soportistas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'soportistas' => $soportistas
    ]);

} catch (PDOException $e) {
    error_log("Error en select-soportistas.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar soportistas'
    ]);
}
?>
