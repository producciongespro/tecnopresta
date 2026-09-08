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

    $sql = "SELECT visitas_sitio.codigo_institucion,
                   visitas_sitio.telefono,
                   visitas_sitio.correo_institucional,
                   visitas_sitio.direccion,
                   visitas_sitio.persona_contacto,
                   DATE_FORMAT(visitas_sitio.fecha_visita, '%d/%m/%Y') AS fecha_visita,
                   DATE_FORMAT(visitas_sitio.hora_visita, '%H:%i') AS hora_visita,
                   visitas_sitio.labor_realizar,
                   visitas_sitio.observaciones,
                   (SELECT GROUP_CONCAT(f.fondos ORDER BY FIND_IN_SET(f.id_fondos, visitas_sitio.arreglo_id_fondos) SEPARATOR ', ')
                    FROM t_fondos f
                    WHERE FIND_IN_SET(f.id_fondos, visitas_sitio.arreglo_id_fondos)) AS fondos_descripcion
            FROM visitas_sitio
            WHERE id_visita = :id_visita";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_visita' => $id_visita]);

    $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $visitas
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Error en select-hoja-de-visita.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar hoja de visita'
    ]);
}

?>
