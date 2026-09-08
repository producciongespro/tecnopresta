<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $id_visita = $_GET['id_visita'] ?? null;

    if ($id_visita === null || $id_visita === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id_visita'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT DISTINCT s.nombre
            FROM soportistas s
            WHERE s.id_soportista IN (
                SELECT id_soportista FROM tickets_visitas_sitio WHERE id_visita = :id_visita
                UNION
                SELECT id_soportista FROM visitas_sitio_soportistas WHERE id_visita = :id_visita2
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_visita' => $id_visita, ':id_visita2' => $id_visita]);

    $soportistas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'soportistas' => $soportistas
    ]);

} catch (PDOException $e) {
    error_log("Error en select-soportistas-visita.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar soportistas'
    ]);
}
?>
