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

   <!-- Bootstrap 5 CSS -->
  <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->

  <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
  <link rel="stylesheet" href="css/formulario_menu_principal.css" />
  <link rel="stylesheet" href="assets/css/nueva-identidad.css"/>
  <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Select2 CSS -->
  <link href="select2/select2.min.css" rel="stylesheet" />
  <!-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"> -->

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

    .info-item strong {
      color: var(--mep-blue);
      font-size: 0.82rem;
    }

    .info-item span {
      font-size: 0.88rem;
      color: #2c3e50;
    }

    .search-results-wrap {
      max-height: 420px;
      overflow-y: auto;
      border-radius: 0 0 10px 10px;
    }

    .search-results-wrap::-webkit-scrollbar { width: 6px; }
    .search-results-wrap::-webkit-scrollbar-track { background: #f1f3f5; border-radius: 3px; }
    .search-results-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

    .result-img {
      width: 42px;
      height: 42px;
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

    .btn-volver:hover {
      background: rgba(255,255,255,0.15);
      color: #fff;
    }

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

    .form-label {
      font-weight: 600;
      color: #1f3b57;
      font-size: 0.85rem;
      margin-bottom: 4px;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #8a9aa8;
    }

    .empty-state .bi { font-size: 2.5rem; opacity: 0.4; }

    .select2-container--default .select2-selection--single {
      height: 38px;
      padding-top: 5px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }

    .select2-container--default .select2-selection--single:focus {
      border-color: var(--mep-blue);
      box-shadow: 0 0 0 3px rgba(0, 56, 118, 0.1);
    }

    @media (max-width: 768px) {
      .asset-detail-card .row.g-0 { flex-direction: column; }
      .asset-detail-card .col-md-4 { text-align: center; margin-bottom: 1rem; }
    }
  </style>
</head>
<body class="d-flex flex-column min-vh-100">
  <?php include 'partials/header.php'; ?>
  <main class="flex-grow-1">
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
       style="bottom: 100px;" title="Volver a Módulos del Sistema">
      <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <div class="container mt-4">

      <!-- ════════════════════════════════════════════════════════
           FASE 1: BÚSQUEDA / SELECCIÓN
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
                  <li class="mb-1">Opcionalmente seleccione un <strong>fondo presupuestario</strong> para filtrar.</li>
                  <li class="mb-1">Escriba un texto de búsqueda (clase, marca o modelo) y haga clic en <strong>Buscar</strong>.</li>
                  <li class="mb-1">De los resultados, haga clic en <strong>Seleccionar</strong> sobre el activo deseado.</li>
                  <li>Complete la placa, serial y confirme el origen presupuestario.</li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <!-- Card de búsqueda -->
        <div class="card">
          <div class="card-header" style="background:linear-gradient(135deg,var(--mep-blue),var(--mep-blue2));color:#fff;">
            <h5 class="mb-0"><i class="bi bi-search me-2"></i>Buscar Activo Existente</h5>
          </div>
          <div class="card-body">

            <!-- Fondo presupuestario -->
            <div class="row g-3 mb-3">
              <div class="col-md-5">
                <label class="form-label">Fondo Presupuestario <small class="text-muted">(opcional)</small></label>
                <select id="fondo_select" class="form-select" style="width:100%;">
                  <option value="">Todos los fondos</option>
                  <?php foreach ($fondos as $f): ?>
                    <option value="<?= (int)$f['id_fondos'] ?>"><?= htmlspecialchars($f['fondos']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label">Buscar por clase, marca o modelo</label>
                <input type="text" id="input_busqueda" class="form-control"
                       placeholder="Ej: Computadora, Dell, OptiPlex...">
              </div>
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
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Resultados de Búsqueda</h5>
            <span class="badge bg-light text-primary" id="badge_total">0</span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive search-results-wrap">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:60px;"></th>
                    <th>Clase</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Color</th>
                    <th style="width:130px;">Acción</th>
                  </tr>
                </thead>
                <tbody id="resultados_body"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Mensaje vacío -->
        <div id="mensaje_vacio" class="card mt-4" style="display:none;">
          <div class="card-body">
            <div class="empty-state">
              <i class="bi bi-search d-block mb-2"></i>
              <p class="mb-1 fw-bold" style="color:#1f3b57;">No se encontraron resultados</p>
              <p class="mb-0" style="font-size:0.88rem;" id="texto_vacio">
                No se encontraron activos que coincidan con la búsqueda.
              </p>
              <button type="button" id="btnMostrarTodos" class="btn btn-outline-primary mt-3" style="display:none;">
                <i class="bi bi-eye me-1"></i> Mostrar todos los activos
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════
           FASE 2: REGISTRO DE PLACA / SERIAL
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
              <i class="bi bi-arrow-left"></i> Volver a búsqueda
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
                      <strong><i class="bi bi-tag me-1 text-primary"></i> Clase:</strong>
                      <span id="detalle_clase"></span>
                    </div>
                    <div class="info-item">
                      <strong><i class="bi bi-building me-1 text-primary"></i> Marca:</strong>
                      <span id="detalle_marca"></span>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="info-item">
                      <strong><i class="bi bi-upc-scan me-1 text-primary"></i> Modelo:</strong>
                      <span id="detalle_modelo"></span>
                    </div>
                    <div class="info-item">
                      <strong><i class="bi bi-palette me-1 text-primary"></i> Color:</strong>
                      <span id="detalle_color"></span>
                    </div>
                  </div>
                </div>
                <div class="alert alert-light mt-3 d-flex align-items-center mb-0">
                  <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
                  <small class="text-muted">Verifique la información antes de registrar placa y serial.</small>
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

              <div class="row g-3">

                <!-- Placa -->
                <div class="col-md-6">
                  <label for="input_placa" class="form-label">Placa</label>
                  <input type="text" class="form-control" id="input_placa" name="placa"
                         placeholder="Ingrese la placa del activo" required maxlength="50"
                         style="text-transform:uppercase;">
                </div>

                <!-- Serial -->
                <div class="col-md-6">
                  <label for="input_serial" class="form-label">Serial</label>
                  <input type="text" class="form-control" id="input_serial" name="serial"
                         placeholder="Ingrese el serial del activo" required maxlength="50"
                         style="text-transform:uppercase;">
                </div>

                <!-- Origen Presupuestario -->
                <div class="col-md-12">
                  <label for="fondo_registro" class="form-label">Origen Presupuestario</label>
                  <select class="form-select" id="fondo_registro" name="id_fondos" required style="width:100%;">
                    <option value="">Seleccione el origen presupuestario</option>
                    <?php foreach ($fondos as $f): ?>
                      <option value="<?= (int)$f['id_fondos'] ?>"><?= htmlspecialchars($f['fondos']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Botones -->
                <div class="col-12 mt-4">
                  <div class="d-flex justify-content-center gap-3">
                    <button type="button" id="btnVolverBusqueda2" class="btn btn-outline-secondary px-4">
                      <i class="bi bi-arrow-left me-1"></i> Volver
                    </button>
                    <button type="submit" class="btn btn-primary px-5" id="btnGuardar">
                      <i class="bi bi-save me-1"></i> Guardar Registro
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

  <!-- jQuery -->
  <script src="js/jquery-3.7.1.min.js"></script>
  <!-- Bootstrap 5 JS -->
  <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
  <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
  
  <!-- Select2 JS -->
  <script src="select2/select2.min.js"></script>
  <!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> -->

  <!-- SweetAlert2 JS -->
  <script src="sweetalert2/sweetalert2.all.min.js"></script>
  <!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script> -->

  <script>
  $(document).ready(function() {

    // ── Select2 ──
    $('#fondo_select').select2({
      placeholder: "Todos los fondos",
      allowClear: true,
      width: '100%',
      language: { noResults: function(){ return "No se encontraron resultados"; } }
    });

    $('#fondo_registro').select2({
      placeholder: "Seleccione el origen presupuestario",
      allowClear: true,
      width: '100%',
      language: { noResults: function(){ return "No se encontraron resultados"; } }
    });

    // ── Auto-buscar al cambiar fondo ──
    $('#fondo_select').on('change', function() {
      var valor = $(this).val();
      console.log('[FONDO] Cambio detectado, valor:', valor);
      if (valor) {
        ejecutarBusqueda();
      }
    });

    // ── Buscar ──
    $('#btnBuscar').on('click', function() {
      ejecutarBusqueda();
    });

    $('#input_busqueda').on('keypress', function(e) {
      if (e.which === 13) {
        e.preventDefault();
        ejecutarBusqueda();
      }
    });

    // ── Mostrar todos (fallback cuando fondo no tiene resultados) ──
    $(document).on('click', '#btnMostrarTodos', function() {
      console.log('[FALLBACK] Mostrar todos clicked');
      $('#fondo_select').val(null).trigger('change');
      $('#input_busqueda').val('');
      ejecutarBusqueda();
    });

    function ejecutarBusqueda() {
      var idFondos = $('#fondo_select').val();
      var busqueda = $('#input_busqueda').val().trim();

      console.log('[BUSQUEDA] idFondos:', idFondos, '| busqueda:', busqueda);

      if (!idFondos && busqueda === '') {
        console.log('[BUSQUEDA] Sin datos, mostrando alerta');
        Swal.fire('Atención', 'Seleccione un fondo o escriba un texto de búsqueda.', 'info');
        return;
      }

      var fondoParaEnviar = (idFondos && idFondos !== '') ? idFondos : 0;
      console.log('[BUSQUEDA] Enviando fondo:', fondoParaEnviar, 'texto:', busqueda);

      $('#btnBuscar').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Buscando...');
      $('#contenedor_resultados').hide();
      $('#mensaje_vacio').hide();
      $('#btnMostrarTodos').hide();

      $.ajax({
        url: 'ajax/ajax_buscar_activos_nuevo_n.php',
        type: 'POST',
        data: { id_fondos: fondoParaEnviar, busqueda: busqueda },
        dataType: 'json',
        success: function(response) {
          console.log('[AJAX] Respuesta:', response);
          $('#btnBuscar').prop('disabled', false).html('<i class="bi bi-search me-1"></i> Buscar');
          $('#resultados_body').empty();

          if (response.success && response.resultados && response.resultados.length > 0) {
            $('#badge_total').text(response.total);

            response.resultados.forEach(function(row) {
              var tr = '<tr>' +
                '<td class="text-center"><img src="img/' + escHtml(row.imagen) + '" class="result-img" alt=""></td>' +
                '<td>' + escHtml(row.clase) + '</td>' +
                '<td>' + escHtml(row.marca) + '</td>' +
                '<td>' + escHtml(row.modelo) + '</td>' +
                '<td>' + escHtml(row.color) + '</td>' +
                '<td>' +
                  '<button class="btn-seleccionar btn-seleccionar-activo" ' +
                    'data-id="' + row.id_activo + '" ' +
                    'data-clase="' + escAttr(row.clase) + '" ' +
                    'data-marca="' + escAttr(row.marca) + '" ' +
                    'data-modelo="' + escAttr(row.modelo) + '" ' +
                    'data-color="' + escAttr(row.color) + '" ' +
                    'data-imagen="' + escAttr(row.imagen) + '">' +
                    '<i class="bi bi-check-circle"></i> Seleccionar' +
                  '</button>' +
                '</td>' +
              '</tr>';
              $('#resultados_body').append(tr);
            });

            $('#contenedor_resultados').show(300);

            $('html, body').animate({
              scrollTop: $('#contenedor_resultados').offset().top - 20
            }, 500);

          } else {
            console.log('[AJAX] Sin resultados');
            var fondoSeleccionado = $('#fondo_select').val();
            var texto = 'No se encontraron activos que coincidan con la búsqueda.';
            if (fondoSeleccionado && fondoSeleccionado !== '') {
              texto = 'No se encontraron activos vinculados a este fondo presupuestario. ' +
                      'Esto puede deberse a que los activos aún no tienen asignado su modelo en el catálogo maestro.';
              $('#btnMostrarTodos').show();
            }
            $('#texto_vacio').text(texto);
            $('#mensaje_vacio').show(300, function() {
              $('html, body').animate({
                scrollTop: $('#mensaje_vacio').offset().top - 20
              }, 400);
            });
          }
        },
        error: function(xhr, status, error) {
          console.error('[AJAX] Error:', status, error, xhr.responseText);
          $('#btnBuscar').prop('disabled', false).html('<i class="bi bi-search me-1"></i> Buscar');
          Swal.fire('Error', 'No se pudo realizar la búsqueda. Intente de nuevo.', 'error');
        }
      });
    }

    // ── Seleccionar activo ──
    $(document).on('click', '.btn-seleccionar-activo', function() {
      var idActivo = $(this).data('id');
      var clase    = $(this).data('clase');
      var marca    = $(this).data('marca');
      var modelo   = $(this).data('modelo');
      var color    = $(this).data('color');
      var imagen   = $(this).data('imagen');
      var fondoActual = $('#fondo_select').val();

      $('#detalle_imagen').attr('src', 'img/' + imagen);
      $('#detalle_clase').text(clase);
      $('#detalle_marca').text(marca);
      $('#detalle_modelo').text(modelo);
      $('#detalle_color').text(color);
      $('#detalle_titulo').text(clase + ' ' + marca + ' ' + modelo);
      $('#hidden_id_activo').val(idActivo);

      if (fondoActual && fondoActual !== '') {
        $('#fondo_registro').val(fondoActual).trigger('change');
      } else {
        $('#fondo_registro').val('').trigger('change');
      }

      $('#fase-busqueda').slideUp(300, function() {
        $('#fase-registro').slideDown(300, function() {
          $('html, body').animate({ scrollTop: 0 }, 300);
        });
      });
    });

    // ── Volver a búsqueda ──
    $('#btnVolverBusqueda, #btnVolverBusqueda2').on('click', function() {
      $('#fase-registro').slideUp(300, function() {
        $('#fase-busqueda').slideDown(300);
        $('#formRegistro')[0].reset();
        $('#fondo_registro').val('').trigger('change');
      });
    });

    // ── Guardar registro ──
    $('#formRegistro').on('submit', function(e) {
      e.preventDefault();

      var placa    = $('#input_placa').val().trim();
      var serial   = $('#input_serial').val().trim();
      var idFondos = $('#fondo_registro').val();
      var idActivo = $('#hidden_id_activo').val();

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
        url: 'guardar_placa_n.php',
        type: 'POST',
        data: {
          placa: placa,
          serial: serial,
          id_activo: idActivo,
          id_fondos: idFondos
        },
        dataType: 'json',
        success: function(response) {
          Swal.close();
          if (response.success) {
            Swal.fire({
              title: '¡Registro Exitoso!',
              html: response.message +
                    '<br><small class="text-muted">Placa: <strong>' + escHtml(placa) +
                    '</strong> | Serial: <strong>' + escHtml(serial) + '</strong></small>',
              icon: 'success',
              confirmButtonText: 'Aceptar'
            }).then(function() {
              $('#formRegistro')[0].reset();
              $('#fondo_registro').val('').trigger('change');
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
          Swal.fire('Error', 'Ocurrió un error al procesar la solicitud: ' + error, 'error');
        }
      });
    });

    // ── Helpers ──
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

  });
  </script>
</body>
</html>
