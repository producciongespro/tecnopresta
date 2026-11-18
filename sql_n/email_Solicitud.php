<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require "../PHPMailer/SMTP.php";
require "../PHPMailer/PHPMailer.php";
require "../PHPMailer/Exception.php";

header('Content-Type: text/html; charset=UTF-8');

class Email_Solicitud
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

    public function email_Solicitud($Dependencia,
                                    $solicitud_uso, 
                                    $para,                                 
                                    $solicitud_nombre_solicitante,
                                    $solicitud_Fecha_formato,
                                    $solicitud_fechaRetiro_formato,
                                    $solicitud_horaRetiro,
                                    $solicitud_fechaDevolucion_formato,
                                    $solicitud_horaDevolucion,
                                    $arrayNombreAlias, 
                                    $arraySoftwareDescripcion,
                                    $seccionDescripcion, $arrayActivos) 
    {

        $txtNombresActivos = "";

        if(!empty($arrayActivos)) {
    
            if (count($arrayActivos)== 1) {
    
                foreach($arrayActivos as $key => $nombre) {
                    $txtNombresActivos =  $nombre["solicitud_detalle_ArticuloNombre"];
                }                
    
            } else {
                
                    foreach($arrayActivos as $key => $nombre) {
                        $txtNombresActivos = $txtNombresActivos . " / ".$nombre["solicitud_detalle_ArticuloNombre"]." ";
                    }
            }
        }
    
        $txtNombresAlias = "";

        if(!empty($arrayNombreAlias)) {
    
            if (count($arrayNombreAlias)== 1) {
    
                foreach($arrayNombreAlias as $key => $nombre) {
                    $txtNombresAlias =  $nombre;
                }                
    
            } else {
                
                    foreach($arrayNombreAlias as $key => $nombre) {
                        $txtNombresAlias = $txtNombresAlias . " / " . $nombre . " ";
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

        $txtDependencia = $Dependencia;
        $txtUso = $solicitud_uso;
        $txtNombresActivoFormato = $txtNombresActivos;
        $txtNombresAliasFormato = $txtNombresAlias;
        $txtSoftwareDescripcionFormato = $txtSoftwareDescripcion;
           
        $the_subject = "Solicitud de Equipo/Para valoracion";
        $address_to = $para;
        $from_name = "Administrador";
        $phpmailer = new PHPMailer();
        $phpmailer->CharSet = 'UTF-8';   // PHPMailer usa ISO-8859-1 por defecto (antes se usaba utf8_decode)
        $phpmailer->Encoding = 'base64';
    
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
        $phpmailer->AddEmbeddedImage('../img/correo/encabezado-tecnopresta.png', 'encabezado', 'attachment', 'base64', 'image/png');
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
        $phpmailer->Body .= "<img src=\"cid:encabezado\" width=\"100%\" height=\"40%\" alt=\"TecnoPresta - Ministerio de Educaci&oacute;n P&uacute;blica\" />";
        $phpmailer->Body .= "<h3> Estimado Funcionario (a) </h3>";
        $phpmailer->Body .="<p> <b> El Sistema de Inventario e Incidencias de Pr&eacute;stamos de Equipo  [Tecnopresta] </b> le informa:</p>";
        $phpmailer->Body .= "<h1 style='margin:20px 0; padding:14px 10px; text-align:center; font-family:Arial, Helvetica, sans-serif; font-size:28px; font-weight:bold; letter-spacing:1px; color:#003366; border-top:4px solid #003366; border-bottom:4px solid #003366;'>Pre-Solicitud de Equipo</h1>";
        $phpmailer->Body .= "<div id='container'> <table style='width:100%'>
                            <tbody>
                            <tr style='width:50%'>
                                <th scope='row'>Solicitante</th>
                                <td> ".$solicitud_nombre_solicitante."</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Instituci&oacuten</th>
                                <td> ".$txtDependencia."</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Fecha y hora de Solicitud</th>
                                <td> ".$solicitud_Fecha_formato."</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Fecha y hora en que deber&aacute; retirarlo</th>
                                <td> ".$solicitud_fechaRetiro_formato." ". $solicitud_horaRetiro ."</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Fecha y hora en que deber&aacute; devolverlo</th>
                                <td> ".$solicitud_fechaDevolucion_formato." " . $solicitud_horaDevolucion . "</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Estado de la solicitud</th>
                                <td> <strong> En proceso de aprobaci&oacuten </strong> </td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Uso que se le dar&aacute; al equipo</th>
                                <td> ".$txtUso."</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Equipos solicitados</th>
                                <td> ".$txtNombresAliasFormato. " " . $txtNombresActivoFormato."</td>
                            </tr>                            
                            </tbody> 
                            </table> </div> <br/>";


        //Categoria del Software y Seccion
        //*******************************   
        // <tr style='width:50%'>
        // <th scope='row'>Categoria del Software a utilizar</th>
        // <td> ".$txtSoftwareDescripcionFormato."</td>
        // </tr>
        // <tr style='width:50%'>
        //     <th scope='row'>Secci&oacute;n</th>
        //     <td> ".$seccionDescripcion."</td>
        // </tr>                            
    
        $phpmailer->IsHTML(true);  // Activar si se envia etiquetas html
        $phpmailer->Send();
            
        return true; 
 
    }
    
}

?>