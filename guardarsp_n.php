<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/*
if (!$_SESSION){
echo '<script language = javascript>
alert("usuario no autenticado")
self.location = "index.php"
</script>';
}
*/
require_once("conexion.php");
$link = $mysqli;
 
if (mysqli_connect_errno()) {
	echo "Error de conexion a mysql: " . mysqli_connect_error();
}

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {

}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// Inicia detección de campos vacios y el pase de variables 

$post = (isset($_POST['placa']) && !empty($_POST['placa'])) &&
        (isset($_POST['serial']) && !empty($_POST['serial'])) &&
        (isset($_POST['codigo']) && !empty($_POST['codigo'])) &&
        (isset($_POST['fondos']) && !empty($_POST['fondos'])) &&
        (isset($_POST['idactivo']) && !empty($_POST['idactivo']));

if($post)
{

$placa = $_POST['placa'];
$serial = $_POST['serial'];
$codigo = $_POST['codigo'];
$activo = $_POST['idactivo'];
$fondos = $_POST['fondos'];
$estado = 1;
$prestar = 1;
$activado = 1;
$alias_id = 0;
$numero_activo = 0;
$idlugar = 0;

$sqls = "SELECT * FROM t_placa WHERE serial = '$serial'";
$results = $link->query($sqls);
if ($results->num_rows >= 1) {   
  $encontrados=$serial;
} 


$sqlp = "SELECT * FROM t_placa WHERE placa = '$placa'";
$resultp = $link->query($sqlp);
if ($resultp->num_rows >= 1) {   
  $encontradop=$placa;
} 


$sql = "SELECT * FROM t_placa WHERE serial = '$serial' or placa = '$placa'";
$result = $link->query($sql);
	
	if ($result->num_rows >= 1) {

		  $respuesta = new stdClass();
		  $respuesta->mensaje = "<img src=\"ico/alerta.png\" width=\"50\" height=\"50\" /> Por favor revise: $encontrados │ $encontradop";
		  echo json_encode($respuesta);

	} else {

		  
		  $consulta = "INSERT INTO t_placa (placa,serial,id_activo,codigo,id_estado,prestar,activo,id_fondos,alias_id,numero_activo,id_lugar)VALUES('$placa','$serial',$activo,'$codigo',$estado,$prestar,$activado,$fondos,$alias_id,$numero_activo,$idlugar)";
		  
		  
		  $respuesta = new stdClass();
		  
		  if($link->query($consulta)){
		    $respuesta->mensaje = "Se guardó correctamente, desea agregar otro"."<script>ok()</script>";
		  }
		  else {
		    $respuesta->mensaje = "Ocurrió un error"."<script>error()</script>";
		  }
		  echo json_encode($respuesta);
	}  
} else {

	  $respuesta = new stdClass();
	  $respuesta->mensaje = "Debe completar todos los campos"."<script>error()</script>";
	  echo json_encode($respuesta);

} //Cierre del if principal
?>


  

