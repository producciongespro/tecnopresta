<?php

require_once("select.php");		

try {

	$codigo = $_GET['codigo'];

	$db = new sql();		
	$rs = $db->conCantidadPrestamo($codigo);
	
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
