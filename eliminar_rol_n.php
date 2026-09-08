<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

require_once("conexion.php");
require_once __DIR__ . '/usuarioAzure.php';
require_once("variablesemail.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$link = $mysqli;
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Usuario no autenticado'];
    header("Location: index.html");
    exit;
}
$logcodigo = $usuario_azure['codigoPresu'] ?? '';
$esRoot = $usuario_azure['esRoot'] ?? false;
$estafecha = date('d-m-Y h:i:s');

if (mysqli_connect_errno()) {
    error_log("Error de conexion a mysql: " . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    error_log("Error cargando el conjunto de caracteres utf8");
}

function verificacion($correousuario,$nombreusuario,$rolusuario,$correosistema,$passmail,$fechora){
    $fh=$fechora;
    $roles=$rolusuario;
    $para = $correousuario;
    $completo = $nombreusuario;
    $email_user = $correosistema;
    $email_password = $passmail;
    $the_subject = "Revocaci\u00f3n de Permisos Espec\u00edficos";
    $address_to = $para;
    $from_name = "Tecnopresta";
    $phpmailer = new PHPMailer();
    $texto = "Test email";
    // ---------- datos de la cuenta de Tecnopresta -------------------------------
    $phpmailer->Username = $email_user;
    $phpmailer->Password = $email_password;
    //-----------------------------------------------------------------------

    $phpmailer->SMTPDebug = 0;
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->Host = "smtp.office365.com";
    $phpmailer->Port = 587;
    $phpmailer->IsSMTP();
    $phpmailer->SMTPAuth = true;
    $phpmailer->CharSet = 'UTF-8';
    $phpmailer->setFrom($phpmailer->Username,$from_name);
    $phpmailer->AddAddress($address_to);
    $phpmailer->Subject = $the_subject;
    $phpmailer->Body .= "<img src=\"http://tecnopresta.mep.go.cr/ico/permisorevocado.png\" alt=\"\" width=\"600px\" height=\"500px\" />";
    $phpmailer->Body .="<h2 style='color:#3498db;'>Tecnopresta Alerta</h2>";
    $phpmailer->Body .="<table border=\"1\" WIDTH=\"600\" HEIGHT=\"500\">
                                  <tr>
                                    <td align=\"center\" width=\"20%\"><img src=\"http://tecnopresta.mep.go.cr/revi/usuario.png\" alt=\"\" width=\"50px\" height=\"50px\" /></td>
                                    <td align=\"center\">$completo</td>
                                  </tr>
                                  <tr>
                                    <td align=\"center\" width=\"20%\"><img src=\"http://tecnopresta.mep.go.cr/revi/mensaje.png\" alt=\"\" width=\"50px\" height=\"50px\" /></td>
                                    <td align=\"center\">Estimado se&ntilde;or (a) se comunica que se le han revocado el siguiente rol de permisos en el sistema</td>
                                  </tr>
                                  <tr>
                                    <td align=\"center\" width=\"20%\"><img src=\"http://tecnopresta.mep.go.cr/revi/candado.png\" alt=\"\" width=\"50px\" height=\"50px\" /></td>
                                    <td align=\"center\"><b>$roles</b></td>
                                  </tr>
                                  <tr>
                                    <td align=\"center\" width=\"20%\"><img src=\"http://tecnopresta.mep.go.cr/revi/reloj.png\" alt=\"\" width=\"50px\" height=\"50px\" /></td>
                                    <td align=\"center\">$fh</td>
                                  </tr>
                                </table>
            ";
    $phpmailer->Body .= "<img src=\"http://tecnopresta.mep.go.cr/ico/eserialb.png\" alt=\"\" width=\"600px\" height=\"150px\" />";
    $phpmailer->IsHTML(true);
    $phpmailer->Send();
}

$correo = "tecnopresta@mep.go.cr";

$xeliminar = isset($_GET['gps']) ? intval($_GET['gps']) : 0;
if ($xeliminar <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Identificador inválido'];
    header("Location: navegar.php?ruta=formulario_crear_roles_n.php");
    exit;
}

$qr = mysqli_query($link, "SELECT ur.id, u.correo, u.cedula, ur.codigo_presu, ur.rol_id FROM usuarios_roles ur INNER JOIN usuarios u ON u.id = ur.usuario_id WHERE ur.id = $xeliminar AND ur.eliminado = 0");
if (!$qr || mysqli_num_rows($qr) == 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Registro no encontrado'];
    header("Location: navegar.php?ruta=formulario_crear_roles_n.php");
    exit;
}
$row = mysqli_fetch_array($qr);
$id_rol = $row['rol_id'];
$xmail = $row['correo'];
$cedula = $row['cedula'];
$codigo = $row['codigo_presu'];

if ($codigo != $logcodigo) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'El registro no pertenece a su centro'];
    header("Location: navegar.php?ruta=formulario_crear_roles_n.php");
    exit;
}

$cedula_actual = $usuario_azure['cedula'] ?? null;
if ($cedula_actual !== null && $cedula === $cedula_actual) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'No puede eliminar sus propios permisos'];
    header("Location: navegar.php?ruta=formulario_crear_roles_n.php");
    exit;
}

if (!$esRoot && $id_rol == 1) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'No puede eliminar un usuario root'];
    header("Location: navegar.php?ruta=formulario_crear_roles_n.php");
    exit;
}

$qr2 = mysqli_query($link, "SELECT rol FROM t_roles WHERE id_rol = $id_rol");
$nrol = mysqli_fetch_array($qr2)['rol'];

mysqli_query($link, "UPDATE usuarios_roles SET eliminado = 1 WHERE id = $xeliminar");

// Desactivar los ámbitos del prestador (soft delete, conserva histórico)
mysqli_query($link, "UPDATE t_ambitos_prestador SET eliminado = 1, updated_at = NOW()
                     WHERE usuarios_roles_id = $xeliminar AND eliminado = 0");

verificacion($xmail, $cedula, $nrol, $correo, $passemail, $estafecha);

mysqli_close($link);

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Eliminado correctamente'];
header("Location: navegar.php?ruta=formulario_crear_roles_n.php");
exit;
