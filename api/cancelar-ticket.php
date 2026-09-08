<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $body = json_decode(file_get_contents('php://input'), true);
    $id_ticket = $body['id_ticket'] ?? null;
    $comentario = isset($body['comentario']) ? trim($body['comentario']) : null;

    if ($id_ticket === null || $id_ticket === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id_ticket'
        ]);
        exit;
    }

    if ($comentario === null || strlen($comentario) < 10) {
        echo json_encode([
            'success' => false,
            'message' => 'El comentario debe tener al menos 10 caracteres'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("UPDATE tickets SET estado_id = 6, updated_at = NOW() WHERE id = :id_ticket");
    $stmt->execute([':id_ticket' => $id_ticket]);

    $stmt2 = $pdo->prepare("INSERT INTO tickets_historial (ticket_id, comentario, estado_id, created_at) VALUES (:ticket_id, :comentario, 6, NOW())");
    $stmt2->execute([':ticket_id' => $id_ticket, ':comentario' => $comentario]);

    echo json_encode([
        'success' => true,
        'message' => 'Ticket cancelado correctamente'
    ]);

} catch (PDOException $e) {
    error_log("Error en cancelar-ticket.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
