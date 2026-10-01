<?php

require 'insertDevolucion.php';

try {
		    
	$arrayArticulos = array();
    $arrayArticulos = json_decode($_POST['arrayArticulos']);
    $prestamo_Id = $_POST['prestamo_Id'];

	$prestamo_email_solicitante = $_POST['prestamo_email_solicitante'];
	$arrayArticulosNombre = array();	
	$arrayArticulosNombre = json_decode($_POST['arrayArticulosNombre']);
	$prestamo_nombre_solicitante = $_POST['prestamo_nombre_solicitante'];
	$prestamo_incidente = $_POST['prestamo_incidente'];
	$prestamo_incidente_comentario = $_POST['prestamo_incidente_comentario'];

	$db = new insertDevolucion();
    $db-> insertDevolucion($arrayArticulos, $prestamo_Id, 
							$prestamo_email_solicitante,
							$prestamo_nombre_solicitante,
							$arrayArticulosNombre,
							$prestamo_incidente,
							$prestamo_incidente_comentario);
	  
    echo "ok";

} 
catch (Exception $e) {		
	console.log("Error de la aplicación: " + $e->getMessage());
	echo "Error al conectar con la base de datos: " . $e->getMessage() . "\n";
	$db = null;
	exit;
}

?>