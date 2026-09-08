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

$alias_id = isset($_POST['alias_id']) ? intval($_POST['alias_id']) : 0;
$nuevo_alias = isset($_POST['alias']) ? trim($_POST['alias']) : '';

if ($alias_id <= 0 || empty($nuevo_alias)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
    exit();
}

require_once dirname(__DIR__) . '/conexion.php';
$link = $mysqli;
mysqli_set_charset($link, "utf8");

$sql = "UPDATE t_alias SET alias = ? WHERE alias_id = ? AND codigo = ?";
$stmt = mysqli_prepare($link, $sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar consulta: ' . mysqli_error($link)]);
    mysqli_close($link);
    exit();
}

mysqli_stmt_bind_param($stmt, "sis", $nuevo_alias, $alias_id, $logcodigo);
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    echo json_encode(['success' => true, 'message' => 'Nombre del alias actualizado correctamente']);
} else {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($link);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar alias: ' . $err]);
}
