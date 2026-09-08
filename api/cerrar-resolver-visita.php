<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $body = json_decode(file_get_contents('php://input'), true);
    $id_ticket = $body['id_ticket'] ?? null;
    $estado_id = isset($body['estado_id']) ? (int)$body['estado_id'] : null;
    $comentario = isset($body['comentario']) ? trim($body['comentario']) : null;

    if ($id_ticket === null || $id_ticket === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id_ticket'
        ]);
        exit;
    }

    if (!in_array($estado_id, [5, 6], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado no permitido'
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

    $stmtTicket = $pdo->prepare("SELECT estado_id FROM tickets WHERE id = :id_ticket");
    $stmtTicket->execute([':id_ticket' => $id_ticket]);
    $ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el ticket indicado'
        ]);
        exit;
    }

    if (in_array((int)$ticket['estado_id'], [5, 6], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'El ticket ya fue resuelto/cerrado'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("UPDATE tickets SET estado_id = :estado_id, updated_at = NOW() WHERE id = :id_ticket");
        $stmtUpdate->execute([':estado_id' => $estado_id, ':id_ticket' => $id_ticket]);

        $stmtHistorial = $pdo->prepare("INSERT INTO tickets_historial (ticket_id, comentario, estado_id, created_at) VALUES (:ticket_id, :comentario, :estado_id, NOW())");
        $stmtHistorial->execute([':ticket_id' => $id_ticket, ':comentario' => $comentario, ':estado_id' => $estado_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'El ticket se actualizó satisfactoriamente'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Error en cerrar-resolver-visita.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el ticket'
    ]);
}
?>
