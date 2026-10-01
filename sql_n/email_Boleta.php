<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: text/html; charset=UTF-8');

class Email_Boleta
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

    public function email_Boleta($Dependencia,
                                $para,                                 
                                $prestamo_nombre_solicitante,
                                $prestamo_Fecha_formato,
                                $prestamo_fechaRetiro_formato,
                                $prestamo_fechaDevolucion_formato,
                                $destino, $prestamo_Id, $codigo, $prestamo_uso) 
    {
                    
        $txtDependencia = utf8_decode($Dependencia);
        $txtDestino = utf8_decode($destino);
       
        $the_subject = "Boleta Imprimible para Oficial de Seguridad";
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

        $phpmailer->Body .= "<h3>Formulario para Salida de Activos</h3>";

        $phpmailer->Body .= "<div id='container'> <table style='width:100%'>
        <tbody>
            <tr style='width:50%'>
                <th scope='row'>Fecha de Salida: </th>
                <td> ".$prestamo_fechaRetiro_formato."</td>
            </tr>        
            <tr style='width:50%'>
                <th scope='row'>Funcionario(a) que efect&uacute;a la salida de los bienes:</th>
                <td> ".$prestamo_nombre_solicitante."</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Dependencia del funcionario(a):</th>
                <td> ".$txtDependencia."</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'>Destino de los bienes</th>
                <td> ".$txtDestino. "</td>
            </tr>
            <tr style='width:50%'>
                <th scope='row'> Fecha de reingreso </th>
                <td> ".$prestamo_fechaDevolucion_formato."</td>
            </tr>                       
        </tbody> 
        </table> </div> <br/>";

		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {								
	
            $consultaSQL = "SELECT t_activo.id_activo, clase, placa, serial, modelo, marca, estado
                            FROM t_activo						
                            INNER JOIN t_marca 
                                ON t_activo.id_marca = t_marca.id_marca
                            INNER JOIN t_activo_general
                                ON t_activo_general.id_ag = t_activo.id_ag
                            INNER JOIN t_placa
                                ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'
                            INNER JOIN t_estado
                                ON t_estado.id_estado = t_placa.id_estado
                            INNER JOIN t_prestamo_detalle
                                ON t_prestamo_detalle.prestamo_detalle_id_placa = t_placa.id_placa
                            WHERE t_prestamo_detalle.prestamo_Id = $prestamo_Id";
	
			$sql = $pdo->query($consultaSQL);
            
            $phpmailer->Body .= "<h3>Detalle de los Bienes</h3>";
            $phpmailer->Body .= "<div id='container'> <table style='width:100%'>
                                <tbody>";
            $phpmailer->Body .= "<tr>
                                    <th scope='row'>Descripci&oacute;n del bien</th>                                            
                                    <th scope='row'>Placa</th>                                            
                                    <th scope='row'>Serie</th>                                            
                                    <th scope='row'>Modelo</th>                                            
                                    <th scope='row'>Marca</th>                                            
                                    <th scope='row'>Estado</th>                                            
                                </tr>";

			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    
                    $phpmailer->Body .= "<tr>
                                            <td> ".$row['clase']."</td>
                                            <td> ".$row['placa']."</td>
                                            <td> ".$row['serial']."</td>
                                            <td> ".$row['modelo']."</td>
                                            <td> ".$row['marca']."</td>
                                            <td> ".$row['estado']."</td>
                                        </tr>";                    
            }
            
            $phpmailer->Body .= "</tbody> 
                                </table> </div> <br/>";
            
        }
        
        $phpmailer->Body .= "<b>Detalle de labores que justifican la salida de los bienes: </b>".$prestamo_uso."<br/>";
        // $phpmailer->Body .= "<p>".$prestamo_uso."</p> <br/>";
        
        $phpmailer->Body .= "<p align='center'>sello</p> <br/>";        

        $phpmailer->Body .= "<p align='center'>____________________________</p>";
        $phpmailer->Body .= "<p align='center'>Firma del funcionario(a) que efect&uacute;a la salida de los bienes</p> <br/>";

        $phpmailer->Body .= "<p align='center'>____________________________</p>";
        $phpmailer->Body .= "<p align='center'>Nombre y firma del oficial de seguridad</p> <br/>";
        
        $phpmailer->Body .= "<p align='center'>____________________________</p>";
        $phpmailer->Body .= "<p align='center'>VB de la jefatura</p> <br/>";
                                      
        $phpmailer->IsHTML(true);
        $phpmailer->Send();
        
        return true; 
 
    }
    
}

?>