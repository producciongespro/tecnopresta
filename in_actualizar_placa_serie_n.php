<?php
session_start();
$tienellave = in_array($_SESSION['tipo'], [1]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su administrador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
require_once("conexion.php");
$link = $mysqli;

// Configurar la conexión para usar UTF-8
$link->set_charset("utf8");

// Verificar que los datos fueron enviados
if (isset($_POST['idPlaca'], $_POST['placa'], $_POST['serial'])) {
    $idPlaca = $_POST['idPlaca'];
    $placa = $_POST['placa'];
    $serial = $_POST['serial'];

    // Preparar la consulta para actualizar
    $stmt = $link->prepare("UPDATE t_placa SET placa = ?, serial = ? WHERE id_placa = ?");
    $stmt->bind_param("sss", $placa, $serial, $idPlaca);

    if ($stmt->execute()) {
        echo '<script language="javascript">
    alert("Datos actualizados");
    window.location.href = "in_formulario_redistribuir.php";
    </script>';
    exit();
    } else {
        echo "Error al actualizar los datos: " . $stmt->error;
    }

    $stmt->close();
} else {
        echo '<script language="javascript">
    alert("Datos incompletos");
    window.location.href = "in_formulario_redistribuir.php";
    </script>';
    exit();
}
?>