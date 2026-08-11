<?php
session_start();
if (!$_SESSION){
echo '<script language = javascript>
alert("usuario no autenticado")
self.location = "index.html"
</script>';
}
require_once("conexion.php");
$link = $mysqli;
 



if (mysqli_connect_errno())

{

echo "Error de conexion a mysql: " . mysqli_connect_error();

}

$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logcodigo = $_SESSION['codigo'];

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {
    	
}

$fondos = $_POST['fondos'];
$id_fondos = $_POST['idfondos'];

 
// sql para actualizar

$update = "UPDATE t_fondos SET fondos = '".$fondos."' WHERE id_fondos = '".$id_fondos."'";
$link->query($update);  

if (mysqli_query($link, $update)) {
    
} else {
    echo "Error al actualizar registro: " . mysqli_error($link);
}

mysqli_close($link);

// Redireccion al index 
header('Location: formulario_crear_tipo_fondos.php');
exit();  
?> 
