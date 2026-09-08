<?php

require_once("db_config.php");
require_once __DIR__ . '/../notificaciones-visita.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {

    $body = json_decode(file_get_contents('php://input'), true);

    $id_visita = $body['id_visita'] ?? null;
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
    if ($id_visita === null || $id_visita === '') $faltantes[] = 'id_visita';
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

    $stmtEstado = $pdo->prepare("SELECT estado FROM visitas_sitio WHERE id_visita = :id_visita");
    $stmtEstado->execute([':id_visita' => $id_visita]);
    $visitaActual = $stmtEstado->fetch(PDO::FETCH_ASSOC);

    if (!$visitaActual) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró la visita indicada'
        ]);
        exit;
    }

    if ((int)$visitaActual['estado'] !== 1) {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede editar una visita ya cerrada'
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $sqlUpdateVisita = "UPDATE visitas_sitio SET
                codigo_institucion = :codigo_institucion,
                nombre_institucion = :nombre_institucion,
                telefono = :telefono,
                correo_institucional = :correo_institucional,
                direccion = :direccion,
                persona_contacto = :persona_contacto,
                fecha_visita = :fecha_visita,
                hora_visita = :hora_visita,
                descripcion_problema = :descripcion_problema,
                labor_realizar = :labor_realizar,
                observaciones = :observaciones,
                arreglo_id_fondos = :arreglo_id_fondos
            WHERE id_visita = :id_visita";

        $stmtUpdateVisita = $pdo->prepare($sqlUpdateVisita);
        $stmtUpdateVisita->execute([
            ':codigo_institucion' => $codigo_institucion,
            ':nombre_institucion' => $nombre_institucion,
            ':telefono' => $telefono,
            ':correo_institucional' => $correo_institucional,
            ':direccion' => $direccion,
            ':persona_contacto' => $persona_contacto,
            ':fecha_visita' => $fecha_visita,
            ':hora_visita' => $hora_visita,
            ':descripcion_problema' => $descripcion_problema,
            ':labor_realizar' => $labor_realizar,
            ':observaciones' => $observaciones !== '' ? $observaciones : null,
            ':arreglo_id_fondos' => $arreglo_id_fondos,
            ':id_visita' => $id_visita
        ]);

        $stmtDeleteAsignaciones = $pdo->prepare("DELETE FROM visitas_sitio_soportistas WHERE id_visita = :id_visita");
        $stmtDeleteAsignaciones->execute([':id_visita' => $id_visita]);

        $sqlInsertAsignacion = "INSERT INTO visitas_sitio_soportistas (id_visita, id_soportista) VALUES (:id_visita, :id_soportista)";
        $stmtAsignacion = $pdo->prepare($sqlInsertAsignacion);

        foreach ($soportistas as $id_soportista) {
            $stmtAsignacion->execute([
                ':id_visita' => $id_visita,
                ':id_soportista' => $id_soportista
            ]);
        }

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
            'message' => 'La visita se actualizó satisfactoriamente'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Error en modificar-visita-manual.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la visita'
    ]);
}
?>
