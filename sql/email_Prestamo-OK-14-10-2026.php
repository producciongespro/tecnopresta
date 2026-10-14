<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require "/home/tecnopresta/PHPMailer/src/SMTP.php";
require "/home/tecnopresta/PHPMailer/src/PHPMailer.php";
require "/home/tecnopresta/PHPMailer/src/Exception.php";

header('Content-Type: text/html; charset=UTF-8');

class Email_Prestamo
{
    private $correo;
    private $passemail;
       	
	function __construct()
	{
                
        $correo = Email;
        $passemail = Email_PASS;
        $this->correo = $correo;
        $this->passemail = $passemail;
        
    }

    public function email_Prestamo($Dependencia,
                                $prestamo_uso, 
                                $para, 
                                $arrayArticulosNombre,
                                $prestamo_nombre_solicitante,
                                $prestamo_Fecha_formato,
                                $prestamo_fechaRetiro_formato,
                                $prestamo_horaRetiro,
                                $prestamo_fechaDevolucion_formato,
                                $prestamo_horaDevolucion, 
                                $arraySoftwareDescripcion,
                                $seccionDescripcion,
                                $arrayArticulosNombreNoSeleccionados) 
    {

        $txtNombres = "";

        if(!empty($arrayArticulosNombre)) 
        {

            if (count($arrayArticulosNombre)== 1) {

                foreach($arrayArticulosNombre as $key => $nombre) {
                    $txtNombres =  $nombre;
                }                

            } else {
                
                    foreach($arrayArticulosNombre as $key => $nombre) {
                        $txtNombres = $txtNombres . " " . $nombre . ", ";
                    }
            }

        }

        $txtSoftwareDescripcion = "";

        if(!empty($arraySoftwareDescripcion)) {
    
            if (count($arraySoftwareDescripcion)== 1) {
    
                foreach($arraySoftwareDescripcion as $key => $nombre) {
                    $txtSoftwareDescripcion =  $nombre;
                }                
    
            } else {
                
                    foreach($arraySoftwareDescripcion as $key => $nombre) {
                        $txtSoftwareDescripcion = $txtSoftwareDescripcion . " / " . $nombre . " ";
                    }
            }
        }

        $txtNombresNoSeleccionados = "";

        if(!empty($arrayArticulosNombreNoSeleccionados)) 
        {

            if (count($arrayArticulosNombreNoSeleccionados)== 1) {

                foreach($arrayArticulosNombreNoSeleccionados as $keyNoSeleccionados => $nombreNoSeleccionados) {
                    $txtNombresNoSeleccionados =  $nombreNoSeleccionados;
                }                

            } else {
                
                    foreach($arrayArticulosNombreNoSeleccionados as $keyNoSeleccionados => $nombreNoSeleccionados) {
                        $txtNombresNoSeleccionados = $txtNombresNoSeleccionados . " " . $nombreNoSeleccionados . ", ";
                    }
            }

        }
            
        $txtDependencia = utf8_decode($Dependencia);
        $txtUso = utf8_decode($prestamo_uso);
        $txtNombresFormato = utf8_decode($txtNombres);
        $txtSoftwareDescripcionFormato = utf8_decode($txtSoftwareDescripcion);
        $txtNombresFormatoNoSeleccionados = utf8_decode($txtNombresNoSeleccionados);
       
        $the_subject = "Resultado de la Solicitud de Equipo";
        $address_to = $para;
        $from_name = "Administrador";
        $phpmailer = new PHPMailer();
    
        // ---------- datos de la cuenta de Tecnopresta -------------------------------
        $phpmailer->Username = $this->correo;
        $phpmailer->Password = $this->passemail;
        //----------------------------------------------------------------------- 
     
        $phpmailer->SMTPDebug = 0;  // Opciones 0, 1, 2
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->Host = "smtp.office365.com"; // Office365
        $phpmailer->Port = 587;
        $phpmailer->IsSMTP(); // use SMTP
        $phpmailer->SMTPAuth = true;
        $phpmailer->setFrom($phpmailer->Username,$from_name);
        $phpmailer->AddAddress($address_to); // recipients email
        $phpmailer->AddEmbeddedImage('../img/correo/envio2.png', 'resultadoSolicitud', 'attachment', 'base64', 'image/png');
        $phpmailer->AddEmbeddedImage('../img/correo/Emailtemplates-11.png', 'piePagina', 'attachment', 'base64', 'image/png');
        $phpmailer->Subject = $the_subject;

        $phpmailer->Body .= "<head>
                        <meta http-equiv='Content-type' content='text/html; charset=utf-8'/>
                        <style>
                            html {
                                overflow-y: auto;
                            }
                            
                            img
                            {
                                max-width: 100%;
                            }
                            
                            html, body {
                                height: 100%;
                            }
                            
                            body {
                                margin: 0%;                                                                                                   
                            }
                            
                            #container {
                                display: flex;
                            }

