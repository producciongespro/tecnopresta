<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $pdo = getDBConnection();

    // Tickets en estado "En proceso" (estado_id = 2) que aún no tienen visita
    // de soporte en sitio creada. Se excluyen por tickets_visitas_sitio para
    // que un ticket no reaparezca aquí después de haber sido procesado.
    $sql = "SELECT tickets.id, DATE_FORMAT(tickets.created_at, '%d/%m/%Y') AS created_at, tickets.circuito, tickets.dependencia, tickets.asunto, tickets.descripcion
            FROM tickets
            WHERE tickets.estado_id = 2
              AND NOT EXISTS (
                  SELECT 1 FROM tickets_visitas_sitio tvs WHERE tvs.id = tickets.id
              )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tickets' => $tickets
    ]);

} catch (PDOException $e) {
    error_log("Error en select-tickets-estado-id-7.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar tickets'
    ]);
}
?>
