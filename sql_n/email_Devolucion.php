<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require "/home/tecnopresta/PHPMailer/src/SMTP.php";
require "/home/tecnopresta/PHPMailer/src/PHPMailer.php";
require "/home/tecnopresta/PHPMailer/src/Exception.php";

header('Content-Type: text/html; charset=UTF-8');

class Email_Devolucion
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
 
    public function email_Devolucion($solicitud_email_funcionario,                                          
                                           $prestamo_nombre_solicitante, 
                                           $arrayArticulosNombre,
                                           $prestamo_incidente_comentario) {

        $txtNombres = "";
        if(!empty($arrayArticulosNombre)) {

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
                
        date_default_timezone_set('America/Costa_Rica');
        
        $txtNombresFormato = utf8_decode($txtNombres);
      
        $the_subject = "Devolucion de Equipo";
        $address_to = $solicitud_email_funcionario;
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
        $phpmailer->Body .= "<img src=\"cid:encabezado\" width=\"100%\" alt=\"TecnoPresta - Ministerio de Educaci&oacute;n P&uacute;blica\" />";
        $phpmailer->Body .= "<h3> Estimado Funcionario (a) </h3>";
        $phpmailer->Body .="<p> <b> El Sistema de Inventario e Incidencias de Pr&eacute;stamos de Equipo  [Tecnopresta] </b> le informa:</p>";

        $phpmailer->Body .= "<div id='container'> <table style='width:100%'>
                            <tbody>
                            <tr style='width:50%'>
                                <th scope='row'>Solicitante</th>
                                <td> ".$prestamo_nombre_solicitante."</td>
                            </tr>    
                            <tr style='width:50%'>
                                <th scope='row'> Estado del pr&eacute;stamo</th>
                                <td> <strong>  Devuelto </strong> </td>
                            </tr>       
                            <tr style='width:50%'>
                                <th scope='row'>Equipos devueltos </th>
                                <td> ".$txtNombresFormato."</td>
                            </tr>
                            <tr style='width:50%'>
                                <th scope='row'>Observaciones </th>
                                <td> ".$prestamo_incidente_comentario."</td>
                            </tr>
                            </tbody> 
                            </table> </div> <br/>";
        
        $phpmailer->IsHTML(true);  // Activar si se envia etiquetas html
        $phpmailer->Send();
        

        return true; 

    }
    
}

?>