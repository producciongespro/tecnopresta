<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: text/html; charset=UTF-8');

class Email_Solicitud_Notifica_Prestador
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

    public function email_Solicitud_Notifica_Prestador($idAmbito)
    {
        

        try {

           
            $phpmailer = new PHPMailer();
            $pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        
            // ---------- datos de la cuenta de Tecnopresta -------------------------------
            $phpmailer->Username = $this->correo;
            $phpmailer->Password = $this->passemail;
            //----------------------------------------------------------------------- 
        
            $the_subject = "AVISO / Un funcionario(a) a realizado una solicitud en Tecnopresta";       
            $from_name = "Administrador";

            $phpmailer->SMTPDebug = 0;  // Opciones 0, 1, 2
            $phpmailer->SMTPSecure = 'tls';
            $phpmailer->Host = "smtp.office365.com"; // Office365
            $phpmailer->Port = 587;
            $phpmailer->IsSMTP(); // use SMTP
            $phpmailer->SMTPAuth = true;
            $phpmailer->setFrom($phpmailer->Username,$from_name);
            $phpmailer->Subject = $the_subject;
                                                                    
            if ($pdo != null && !empty($idAmbito)) {

                $consultaSQL = "SELECT DISTINCT usuarios.correo
                                FROM t_ambitos_prestador
                                INNER JOIN usuarios_roles ON t_ambitos_prestador.usuarios_roles_id = usuarios_roles.id
                                INNER JOIN usuarios ON usuarios_roles.usuario_id = usuarios.id
                                WHERE t_ambitos_prestador.id_ambito = :idAmbito
                                  AND t_ambitos_prestador.eliminado = 0
                                  AND usuarios_roles.eliminado = 0
                                  AND usuarios.eliminado = 0";

                $sql = $pdo->prepare($consultaSQL);
                $sql->bindValue(':idAmbito', $idAmbito, \PDO::PARAM_INT);
                $sql->execute();

                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {

                    $phpmailer->AddAddress($row['correo']);

                }

            }

            $phpmailer->AddEmbeddedImage('../img/correo/encabezado-tecnopresta.png', 'encabezado', 'attachment', 'base64', 'image/png');

            $pdo = null;

            $phpmailer->Body .= "<head><meta http-equiv='Content-type' content='text/html; charset=utf-8'/></head>";
            $phpmailer->Body .= "<img src=\"cid:encabezado\" width=\"100%\" height=\"150px\" alt=\"TecnoPresta - Ministerio de Educaci&oacute;n P&uacute;blica\" />";

            $phpmailer->Body .= "<h1>Tecnopresta le informa</h1>
                                <h2>Existe Nueva Solicitud de Equipo en el sistema</h2>
                                <h3><a href='https://tecnopresta.mep.go.cr'> Ir a Tecnopresta</a></h3>
                                ";

            $phpmailer->IsHTML(true);  // Activar si se envia etiquetas html
            $phpmailer->Send();

            return true;

        } catch (\Throwable $th) {
            echo "Error al guardar solicitud: " . $th->getMessage() . "\n";            				
        }    
                      
    }
    
}

?>