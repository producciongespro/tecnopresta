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

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {

}

$fondos = $_POST['fondos'];


$query = "select id_fondos from t_fondos where fondos='$fondos'";
$result = mysqli_query($link,$query);
$check_user = mysqli_num_rows($result);


if($check_user>0){
  echo '<script language = javascript>
  alert("El tipo del origen que intenta registrar ya existe")
  self.location = "formulario_crear_tipo_fondos.php"
  </script>';
} else {
mysqli_free_result($result);

	
$query = "INSERT INTO t_fondos (fondos)VALUES('".$fondos."')";
	$link->query($query);
	mysqli_close($link);


	echo '<script language = javascript>
	alert("El tipo fue guardada correctamente")
	self.location = "formulario_crear_tipo_fondos.php"
	</script>';

} /* Cierre del else que corresponde a else del $check_user>0 */
?>
