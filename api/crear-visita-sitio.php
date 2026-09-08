<?php

require_once("db_config.php");
require_once __DIR__ . '/../notificaciones-visita.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $body = json_decode(file_get_contents('php://input'), true);

    $id_ticket = $body['id_ticket'] ?? null;
    $id_usuario_registro = $body['id_usuario_registro'] ?? null;
    $codigo_institucion = trim($body['codigo_institucion'] ?? '');
    $nombre_institucion = trim($body['nombre_institucion'] ?? '');
    $telefono = trim($body['telefono'] ?? '');
    $correo_institucional = trim($body['correo_institucional'] ?? '');
    $direccion = trim($body['direccion'] ?? '');
    $persona_contacto = trim($body['persona_contacto'] ?? '');
    $fecha_visita = $body['fecha_visita'] ?? null;
    $hora_visita = $body['hora_visita'] ?? null;
    $descripcion_problema = trim($body['descripcion_problema'] ?? '');
    $labor_realizar = trim($body['labor_realizar'] ?? '');
    $observaciones = trim($body['observaciones'] ?? '');
    $arreglo_id_fondos = trim($body['arreglo_id_fondos'] ?? '');
    $soportistas = $body['soportistas'] ?? null;
    $notificar_centro = (bool) ($body['notificar_centro'] ?? false);
    $notificar_soportistas = (bool) ($body['notificar_soportistas'] ?? false);

    $faltantes = [];
    if ($id_ticket === null || $id_ticket === '') $faltantes[] = 'id_ticket';
    if ($id_usuario_registro === null || $id_usuario_registro === '') $faltantes[] = 'id_usuario_registro';
    if ($codigo_institucion === '') $faltantes[] = 'codigo_institucion';
    if ($nombre_institucion === '') $faltantes[] = 'nombre_institucion';
    if ($telefono === '') $faltantes[] = 'telefono';
    if ($correo_institucional === '') $faltantes[] = 'correo_institucional';
    if ($direccion === '') $faltantes[] = 'direccion';
    if ($persona_contacto === '') $faltantes[] = 'persona_contacto';
    if (empty($fecha_visita)) $faltantes[] = 'fecha_visita';
    if (empty($hora_visita)) $faltantes[] = 'hora_visita';
    if ($descripcion_problema === '') $faltantes[] = 'descripcion_problema';
    if ($labor_realizar === '') $faltantes[] = 'labor_realizar';
    if ($arreglo_id_fondos === '') $faltantes[] = 'arreglo_id_fondos';
    if (!is_array($soportistas) || count($soportistas) === 0) $faltantes[] = 'soportistas';

    if (!empty($faltantes)) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan campos requeridos: ' . implode(', ', $faltantes)
        ]);
        exit;
    }

    $pdo = getDBConnection();

    try {
        $pdo->beginTransaction();

        $sqlInsertVisita = "INSERT INTO visitas_sitio (
                codigo_institucion,
                nombre_institucion,
                telefono,
                correo_institucional,
                direccion,
                persona_contacto,
                fecha_visita,
                hora_visita,
                titulo_problema,
                descripcion_problema,
                prioridad,
                equipos_afectados,
                labor_realizar,
                observaciones,
                arreglo_id_fondos,
                problema_original,
                id_usuario_registro
            ) VALUES (
                :codigo_institucion,
                :nombre_institucion,
                :telefono,
                :correo_institucional,
                :direccion,
                :persona_contacto,
                :fecha_visita,
                :hora_visita,
                :titulo_problema,
                :descripcion_problema,
                :prioridad,
                NULL,
                :labor_realizar,
                :observaciones,
                :arreglo_id_fondos,
                NULL,
                :id_usuario_registro
            )";

        $stmtVisita = $pdo->prepare($sqlInsertVisita);
        $stmtVisita->execute([
            ':codigo_institucion' => $codigo_institucion,
            ':nombre_institucion' => $nombre_institucion,
            ':telefono' => $telefono,
            ':correo_institucional' => $correo_institucional,
            ':direccion' => $direccion,
            ':persona_contacto' => $persona_contacto,
            ':fecha_visita' => $fecha_visita,
            ':hora_visita' => $hora_visita,
            ':titulo_problema' => 'Técnico',
            ':descripcion_problema' => $descripcion_problema,
            ':prioridad' => 'alta',
            ':labor_realizar' => $labor_realizar,
            ':observaciones' => $observaciones !== '' ? $observaciones : null,
            ':arreglo_id_fondos' => $arreglo_id_fondos,
            ':id_usuario_registro' => $id_usuario_registro
        ]);

        $id_visita = $pdo->lastInsertId();

        $sqlInsertAsignacion = "INSERT INTO tickets_visitas_sitio (id_visita, id, id_soportista) VALUES (:id_visita, :id_ticket, :id_soportista)";
        $stmtAsignacion = $pdo->prepare($sqlInsertAsignacion);

        foreach ($soportistas as $id_soportista) {
            $stmtAsignacion->execute([
                ':id_visita' => $id_visita,
                ':id_ticket' => $id_ticket,
                ':id_soportista' => $id_soportista
            ]);
        }

        // Actualizar el estado del ticket a "En proceso" (estado_id = 2)
        // Antes estaba en 8, cambio realizado el 24/8/2026
        $sqlUpdateTicket = "UPDATE tickets SET estado_id = 2, updated_at = NOW() WHERE id = :id_ticket";
        $stmtUpdateTicket = $pdo->prepare($sqlUpdateTicket);
        $stmtUpdateTicket->execute([':id_ticket' => $id_ticket]);

        $pdo->commit();

        enviarNotificacionesVisita($pdo, [
            'nombre_institucion' => $nombre_institucion,
            'codigo_institucion' => $codigo_institucion,
            'telefono' => $telefono,
            'correo_institucional' => $correo_institucional,
            'direccion' => $direccion,
            'persona_contacto' => $persona_contacto,
            'fecha_visita' => $fecha_visita,
            'hora_visita' => $hora_visita,
            'descripcion_problema' => $descripcion_problema,
            'labor_realizar' => $labor_realizar,
            'observaciones' => $observaciones
        ], $soportistas, $notificar_centro, $notificar_soportistas);

        echo json_encode([
            'success' => true,
            'message' => 'La visita se creó satisfactoriamente'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Error en crear-visita-sitio.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la visita'
    ]);
}
?>
