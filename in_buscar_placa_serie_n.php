<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su administrador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
    */
require_once("conexion.php");
$link = $mysqli;

// Configurar la conexión para usar UTF-8
$link->set_charset("utf8");

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Consulta con el nombre correcto de la columna 'serial'
    $stmt = $link->prepare("SELECT placa, serial FROM t_placa WHERE id_placa = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->bind_result($placa_b, $serial_b);

    if ($stmt->fetch()) {
        // Retornar los datos como JSON
        echo json_encode([
            "placa" => $placa_b,
            "serial" => $serial_b
        ]);
    } else {
        echo json_encode([
            "error" => "No se encontró ningún registro con ese ID"
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        "error" => "ID no proporcionado"
    ]);
}
?>
