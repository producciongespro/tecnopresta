<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once("../conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a MySQL: ' . mysqli_connect_error()]);
    exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo json_encode(['success' => false, 'error' => 'Error cargando el conjunto de caracteres utf8']);
    exit();
}

require_once __DIR__ . '/../usuarioAzure.php';

if (!obtenerUsuarioSesion()) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$id_fondos = intval($_POST['id_fondos'] ?? 0);
$busqueda  = trim($_POST['busqueda'] ?? '');

if ($id_fondos <= 0 && $busqueda === '') {
    $query = "SELECT DISTINCT a.id_activo, ag.clase, m.marca, a.modelo, c.color, ag.imagen
              FROM t_activo a
              INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
              INNER JOIN t_marca m ON a.id_marca = m.id_marca
              INNER JOIN t_color c ON a.id_color = c.id_color
              INNER JOIN t_modelos tm ON a.modelo_id = tm.id_modelo
              ORDER BY ag.clase, m.marca, a.modelo";

    $stmt = $link->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'resultados' => $rows, 'total' => count($rows)]);
    exit();
}

if ($id_fondos > 0) {
    $query = "SELECT DISTINCT a.id_activo, ag.clase, m.marca, a.modelo, c.color, ag.imagen
              FROM t_activo a
              INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
              INNER JOIN t_marca m ON a.id_marca = m.id_marca
              INNER JOIN t_color c ON a.id_color = c.id_color
              INNER JOIN t_modelos tm ON a.modelo_id = tm.id_modelo
              INNER JOIN t_modelo_fondos mf ON tm.id_modelo = mf.id_modelo
              WHERE mf.id_fondos = ?";
    $params = [$id_fondos];
    $types  = 'i';

    if ($busqueda !== '') {
        $term = "%$busqueda%";
        $query .= " AND (ag.clase LIKE ? OR m.marca LIKE ? OR a.modelo LIKE ?)";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $types  .= 'sss';
    }

    $query .= " ORDER BY ag.clase, m.marca, a.modelo";
} else {
    $term = "%$busqueda%";
    $query = "SELECT DISTINCT a.id_activo, ag.clase, m.marca, a.modelo, c.color, ag.imagen
              FROM t_activo a
              INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
              INNER JOIN t_marca m ON a.id_marca = m.id_marca
              INNER JOIN t_color c ON a.id_color = c.id_color
              INNER JOIN t_modelos tm ON a.modelo_id = tm.id_modelo
              WHERE (ag.clase LIKE ? OR m.marca LIKE ? OR a.modelo LIKE ?)
              ORDER BY ag.clase, m.marca, a.modelo";
    $params = [$term, $term, $term];
    $types  = 'sss';
}

$stmt = $link->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'resultados' => $rows, 'total' => count($rows)]);
