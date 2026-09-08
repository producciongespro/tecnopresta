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

    $sqlVisita = "SELECT v.*, MIN(tvs.id) AS id_ticket,
                         MIN(t.asunto) AS ticket_asunto,
                         MIN(t.descripcion) AS ticket_descripcion
                  FROM visitas_sitio v
                  INNER JOIN tickets_visitas_sitio tvs ON tvs.id_visita = v.id_visita
                  INNER JOIN tickets t ON t.id = tvs.id
                  WHERE v.id_visita = :id_visita
                  GROUP BY v.id_visita";

    $stmtVisita = $pdo->prepare($sqlVisita);
    $stmtVisita->execute([':id_visita' => $id_visita]);
    $visita = $stmtVisita->fetch(PDO::FETCH_ASSOC);

    if (!$visita) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró la visita indicada'
        ]);
        exit;
    }

    $sqlSoportistas = "SELECT id_soportista FROM tickets_visitas_sitio WHERE id_visita = :id_visita";
    $stmtSoportistas = $pdo->prepare($sqlSoportistas);
    $stmtSoportistas->execute([':id_visita' => $id_visita]);
    $id_soportistas = array_column($stmtSoportistas->fetchAll(PDO::FETCH_ASSOC), 'id_soportista');

    $id_fondos = $visita['arreglo_id_fondos'] !== null && $visita['arreglo_id_fondos'] !== ''
        ? array_map('intval', explode(',', $visita['arreglo_id_fondos']))
        : [];

    echo json_encode([
        'success' => true,
        'visita' => $visita,
        'id_fondos' => $id_fondos,
        'id_soportistas' => $id_soportistas
    ]);

} catch (PDOException $e) {
    error_log("Error en select-visita-sitio-detalle.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar la visita'
    ]);
}
?>
