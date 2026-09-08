<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validación de autenticación (Azure o sesión estándar)
$usuario_azure = null;
if (file_exists(__DIR__ . '/../usuarioAzure.php')) {
    require_once __DIR__ . '/../usuarioAzure.php';
    if (function_exists('obtenerUsuarioSesion')) {
        $usuario_azure = obtenerUsuarioSesion();
    }
}

$logcodigo = $usuario_azure['codigoPresu'] ?? ($_SESSION['codigo'] ?? null);
$logusuario = $usuario_azure['cedula'] ?? ($_SESSION['cedula'] ?? null);

// Validar permisos
$tienellave = false;
if (isset($_SESSION['tipo']) && in_array($_SESSION['tipo'], [1, 2, 3])) {
    $tienellave = true;
} elseif ($usuario_azure !== null) {
    $tienellave = true;
}

if (!$tienellave || empty($logcodigo)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado o sesión expirada']);
    exit();
}

require_once(__DIR__ . "/../conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}
mysqli_set_charset($link, "utf8");

header('Content-Type: application/json; charset=utf-8');

// Obtener id_ins de la institución para consultar ámbitos
$stmt_ins = mysqli_prepare($link, "SELECT id_ins FROM t_instituciones WHERE codigo = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_ins, "s", $logcodigo);
mysqli_stmt_execute($stmt_ins);
$res_ins = mysqli_stmt_get_result($stmt_ins);
$row_ins = mysqli_fetch_assoc($res_ins);
$id_instituciones = (int)($row_ins['id_ins'] ?? 0);
mysqli_stmt_close($stmt_ins);

// Parámetros de filtrado y paginación
$page       = max(1, intval($_GET['page'] ?? 1));
$limit      = max(10, min(200, intval($_GET['limit'] ?? 50)));
$offset     = ($page - 1) * $limit;
$search     = trim($_GET['search'] ?? '');
$id_fondos  = trim($_GET['id_fondos'] ?? '');
$alias_id   = trim($_GET['alias_id'] ?? '');
$id_ag      = trim($_GET['id_ag'] ?? '');
$id_estado  = trim($_GET['id_estado'] ?? '');
$id_lugar   = trim($_GET['id_lugar'] ?? '');
$id_ambito  = trim($_GET['id_ambito'] ?? '');

// 1. Obtener lista completa de alias de la institución y sus conteos actuales
$sql_alias = "SELECT a.alias_id, a.alias, a.alias_imagen, 
                     COUNT(p.id_placa) AS total_activos
              FROM t_alias a
              LEFT JOIN t_placa p ON a.alias_id = p.alias_id AND p.codigo = ? AND p.activo = 1
              WHERE a.codigo = ?
              GROUP BY a.alias_id, a.alias, a.alias_imagen
              ORDER BY a.alias ASC";

$stmt_alias = mysqli_prepare($link, $sql_alias);
mysqli_stmt_bind_param($stmt_alias, "ss", $logcodigo, $logcodigo);
mysqli_stmt_execute($stmt_alias);
$res_alias = mysqli_stmt_get_result($stmt_alias);
$lista_alias = [];
$total_activos_con_alias = 0;
while ($row = mysqli_fetch_assoc($res_alias)) {
    $row['total_activos'] = (int)$row['total_activos'];
    $total_activos_con_alias += $row['total_activos'];
    $lista_alias[] = $row;
}
mysqli_stmt_close($stmt_alias);

// Conteo de activos sin contenedor (alias_id = 0 o NULL)
$sql_libres = "SELECT COUNT(*) as total_libres 
               FROM t_placa 
               WHERE codigo = ? AND activo = 1 AND (alias_id = 0 OR alias_id IS NULL)";
$stmt_libres = mysqli_prepare($link, $sql_libres);
mysqli_stmt_bind_param($stmt_libres, "s", $logcodigo);
mysqli_stmt_execute($stmt_libres);
$res_libres = mysqli_stmt_get_result($stmt_libres);
$row_libres = mysqli_fetch_assoc($res_libres);
$total_sin_contenedor = (int)($row_libres['total_libres'] ?? 0);
mysqli_stmt_close($stmt_libres);

// 2. Construcción de consulta dinámica para el listado de activos
$where_clauses = ["Tp.codigo = ?", "Tp.activo = 1"];
$params = [$logcodigo];
$types = "s";

// Filtro de origen presupuestario (t_fondos)
if ($id_fondos !== '' && is_numeric($id_fondos)) {
    $where_clauses[] = "Tp.id_fondos = ?";
    $params[] = (int)$id_fondos;
    $types .= "i";
}

// Filtro de contenedor/alias:
// 'sin_alias' o '0' => alias_id = 0 OR alias_id IS NULL
// 'con_alias'       => alias_id != 0 AND alias_id IS NOT NULL
// id numérico       => alias_id = id
if ($alias_id !== '') {
    if ($alias_id === '0' || $alias_id === 'sin_alias') {
        $where_clauses[] = "(Tp.alias_id = 0 OR Tp.alias_id IS NULL)";
    } elseif ($alias_id === 'con_alias') {
        $where_clauses[] = "(Tp.alias_id != 0 AND Tp.alias_id IS NOT NULL)";
    } elseif (is_numeric($alias_id)) {
        $where_clauses[] = "Tp.alias_id = ?";
        $params[] = (int)$alias_id;
        $types .= "i";
    }
}

// Filtro de clase de activo (t_activo_general)
if ($id_ag !== '' && is_numeric($id_ag)) {
    $where_clauses[] = "Ta.id_ag = ?";
    $params[] = (int)$id_ag;
    $types .= "i";
}

// Filtro de estado (t_estado)
if ($id_estado !== '' && is_numeric($id_estado)) {
    $where_clauses[] = "Tp.id_estado = ?";
    $params[] = (int)$id_estado;
    $types .= "i";
}

// Filtro de lugar (t_lugar)
if ($id_lugar !== '' && is_numeric($id_lugar)) {
    $where_clauses[] = "Tp.id_lugar = ?";
    $params[] = (int)$id_lugar;
    $types .= "i";
}

// Filtro de ámbito (t_ambitos_prestador)
if ($id_ambito !== '') {
    if ($id_ambito === 'sin_ambito') {
        $where_clauses[] = "Tamb.id_ambito IS NULL";
    } elseif (is_numeric($id_ambito)) {
        $where_clauses[] = "Tamb.id_ambito = ?";
        $params[] = (int)$id_ambito;
        $types .= "i";
    }
}

// Filtro de búsqueda textual (placa, serial, modelo, marca, clase, alias, ambito)
if ($search !== '') {
    $search_term = "%" . $search . "%";
    $where_clauses[] = "(Tp.placa LIKE ? OR Tp.serial LIKE ? OR Ta.modelo LIKE ? OR Tm.marca LIKE ? OR Tg.clase LIKE ? OR Tz.alias LIKE ? OR Tamb.nombre LIKE ?)";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $search_term;
        $types .= "s";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// 3. Conteo total de registros con los filtros aplicados
$sql_count = "SELECT COUNT(*) as total_filtrados
              FROM t_placa Tp
              INNER JOIN t_activo Ta ON Tp.id_activo = Ta.id_activo
              INNER JOIN t_marca Tm ON Ta.id_marca = Tm.id_marca
              INNER JOIN t_color Tc ON Ta.id_color = Tc.id_color
              INNER JOIN t_activo_general Tg ON Ta.id_ag = Tg.id_ag
              LEFT JOIN t_fondos Tf ON Tp.id_fondos = Tf.id_fondos
              LEFT JOIN t_lugar Tl ON Tp.id_lugar = Tl.id_lugar
              LEFT JOIN t_estado Te ON Tp.id_estado = Te.id_estado
              LEFT JOIN t_alias Tz ON Tp.alias_id = Tz.alias_id
              LEFT JOIN (
                  SELECT apl.lugar_id, amb.id_ambito, amb.nombre
                  FROM t_ambitos_prestador_lugar apl
                  INNER JOIN (
                      SELECT lugar_id, MAX(id) AS max_id
                      FROM t_ambitos_prestador_lugar
                      GROUP BY lugar_id
                  ) latest_apl ON apl.id = latest_apl.max_id
                  INNER JOIN t_ambitos_prestador amb ON apl.ambito_id = amb.id_ambito 
                                                      AND amb.id_instituciones = ? 
                                                      AND amb.eliminado = 0
              ) Tamb ON Tp.id_lugar = Tamb.lugar_id
              WHERE $where_sql";

$params_count = array_merge([$id_instituciones], $params);
$types_count = "i" . $types;

$stmt_count = mysqli_prepare($link, $sql_count);
if (!$stmt_count) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en consulta de conteo: ' . mysqli_error($link)]);
    exit();
}
if ($types_count !== "") {
    mysqli_stmt_bind_param($stmt_count, $types_count, ...$params_count);
}
mysqli_stmt_execute($stmt_count);
$res_count = mysqli_stmt_get_result($stmt_count);
$total_filtrados = (int)(mysqli_fetch_assoc($res_count)['total_filtrados'] ?? 0);
mysqli_stmt_close($stmt_count);

// 4. Consulta paginada de registros
$sql_data = "SELECT Tp.id_placa, Tp.placa, Tp.serial, Tp.codigo, Tp.activo, 
                    Tp.numero_activo, Tp.alias_id,
                    Ta.id_activo, Ta.modelo,
                    Tg.id_ag, Tg.clase, Tg.imagen AS clase_imagen,
                    Tm.id_marca, Tm.marca, Tm.logo AS marca_logo,
                    Tc.id_color, Tc.color,
                    Tf.id_fondos, Tf.fondos,
                    Tl.id_lugar, Tl.lugar,
                    Te.id_estado, Te.estado,
                    Tz.alias, Tz.alias_imagen,
                    Tamb.id_ambito, Tamb.nombre AS ambito_nombre
             FROM t_placa Tp
             INNER JOIN t_activo Ta ON Tp.id_activo = Ta.id_activo
             INNER JOIN t_marca Tm ON Ta.id_marca = Tm.id_marca
             INNER JOIN t_color Tc ON Ta.id_color = Tc.id_color
             INNER JOIN t_activo_general Tg ON Ta.id_ag = Tg.id_ag
             LEFT JOIN t_fondos Tf ON Tp.id_fondos = Tf.id_fondos
             LEFT JOIN t_lugar Tl ON Tp.id_lugar = Tl.id_lugar
             LEFT JOIN t_estado Te ON Tp.id_estado = Te.id_estado
             LEFT JOIN t_alias Tz ON Tp.alias_id = Tz.alias_id
             LEFT JOIN (
                  SELECT apl.lugar_id, amb.id_ambito, amb.nombre
                  FROM t_ambitos_prestador_lugar apl
                  INNER JOIN (
                      SELECT lugar_id, MAX(id) AS max_id
                      FROM t_ambitos_prestador_lugar
                      GROUP BY lugar_id
                  ) latest_apl ON apl.id = latest_apl.max_id
                  INNER JOIN t_ambitos_prestador amb ON apl.ambito_id = amb.id_ambito 
                                                      AND amb.id_instituciones = ? 
                                                      AND amb.eliminado = 0
             ) Tamb ON Tp.id_lugar = Tamb.lugar_id
             WHERE $where_sql
             ORDER BY Tz.alias ASC, Tp.numero_activo ASC, Tg.clase ASC, Tp.placa ASC
             LIMIT ? OFFSET ?";

$params_data = array_merge([$id_instituciones], $params);
$types_data = "i" . $types . "ii";
$params_data[] = $limit;
$params_data[] = $offset;

$stmt_data = mysqli_prepare($link, $sql_data);
if (!$stmt_data) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en consulta de datos: ' . mysqli_error($link)]);
    exit();
}
mysqli_stmt_bind_param($stmt_data, $types_data, ...$params_data);
mysqli_stmt_execute($stmt_data);
$res_data = mysqli_stmt_get_result($stmt_data);

$activos = [];
while ($row = mysqli_fetch_assoc($res_data)) {
    $activos[] = [
        'id_placa'      => (int)$row['id_placa'],
        'placa'         => $row['placa'] ?? '',
        'serial'        => $row['serial'] ?? '',
        'clase'         => $row['clase'] ?? 'Sin categoría',
        'clase_imagen'  => $row['clase_imagen'] ?? '',
        'marca'         => $row['marca'] ?? '',
        'marca_logo'    => $row['marca_logo'] ?? '',
        'modelo'        => $row['modelo'] ?? '',
        'color'         => $row['color'] ?? '',
        'fondos'        => $row['fondos'] ?? 'No especificado',
        'id_fondos'     => (int)($row['id_fondos'] ?? 0),
        'lugar'         => $row['lugar'] ?? 'Sin asignar',
        'id_lugar'      => (int)($row['id_lugar'] ?? 0),
        'id_ambito'     => !empty($row['id_ambito']) ? (int)$row['id_ambito'] : null,
        'ambito_nombre' => $row['ambito_nombre'] ?? null,
        'estado'        => $row['estado'] ?? 'Normal',
        'id_estado'     => (int)($row['id_estado'] ?? 0),
        'alias_id'      => (int)($row['alias_id'] ?? 0),
        'alias'         => $row['alias'] ?? null,
        'alias_imagen'  => $row['alias_imagen'] ?? '',
        'numero_activo' => $row['numero_activo'] !== null ? (int)$row['numero_activo'] : null
    ];
}
mysqli_stmt_close($stmt_data);
mysqli_close($link);

$total_pages = ceil($total_filtrados / $limit);

echo json_encode([
    'success' => true,
    'data' => [
        'resumen' => [
            'total_con_alias'     => $total_activos_con_alias,
            'total_sin_contenedor'=> $total_sin_contenedor,
            'total_general'       => $total_activos_con_alias + $total_sin_contenedor,
            'alias'               => $lista_alias
        ],
        'activos' => $activos,
        'paginacion' => [
            'total_registros' => $total_filtrados,
            'total_paginas'   => max(1, $total_pages),
            'pagina_actual'   => $page,
            'por_pagina'      => $limit
        ]
    ]
], JSON_UNESCAPED_UNICODE);
