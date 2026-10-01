<?php

require_once("select.php");		

try {

	$aliasActivo = $_GET['aliasActivo'];
	$codigo = $_GET['codigo'];
	$idAmbito = $_GET['idAmbito'] ?? '';

	$db = new sql();
	$rs = $db->conActivoAlias($aliasActivo, $codigo, $idAmbito);
	
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
