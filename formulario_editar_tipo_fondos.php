<?php  
session_start();
$tienellave = ($_SESSION['tipo']==1);
if ($tienellave == false){
echo '<script language = javascript>
alert("No tienes permisos")
self.location = "formulario_menu_inventario.html"
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
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];

$id_fondos = $_GET['gps'];

		$preguntar = mysqli_query($link, "select fondos from t_fondos where id_fondos='$id_fondos'");   
		$respuesta = mysqli_fetch_array($preguntar);
		$tipo = $respuesta['fondos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>PNTM Principal</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/bootstrap.min.css">
  <link rel="stylesheet" href="fondoresponsive.css">
  <script src="/css/jquery.min.js"></script>
  <script src="/css/popper.min.js"></script>
  <script src="/css/bootstrap.min.js"></script>
  <link rel="stylesheet" type="text/css" href="/css/style.css">

</head>
<body>

<nav class="navbar navbar-expand-md bg-dark navbar-dark">
  <img src="img/logomep2020.png" width="45" height="30" alt="" loading="lazy"><a class="navbar-brand" href="formulario_menu_principal.html">Tecnopresta</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="formulario_crear_tipo_fondos.php"> <span class="icon icon-undo2"></span> Tipos</a>
      </li> 
      <li class="nav-item">
        <a class="nav-link" href="gameover.php"><span class="icon icon-enter"></span> Cerrar Sesi&oacute;n</a>
      </li>  
    </ul>
  </div>  
</nav>
<br>

<div class="container">
    
	  <h3>Usuario: <?php echo $lognombre; ?> </h3><br>

<h3>Editar Tipo de Origen</h3><br>

      <div class="row">

        <div class="col-md-6">
		<form action="editar_tipofondos.php" method="post">
		  <div class="form-group">
		    <label for="fondos">Tipo de origen de los fondos</label>
		    <input type="text" class="form-control" name="fondos" id="fondos" aria-describedby="tipoHelp" value="<?php echo $tipo;?>">
		    <small id="fondosHelp" class="form-text text-muted">Recuerde que este apartado es únicamente para corregir errores de escritura.</small>
		  </div>
  		    <input type="hidden" id="idfondos" name="idfondos" value="<?php echo $id_fondos;?>">
		  <button type="submit" class="btn btn-dark"><span class="icon icon-floppy-disk"> Guardar</span></button>
		</form>
        </div>
        <div class="col-md-6">
<div class="d-none d-sm-none d-md-block"><img src="img/editar-fondos.png "width="600" height="600"></div>
        </div>

      </div>

</div>
</body>
</html>
