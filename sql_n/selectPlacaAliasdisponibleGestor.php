<?php

require_once("select.php");		

try {

	$alias_id = $_GET['alias_id'];
	$codigo = $_GET['codigo'];
	
	$db = new sql();		
	$rs = $db->conDisponible($codigo, $alias_id);
	
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
