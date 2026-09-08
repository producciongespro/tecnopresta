<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

require_once("db_config.php");

try {
    if (!isset($_GET['id_visita']) || empty($_GET['id_visita'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'id_visita es requerido']);
        exit;
    }

    $id_visita = $_GET['id_visita'];
    $pdo = getDBConnection();

    // Nombre del Centro Educativo
    $stmt = $pdo->prepare("SELECT tickets.dependencia FROM tickets INNER JOIN tickets_visitas_sitio ON tickets.id = tickets_visitas_sitio.id WHERE tickets_visitas_sitio.id_visita = :id_visita");
    $stmt->execute([':id_visita' => $id_visita]);
    $centro = $stmt->fetchColumn();

    // Fecha de la visita
    $stmt = $pdo->prepare("SELECT fecha_visita FROM visitas_sitio WHERE id_visita = :id_visita");
    $stmt->execute([':id_visita' => $id_visita]);
    $fecha_raw = $stmt->fetchColumn();
    $fecha_visita = $fecha_raw ? date('d/m/Y', strtotime($fecha_raw)) : null;

    // Ingenieros asignados (con ticket vía tickets_visitas_sitio, sin ticket vía visitas_sitio_soportistas)
    $stmt = $pdo->prepare("SELECT DISTINCT soportistas.nombre
        FROM soportistas
        WHERE soportistas.id_soportista IN (
            SELECT id_soportista FROM tickets_visitas_sitio WHERE id_visita = :id_visita
            UNION
            SELECT id_soportista FROM visitas_sitio_soportistas WHERE id_visita = :id_visita2
        )");
    $stmt->execute([':id_visita' => $id_visita, ':id_visita2' => $id_visita]);
    $ingenieros = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Persona(s) de contacto, motivo, descripcion
    $stmt = $pdo->prepare("SELECT persona_contacto, titulo_problema, descripcion_problema FROM visitas_sitio WHERE id_visita = :id_visita");
    $stmt->execute([':id_visita' => $id_visita]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $persona_contacto = $row['persona_contacto'] ?? null;
    $titulo_problema = $row['titulo_problema'] ?? null;
    $descripcion_problema = $row['descripcion_problema'] ?? null;

    // Procedimientos realizados
    $stmt = $pdo->prepare("SELECT visitas_procedimiento.visitas_procedimiento_descripcion AS descripcion, visitas_sitio_hoja_trabajo_procedimiento.visitas_sitio_hoja_trabajo_procedimiento_comentario AS comentario FROM visitas_sitio_hoja_trabajo_procedimiento INNER JOIN visitas_procedimiento ON visitas_sitio_hoja_trabajo_procedimiento.visitas_procedimiento_id = visitas_procedimiento.visitas_procedimiento_id INNER JOIN visitas_sitio_hoja_trabajo ON visitas_sitio_hoja_trabajo.visitas_sitio_hoja_trabajo_id = visitas_sitio_hoja_trabajo_procedimiento.visitas_sitio_hoja_trabajo_id WHERE visitas_sitio_hoja_trabajo.id_visita = :id_visita");
    $stmt->execute([':id_visita' => $id_visita]);
    $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resultado final e indicaciones
    $stmt = $pdo->prepare("SELECT visitas_sitio_hoja_trabajo_resultado AS resultado, visitas_sitio_hoja_trabajo_indicaciones AS indicaciones FROM visitas_sitio_hoja_trabajo WHERE id_visita = :id_visita");
    $stmt->execute([':id_visita' => $id_visita]);
    $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
    $resultado_final = $row2['resultado'] ?? null;
    $indicaciones = $row2['indicaciones'] ?? null;

    // Equipos atendidos
    $stmt = $pdo->prepare("SELECT t_activo_general.clase AS clase, t_marca.marca AS marca, t_activo.modelo AS modelo, t_placa.placa AS placa, t_placa.serial AS serie, visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_comentario AS comentario FROM t_placa INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag INNER JOIN visitas_sitio_hoja_trabajo_activos ON visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_id_placa = t_placa.id_placa INNER JOIN visitas_sitio_hoja_trabajo ON visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_id = visitas_sitio_hoja_trabajo.visitas_sitio_hoja_trabajo_id WHERE visitas_sitio_hoja_trabajo.id_visita = :id_visita");
    $stmt->execute([':id_visita' => $id_visita]);
    $equipos_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $equipos = [];
    foreach ($equipos_rows as $r) {
        $descripcion = trim(trim($r['clase'] ?? '') . ' ' . trim($r['marca'] ?? '') . ' ' . trim($r['modelo'] ?? ''));
        $equipos[] = [
            'descripcion' => $descripcion,
            'placa' => $r['placa'] ?? null,
            'serie' => $r['serie'] ?? null,
            'comentario' => $r['comentario'] ?? null
        ];
    }

    $data = [
        'centro' => $centro,
        'fecha_visita' => $fecha_visita,
        'ingenieros' => $ingenieros,
        'persona_contacto' => $persona_contacto,
        'titulo_problema' => $titulo_problema,
        'descripcion_problema' => $descripcion_problema,
        'procedimientos' => $procedimientos,
        'resultado_final' => $resultado_final,
        'indicaciones' => $indicaciones,
        'equipos' => $equipos
    ];

    echo json_encode(['success' => true, 'datos' => $data], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log('Error select-informe-visita-tecnica: ' . $e->getMessage());
    echo json_encode(['success' => false, 'mensaje' => 'Error interno del servidor']);
    exit;
}

?>
