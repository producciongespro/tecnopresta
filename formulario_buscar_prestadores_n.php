<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="manifest.json">
    <title>Seleccionar Ambito</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=20260924_2">
    <link rel="stylesheet" href="css/formulario_menu_principal.css">
    <link rel="stylesheet" href="css/fondoresponsive.css">
    <link href="sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <script src="sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        html, body {
            height: 100%;
        }
        body.layout-page {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main.contenido-principal {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        .card-prestador {
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--mep-secondary, #0035A0);
            border-color: var(--mep-secondary, #0035A0);
        }
        .card-prestador:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border-color: var(--mep-primary, #192952);
        }
        .card-prestador .card-title {
            color: #fff;
        }
    </style>
</head>
<body class="layout-page">
    <div class="d-none d-md-block">
        <?php include 'partials/header.php'; ?>
    </div>

    <main class="contenido-principal">
        <div class="container py-4 text-center">
            <h4 class="mb-1 fw-bold" style="color: var(--mep-primary);">
                Seleccionar Ambito
            </h4>
        </div>

        <div class="container mb-3">
            <div class="row justify-content-center">
                <div id="mensaje"></div>
            </div>
        </div>

        <div class="container" style="padding-top: 0.5em;" id="contenedor">
            <ul class="list-unstyled">
                <div class="d-flex flex-column align-items-center gap-3" id="fila">
                </div>
            </ul>
        </div>
    </main>

    <!-- Floating Back -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <div class="d-none d-md-block">
        <?php include 'partials/footer.php'; ?>
    </div>

    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="js/formulario_buscar_prestadores_n.js?version=1.8"></script>
</body>
</html>
