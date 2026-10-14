<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
    
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexión a MySQL: " . mysqli_connect_error();
    exit;
}

mysqli_set_charset($link, "utf8");

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// ==== CONSTRUIR RUTA DE REGRESO =====
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $_SESSION['subsistema_id'] = intval($_GET['subsistema_id']);
    $_SESSION['modulo_id'] = intval($_GET['modulo_id']);
}

$sid = $_GET['subsistema_id'] ?? $_SESSION['subsistema_id'] ?? null;
$mid = $_GET['modulo_id'] ?? $_SESSION['modulo_id'] ?? null;

$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';
if ($sid && $mid) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . intval($sid)
    . '&modulo_id=' . intval($mid);
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}
?>
<!doctype html>
<html lang="es">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="manifest" href="manifest.json">

    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/style.css">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="css/formulario_menu_principal.css?version=4" />
    <link rel="stylesheet" href="assets/css/nueva-identidad.css"/>

    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <title>Solicitudes</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
  </head>
  <body class="layout-page">
    
    <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">

    <div class="container">
      <br>
      <div class="solicitud-titulo-seccion">
        <h3><i class="bi bi-inbox"></i>Solicitudes Pendientes de Revisión</h3>
        <a href="navegar.php?ruta=contactenos_n.php&rep=Error en formulario vista solicitud html" class="reporte-link">
          <i class="bi bi-exclamation-triangle"></i>Reportar Incidencia / Error
        </a>
      </div>
      <hr class="my-3">
    </div>

    <div class="container-sm container-md container-lg">
      <div class="row justify-content-center">
        <div id="contenedorError" class="form-group row"></div>
      </div>
    </div>

    <!-- Contenedor Principal -->
    <div class="container-sm container-md container-lg px-3">

      <div class="row align-items-stretch" id="fila"></div>

    </div>

      <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
        style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

   <!-- Optional JavaScript -->
    <!-- jQuery first, then Bootstrap JS -->    
    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="js/formulario_VistaSolicitud_n.js?version=14"></script>

  </main>
  <?php include 'partials/footer.php'; ?>
  </body>
</html>
