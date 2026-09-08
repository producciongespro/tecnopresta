<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_azure = null;
if (file_exists(__DIR__ . '/../usuarioAzure.php')) {
    require_once __DIR__ . '/../usuarioAzure.php';
    if (function_exists('obtenerUsuarioSesion')) {
        $usuario_azure = obtenerUsuarioSesion();
    }
}

$logcodigo = $usuario_azure['codigoPresu'] ?? ($_SESSION['codigo'] ?? null);
$logusuario = $usuario_azure['cedula'] ?? ($_SESSION['cedula'] ?? null);

$tienellave = false;
if (isset($_SESSION['tipo']) && in_array($_SESSION['tipo'], [1, 2, 3])) {
    $tienellave = true;
} elseif ($usuario_azure !== null) {
    $tienellave = true;
}

header('Content-Type: application/json; charset=utf-8');

if (!$tienellave || empty($logcodigo)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado o sesión expirada']);
    exit();
}

require_once(__DIR__ . "/../conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit();
}
mysqli_set_charset($link, "utf8");

// Leer payload JSON o POST estándar
$raw_input = file_get_contents('php://input');
$payload = json_decode($raw_input, true) ?? $_POST;

$action = $payload['action'] ?? '';

if (!in_array($action, ['asignar_alias', 'quitar_alias', 'actualizar_numeracion', 'guardar_lote'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no soportada o inválida']);
    mysqli_close($link);
    exit();
}

// Obtener id_ins de la institución
$stmt_ins = mysqli_prepare($link, "SELECT id_ins FROM t_instituciones WHERE codigo = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_ins, "s", $logcodigo);
mysqli_stmt_execute($stmt_ins);
$res_ins = mysqli_stmt_get_result($stmt_ins);
$row_ins = mysqli_fetch_assoc($res_ins);
$id_instituciones = (int)($row_ins['id_ins'] ?? 0);
mysqli_stmt_close($stmt_ins);

if ($id_instituciones <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se pudo identificar la institución del usuario']);
    mysqli_close($link);
    exit();
}

mysqli_begin_transaction($link);

try {
    if ($action === 'asignar_alias') {
        $target_alias_id = intval($payload['alias_id'] ?? 0);
        $ids_placas = $payload['ids_placas'] ?? [];

        if ($target_alias_id <= 0) {
            throw new Exception("El contenedor seleccionado es inválido.");
        }
        if (empty($ids_placas) || !is_array($ids_placas)) {
            throw new Exception("No se especificaron activos para asignar.");
        }

        // Validar que el alias pertenezca al código de la institución
        $stmt_check_alias = mysqli_prepare($link, "SELECT alias, alias_imagen FROM t_alias WHERE alias_id = ? AND codigo = ?");
        mysqli_stmt_bind_param($stmt_check_alias, "is", $target_alias_id, $logcodigo);
        mysqli_stmt_execute($stmt_check_alias);
        $res_alias = mysqli_stmt_get_result($stmt_check_alias);
        $info_alias = mysqli_fetch_assoc($res_alias);
        mysqli_stmt_close($stmt_check_alias);

        if (!$info_alias) {
            throw new Exception("El contenedor seleccionado no existe o no pertenece a su institución.");
        }

        // Sanitizar array de enteros
        $ids_clean = array_map('intval', $ids_placas);
        $ids_clean = array_values(array_unique(array_filter($ids_clean, function($id) { return $id > 0; })));

        if (empty($ids_clean)) {
            throw new Exception("IDs de placas inválidos.");
        }

        // 1. Obtener información de ámbito y lugar de los activos que se quieren asignar
        $placeholders_in = implode(',', array_fill(0, count($ids_clean), '?'));
        $sql_activos_amb = "SELECT p.id_placa, p.placa, p.id_lugar, l.lugar,
                                   amb.id_ambito, amb.nombre AS ambito_nombre
                            FROM t_placa p
                            LEFT JOIN t_lugar l ON p.id_lugar = l.id_lugar
                            LEFT JOIN (
                                SELECT apl.lugar_id, a.id_ambito, a.nombre
                                FROM t_ambitos_prestador_lugar apl
                                INNER JOIN (
                                    SELECT lugar_id, MAX(id) AS max_id
                                    FROM t_ambitos_prestador_lugar
                                    GROUP BY lugar_id
                                ) latest_apl ON apl.id = latest_apl.max_id
                                INNER JOIN t_ambitos_prestador a ON apl.ambito_id = a.id_ambito 
                                                                  AND a.id_instituciones = ? 
                                                                  AND a.eliminado = 0
                            ) amb ON p.id_lugar = amb.lugar_id
                            WHERE p.codigo = ? AND p.id_placa IN ($placeholders_in)";

        $stmt_activos_amb = mysqli_prepare($link, $sql_activos_amb);
        if (!$stmt_activos_amb) {
            throw new Exception("Error al preparar consulta de ámbitos: " . mysqli_error($link));
        }
        $types_activos = "is" . str_repeat('i', count($ids_clean));
        $params_activos = array_merge([$id_instituciones, $logcodigo], $ids_clean);
        mysqli_stmt_bind_param($stmt_activos_amb, $types_activos, ...$params_activos);
        mysqli_stmt_execute($stmt_activos_amb);
        $res_activos_amb = mysqli_stmt_get_result($stmt_activos_amb);

        $activos_sin_ambito = [];
        $ambitos_encontrados = []; // [id_ambito => nombre]

        while ($row_act = mysqli_fetch_assoc($res_activos_amb)) {
            if (empty($row_act['id_ambito'])) {
                $ubicacion_desc = $row_act['lugar'] ? $row_act['lugar'] : 'Sin lugar';
                $activos_sin_ambito[] = "Placa {$row_act['placa']} ({$ubicacion_desc})";
            } else {
                $ambitos_encontrados[$row_act['id_ambito']] = $row_act['ambito_nombre'];
            }
        }
        mysqli_stmt_close($stmt_activos_amb);

        // Validación A: Ningún activo puede carecer de ámbito
        if (!empty($activos_sin_ambito)) {
            $lista_placas = implode(', ', array_slice($activos_sin_ambito, 0, 5));
            if (count($activos_sin_ambito) > 5) {
                $lista_placas .= " y " . (count($activos_sin_ambito) - 5) . " más";
            }
            throw new Exception("No se puede realizar la asignación. Los siguientes activos no tienen un ámbito asociado: " . $lista_placas . ". Configure primero el ámbito de sus ubicaciones de resguardo.");
        }

        // Validación B: Todos los activos a asignar deben pertenecer al mismo ámbito entre sí
        if (count($ambitos_encontrados) > 1) {
            $nombres_ambitos = implode(', ', array_unique(array_values($ambitos_encontrados)));
            throw new Exception("Conflicto de ámbitos: Ha seleccionado activos pertenecientes a diferentes ámbitos ({$nombres_ambitos}). Un contenedor solo puede albergar activos del mismo ámbito.");
        }

        $id_ambito_seleccionados = array_key_first($ambitos_encontrados);
        $nombre_ambito_seleccionados = reset($ambitos_encontrados);

        // Validación C: Si el contenedor ya tiene activos, debe coincidir con el ámbito de esos activos
        $sql_check_existentes = "SELECT DISTINCT amb.id_ambito, amb.nombre AS ambito_nombre
                                 FROM t_placa p
                                 INNER JOIN (
                                     SELECT apl.lugar_id, a.id_ambito, a.nombre
                                     FROM t_ambitos_prestador_lugar apl
                                     INNER JOIN (
                                         SELECT lugar_id, MAX(id) AS max_id
                                         FROM t_ambitos_prestador_lugar
                                         GROUP BY lugar_id
                                     ) latest_apl ON apl.id = latest_apl.max_id
                                     INNER JOIN t_ambitos_prestador a ON apl.ambito_id = a.id_ambito 
                                                                       AND a.id_instituciones = ? 
                                                                       AND a.eliminado = 0
                                 ) amb ON p.id_lugar = amb.lugar_id
                                 WHERE p.codigo = ? AND p.alias_id = ? AND p.activo = 1";

        $stmt_check_existentes = mysqli_prepare($link, $sql_check_existentes);
        mysqli_stmt_bind_param($stmt_check_existentes, "isi", $id_instituciones, $logcodigo, $target_alias_id);
        mysqli_stmt_execute($stmt_check_existentes);
        $res_check_existentes = mysqli_stmt_get_result($stmt_check_existentes);

        $ambitos_existentes = [];
        while ($row_ex = mysqli_fetch_assoc($res_check_existentes)) {
            if (!empty($row_ex['id_ambito'])) {
                $ambitos_existentes[$row_ex['id_ambito']] = $row_ex['ambito_nombre'];
            }
        }
        mysqli_stmt_close($stmt_check_existentes);

        if (!empty($ambitos_existentes)) {
            if (!isset($ambitos_existentes[$id_ambito_seleccionados])) {
                $nombre_existente = implode(', ', array_values($ambitos_existentes));
                throw new Exception("El contenedor '{$info_alias['alias']}' ya tiene activos asignados al ámbito '{$nombre_existente}'. No es posible agregar activos del ámbito '{$nombre_ambito_seleccionados}'.");
            }
        }

        // Crear placeholders para la consulta de actualización segura
        $placeholders = implode(',', array_fill(0, count($ids_clean), '?'));
        $sql_update = "UPDATE t_placa 
                       SET alias_id = ? 
                       WHERE codigo = ? AND id_placa IN ($placeholders)";

        $stmt_update = mysqli_prepare($link, $sql_update);
        $types = "is" . str_repeat('i', count($ids_clean));
        $params = array_merge([$target_alias_id, $logcodigo], $ids_clean);
        mysqli_stmt_bind_param($stmt_update, $types, ...$params);
        mysqli_stmt_execute($stmt_update);
        $afectados = mysqli_stmt_affected_rows($stmt_update);
        mysqli_stmt_close($stmt_update);

        mysqli_commit($link);
        echo json_encode([
            'success' => true,
            'message' => "Se asignaron {$afectados} activos al contenedor '{$info_alias['alias']}' (Ámbito: {$nombre_ambito_seleccionados}).",
            'afectados' => $afectados,
            'alias' => $info_alias,
            'ambito' => [
                'id_ambito' => $id_ambito_seleccionados,
                'nombre' => $nombre_ambito_seleccionados
            ]
        ]);

    } elseif ($action === 'quitar_alias') {
        $ids_placas = $payload['ids_placas'] ?? [];

        if (empty($ids_placas) || !is_array($ids_placas)) {
            throw new Exception("No se especificaron activos para desvincular.");
        }

        $ids_clean = array_map('intval', $ids_placas);
        $ids_clean = array_filter($ids_clean, function($id) { return $id > 0; });

        if (empty($ids_clean)) {
            throw new Exception("IDs de placas inválidos.");
        }

        $placeholders = implode(',', array_fill(0, count($ids_clean), '?'));
        // Al quitar del contenedor, se reinicia alias_id a 0 y numero_activo a NULL
        $sql_update = "UPDATE t_placa 
                       SET alias_id = 0, numero_activo = NULL 
                       WHERE codigo = ? AND id_placa IN ($placeholders)";

        $stmt_update = mysqli_prepare($link, $sql_update);
        $types = "s" . str_repeat('i', count($ids_clean));
        $params = array_merge([$logcodigo], $ids_clean);
        mysqli_stmt_bind_param($stmt_update, $types, ...$params);
        mysqli_stmt_execute($stmt_update);
        $afectados = mysqli_stmt_affected_rows($stmt_update);
        mysqli_stmt_close($stmt_update);

        mysqli_commit($link);
        echo json_encode([
            'success' => true,
            'message' => "Se desvincularon {$afectados} activos de sus contenedores.",
            'afectados' => $afectados
        ]);

    } elseif ($action === 'actualizar_numeracion') {
        $id_placa = intval($payload['id_placa'] ?? 0);
        $numero = isset($payload['numero_activo']) && $payload['numero_activo'] !== '' ? intval($payload['numero_activo']) : null;

        if ($id_placa <= 0) {
            throw new Exception("ID de activo inválido.");
        }

        // Verificar que el activo pertenezca al código y tenga alias asignado
        $stmt_check = mysqli_prepare($link, "SELECT alias_id FROM t_placa WHERE id_placa = ? AND codigo = ?");
        mysqli_stmt_bind_param($stmt_check, "is", $id_placa, $logcodigo);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);
        $placa_info = mysqli_fetch_assoc($res_check);
        mysqli_stmt_close($stmt_check);

        if (!$placa_info) {
            throw new Exception("El activo no pertenece a su institución.");
        }

        if (empty($placa_info['alias_id']) || $placa_info['alias_id'] == 0) {
            throw new Exception("No se puede asignar número a un activo sin contenedor.");
        }

        $sql_num = "UPDATE t_placa SET numero_activo = ? WHERE id_placa = ? AND codigo = ?";
        $stmt_num = mysqli_prepare($link, $sql_num);
        mysqli_stmt_bind_param($stmt_num, "iis", $numero, $id_placa, $logcodigo);
        mysqli_stmt_execute($stmt_num);
        $afectados = mysqli_stmt_affected_rows($stmt_num);
        mysqli_stmt_close($stmt_num);

        mysqli_commit($link);
        echo json_encode([
            'success' => true,
            'message' => 'Numeración actualizada con éxito.',
            'id_placa' => $id_placa,
            'numero_activo' => $numero
        ]);

    } elseif ($action === 'guardar_lote') {
        $items = $payload['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            throw new Exception("No hay elementos para actualizar.");
        }

        $total_modificados = 0;
        $stmt_item = mysqli_prepare($link, "UPDATE t_placa SET numero_activo = ? WHERE id_placa = ? AND codigo = ? AND alias_id != 0");
        
        foreach ($items as $item) {
            $id = intval($item['id_placa'] ?? 0);
            $num = isset($item['numero_activo']) && $item['numero_activo'] !== '' ? intval($item['numero_activo']) : null;
            if ($id > 0) {
                mysqli_stmt_bind_param($stmt_item, "iis", $num, $id, $logcodigo);
                mysqli_stmt_execute($stmt_item);
                if (mysqli_stmt_affected_rows($stmt_item) > 0) {
                    $total_modificados++;
                }
            }
        }
        mysqli_stmt_close($stmt_item);

        mysqli_commit($link);
        echo json_encode([
            'success' => true,
            'message' => "Se actualizaron las numeraciones de {$total_modificados} activos.",
            'afectados' => $total_modificados
        ]);
    }

} catch (Exception $e) {
    mysqli_rollback($link);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    mysqli_close($link);
}
