<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$logcodigo = $usuario_azure['codigoPresu'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$alias = isset($_POST['alias']) ? trim($_POST['alias']) : '';
$imagen = isset($_POST['imagen']) ? trim($_POST['imagen']) : '';

if (empty($alias) || empty($imagen)) {
    echo json_encode(['success' => false, 'message' => 'Debe completar todos los campos requeridos']);
    exit();
}

require_once dirname(__DIR__) . '/conexion.php';
$link = $mysqli;
mysqli_set_charset($link, "utf8");

$sql = "INSERT INTO t_alias (alias, alias_imagen, codigo) VALUES (?, ?, ?)";
$stmt = mysqli_prepare($link, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar consulta: ' . mysqli_error($link)]);
    mysqli_close($link);
    exit();
}

mysqli_stmt_bind_param($stmt, "sss", $alias, $imagen, $logcodigo);
if (mysqli_stmt_execute($stmt)) {
    $inserted_id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    echo json_encode([
        'success' => true, 
        'message' => 'Alias registrado con éxito',
        'alias_id' => $inserted_id
    ]);
} else {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    echo json_encode(['success' => false, 'message' => 'Error al guardar alias: ' . $err]);
}
