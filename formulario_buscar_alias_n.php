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
    <title>Buscar</title>
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
        .cart-fab {
            position: fixed;
            bottom: 170px;
            right: 30px;
            z-index: 999;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--mep-primary, #192952);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .cart-fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
        }
        .cart-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            line-height: 1;
        }
        .cart-fab[data-tooltip]::before {
            content: attr(data-tooltip);
            position: absolute;
            right: 74px;
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            font-family: 'Henderson Sans', Arial, sans-serif;
        }
        .cart-fab:hover[data-tooltip]::before {
            opacity: 1;
        }

        .card-img-mep {
            width: 100%;
            height: 180px;
            object-fit: contain;
            padding: 16px;
            background: #f4f7fb;
        }

        .btn-outline-mep {
            border: 2px solid var(--mep-primary, #192952);
            color: var(--mep-primary, #192952);
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-outline-mep:hover {
            background: var(--mep-primary, #192952);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 41, 82, 0.2);
        }

        #btnBuscar {
            background: #e8e8e8;
            border-color: #d0d0d0;
            color: #333;
        }
        #btnBuscar:hover {
            background: #d4d4d4;
            border-color: #c0c0c0;
            color: #000;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold" style="color: var(--mep-primary);">
                    Buscar Activos y Alias
                </h4>
                <!-- <small class="text-muted">                    
                    <a href="contactenos_n.php?rep=Error en formulario buscar alias html" class="text-decoration-none"><i class="bi bi-envelope"></i> Reportar Incidencia / Error</a>
                </small> -->
            </div>
        </div>
    </div>

    <div class="container mb-3">
        <div class="row justify-content-center">
            <div id="mensaje"></div>
        </div>
    </div>

    <div class="container mb-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="input-group">
                    <input id="txtBuscar" type="text" class="form-control" placeholder="buscar..." aria-label="Buscar">
                    <button id="btnLimpiarBusqueda" type="button" class="btn btn-link text-decoration-none px-2" style="display:none; color:#999;" aria-label="Limpiar búsqueda">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button id="btnBuscar" onclick="buscar();" type="button" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="padding-top: 0.5em;" id="contenedor">
        <ul class="list-unstyled">
            <div class="row g-3" id="fila">
            </div>
        </ul>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalMensaje" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row justify-content-center">
                        <div class="alert alert-secondary">
                            <h1><strong class="font-italic" id="tituloMensaje"></strong></h1>
                            <p class="fw-bold fst-italic text-center" id="mensajeModal"></p>
                        </div>
                        <p class="fst-italic text-center" id="mensajeModalParrafo"></p>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="row">
                            <div class="col" style="width: 5rem;">
                                <img class="card-img-top" src="img/mensaje.png">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Cart -->
    <a class="cart-fab" href="javascript:void(0)" onclick="formularioCanasta();" data-tooltip="Ver Carrito">
        <img src="img/canastaeducativa2.png" width="40" height="40" alt="Carrito" />
        <span class="cart-badge" id="contador">0</span>
    </a>

    <!-- Floating Back -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <?php include 'partials/footer.php'; ?>

    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="js/formulario_buscar_alias_n.js?version=5"></script>
</body>
</html>