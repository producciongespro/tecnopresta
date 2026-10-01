<?php

require_once 'updateSolicitudRechazo.php';
	
try {
		    
    $solicitud_Id = $_POST['solicitud_Id'];
	$solicitud_email_funcionario = $_POST['solicitud_email_funcionario'];
	$solicitud_motivo_rechazo = $_POST['solicitud_motivo_rechazo'];
	$arrayArticulosNombre = array();	
	$arrayArticulosNombre = json_decode($_POST['arrayArticulosNombre']);
	$prestamo_nombre_solicitante = $_POST['prestamo_nombre_solicitante'];

	$db = new updateSolicitudRechazo();
    $db-> updateSolicitudRechazo($solicitud_Id, 
								 $solicitud_email_funcionario, 
								 $solicitud_motivo_rechazo,
								 $prestamo_nombre_solicitante,
								 $arrayArticulosNombre);

    echo "ok";

} 
catch (Exception $e) {		
	error_log("Error de la aplicación: " . $e->getMessage());
	echo "Error al conectar con la base de datos: " . $e->getMessage() . "\n";
	$db = null;
	exit;
}

?>