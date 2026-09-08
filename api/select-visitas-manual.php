<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $estado = isset($_GET['estado']) ? (int)$_GET['estado'] : null;

    if (!in_array($estado, [1, 3], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado no permitido'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    $sql = "SELECT
                v.id_visita,
                v.nombre_institucion,
                v.codigo_institucion,
                v.descripcion_problema,
                v.observaciones,
                v.labor_realizar,
                v.arreglo_id_fondos,
                DATE_FORMAT(v.fecha_visita, '%d/%m/%Y') AS fecha_visita,
                DATE_FORMAT(v.hora_visita, '%H:%i') AS hora_visita,
                GROUP_CONCAT(DISTINCT soportistas.nombre SEPARATOR ', ') AS soportistas_asignados
            FROM visitas_sitio v
            INNER JOIN visitas_sitio_soportistas vss ON vss.id_visita = v.id_visita
            INNER JOIN soportistas ON vss.id_soportista = soportistas.id_soportista
            WHERE v.estado = :estado
            GROUP BY v.id_visita, v.fecha_visita, v.nombre_institucion, v.descripcion_problema
            ORDER BY v.fecha_visita;";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':estado' => $estado]);
    $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtFondos = $pdo->query("SELECT id_fondos, fondos FROM t_fondos");
    $fondosPorId = [];
    foreach ($stmtFondos->fetchAll(PDO::FETCH_ASSOC) as $fondo) {
        $fondosPorId[(int)$fondo['id_fondos']] = $fondo['fondos'];
    }

    foreach ($visitas as &$visita) {
        $nombresFondos = [];
        if ($visita['arreglo_id_fondos'] !== null && $visita['arreglo_id_fondos'] !== '') {
            foreach (explode(',', $visita['arreglo_id_fondos']) as $idFondo) {
                $idFondo = (int)$idFondo;
                if (isset($fondosPorId[$idFondo])) {
                    $nombresFondos[] = $fondosPorId[$idFondo];
                }
            }
        }
        $visita['origenes_presupuestarios'] = implode(', ', $nombresFondos);
    }
    unset($visita);

    echo json_encode([
        'success' => true,
        'visitas' => $visitas
    ]);

} catch (PDOException $e) {
    error_log("Error en select-visitas-manual.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar visitas'
    ]);
}
?>
