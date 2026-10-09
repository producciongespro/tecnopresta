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
$ruta_regreso ='navegar.php?ruta=formulario_menu_principal.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
    . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="manifest" href="manifest.json">

    <title>Estado de Activos por Origen Presupuestario</title>

    <!-- Bootstrap 5 -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <!-- MEP Institutional Styles -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=9" />

    <!-- Bootstrap JS -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

  </head>
  <body class="layout-page">

    <?php include 'partials/header.php'; ?>
    <main class="contenido-principal">

    <!-- Hero Header MEP -->
    <div class="container">
    <div class="hero-box mb-4 mt-2 fade-enter">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="d-flex align-items-center gap-3">
            <div class="hero-icon">
              <i class="bi bi-clipboard-data" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h2 class="fw-bold mb-1">Estado de Activos</h2>
              <p class="mb-0 opacity-75">Gestión por Origen Presupuestario</p>
            </div>
          </div>
        </div>
        <!-- <div class="col-md-4 mt-3 mt-md-0 text-md-end">
          <a href="ayuda.html#aan" class="text-white text-decoration-none opacity-75 small me-3">
            <i class="bi bi-question-circle"></i> Ayuda
          </a>
          <a href="contactenos.php?rep=Error en formulario prestamo html" class="text-white text-decoration-none opacity-75 small">
            <i class="bi bi-envelope"></i> Reportar
          </a>
        </div> -->
      </div>
    </div>
    </div>

    <!-- Mensajes dinámicos -->
    <div class="container-sm container-md container-lg mb-3">
      <div class="row justify-content-center">
        <div id="mensaje" class="col-12"></div>
      </div>
    </div>

    <!-- Contenedor Principal -->
    <div class="container mb-4">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
          <select class="form-select select-mep text-center"
                  id="cboFondos"
                  onchange="cargaDatosBd(this.value);">
            <option value="0">Seleccione el Origen Presupuestario</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Botón Guardar -->
    <div class="container mb-4">
      <div class="row justify-content-center">
        <div class="col-auto">
          <button id="btnGuardar"
                  onclick="guardar();"
                  type="button"
                  class="btn btn-mep-primary btn-lg px-5">
            <i class="bi bi-floppy me-2"></i>Guardar
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla de artículos -->
    <div class="container">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0" style="color: var(--mep-primary);">
          <i class="bi bi-box-seam me-2"></i>Seleccione los Artículos
        </h4>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="muestraEstadoDescripcion(); return true;">
          <i class="bi bi-info-circle me-1"></i>Significado de estados
        </button>
      </div>

      <!-- Wrapper responsive (scroll horizontal en móviles) -->
      <div class="activos-wrapper">

        <!-- Header de columnas -->
        <div class="activos-header">
          <div class="hdr-check">
            <input id="chkTodos" type="checkbox" class="check-mep" style="accent-color:#fff;"
                   onclick="checkAll(this);">
          </div>
          <div class="hdr-nombre">Artículo</div>
          <div class="hdr-placa">Placa</div>
          <div class="hdr-estado">
            <div class="hdr-estado-wrap">
              <span>Estado</span>
            </div>
          </div>
          <div class="hdr-uso">En uso</div>
          <div class="hdr-donar">Disponible para Donación</div>
        </div>

        <!-- Cuerpo scrollable -->
        <div class="activos-scroll" id="plantilla" style="height: 520px;">
          <!-- Filas generadas por JS -->
        </div>

      </div>
    </div>

    
      <!-- Botón flotante Volver -->
      <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
          style="bottom: 100px;" data-tooltip="Regresar">
          <i class="bi bi-arrow-left-circle-fill"></i>
      </a>

      <!-- Spans ocultos para compatibilidad con JS login() -->
      <span id="nombre" class="d-none"></span>
      <span id="codigo" class="d-none"></span>

      <!-- Modal: Guardar exitoso -->
      <div class="modal fade" id="modalMensajeGuardar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="prestamo-modal-header">
              <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Resultado</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="fw-semibold" style="color: var(--mep-primary);" id="tituloMensajeGuardar"></p>
              <p id="mensajeModalGuardar"></p>
              <p class="text-muted small" id="mensajeModalParrafoGuardar"></p>
              <img src="img/listo.png" style="width: 5rem; margin-top: 10px;">
            </div>
            <div class="prestamo-modal-footer">
              <button type="button" class="btn btn-mep-primary" data-bs-dismiss="modal" onclick="window.location.reload();">
                Aceptar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal: Error -->
      <div class="modal fade" id="modalMensaje" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="prestamo-modal-header">
              <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Atención</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="fw-semibold" style="color: var(--mep-primary);" id="tituloMensaje"></p>
              <p id="mensajeModal"></p>
              <p class="text-muted small" id="mensajeModalParrafo"></p>
              <img src="img/mensaje.png" style="width: 5rem; margin-top: 10px;">
            </div>
            <div class="prestamo-modal-footer">
              <button type="button" class="btn btn-mep-primary" data-bs-dismiss="modal">Aceptar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal: Significado estados -->
      <div class="modal fade" id="modalMensajeEstado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="prestamo-modal-header">
              <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Significado de Estados</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4 px-4">
              <div class="mb-3 p-3" style="background:#f4f7fb; border-radius:10px; border-left:4px solid #28a745;">
                <h6 class="fw-bold" style="color: var(--mep-primary);">Condición Muy Buena</h6>
                <p class="small mb-0 text-muted">Este artículo se encuentra en muy buena condición, sin señales de uso o desgaste visibles. Todos sus componentes funcionan perfectamente y ha sido mantenido con sumo cuidado, lo que garantiza su óptimo estado tanto en apariencia como en funcionamiento.</p>
              </div>
              <div class="mb-3 p-3" style="background:#f4f7fb; border-radius:10px; border-left:4px solid #17a2b8;">
                <h6 class="fw-bold" style="color: var(--mep-primary);">Condición Buena</h6>
                <p class="small mb-0 text-muted">Este artículo se encuentra en condición buena, con ligeros signos de uso que no afectan su funcionalidad. Presenta algunas marcas menores o desgaste superficial, pero sigue siendo completamente funcional y estéticamente agradable.</p>
              </div>
              <div class="mb-3 p-3" style="background:#f4f7fb; border-radius:10px; border-left:4px solid #ffc107;">
                <h6 class="fw-bold" style="color: var(--mep-primary);">Condición Regular</h6>
                <p class="small mb-0 text-muted">Este artículo se encuentra en condición regular, con evidentes signos de uso y desgaste. Algunas partes pueden mostrar deterioro, pero sigue siendo funcional en su mayor parte. Puede requerir mantenimiento o reparaciones menores para mejorar su rendimiento.</p>
              </div>
              <div class="p-3" style="background:#f4f7fb; border-radius:10px; border-left:4px solid #dc3545;">
                <h6 class="fw-bold" style="color: var(--mep-primary);">Condición Mala</h6>
                <p class="small mb-0 text-muted">Este artículo se encuentra en condición mala, con desgaste significativo o daños visibles que afectan tanto su apariencia como su funcionalidad. Es probable que necesite reparaciones considerables o que algunas de sus partes ya no funcionen correctamente.</p>
              </div>
            </div>
            <div class="prestamo-modal-footer">
              <button type="button" class="btn btn-mep-primary" data-bs-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      </div>

    <!-- jQuery -->
    <script src="js/jquery-3.5.1.min.js"></script>

    <!-- js del formulario -->
    <script src="js/formulario_inventario_por_fuente_financiamiento.js?v=4"></script>

  </main>
  <?php include 'partials/footer.php'; ?>
  </body>
</html>
