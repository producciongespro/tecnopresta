<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $id_visita = $_GET['id_visita'] ?? null;

    if ($id_visita === null || $id_visita === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener el id de visita'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT tickets.circuito,
                   tickets.dependencia,
                   tickets.asunto,
                   tickets.descripcion
            FROM tickets
            INNER JOIN tickets_visitas_sitio ON tickets.id = tickets_visitas_sitio.id
            WHERE tickets_visitas_sitio.id_visita = :id_visita";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_visita' => $id_visita]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $data
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en select-ticket-hoja-de-visita.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar hoja de visita'
    ]);
}

?>