                            table, th, td {
                            border: 1px solid black;
                            border-collapse: collapse;
                            }
                        </style>
                        </head>";
        //$phpmailer->Body .= "<img src=\"http://tecnopresta.mep.go.cr/img/correo/resultadoSolicitud.png\" alt=\"\" width=\"100%\" height=\"500px\" />";
        $phpmailer->Body .= "<img src=\"cid:resultadoSolicitud\" width=\"100%\" height=\"400px\" />";        
        $phpmailer->Body .= "<h3> Estimado Funcionario (a) </h3>";
        $phpmailer->Body .="<p> <b> El Sistema de Inventario e Incidencias de Pr&eacute;stamos de Equipo  [Tecnopresta] </b> le informa:</p>";

        $phpmailer->Body .= "<div id='container'> <table style='width:100%'>
        <tbody>
            <tr style='width:50%'>
                <th scope='row'>Solicitante</th>
                <td> ".$prestamo_nombre_solicitante."</td>
            </tr>        
            <tr style='width:50%'>
                <th scope='row'>Fecha y hora de Solicitud</th>
                <td> ".$prestamo_Fecha_formato."</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Fecha y hora en que deber&aacute; retirarlo</th>
                <td> ".$prestamo_fechaRetiro_formato." ". $prestamo_horaRetiro ."</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Fecha y hora en que deber&aacute; devolverlo</th>
                <td> ".$prestamo_fechaDevolucion_formato." " . $prestamo_horaDevolucion . "</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'> Estado del pr&eacute;stamo</th>
                <td> <strong>  Aprobado </strong> </td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Uso que se le dar&aacute; al equipo</th>
                <td> ".$txtUso."</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Equipos Aprobados para Pr&eacute;stamo:</th>
                <td> ".$txtNombresFormato."</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Equipos No Aprobados para Pr&eacute;stamo:</th>
                <td> ".$txtNombresFormatoNoSeleccionados."</td>
            </tr>                        
        </tbody> 
        </table> </div> <br/>";

        //Categoria del Software y Seccion
        //*********************************
        // <tr style='width:50%'>
        //         <th scope='row'>Categoria del Software a utilizar</th>
        //         <td> ".$txtSoftwareDescripcionFormato."</td>
        // </tr>
        // <tr style='width:50%'>
        //     <th scope='row'>Secci&oacute;n</th>
        //     <td> ".$seccionDescripcion."</td>
        // </tr>
        
        //$phpmailer->Body .= "<img src=\"http://tecnopresta.mep.go.cr/img/correo/Emailtemplates-07.png\" alt=\"\" width=\"90%\" height=\"125px\" />";
        $phpmailer->Body .= "<img src=\"cid:piePagina\" width=\"100%\" height=\"150px\" />";
        $phpmailer->IsHTML(true);  // Activar si se envia etiquetas html
        $phpmailer->Send();
        
        return true; 
 
    }
    
}

?>