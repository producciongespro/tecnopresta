<?php

error_reporting(-1);  //Remove from production version
ini_set("display_errors", "on");  //Remove from production version

$url = "https://apps.mep.go.cr/wstecnopresta/servicio.asmx?WSDL";


$correo = $_POST['correo'];
$pass = $_POST['pass'];
$cedula = "";

try {

    $client = new SoapClient($url);

    //Obtiene la cédula del usuario mediante la validación de correo y password    
    $resValidaCredenciales = $client->ValidaCredenciales(array(
                           'str_correo' => $correo,
                           'str_pass' => $pass));
       
//    $resValidaCredenciales = $client->ValidaCredenciales(array(
//                            'str_correo' => 'sandro.yee.vasquez@mep.go.cr',
//                            'str_pass' => ''));

    $cedula = $resValidaCredenciales->ValidaCredencialesResult;
    
    $new_strcedula = str_replace('"', '', $cedula); //Las consulta devuelve "" de más y son eliminadas
                                                    // en la variable new_strcedula

    //JSON con información del usuario
    $resConsultaFuncionario = $client->ConsultaFuncionario(array('str_identificacion' => $new_strcedula));
            
    echo $resConsultaFuncionario->ConsultaFuncionarioResult;

} catch (\Throwable $th) {
    echo "Error al conectar con la base de datos: " . $th->getMessage() . "\n";
	exit;		
}

?>