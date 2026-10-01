<?php

require_once("select.php");		

$codigo = $_GET['codigo'];

try {

	$db = new sql();		
	$rs = $db->conVistaDevolucionEncabezado($codigo);
	
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
