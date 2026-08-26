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
$ruta_regreso = 'formulario_solicitud_canasta_n.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="manifest.json">
    <title>Solicitud</title>
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

    <!-- Gijgo Datepicker -->
    <link href="gijgo/gijgo.min.css" rel="stylesheet" type="text/css" />

    <style>
        .section-card {
            background: #fff;
            border: 1px solid #eef0f4;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .section-card-header {
            padding: 18px 22px 0;
            font-weight: 700;
            font-size: 1rem;
            color: var(--mep-primary, #192952);
        }
        .section-card-header .bi {
            color: var(--mep-accent, #CFAC65);
        }
        .section-card-body {
            padding: 16px 22px 22px;
        }
        .solicitud-header {
            background: var(--mep-primary, #192952);
            color: #fff;
            border-radius: 12px;
            padding: 14px 22px;
            margin-bottom: 24px;
        }
        .solicitud-header a {
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 0.85rem;
        }
        .solicitud-header a:hover {
            color: #fff;
        }
        .btn-mep-primary {
            background: var(--mep-accent, #CFAC65);
            border-color: var(--mep-accent, #CFAC65);
            color: var(--mep-primary, #192952);
            font-weight: 600;
        }
        .btn-mep-primary:hover {
            background: #c49b4e;
            border-color: #c49b4e;
            color: var(--mep-primary, #192952);
        }
        #colCards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }
        .cart-item-card {
            border: none;
            border-bottom: 2px solid var(--mep-border, #D9D9D9);
            border-radius: 10px;
            background: #fff;
            transition: all 0.2s ease;
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
        .search-result-card {
            border: none;
            border-bottom: 1px solid var(--mep-border, #D9D9D9);
            border-radius: 10px;
            background: #fff;
            transition: all 0.2s ease;
            margin-bottom: 8px;
        }
        .search-result-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border-bottom-color: var(--mep-accent, #CFAC65);
        }
        .form-label-custom {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--mep-text, #2C3E50);
            margin-bottom: 4px;
        }
        .gj-datepicker .form-control,
        .gj-timepicker .form-control {
            border-radius: 8px;
        }
        .gj-datepicker-md [role="right-icon"],
        .gj-timepicker-md [role="right-icon"] {
            top: 6px;
        }
        .modal-search-header {
            border-bottom: none;
            padding: 16px 20px 0;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <div class="container py-4 contenido-principal">
        <!-- Header -->
        <div class="solicitud-header d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0 fw-bold"><i class="bi bi-file-text me-2"></i>Solicitar Equipo</h4>
            </div>
            <div>
                <small>
                    <a href="contactenos_n.php?rep=Error en formulario solicitud" class="text-white text-decoration-none opacity-75">
                        <i class="bi bi-envelope"></i> Reportar
                    </a>
                </small>
            </div>
        </div>

        <div class="row justify-content-center">
            <div id="mensaje" class="col-12"></div>
        </div>

        <!-- Card 1: Rango de la Solicitud -->
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-calendar-range me-1"></i> Rango de la Solicitud
            </div>
            <div class="section-card-body">
                <div class="row g-3">
                    <div class="col-md-5 col-lg-3">
                        <label for="fechaRetiro" class="form-label-custom">Fecha de retiro</label>
                        <input class="form-control" id="fechaRetiro" autocomplete="off" />
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label for="horaRetiro" class="form-label-custom">Hora de retiro</label>
                        <input class="form-control" id="horaRetiro" autocomplete="off" />
                    </div>
                    <div class="col-md-5 col-lg-3">
                        <label for="fechaDevolucion" class="form-label-custom">Fecha de devolución</label>
                        <input class="form-control" id="fechaDevolucion" autocomplete="off" />
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label for="horaDevolucion" class="form-label-custom">Hora de devolución</label>
                        <input class="form-control" id="horaDevolucion" autocomplete="off" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Activos Solicitados -->
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-box-seam me-1"></i> Activos Solicitados
            </div>
            <div class="section-card-body">
                <div id="colCards"></div>

                <div class="text-center mt-4">
                    <button id="btnAgregarArticulo" onclick="buscar();" type="button"
                        class="btn btn-outline-mep px-4">
                        <i class="bi bi-plus-circle me-1"></i>Agregar m&aacute;s art&iacute;culos
                    </button>
                </div>
            </div>
        </div>

        <!-- Card 3: Información Adicional -->
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-info-circle me-1"></i> Informaci&oacute;n Adicional
            </div>
            <div class="section-card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="txtUso" class="form-label-custom">Uso que se le dar&aacute; al equipo</label>
                        <textarea class="form-control rounded-3" rows="3"
                            placeholder="Describa el uso que se le dar&aacute; al equipo solicitado"
                            maxlength="255" id="txtUso"></textarea>
                    </div>

                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" class="form-check-input" id="chkBoleta" />
                                <label class="form-check-label fw-semibold" for="chkBoleta" id="lblBoleta">
                                    Generar Boleta para Oficial de Seguridad
                                </label>
                            </div>
                            <img src="img/oficial de Seguridad.png" alt="Oficial de Seguridad"
                                style="width: 40px; height: auto; opacity: 0.7;" loading="lazy" />
                        </div>
                        <textarea class="form-control rounded-3" rows="2"
                            placeholder="Destino probable del activo, por ejemplo: gira de trabajo a x o y lugar."
                            maxlength="255" id="txtBoleta" readonly></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 mb-5">
            <button id="btnGuardar" type="button"
                class="btn btn-mep-primary btn-lg px-5"
                onclick="guardar();">
                <i class="bi bi-check2-circle me-1"></i>Registrar solicitud de equipo
            </button>
        </div>
    </div>

    <!-- Modal: Confirmar quitar alias -->
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
                                <img class="card-img-top" src="img/mensaje.png" alt="mensaje">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="quitarElementoArray();" class="btn btn-primary">S&iacute;</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmar quitar activo -->
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
                                <img class="card-img-top" src="img/mensaje.png" alt="mensaje">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="quitarElementoArrayArticulo();" class="btn btn-primary">S&iacute;</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Mensaje genérico -->
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
                                <img class="card-img-top" src="img/mensaje.png" alt="mensaje">
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

    <!-- Modal: Mensaje guardar éxito -->
    <div class="modal fade" id="modalMensajeGuardar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row justify-content-center">
                        <div class="alert alert-secondary">
                            <h1><strong class="fst-italic" id="tituloMensajeGuardar"></strong></h1>
                            <p class="fw-bold fst-italic text-center" id="mensajeModalGuardar"></p>
                        </div>
                        <p class="fst-italic text-center" id="mensajeModalParrafoGuardar"></p>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="row">
                            <div class="col" style="width: 5rem;">
                                <img class="card-img-top" src="img/listo.png" alt="listo">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="salir();">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Búsqueda -->
    <div class="modal fade" id="buscarModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-search-header">
                    <h5 class="fw-bold mb-0"><i class="bi bi-search me-2 text-mep-accent"></i>Buscar art&iacute;culos</h5>
                    <button type="button" onclick="cerrarModal()" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="px-4 pt-3">
                    <div class="input-group">
                        <input id="txtBuscar" type="text" class="form-control rounded-start-3" placeholder="Buscar activos o alias..." aria-label="Buscar" />
                        <button id="btnBuscar" onclick="buscar();" type="button" class="btn btn-primary rounded-end-3 px-3">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row justify-content-center">
                        <div id="mensajeModalBuscar" class="col-12"></div>
                    </div>
                    <div id="colCardsBuscar"></div>
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

    <!-- Gijgo Datepicker -->
    <script src="gijgo/gijgo.min.js" type="text/javascript"></script>
    <script src="gijgo/messages.es-es.js" type="text/javascript"></script>

    <script src="js/formulario_solicitud_n.js?version=2"></script>
</body>
</html>
