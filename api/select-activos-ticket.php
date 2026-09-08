<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $id = $_GET['id'] ?? null;

    if ($id === null || $id === '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener id del ticket'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT clase, marca, modelo, placa, serial AS serie,
                   tickets_activos.observacion_usuario,
                   tickets_activos.diagnostico_tecnico,
                   tickets_activos.reparacion_realizada
            FROM t_placa
            INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
            INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
            INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag
            INNER JOIN tickets_activos ON t_placa.id_placa = tickets_activos.id_placa
            WHERE tickets_activos.id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'activos' => $activos
    ]);

} catch (PDOException $e) {
    error_log("Error en select-activos-ticket.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar activos del ticket'
    ]);
}
?>
