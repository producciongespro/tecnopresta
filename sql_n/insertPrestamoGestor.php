<?php

require 'insertPrestamo.php';
require 'selectActivoPrestado.php';
	
try {
		    
    $fechaRetiro = $_POST['prestamo_fechaRetiro'];
    $horaRetiro = $_POST['prestamo_horaRetiro'];
    $fechaDevolucion = $_POST['prestamo_fechaDevolucion'];
    $horaDevolucion = $_POST['prestamo_horaDevolucion'];
    $prestamo_uso = $_POST['prestamo_uso'];
	$arrayArticulos = array();
    $arrayArticulos = json_decode($_POST['arrayArticulos']);
    $prestamo_cedula_funcionario = $_POST['prestamo_cedula_funcionario'];
    $prestamo_nombre_funcionario = $_POST['prestamo_nombre_funcionario'];
    $prestamo_codigo_presupuestario = $_POST['prestamo_codigo_presupuestario'];
    $prestamo_nombre_solicitante = $_POST['prestamo_nombre_solicitante'];
    $para = $_POST['para'];
    $solicitud_Id = $_POST['solicitud_Id'];
    $codigo = $_POST['codigo'];
    $Dependencia = $_POST['Dependencia'];
    $arrayArticulosNombre = array();
    $arrayArticulosNombre = json_decode($_POST['arrayArticulosNombre']);
    $arraySoftwareDescripcion = json_decode($_POST['arraySoftwareDescripcion'], true);
    $arraySoftwareId = array();
    $arraySoftwareId = json_decode($_POST['arraySoftwareId'], true);
    $seccionDescripcion = $_POST['seccionDescripcion'];
    $seccion_Id = $_POST['seccion_Id'];
    $arrayArticulosNombreNoSeleccionados = json_decode($_POST['arrayArticulosNombreNoSeleccionados']);
    $boleta = $_POST['boleta'];
    $destino = $_POST['destino'];
       
    $prestamo_fechaRetiroY = substr($fechaRetiro, -4); 
    $prestamo_fechaRetiroM = substr($fechaRetiro, 3, 2); 
    $prestamo_fechaRetiroD = substr($fechaRetiro, 0, 2);
    $prestamo_fechaRetiro = $prestamo_fechaRetiroY . "-" . $prestamo_fechaRetiroM . "-" . $prestamo_fechaRetiroD;
    $prestamo_fechaRetiro_formato =  $prestamo_fechaRetiroD ."/". $prestamo_fechaRetiroM ."/". $prestamo_fechaRetiroY;
    
    $prestamo_fechaDevolucionY = substr($fechaDevolucion, -4); 
    $prestamo_fechaDevolucionM = substr($fechaDevolucion, 3, 2); 
    $prestamo_fechaDevolucionD = substr($fechaDevolucion, 0, 2);
    $prestamo_fechaDevolucion = $prestamo_fechaDevolucionY . "-" . $prestamo_fechaDevolucionM . "-" . $prestamo_fechaDevolucionD;
    $prestamo_fechaDevolucion_formato = $prestamo_fechaDevolucionD . "/". $prestamo_fechaDevolucionM . "/" . $prestamo_fechaDevolucionY;

    $prestamo_horaRetiroH = substr($horaRetiro, 0, 2);
    $prestamo_horaRetiroM = substr($horaRetiro, 3, 2);
    $prestamo_horaRetiro = $prestamo_horaRetiroH . ":" . $prestamo_horaRetiroM . ":00";

    $prestamo_horaDevolucionH = substr($horaDevolucion, 0, 2);
    $prestamo_horaDevolucionM = substr($horaDevolucion, 3, 2);
    $prestamo_horaDevolucion = $prestamo_horaDevolucionH . ":" . $prestamo_horaDevolucionM . ":00";

    $dbActivoPrestado = new SelectActivoPrestado();
    
    $rs = "";    
    $rs = $dbActivoPrestado-> selectActivoPrestado($arrayArticulos, $codigo,
                                                $prestamo_fechaRetiro,
                                                $prestamo_fechaDevolucion,
                                                $prestamo_horaRetiro,
                                                $prestamo_horaDevolucion);

    if ($rs != "ok") {
        echo $rs;
        exit;
    }

	$db = new insertPrestamo();
    $db-> insertPrestamo($prestamo_fechaRetiro, $prestamo_horaRetiro, 
                        $prestamo_fechaDevolucion, $prestamo_horaDevolucion, 
                        $prestamo_uso, $arrayArticulos, $prestamo_cedula_funcionario, 
                        $prestamo_nombre_funcionario, $prestamo_codigo_presupuestario, 
                        $prestamo_nombre_solicitante, $para, $solicitud_Id, $Dependencia,
                        $prestamo_fechaRetiro_formato, $prestamo_fechaDevolucion_formato,
                        $arrayArticulosNombre, $arraySoftwareDescripcion,
                        $seccionDescripcion, $seccion_Id, $arraySoftwareId, 
                        $arrayArticulosNombreNoSeleccionados, $boleta, $destino, $codigo);
        	                    
    echo "ok";
    
} 
catch (Exception $e) {		
	error_log("Error de la aplicación: " . $e->getMessage());
	//echo "Error al conectar con la base de datos: " . $e->getMessage() . "\n";
	$db = null;
	exit;
}

?>