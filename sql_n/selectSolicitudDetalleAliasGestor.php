<?php

require_once("select.php");		

try {

	$solicitud_Id = $_GET['solicitud_Id'];
	$codigo = $_GET['codigo'];

	$db = new sql();		
	$rs = $db->conSolicitudDetalleAlias($solicitud_Id, $codigo);
	
	echo json_encode($rs);
   
    $rs = null;
    $db = null;
 
} 
catch (PDOException $e) {		
	$rs = null;
	$db = null;
	echo "Error al conectar con la base de datos: " . $e->getMessage() . "\n";
	exit;
}
?>
