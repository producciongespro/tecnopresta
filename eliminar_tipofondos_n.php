<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/usuarioAzure.php';

if (!obtenerUsuarioSesion()) {
    header("Location: index.html");
    exit();
}

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}

$xeliminar = intval($_GET['gps'] ?? 0);

$ruta_form = "navegar.php?ruta=formulario_crear_tipo_fondos_n.php";

$consulta = $link->prepare("SELECT id_placa FROM t_placa WHERE id_fondos = ?");
$consulta->bind_param("i", $xeliminar);
$consulta->execute();
$consulta->store_result();

if ($consulta->num_rows >= 1) {
    $consulta->close();
    echo '<script language = javascript>
            alert("No se puede eliminar el tipo, es utilizado en el registro de activos plaqueados")
            self.location = "' . $ruta_form . '"
            </script>';
} else {
    $consulta->close();

    $borrar = $link->prepare("DELETE FROM t_fondos WHERE id_fondos = ?");
    $borrar->bind_param("i", $xeliminar);
    $borrar->execute();
    $borrar->close();
    mysqli_close($link);

    header('Location: ' . $ruta_form);
    exit();
}
?>
