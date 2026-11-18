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
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=20260924_2"/>

    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <title>Devolución</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">

    <style>
      input[type=checkbox] {
        transform: scale(1.5);
      }
    </style>
  </head>
  <body class="layout-page">

    <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">

    <div class="container">
      <br>
      <div class="solicitud-titulo-seccion">
        <h3><i class="bi bi-arrow-return-left"></i> Recibir Equipo</h3>
        <a href="navegar.php?ruta=contactenos_n.php&rep=Error en formulario devolucion" class="reporte-link">
          <i class="bi bi-exclamation-triangle"></i> Reportar Incidencia / Error
        </a>
      </div>
      <hr class="my-3">
    </div>

    <div class="container-sm container-md container-lg">
      <div class="row justify-content-center">
        <div id="mensaje" class="form-group row"></div>
      </div>
    </div>

    <!-- Contenedor Principal -->
    <div class="container-sm container-md container-lg px-3" id="contenedor">

      <div class="card prestamo-form-card">
        <div class="card-header prestamo-form-header">
          <i class="bi bi-info-circle"></i> Información del Préstamo
        </div>
        <div class="card-body prestamo-form-body">

          <div class="form-group row mb-3">
            <label for="fechaRetiro" class="col-sm-3 col-form-label form-label">Fecha y hora Retiro del equipo:</label>
            <div class="col-5 col-sm-4 col-md-3 col-lg-3 col-xl-2">
                <input class="form-control" id="fechaRetiro" readonly>
            </div>
          </div>

          <div class="form-group row mb-3">
            <label for="fechaDevolución" class="col-sm-3 col-form-label form-label">Fecha y hora Devolución del equipo:</label>
            <div class="col-5 col-sm-4 col-md-3 col-lg-3 col-xl-2">
                <input class="form-control" id="fechaDevolución" readonly>
            </div>
          </div>

          <div class="form-group row mb-3">
            <label for="txtUso" class="col-sm-3 col-form-label form-label">Uso para el que se prestó el equipo:</label>
            <div class="col-sm-8">
              <textarea class="form-control" rows="3" cols="38"
                  placeholder="Describa el uso que se le dará al equipo solicitado"
                  maxlength="255" id="txtUso" readonly></textarea>
            </div>
          </div>

          <div class="form-group row mb-0">
            <div class="col-sm-3 col-form-label form-label">
              <div class="form-check form-switch">
                  <input type="checkbox"
                          class="form-check-input"
                          id="chkIncidencia"
                  >
                  <label class="form-check-label"
                          for="chkIncidencia"
                          id="lblIncidencia"
                      >Registrar Incidencia
                  </label>
              </div>
            </div>
            <div class="col-sm-8">
              <textarea class="form-control" rows="3" cols="38"
                  placeholder="Registrar incidencia..."
                  maxlength="255" id="txtIncidencia"
                  readonly></textarea>
            </div>
          </div>

        </div>
      </div>

    </div>

    <!-- Items Section -->
    <div class="container-sm container-md container-lg">

      <div class="card prestamo-items-card">
        <div class="card-header prestamo-items-header">
          <i class="bi bi-pc-display"></i> Seleccione los artículos que va a devolver
        </div>
        <div class="card-body prestamo-items-body">

          <div class="prestamo-items-header-row">
            <div class="col-1" id="check">
              <div class="form-check prestamo-check-all">
                <input id="chkTodos"
                      class="form-check-input"
                      type="checkbox"
                      onclick="checkAll(this);">
              </div>
            </div>
            <div class="col-4" id="lblnombre">Artículo</div>
            <div class="col" id="marca">Modelo</div>
            <div class="col" id="placa">Placa</div>
            <div class="col" id="numero_activo">Etiqueta</div>
          </div>

        </div>
      </div>

    </div>

    <div class="container-sm container-md container-lg px-3" id="plantilla">
      <div class="form-group row" id="item">
      </div>
    </div>

    <div class="container-sm container-md container-lg prestamo-actions">
      <button id="btnGuardar" onclick="guardar();" type="button"
              class="prestamo-btn-primary">
              <i class="bi bi-check-lg"></i> Registrar devolución de equipo
      </button>
    </div>

    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
        style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <!-- Modal: Mensaje -->
    <div class="modal fade" id="modalMensaje" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="prestamo-modal-header">
            <h5 class="modal-title"><i class="bi bi-info-circle"></i> Información</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body prestamo-modal-body">
              <div class="alert">
                  <h1 id="tituloMensaje"></h1>
                  <p id="mensajeModal"></p>
              </div>
              <p id="mensajeModalParrafo"></p>
              <div class="d-flex justify-content-center mt-3">
                <img src="img/mensaje.png" style="width: 80px;">
              </div>
          </div>
          <div class="modal-footer prestamo-modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Guardar -->
    <div class="modal fade" id="modalMensajeGuardar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabelTipo" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="prestamo-modal-header">
            <h5 class="modal-title"><i class="bi bi-check-circle"></i> Devolución Registrada</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body prestamo-modal-body">
              <div class="alert">
                  <h1 id="tituloMensajeGuardar"></h1>
                  <p id="mensajeModalGuardar"></p>
              </div>
              <p id="mensajeModalParrafoGuardar"></p>
              <div class="d-flex justify-content-center mt-3">
                <img src="img/listo.png" style="width: 80px;">
              </div>
          </div>
          <div class="modal-footer prestamo-modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="salir();">Aceptar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Optional JavaScript -->
    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

    <script src="js/formulario_devolucion_n.js?version=1.2"></script>

  </main>
  <?php include 'partials/footer.php'; ?>
  </body>
</html>
