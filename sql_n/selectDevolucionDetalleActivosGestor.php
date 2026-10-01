<?php

require_once("select.php");		

try {

	$prestamo_Id = $_GET['prestamo_Id'];
	$codigo = $_GET['codigo'];

	$db = new sql();		
	$rs = $db->conVistaDevolucionDetalleActivos($prestamo_Id, $codigo);
	
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
