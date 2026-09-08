<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $id_ticket = $_GET['id_ticket'] ?? null;

    if ($id_ticket === null || $id_ticket === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id_ticket'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT codigo, dependencia, DATE_FORMAT(created_at, '%d/%m/%Y') AS created_at, circuito, asunto, descripcion FROM tickets WHERE id = :id_ticket";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_ticket' => $id_ticket]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el ticket indicado'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'ticket' => $ticket
    ]);

} catch (PDOException $e) {
    error_log("Error en select-ticket-sitio.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar el ticket'
    ]);
}
?>
