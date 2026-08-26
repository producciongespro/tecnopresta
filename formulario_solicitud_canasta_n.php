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
/*
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}
*/
$ruta_regreso = 'navegar.php?ruta=formulario_buscar_alias_n.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso .= '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
    . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="manifest.json">
    <title>Carrito</title>
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
        .cart-item-card {
            border: none;
            border-bottom: 2px solid var(--mep-border, #D9D9D9);
            border-radius: 10px;
            background: #fff;
            transition: all 0.2s ease;
            margin-bottom: 10px;
        }
        .cart-item-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border-bottom-color: var(--mep-accent, #CFAC65);
        }
        .cart-item-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 8px;
            background: #f4f7fb;
            padding: 6px;
            flex-shrink: 0;
        }
        .btn-quitar-item {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: #999;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .btn-quitar-item:hover {
            background: #fee2e2;
            color: #dc2626;
        }
        .cart-header {
            background: var(--mep-primary, #192952);
            color: #fff;
            border-radius: 12px;
            padding: 14px 22px;
        }
        .search-section-divider {
            border-top: 2px dashed var(--mep-border, #D9D9D9);
            margin: 24px 0;
            padding-top: 20px;
        }
        .btn-agregar-resultado {
            border: 1px solid var(--mep-primary, #192952);
            color: var(--mep-primary, #192952);
            background: transparent;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-agregar-resultado:hover {
            background: var(--mep-primary, #192952);
            color: #fff;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <div class="container py-4 contenido-principal">
        <div class="cart-header d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Carrito</h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge rounded-pill bg-light text-dark fs-6 px-3 py-2" id="contador">0</span>
                <!-- <small>
                    <a href="contactenos_n.php?rep=Error en formulario solicitud canasta" class="text-white text-decoration-none opacity-75">
                        <i class="bi bi-envelope"></i> Reportar
                    </a>
                </small> -->
            </div>
        </div>

        <div class="row justify-content-center mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <input id="txtBuscar" type="text" class="form-control" placeholder="buscar artículos para agregar..." aria-label="Buscar">
                    <button id="btnLimpiarBusqueda" type="button" class="btn btn-link text-decoration-none px-2" style="display:none; color:#999;" aria-label="Limpiar búsqueda">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button id="btnBuscar" onclick="buscar();" type="button" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        <i class="bi bi-inboxes me-1"></i>
                        Busque artículos en el inventario general para agregarlos al carrito
                    </small>
                </div>
            </div>
        </div>

        <div id="mensaje"></div>

        <div id="colCards"></div>

        <div class="search-section-divider" id="resultadosDivider" style="display:none;">
            <span class="text-muted small fw-semibold">Resultados de búsqueda</span>
        </div>

        <div id="resultadosBusqueda" style="display:none;">
            <div id="filaResultados"></div>
        </div>

        <div class="text-center mt-4 mb-5">
            <button id="btnGuardar" type="button" class="btn btn-primary btn-lg px-5"
                onclick="solicitud();">
                <i class="bi bi-check2-circle me-2"></i>Proceder con la solicitud
            </button>
        </div>
    </div>

    <!-- Modal Mensaje -->
    <div class="modal fade" id="modalMensaje" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row justify-content-center">
                        <div class="alert alert-secondary">
                            <h1><strong class="fst-italic" id="tituloMensaje"></strong></h1>
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

    <!-- Modal Si/No Alias -->
    <div class="modal fade" id="modalMensajeSiNo" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row justify-content-center">
                        <div class="form-group row">
                            <div class="alert alert-secondary">
                                <h1><strong class="fst-italic" id="tituloMensajeSiNo"></strong></h1>
                                <p class="fw-bold fst-italic text-center" id="mensajeModalSiNo"></p>
                            </div>
                        </div>
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
                    <button type="button" onclick="quitarElementoArray();" class="btn btn-primary">Sí</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Si/No Activo -->
    <div class="modal fade" id="modalMensajeSiNoArticulo" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row justify-content-center">
                        <div class="form-group row">
                            <div class="alert alert-secondary">
                                <h1><strong class="fst-italic" id="tituloMensajeSiNoArticulo"></strong></h1>
                                <p class="fw-bold fst-italic text-center" id="mensajeModalSiNoArticulo"></p>
                            </div>
                        </div>
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
                    <button type="button" onclick="quitarElementoArrayArticulo();" class="btn btn-primary">Sí</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Back -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <?php include 'partials/footer.php'; ?>

    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="js/formulario_solicitud_canasta_n.js?version=2"></script>
</body>
</html>
