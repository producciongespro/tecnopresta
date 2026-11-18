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
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
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

        /* Aviso sin prestadores + enlace a la guía */
        .aviso-sin-prestadores {
            max-width: 640px;
            margin: 0 auto;
        }
        .guia-cta {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            border-radius: 14px;
            text-decoration: none;
            text-align: left;
            color: #fff;
            background: linear-gradient(135deg, var(--mep-primary, #192952) 0%, var(--mep-secondary, #0035A0) 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px rgba(0, 53, 160, 0.25);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            animation: guia-cta-pulso 2.4s ease-in-out infinite;
        }
        .guia-cta:hover,
        .guia-cta:focus-visible {
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(0, 53, 160, 0.35);
            animation-play-state: paused;
        }
        .guia-cta:focus-visible {
            outline: 3px solid #fbbf24;
            outline-offset: 3px;
        }
        .guia-cta-icono {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #192952;
            background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.45);
        }
        .guia-cta-texto {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 0.98rem;
            line-height: 1.45;
        }
        .guia-cta-texto strong {
            color: #fde68a;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .guia-cta-etiqueta {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #93c5fd;
        }
        .guia-cta-flecha {
            flex-shrink: 0;
            font-size: 1.75rem;
            color: #fbbf24;
            transition: transform 0.25s ease;
        }
        .guia-cta:hover .guia-cta-flecha {
            transform: translateX(5px);
        }
        @keyframes guia-cta-pulso {
            0%, 100% { box-shadow: 0 8px 24px rgba(0, 53, 160, 0.25), 0 0 0 0 rgba(251, 191, 36, 0.55); }
            50%      { box-shadow: 0 8px 24px rgba(0, 53, 160, 0.25), 0 0 0 8px rgba(251, 191, 36, 0); }
        }
        @media (max-width: 576px) {
            .guia-cta { padding: 14px 16px; gap: 12px; }
            .guia-cta-icono { width: 40px; height: 40px; font-size: 1.25rem; }
            .guia-cta-flecha { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .guia-cta { animation: none; }
            .guia-cta, .guia-cta-flecha { transition: none; }
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
    <script src="js/formulario_buscar_prestadores_n.js?version=2.5"></script>
</body>
</html>
