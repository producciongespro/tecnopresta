<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexion a MySQL: " . mysqli_connect_error();
    exit;
}
mysqli_set_charset($link, "utf8");

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

$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
        . '&subsistema_id=' . intval($_GET['subsistema_id'])
        . '&modulo_id=' . intval($_GET['modulo_id']);
}

$ruta_ticket = 'ticket_administrativo_n.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_ticket .= '?subsistema_id=' . intval($_GET['subsistema_id'])
                  . '&modulo_id=' . intval($_GET['modulo_id']);
}

$fondos = [];
$res = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos");
while ($row = $res->fetch_assoc()) {
    $fondos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
  <meta http-equiv="X-UA-Compatible" content="ie=edge" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/formulario_menu_principal.css" />
  <link rel="stylesheet" href="assets/css/nueva-identidad.css"/>
  <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

  <title>TecnoPresta | Registro de Nuevo Activo</title>

  <style>
    #fase-registro { display: none; }

    .asset-detail-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 25px rgba(0,0,0,0.06);
      border: none;
      border-bottom: 3px solid var(--mep-blue);
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(10px);
    }

    .asset-detail-card .card-header {
      background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
      color: #fff;
      font-weight: 600;
      letter-spacing: 0.3px;
    }

    .asset-preview {
      max-height: 260px;
      width: auto;
      object-fit: contain;
      background: #f8f9fa;
      border-radius: 8px;
      padding: 12px;
    }

    .info-item {
      background: #f4f7fb;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 8px;
      border-left: 3px solid var(--mep-gold);
      transition: all 0.2s ease;
    }

    .info-item:hover {
      transform: translateX(4px);
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .info-item strong { color: var(--mep-blue); font-size: 0.82rem; }
    .info-item span { font-size: 0.88rem; color: #2c3e50; }

    .search-results-wrap {
      max-height: 420px;
      overflow-y: auto;
      border-radius: 0 0 10px 10px;
    }

    .search-results-wrap::-webkit-scrollbar { width: 6px; }
    .search-results-wrap::-webkit-scrollbar-track { background: #f1f3f5; border-radius: 3px; }
    .search-results-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

    .result-img {
      width: 42px; height: 42px;
      object-fit: contain;
      border-radius: 6px;
      background: #f4f7fb;
      padding: 3px;
    }

    .btn-seleccionar {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--mep-blue);
      color: #fff;
      border: none;
      padding: 6px 16px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.8rem;
      transition: all 0.25s ease;
      cursor: pointer;
      letter-spacing: 0.3px;
    }

    .btn-seleccionar:hover {
      background: var(--mep-gold);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(200,169,81,0.3);
    }

    .btn-volver {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: transparent;
      color: #fff;
      border: 1px solid rgba(255,255,255,0.3);
      padding: 5px 16px;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-volver:hover { background: rgba(255,255,255,0.15); color: #fff; }

    .form-control, .form-select {
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 0.55rem 0.85rem;
      font-size: 0.88rem;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--mep-blue);
      box-shadow: 0 0 0 3px rgba(0, 56, 118, 0.1);
    }

    .form-label { font-weight: 600; color: #1f3b57; font-size: 0.85rem; margin-bottom: 4px; }

    .empty-state { text-align: center; padding: 40px 20px; color: #8a9aa8; }
    .empty-state .bi { font-size: 2.5rem; opacity: 0.4; }

    .ticket-panel {
      max-width: 560px;
      margin: 0 auto;
      padding: 20px 24px;
      background: #f4f7fb;
      border: 1px dashed var(--mep-blue);
      border-radius: 12px;
      text-align: left;
    }

    .ticket-example {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-left: 3px solid var(--mep-gold);
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 0.88rem;
      color: #2c3e50;
    }

    .ticket-example-item { margin-bottom: 8px; }
    .ticket-example-item:last-child { margin-bottom: 0; }

    .ticket-example-label {
      display: block;
      font-weight: 700;
      color: var(--mep-blue);
      font-size: 0.82rem;
      margin-bottom: 2px;
    }

    .limpiar-fondo-aviso {
      max-width: 560px;
      margin: 0 auto;
      border-radius: 12px;
      font-size: 0.9rem;
      text-align: left;
    }

    .btn-ticket {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--mep-blue);
      color: #fff;
      border: none;
      padding: 10px 28px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.25s ease;
      cursor: pointer;
      text-decoration: none;
      letter-spacing: 0.3px;
    }

    .btn-ticket:hover {
      background: var(--mep-gold);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(200,169,81,0.3);
    }

    .select2-container--default .select2-selection--single {
      height: 38px; padding-top: 5px; border-radius: 8px; border: 1px solid #e2e8f0;
    }
    .select2-container--default .select2-selection--single:focus {
      border-color: var(--mep-blue); box-shadow: 0 0 0 3px rgba(0, 56, 118, 0.1);
    }

    .btn-disponibilidad::before { content: "Regresar" !important; }

    /* ── Paleta de colores ── */
    .color-palette {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 8px 0;
    }

    .color-swatch {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      border: 3px solid #dee2e6;
      cursor: pointer;
      transition: all 0.2s ease;
      position: relative;
    }

    .color-swatch:hover {
      transform: scale(1.15);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .color-swatch.selected {
      border-color: var(--mep-gold);
      box-shadow: 0 0 0 3px rgba(200,169,81,0.4);
      transform: scale(1.1);
    }

    .color-swatch.selected::after {
      content: '\f26a';
      font-family: 'bootstrap-icons';
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      font-size: 20px;
      color: #fff;
      text-shadow: 0 1px 3px rgba(0,0,0,0.5);
    }

    .color-swatch-label {
      font-size: 0.7rem;
      color: #6c757d;
      text-align: center;
      margin-top: 2px;
    }

    .color-name-display {
      font-weight: 600;
      color: var(--mep-blue);
      font-size: 0.9rem;
      margin-top: 8px;
    }

    @media (max-width: 768px) {
      .asset-detail-card .row.g-0 { flex-direction: column; }
      .asset-detail-card .col-md-4 { text-align: center; margin-bottom: 1rem; }
      .color-swatch { width: 44px; height: 44px; }
    }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">
  <?php include 'partials/header.php'; ?>
  <main class="flex-grow-1">
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
       style="bottom: 100px;" title="Regresar">
      <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <div class="container mt-4">

      <!-- ════════════════════════════════════════════════════════
           FASE 1: BUSQUEDA / SELECCION
           ════════════════════════════════════════════════════════ -->
      <div id="fase-busqueda">

        <!-- Instrucciones -->
        <div class="card mb-4">
          <div class="card-header" style="background:linear-gradient(135deg,var(--mep-blue),var(--mep-blue2));color:#fff;">
            <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>Registrar un nuevo activo</h5>
          </div>
          <div class="card-body">
            <div class="alert alert-info d-flex align-items-center mb-0">
              <i class="bi bi-lightbulb-fill fs-3 me-3"></i>
              <div>
                <h6 class="alert-heading mb-2">Pasos para registrar tu activo</h6>
                <ol class="mb-0">
                  <li class="mb-1">Escriba un texto de busqueda (tipo, marca o modelo) y haga clic en <strong>Buscar</strong>.</li>
                  <li class="mb-1">Opcionalmente, seleccione un <strong>fondo presupuestario</strong> para filtrar.</li>
                  <li class="mb-1">De los resultados, haga clic en <strong>Seleccionar</strong> sobre el modelo deseado.</li>
                  <li>Seleccione el color, ingrese placa, serial y confirme el registro.</li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <!-- Card de busqueda -->
        <div class="card">
          <div class="card-header" style="background:linear-gradient(135deg,var(--mep-blue),var(--mep-blue2));color:#fff;">
            <h5 class="mb-0"><i class="bi bi-search me-2"></i>Buscar Activo</h5>
          </div>
          <div class="card-body">

            <div class="row g-3 mb-3">
              <!-- Fondo presupuestario (OPCIONAL) -->
              <div class="col-md-4">
                <label class="form-label">Fondo Presupuestario <small class="text-muted">(opcional)</small></label>
                <select id="fondo_select" class="form-select" style="width:100%;">
                  <option value="">Todos los fondos</option>
                  <?php foreach ($fondos as $f): ?>
                    <option value="<?= (int)$f['id_fondos'] ?>"><?= htmlspecialchars($f['fondos']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Cuadro de busqueda unico -->
              <div class="col-md-6">
                <label class="form-label">Buscar tipo, marca o modelo</label>
                <div class="input-group">
                  <span class="input-group-text" style="background:var(--mep-blue);color:#fff;border-color:var(--mep-blue);border-radius:8px 0 0 8px;">
                    <i class="bi bi-search"></i>
                  </span>
                  <input type="text" id="input_busqueda" class="form-control"
                         placeholder="Ej: Computadora, Dell, OptiPlex..."
                         style="border-radius:0 8px 8px 0;">
                </div>
              </div>

              <!-- Boton buscar -->
              <div class="col-md-2 d-flex align-items-end">
                <button type="button" id="btnBuscar" class="btn btn-primary w-100">
                  <i class="bi bi-search me-1"></i> Buscar
                </button>
              </div>
            </div>

          </div>
        </div>

        <!-- Resultados -->
        <div id="contenedor_resultados" class="card mt-4" style="display:none;">
          <div class="card-header d-flex justify-content-between align-items-center"
               style="background:linear-gradient(135deg,var(--mep-blue),var(--mep-blue2));color:#fff;">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Resultados de Busqueda</h5>
            <span class="badge bg-light text-primary" id="badge_total">0</span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive search-results-wrap">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:60px;"></th>
                    <th>Tipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th style="width:130px;">Accion</th>
                  </tr>
                </thead>
                <tbody id="resultados_body"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Mensaje vacio -->
        <div id="mensaje_vacio" class="card mt-4" style="display:none;">
          <div class="card-body">
            <div class="empty-state">
              <i class="bi bi-search d-block mb-2"></i>
              <p class="mb-1 fw-bold" style="color:#1f3b57;">No se encontraron resultados</p>
              <p class="mb-0" style="font-size:0.88rem;" id="texto_vacio">
                No se encontraron modelos que coincidan con la busqueda.
              </p>
            </div>

            <div class="alert alert-warning limpiar-fondo-aviso mb-4" id="aviso_limpiar_fondo" style="display:none;">
              <i class="bi bi-funnel-fill me-2"></i>
              <strong>Sin resultados con el filtro de fondo presupuestario seleccionado.</strong><br>
              Para abarcar un ambito mas amplio, limpie el filtro de <strong>Fondo Presupuestario</strong> y
              vuelva a realizar la busqueda.
            </div>

            <div class="ticket-panel mt-4" id="panel_ticket">
              <div class="d-flex align-items-center mb-2">
                <i class="bi bi-ticket-detailed fs-4 me-2" style="color:var(--mep-blue);"></i>
                <strong class="mb-0" style="color:#1f3b57;">El activo no tiene modelo?</strong>
              </div>
              <p class="mb-3" style="font-size:0.9rem;">
                Si el activo que desea ingresar no cuenta con un modelo registrado, puede crear un
                ticket para que el administrador registre el modelo solicitado.
                A continuacion se muestra un ejemplo de como deberia quedar el ticket:
              </p>

              <div class="ticket-example mb-3">
                <div class="ticket-example-item">
                  <span class="ticket-example-label">Asunto:</span>
                  <span>Solicitud de nuevo modelo de activo</span>
                </div>
                <div class="ticket-example-item">
                  <span class="ticket-example-label">Detalle:</span>
                  <span>
                    Solicitamos el registro del siguiente modelo de activo que no se encuentra en el catalogo:<br>
                    Marca: HP<br>
                    Modelo: ProBook 450 G8<br>
                    Descripcion breve: Computadora portatil de 15.6&quot;, procesador Intel Core i5, 8 GB RAM.
                  </span>
                </div>
              </div>

              <div class="text-center">
                <a href="<?= htmlspecialchars($ruta_ticket) ?>" class="btn-ticket">
                  <i class="bi bi-ticket-detailed me-2"></i>Crear Ticket
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════
           FASE 2: REGISTRO DE PLACA / SERIAL / COLOR
           ════════════════════════════════════════════════════════ -->
      <div id="fase-registro">

        <!-- Card del activo seleccionado -->
        <div class="card asset-detail-card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <i class="bi bi-pc-display-horizontal me-2"></i>
              <span>ACTIVO SELECCIONADO</span>
            </div>
            <button type="button" id="btnVolverBusqueda" class="btn-volver">
              <i class="bi bi-arrow-left"></i> Volver a busqueda
            </button>
          </div>
          <div class="row g-0 p-3">
            <div class="col-md-4 text-center d-flex align-items-center justify-content-center"
                 style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);border-radius:8px;">
              <div>
                <img src="" id="detalle_imagen" class="img-fluid asset-preview" alt="Imagen del activo">
                <div class="mt-2">
                  <small class="text-muted"><i class="bi bi-info-circle"></i> Imagen de referencia</small>
                </div>
              </div>
            </div>
            <div class="col-md-8">
              <div class="ps-3 pt-2">
                <h5 class="text-primary mb-3" id="detalle_titulo" style="font-weight:700;"></h5>
                <div class="row">
                  <div class="col-sm-6">
                    <div class="info-item">
                      <strong><i class="bi bi-tag me-1 text-primary"></i> Tipo:</strong>
                      <span id="detalle_clase">-</span>
                    </div>
                    <div class="info-item">
                      <strong><i class="bi bi-building me-1 text-primary"></i> Marca:</strong>
                      <span id="detalle_marca">-</span>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="info-item">
                      <strong><i class="bi bi-upc-scan me-1 text-primary"></i> Modelo:</strong>
                      <span id="detalle_modelo"></span>
                    </div>
                  </div>
                </div>
                <div class="alert alert-light mt-3 d-flex align-items-center mb-0">
                  <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
                  <small class="text-muted">Verifique la informacion antes de registrar placa y serial.</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Formulario de registro -->
        <div class="card mb-4">
          <div class="card-header" style="background:linear-gradient(135deg,var(--mep-blue),var(--mep-blue2));color:#fff;">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Datos de Registro</h5>
          </div>
          <div class="card-body">
            <form id="formRegistro">
              <input type="hidden" id="hidden_id_activo" name="id_activo" value="">
              <input type="hidden" id="hidden_id_modelo" name="modelo_id" value="">
              <input type="hidden" id="colorHex" name="color_hex" value="">

              <div class="row g-3">

                <!-- Paleta de colores -->
                <div class="col-12">
                  <label class="form-label">Color del activo <span class="text-danger">*</span></label>
                  <div class="color-palette" id="colorPalette"></div>
                  <div class="color-name-display" id="colorNombre">Ningun color seleccionado</div>
                </div>

                <!-- Placa -->
                <div class="col-md-6">
                  <label for="input_placa" class="form-label">Placa <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="input_placa" name="placa"
                         placeholder="Ingrese la placa del activo" required maxlength="50"
                         style="text-transform:uppercase;">
                </div>

                <!-- Serial -->
                <div class="col-md-6">
                  <label for="input_serial" class="form-label">Serial <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="input_serial" name="serial"
                         placeholder="Ingrese el serial del activo" required maxlength="50"
                         style="text-transform:uppercase;">
                </div>

                <!-- Origen Presupuestario -->
                <div class="col-md-6">
                  <label for="fondo_registro" class="form-label">Origen Presupuestario <span class="text-danger">*</span></label>
                  <select class="form-select" id="fondo_registro" name="id_fondos" required style="width:100%;">
                    <option value="">Seleccione el origen presupuestario</option>
                    <?php foreach ($fondos as $f): ?>
                      <option value="<?= (int)$f['id_fondos'] ?>"><?= htmlspecialchars($f['fondos']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Botones -->
                <div class="col-md-6 d-flex align-items-end">
                  <div class="d-flex gap-3 w-100 justify-content-end">
                    <button type="button" id="btnVolverBusqueda2" class="btn btn-outline-secondary px-4">
                      <i class="bi bi-arrow-left me-1"></i> Volver
                    </button>
                    <button type="submit" class="btn btn-primary px-5" id="btnGuardar">
                      <i class="bi bi-save me-1"></i> Registrar
                    </button>
                  </div>
                </div>

              </div>
            </form>
          </div>
        </div>

      </div>

    </div>
  </main>

  <?php include 'partials/footer.php'; ?>

  <script src="js/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

  <script>
  $(document).ready(function() {

    // ════════════════════════════════════════════════════════════
    // PALETA DE 12 COLORES BASICOS
    // ════════════════════════════════════════════════════════════
    var COLORES = [
      { nombre: 'Negro',   hex: '#424242' },
      { nombre: 'Blanco',  hex: '#F5F5F5' },
      { nombre: 'Gris',    hex: '#9E9E9E' },
      { nombre: 'Rojo',    hex: '#E53935' },
      { nombre: 'Azul',    hex: '#1E88E5' },
      { nombre: 'Verde',   hex: '#43A047' },
      { nombre: 'Amarillo', hex: '#FDD835' },
      { nombre: 'Naranja', hex: '#FB8C00' },
      { nombre: 'Marron',  hex: '#6D4C41' },
      { nombre: 'Rosa',    hex: '#EC407A' },
      { nombre: 'Morado',  hex: '#8E24AA' },
      { nombre: 'Beige',   hex: '#D7CCC8' }
    ];

    // Renderizar swatches
    COLORES.forEach(function(c) {
      var borderColor = (c.hex === '#F5F5F5' || c.hex === '#FDD835' || c.hex === '#D7CCC8')
                         ? '#ccc' : c.hex;
      var swatch = $(
        '<div class="text-center">' +
          '<div class="color-swatch" data-hex="' + c.hex + '" data-nombre="' + c.nombre + '" ' +
            'style="background:' + c.hex + '; border-color:' + borderColor + ';" ' +
            'title="' + c.nombre + '"></div>' +
        '</div>'
      );
      $('#colorPalette').append(swatch);
    });

    // Seleccionar color
    $(document).on('click', '.color-swatch', function() {
      $('.color-swatch').removeClass('selected');
      $(this).addClass('selected');
      $('#colorHex').val($(this).data('hex'));
      $('#colorNombre').text($(this).data('nombre') + ' (' + $(this).data('hex') + ')');
    });

    // ════════════════════════════════════════════════════════════
    // SELECT2
    // ════════════════════════════════════════════════════════════
    $('#fondo_select').select2({
      placeholder: "Todos los fondos",
      allowClear: true,
      width: '100%'
    });

    $('#fondo_registro').select2({
      placeholder: "Seleccione el origen presupuestario",
      allowClear: true,
      width: '100%'
    });

    // ════════════════════════════════════════════════════════════
    // BUSQUEDA
    // ════════════════════════════════════════════════════════════

    // Auto-buscar al cambiar fondo (si ya hay algo escrito o solo fondo)
    $('#fondo_select').on('change', function() {
      var busqueda = $('#input_busqueda').val().trim();
      if (busqueda !== '' || $(this).val()) {
        ejecutarBusqueda();
      } else {
        limpiarResultados();
      }
    });

    // Al limpiar el fondo, la lista debe quedar retraida (cerrada)
    // Se cierra con retraso porque Select2 reabre la lista tras el evento de limpieza
    $('#fondo_select').on('select2:clear', function() {
      var $el = $(this);
      setTimeout(function() {
        $el.select2('close');
      }, 100);
    });

    // Buscar con boton
    $('#btnBuscar').on('click', function() {
      ejecutarBusqueda();
    });

    // Buscar con Enter
    $('#input_busqueda').on('keypress', function(e) {
      if (e.which === 13) {
        e.preventDefault();
        ejecutarBusqueda();
      }
    });

    // Limpiar resultados cuando se borra el texto de busqueda
    $('#input_busqueda').on('input', function() {
      if ($(this).val().trim() === '' && (!$('#fondo_select').val() || $('#fondo_select').val() === '')) {
        limpiarResultados();
      }
    });

    function limpiarResultados() {
      $('#contenedor_resultados').hide();
      $('#mensaje_vacio').hide();
      $('#resultados_body').empty();
      $('#badge_total').text('0');
      $('#aviso_limpiar_fondo').hide();
      $('#panel_ticket').hide();
    }

    function ejecutarBusqueda() {
      var idFondos = $('#fondo_select').val() || 0;
      var busqueda = $('#input_busqueda').val().trim();

      // Al menos uno de los dos debe tener datos
      if (idFondos == 0 && busqueda === '') {
        Swal.fire('Atencion', 'Escriba un termino de busqueda o seleccione un fondo.', 'info');
        return;
      }

      $('#btnBuscar').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Buscando...');
      $('#contenedor_resultados').hide();
      $('#mensaje_vacio').hide();

      $.ajax({
        url: 'ajax/ajax_buscar_activos_nuevo_n.php',
        type: 'POST',
        data: { id_fondos: idFondos, busqueda: busqueda },
        dataType: 'json',
        success: function(response) {
          $('#btnBuscar').prop('disabled', false).html('<i class="bi bi-search me-1"></i> Buscar');
          $('#resultados_body').empty();

          if (response.success && response.resultados && response.resultados.length > 0) {
            $('#badge_total').text(response.total);

            response.resultados.forEach(function(row) {
              var clase  = row.clase || '-';
              var marca  = row.marca || '-';
              var imagen = row.imagen || 'default.png';

              var tr = '<tr>' +
                '<td class="text-center"><img src="img/' + escHtml(imagen) + '" class="result-img" alt=""></td>' +
                '<td>' + escHtml(clase) + '</td>' +
                '<td>' + escHtml(marca) + '</td>' +
                '<td><strong>' + escHtml(row.modelo) + '</strong></td>' +
                '<td>' +
                  '<button class="btn-seleccionar btn-seleccionar-modelo" ' +
                    'data-id_activo="' + row.id_activo + '" ' +
                    'data-id_modelo="' + row.id_modelo + '" ' +
                    'data-clase="' + escAttr(clase) + '" ' +
                    'data-marca="' + escAttr(marca) + '" ' +
                    'data-modelo="' + escAttr(row.modelo) + '" ' +
                    'data-imagen="' + escAttr(imagen) + '">' +
                    '<i class="bi bi-check-circle"></i> Seleccionar' +
                  '</button>' +
                '</td>' +
              '</tr>';
              $('#resultados_body').append(tr);
            });

            $('#contenedor_resultados').show(300);
            $('html, body').animate({ scrollTop: $('#contenedor_resultados').offset().top - 20 }, 500);

          } else {
            var msg = response.mensaje || 'No se encontraron modelos que coincidan con la busqueda.';
            $('#texto_vacio').text(msg);

            if (busqueda !== '' && idFondos != 0) {
              $('#aviso_limpiar_fondo').show();
              $('#panel_ticket').hide();
            } else if (busqueda !== '') {
              $('#panel_ticket').show();
              $('#aviso_limpiar_fondo').hide();
            } else {
              $('#panel_ticket').hide();
              $('#aviso_limpiar_fondo').hide();
            }

            $('#mensaje_vacio').show(300, function() {
              $('html, body').animate({ scrollTop: $('#mensaje_vacio').offset().top - 20 }, 400);
            });
          }
        },
        error: function() {
          $('#btnBuscar').prop('disabled', false).html('<i class="bi bi-search me-1"></i> Buscar');
          Swal.fire('Error', 'No se pudo realizar la busqueda. Intente de nuevo.', 'error');
        }
      });
    }

    // ════════════════════════════════════════════════════════════
    // SELECCIONAR MODELO -> FASE 2
    // ════════════════════════════════════════════════════════════
    $(document).on('click', '.btn-seleccionar-modelo', function() {
      var idActivo   = $(this).data('id_activo');
      var idModelo   = $(this).data('id_modelo');
      var clase      = $(this).data('clase');
      var marca      = $(this).data('marca');
      var modelo     = $(this).data('modelo');
      var imagen     = $(this).data('imagen');
      var fondoActual = $('#fondo_select').val();

      $('#detalle_imagen').attr('src', 'img/' + imagen);
      $('#detalle_clase').text(clase);
      $('#detalle_marca').text(marca);
      $('#detalle_modelo').text(modelo);
      $('#detalle_titulo').text(clase + ' ' + marca + ' ' + modelo);
      $('#hidden_id_activo').val(idActivo);
      $('#hidden_id_modelo').val(idModelo);

      // Auto-llenar fondo de registro
      if (fondoActual && fondoActual !== '') {
        $('#fondo_registro').val(fondoActual).trigger('change');
      } else {
        $('#fondo_registro').val('').trigger('change');
      }

      // Reset color palette
      $('.color-swatch').removeClass('selected');
      $('#colorHex').val('');
      $('#colorNombre').text('Ningun color seleccionado');

      $('#fase-busqueda').slideUp(300, function() {
        $('#fase-registro').slideDown(300, function() {
          $('html, body').animate({ scrollTop: 0 }, 300);
        });
      });
    });

    // ════════════════════════════════════════════════════════════
    // VOLVER A BUSQUEDA
    // ════════════════════════════════════════════════════════════
    $('#btnVolverBusqueda, #btnVolverBusqueda2').on('click', function() {
      $('#fase-registro').slideUp(300, function() {
        $('#fase-busqueda').slideDown(300);
        $('#formRegistro')[0].reset();
        $('#hidden_id_activo').val('');
        $('#hidden_id_modelo').val('');
        $('#fondo_registro').val('').trigger('change');
        $('.color-swatch').removeClass('selected');
        $('#colorHex').val('');
        $('#colorNombre').text('Ningun color seleccionado');
      });
    });

    // ════════════════════════════════════════════════════════════
    // GUARDAR REGISTRO
    // ════════════════════════════════════════════════════════════
    $('#formRegistro').on('submit', function(e) {
      e.preventDefault();

      var idActivo  = $('#hidden_id_activo').val();
      var modeloId  = $('#hidden_id_modelo').val();
      var colorHex  = $('#colorHex').val();
      var placa     = $('#input_placa').val().trim();
      var serial    = $('#input_serial').val().trim();
      var idFondos  = $('#fondo_registro').val();

      if (!idActivo || idActivo === '') {
        Swal.fire('Error', 'No se ha seleccionado un activo valido.', 'error');
        return;
      }
      if (!modeloId || modeloId === '') {
        Swal.fire('Error', 'No se ha seleccionado un modelo valido.', 'error');
        return;
      }
      if (!colorHex || colorHex === '') {
        Swal.fire('Campos requeridos', 'Debe seleccionar un color para el activo.', 'warning');
        return;
      }
      if (!placa || !serial || !idFondos) {
        Swal.fire('Campos requeridos', 'Debe completar placa, serial y origen presupuestario.', 'warning');
        return;
      }

      Swal.fire({
        title: 'Procesando...',
        html: 'Por favor espere mientras se registra el activo.',
        allowOutsideClick: false,
        didOpen: function() { Swal.showLoading(); }
      });

      $.ajax({
        url: 'ajax/ajax_registro_activo_n.php',
        type: 'POST',
        data: {
          id_activo: idActivo,
          modelo_id: modeloId,
          color_hex: colorHex,
          placa: placa,
          serial: serial,
          id_fondos: idFondos
        },
        dataType: 'json',
        success: function(response) {
          Swal.close();
          if (response.success) {
            Swal.fire({
              title: 'Registro Exitoso!',
              html: response.message +
                    '<br><small class="text-muted">Placa: <strong>' + escHtml(placa) +
                    '</strong> | Serial: <strong>' + escHtml(serial) +
                    '</strong> | Color: <strong>' + escHtml(colorNombre(colorHex)) + '</strong></small>',
              icon: 'success',
              confirmButtonText: 'Aceptar'
            }).then(function() {
              $('#formRegistro')[0].reset();
              $('#hidden_id_activo').val('');
              $('#hidden_id_modelo').val('');
              $('#fondo_registro').val('').trigger('change');
              $('.color-swatch').removeClass('selected');
              $('#colorHex').val('');
              $('#colorNombre').text('Ningun color seleccionado');
              $('#fase-registro').slideUp(300, function() {
                $('#fase-busqueda').slideDown(300);
              });
            });
          } else {
            var detalle = '';
            if (response.details) {
              if (response.details.placa_existente) {
                detalle = response.details.mensaje_departamento || 'La placa ya existe.';
              } else if (response.details.serial_existente) {
                detalle = response.details.mensaje_departamento || 'El serial ya existe.';
              } else {
                detalle = response.message;
              }
            } else {
              detalle = response.message;
            }
            Swal.fire('Error', detalle, 'error');
          }
        },
        error: function(xhr, status, error) {
          Swal.close();
          Swal.fire('Error', 'Ocurrio un error al procesar la solicitud: ' + error, 'error');
        }
      });
    });

    // ════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════
    function escHtml(str) {
      if (!str) return '';
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str));
      return div.innerHTML;
    }

    function escAttr(str) {
      if (!str) return '';
      return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function colorNombre(hex) {
      if (!hex) return '';
      for (var i = 0; i < COLORES.length; i++) {
        if (COLORES[i].hex.toUpperCase() === hex.toUpperCase()) return COLORES[i].nombre;
      }
      return hex;
    }

  });
  </script>
</body>
</html>
