<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
    
    $cedula = $_GET['cedula'];

    if ($cedula == null OR $cedula === null OR $cedula == '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener la cédula'
        ]);
        exit;
    }

    $estado = isset($_GET['estado']) ? (int)$_GET['estado'] : 1;

    if (!in_array($estado, [1, 3])) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado no permitido'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    

    $sql = "SELECT visitas_sitio.id_visita,
                   visitas_sitio.codigo_institucion,
                   visitas_sitio.nombre_institucion,
                   visitas_sitio.telefono,
                   visitas_sitio.correo_institucional,
                   visitas_sitio.direccion,
                   visitas_sitio.persona_contacto,                   
                   DATE_FORMAT(visitas_sitio.fecha_visita, '%d/%m/%Y') AS fecha_visita,
                   DATE_FORMAT(visitas_sitio.hora_visita, '%H:%i') AS hora_visita,
                   visitas_sitio.labor_realizar,
                   visitas_sitio.observaciones,
                   visitas_sitio.descripcion_problema 
            FROM
            soportistas
            INNER JOIN
            tickets_visitas_sitio ON
            tickets_visitas_sitio.id_soportista = soportistas.id_soportista
            INNER JOIN
            visitas_sitio ON
            visitas_sitio.id_visita = tickets_visitas_sitio.id_visita
            WHERE soportistas.cedula = :cedula
            AND visitas_sitio.estado = :estado

            UNION

            SELECT visitas_sitio.id_visita, 
                   visitas_sitio.codigo_institucion,
                   visitas_sitio.nombre_institucion,
                   visitas_sitio.telefono,
                   visitas_sitio.correo_institucional,
                   visitas_sitio.direccion,
                   visitas_sitio.persona_contacto,
                   DATE_FORMAT(visitas_sitio.fecha_visita, '%d/%m/%Y') AS fecha_visita,
                   DATE_FORMAT(visitas_sitio.hora_visita, '%H:%i') AS hora_visita,
                   visitas_sitio.labor_realizar,
                   visitas_sitio.observaciones,
                   visitas_sitio.descripcion_problema 
            FROM
            soportistas
            INNER JOIN
            visitas_sitio_soportistas ON
            visitas_sitio_soportistas.id_soportista = soportistas.id_soportista
            INNER JOIN
            visitas_sitio ON
            visitas_sitio.id_visita = visitas_sitio_soportistas.id_visita
            WHERE soportistas.cedula = :cedula2
            AND visitas_sitio.estado = :estado2

            ORDER BY fecha_visita;";

    $params = [':cedula' => $cedula, ':estado' => $estado, ':cedula2' => $cedula, ':estado2' => $estado];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $soportista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
                'success' => true,
                'soportistavisita' => $soportista
                ]);

    exit;

    } catch (PDOException $e) {
    error_log("Error en select-soportista-cedula.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar soportista'
    ]);
}
?>