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

$fondos = trim($_POST['fondos'] ?? '');

if ($fondos === '') {
    echo json_encode(['success' => false, 'error' => 'El origen del fondo no puede estar vacío']);
    exit();
}

if (!preg_match('/^[A-Za-z0-9áéíóúÁÉÍÓÚñÑ ]+$/', $fondos)) {
    echo json_encode(['success' => false, 'error' => 'El origen solo puede contener letras, números y espacios']);
    exit();
}

if (!preg_match('/^.{1,50}$/u', $fondos)) {
    echo json_encode(['success' => false, 'error' => 'El origen no puede superar los 50 caracteres']);
    exit();
}

$consulta = $link->prepare("SELECT id_fondos FROM t_fondos WHERE fondos = ?");
$consulta->bind_param("s", $fondos);
$consulta->execute();
$consulta->store_result();

if ($consulta->num_rows > 0) {
    $consulta->close();
    echo json_encode(['success' => false, 'error' => 'El tipo de origen que intenta registrar ya existe']);
    exit();
}
$consulta->close();

$insertar = $link->prepare("INSERT INTO t_fondos (fondos) VALUES (?)");
$insertar->bind_param("s", $fondos);

if ($insertar->execute()) {
    $nuevo_id = $link->insert_id;
    $insertar->close();
    mysqli_close($link);
    echo json_encode(['success' => true, 'id' => $nuevo_id, 'fondos' => $fondos]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $link->error]);
}
?>
