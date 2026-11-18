<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']);
    exit;
}

mysqli_set_charset($link, "utf8");

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida.']);
    exit;
}

if (empty($_POST['idsplacas'])) {
    echo json_encode(['success' => false, 'error' => 'No hay ningún activo seleccionado.']);
    exit;
}

foreach ($_POST['idsplacas'] as $idplaca) {
    $idplaca = intval($idplaca);
    $prestar = isset($_POST['presta' . $idplaca]) ? intval($_POST['presta' . $idplaca]) : 0;

    $update = "UPDATE t_placa SET prestar = '$prestar' WHERE id_placa = '$idplaca'";
    if (!mysqli_query($link, $update)) {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar registro: ' . mysqli_error($link)]);
        mysqli_close($link);
        exit;
    }
}

mysqli_close($link);
echo json_encode(['success' => true]);
?>
