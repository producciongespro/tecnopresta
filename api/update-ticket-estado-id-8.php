<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $body = json_decode(file_get_contents('php://input'), true);
    $id_ticket = $body['id_ticket'] ?? null;

    if ($id_ticket === null || $id_ticket === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id_ticket'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "UPDATE tickets SET estado_id = 8, updated_at = NOW() WHERE id = :id_ticket";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_ticket' => $id_ticket]);

    echo json_encode([
        'success' => true,
        'message' => 'Ticket adjudicado correctamente'
    ]);

} catch (PDOException $e) {
    error_log("Error en update-ticket-estado-id-8.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar ticket'
    ]);
}
?>
