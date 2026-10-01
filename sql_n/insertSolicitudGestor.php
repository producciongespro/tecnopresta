<?php

require_once 'insertSolicitud.php';

try {
		    
    $fechaRetiro = $_POST['solicitud_fechaRetiro'];
    $horaRetiro = $_POST['solicitud_horaRetiro'];
    $fechaDevolucion = $_POST['solicitud_fechaDevolucion'];
    $horaDevolucion = $_POST['solicitud_horaDevolucion'];
    $solicitud_uso = $_POST['solicitud_uso'];
	$arrayArticulos = array();
    $arrayArticulos = json_decode($_POST['arrayArticulos'], true);    
    $solicitud_cedula_funcionario = $_POST['solicitud_cedula_funcionario'];
    $solicitud_nombre_funcionario = $_POST['solicitud_nombre_funcionario'];
    $solicitud_codigo_presupuestario = $_POST['solicitud_codigo_presupuestario'];
    $para = $_POST['para'];
    $arrayActivos = array();
    $arrayActivos = json_decode($_POST['arrayActivos'], true);
    $Dependencia = $_POST['Dependencia'];
    $arrayNombreAlias = json_decode($_POST['arrayNombreAlias'], true);
    $arraySoftwareDescripcion = json_decode($_POST['arraySoftwareDescripcion'], true);
    $arraySoftwareId = array();
    $arraySoftwareId = json_decode($_POST['arraySoftwareId'], true);
    $seccionDescripcion = $_POST['seccionDescripcion'];
    $seccion_Id = $_POST['seccion_Id'];
    $solicitud_uso_externo = $_POST['solicitud_uso_externo'];
    $solicitud_boleta = $_POST['solicitud_boleta'];
    $ambitoPrestadorSeleccionado = $_POST['ambitoPrestadorSeleccionado'] ?? null;

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

    $solicitud_horaRetiroH = substr($horaRetiro, 0, 2);
    $solicitud_horaRetiroM = substr($horaRetiro, 3, 2);
    $solicitud_horaRetiro = $solicitud_horaRetiroH . ":" . $solicitud_horaRetiroM . ":00";

    $solicitud_horaDevolucionH = substr($horaDevolucion, 0, 2);
    $solicitud_horaDevolucionM = substr($horaDevolucion, 3, 2);
    $solicitud_horaDevolucion = $solicitud_horaDevolucionH . ":" . $solicitud_horaDevolucionM . ":00";
     
	$db = new insertSolicitud();
    $db_msj = "";
    $db_msj = $db-> insertSolicitud($prestamo_fechaRetiro, $solicitud_horaRetiro, $prestamo_fechaDevolucion,
                        $solicitud_horaDevolucion, $solicitud_uso, $arrayArticulos, 
                        $solicitud_cedula_funcionario, $solicitud_nombre_funcionario, 
                        $solicitud_codigo_presupuestario, $para, $arrayActivos, $Dependencia,
                        $prestamo_fechaRetiro_formato, $prestamo_fechaDevolucion_formato,
                        $arrayNombreAlias, $arraySoftwareDescripcion,
                        $seccionDescripcion, $seccion_Id, $arraySoftwareId,
                        $solicitud_uso_externo, $solicitud_boleta, $ambitoPrestadorSeleccionado);

    echo $db_msj;
} 
catch (Exception $e) {		
	console.log("Error de la aplicación: " + $e->getMessage());
	echo "Error al conectar con la base de datos: " . $e->getMessage() . "\n";
	$db = null;
	exit;
}

?>