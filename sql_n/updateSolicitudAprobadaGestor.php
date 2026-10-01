<?php

require_once 'updateSolicitudAprobada.php';
	
try {
		    
    $solicitud_Id = $_POST['solicitud_Id'];

	$db = new updateSolicitudAprobada();
    $db-> updateSolicitudAprobada($solicitud_Id);

} 
catch (Exception $e) {		
	console.log("Error de la aplicación: " + $e->getMessage());
	echo "Error al conectar con la base de datos: " . $e->getMessage() . "\n";
	$db = null;
	exit;
}

?>