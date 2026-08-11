<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
if (!$_SESSION){
echo '<script language = javascript>
alert("usuario no autenticado")
self.location = "index.html"
</script>';
}
*/
require_once("conexion.php");
$link = $mysqli;
 
if (mysqli_connect_errno()){
	echo "Error de conexion a mysql: " . mysqli_connect_error();
}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logcodigo = $_SESSION['codigo'];
*/
$xeliminar = $_GET['gps']; 

$miconsulta = "select id_placa from t_placa where id_activo='$xeliminar'";
$mirespuesta = $link->query($miconsulta);

if ($mirespuesta->num_rows >= 1) {
		echo '<script language = javascript>
                alert("No se puede eliminar el activo, hay activos con series reportados")
                self.location = "in_formulario_agregar_activo.php"
                </script>';

} else {
		// sql para eliminar
		$sql = "DELETE FROM t_activo WHERE id_activo=$xeliminar";

		if (mysqli_query($link, $sql)) {
		    
		} else {
		    echo "Error al eliminar registro: " . mysqli_error($link);
		}

		mysqli_close($link);

		// Redireccion al index 
		header('Location: in_formulario_agregar_activo.php');
		exit();
} // Fin del IF
?> 
