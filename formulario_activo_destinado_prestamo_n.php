<?php  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*
$tienellave = ($_SESSION['tipo']==1 or $_SESSION['tipo']==2 or $_SESSION['tipo']==3);
if ($tienellave == false){
    echo '<script language = javascript>
    alert("No tienes permisos")
    self.location = "formulario_menu_inventario.html"
    </script>';
}*/

require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// === BLOQUEAR ACCESO DIRECTO ====
if (!defined('ACCESO_SEGURO')) {

    http_response_code(403);

    exit("Acceso directo no permitido");
}

// ==== CONSTRUIR RUTA DE REGRESO ===== Para el botón regresar
$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php'; // Ruta por defecto si no vienen parámetros
//Validar que vengan los parámetros necesarios para construir la ruta de regreso
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
    . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}
// ==== CONSTRUIR RUTA DE REGRESO PARA CONTACTENOS =====
$ruta_retorno_contactenos = 'navegar.php?ruta=' . basename(__FILE__);
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_retorno_contactenos .= '&subsistema_id=' . intval($_GET['subsistema_id'])
        . '&modulo_id=' . intval($_GET['modulo_id']);
}
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];*/
$logcodigo = $usuario_azure['codigoPresu'];
$activado = 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>TecnoPresta — Activos Destinados al Préstamo</title>
  <link rel="shortcut icon" href="ico/favicon.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <!-- MEP Institutional Styles -->
  <link rel="stylesheet" href="assets/css/nueva-identidad.css">
  <link rel="stylesheet" href="css/formulario_menu_principal.css?v=7" />
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <!-- Hero Header MEP -->
    <div class="hero-box mb-4 mt-2 fade-enter">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="d-flex align-items-center gap-3">
            <div class="hero-icon">
              <i class="bi bi-clipboard-check" style="font-size: 2rem;"></i>
            </div>
            <div>
              <h2 class="fw-bold mb-1">Activos Destinados al Préstamo</h2>
              <p class="mb-0 opacity-75">Gestión de disponibilidad para préstamo</p>
            </div>
          </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0 text-md-end">
          <!-- <a href="navegar.php?ruta=ayuda_n.php#adp" class="text-white text-decoration-none opacity-75 small me-3">
            <i class="bi bi-question-circle"></i> Ayuda
          </a> -->
          <a href="contactenos_n.php?rep=Error en Activos Destinados al Préstamo&ruta_regreso=<?= urlencode($ruta_retorno_contactenos) ?>" class="text-white text-decoration-none opacity-75 small">
            <i class="bi bi-envelope"></i> Reportar
          </a>
        </div>
      </div>
    </div>

    <!-- Buscador -->
    <div class="container mb-4">
      <div class="row">
        <div class="col-md-6 col-lg-4">
          <label for="FiltrarContenido" class="form-label fw-semibold" style="color: var(--mep-primary);">Buscar activos</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="FiltrarContenido" type="text" class="form-control" placeholder="Ingrese el tipo del activo">
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="container mb-4">
      <form action="actualizar_se_presta_n.php" method="post">
        <?php if (isset($_GET['subsistema_id'])): ?>
        <input type="hidden" name="subsistema_id" value="<?= intval($_GET['subsistema_id']) ?>">
        <?php endif; ?>
        <?php if (isset($_GET['modulo_id'])): ?>
        <input type="hidden" name="modulo_id" value="<?= intval($_GET['modulo_id']) ?>">
        <?php endif; ?>

        <div class="activos-wrapper">
          <table class="activos-table">
            <thead>
              <tr>
                <th class="th-check">
                  <input class="check-mep" type="checkbox" id="selectall">
                </th>
                <th class="th-activo">Activo</th>
                <th class="th-marca">Marca</th>
                <th class="th-modelo">Modelo</th>
                <th class="th-color">Color</th>
                <th class="th-placa">Placa</th>
                <th class="th-serial">Serial</th>
                <th class="th-prestamo" colspan="2">Disponible para préstamo</th>
              </tr>
            </thead>
            <tbody class="BusquedaRapida">
              <?php
              $consulta=mysqli_query($link,"SELECT Ta.id_activo, Tg.clase, Tm.marca, Ta.modelo, Tc.color, Tp.id_placa, Tp.placa, Tp.serial, Tp.codigo, Tp.prestar
                    FROM t_activo Ta
                    INNER JOIN t_marca Tm ON Ta.id_marca = Tm.id_marca 
                    INNER JOIN t_color Tc ON Ta.id_color = Tc.id_color
                    INNER JOIN t_placa Tp ON Ta.id_activo = Tp.id_activo
                    INNER JOIN t_activo_general Tg ON Ta.id_ag = Tg.id_ag 
                    WHERE Tp.codigo = '".$logcodigo."'
                    ORDER BY Tp.placa ASC") or die(mysqli_error($link));

              while ($activos=mysqli_fetch_array($consulta)) { 
                $prestar = $activos['prestar'];
              ?>
              <tr>
                <td class="td-check">
                  <input class="check-mep selectall" type="checkbox" name="idsplacas[]" value="<?php echo $activos['id_placa']?>">
                </td>
                <td class="td-activo"><?php echo $activos['clase']?></td>
                <td class="td-marca"><?php echo $activos['marca']?></td>
                <td class="td-modelo"><?php echo $activos['modelo']?></td>
                <td class="td-color"><?php echo $activos['color']?></td>
                <td class="td-placa"><span class="activos-placa"><?php echo $activos['placa']?></span></td>
                <td class="td-serial"><span class="activos-serial"><?php echo $activos['serial']?></span></td>
                <td class="td-prestamo" colspan="2">
                  <div class="d-flex gap-2 justify-content-center">
                    <label class="radio-label-mep">
                      <input class="radio-mep" type="radio" name="presta<?php echo $activos['id_placa']; ?>" <?php if($prestar=="1"){?> checked <?php } ?> value="1">
                      Sí
                    </label>
                    <label class="radio-label-mep">
                      <input class="radio-mep" type="radio" name="presta<?php echo $activos['id_placa']; ?>" <?php if($prestar=="0"){?> checked <?php } ?> value="0">
                      No
                    </label>
                  </div>
                </td>
              </tr>
              <?php } 
              mysqli_close($link);	
              ?>
            </tbody>
          </table>
        </div>

        <button type="submit" name="btnActualizar" class="btn-guardar-flotante" data-tooltip="Guardar Cambios">
          <i class="bi bi-floppy-fill"></i>
        </button>
      </form>
    </div>
    <!-- Botón flotante Volver al Dashboard -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>
    
    <!-- Modal Éxito -->
    <div class="modal fade" id="modalExito" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="prestamo-modal-header">
            <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Resultado</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body text-center py-4">
            <p class="fw-semibold" style="color: var(--mep-primary); font-size: 1.1rem;">¡Cambios realizados!</p>
            <p class="text-muted">Los cambios se guardaron correctamente.</p>
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

    <!-- Modal Error -->
    <div class="modal fade" id="modalError" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="prestamo-modal-header">
            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Atención</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body text-center py-4">
            <p class="fw-semibold" style="color: var(--mep-primary);" id="modalErrorTexto">Error al guardar los cambios.</p>
            <img src="img/mensaje.png" style="width: 5rem; margin-top: 10px;">
          </div>
          <div class="prestamo-modal-footer">
            <button type="button" class="btn btn-mep-primary" data-bs-dismiss="modal">Aceptar</button>
          </div>
        </div>
      </div>
    </div>

    <?php include 'partials/footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery para mejor manejo de eventos -->
    <script src="js/jquery-3.7.1.min.js"></script>
    
    <script>
      $(document).ready(function() {
        // Seleccionar/deseleccionar todos los checkboxes
        $('#selectall').on('click', function() {
          $(".selectall").prop('checked', this.checked);
        });

        // Búsqueda rápida
        $('#FiltrarContenido').keyup(function() {
          var ValorBusqueda = new RegExp($(this).val(), 'i');
          $('.BusquedaRapida tr').hide();
          $('.BusquedaRapida tr').filter(function() {
            return ValorBusqueda.test($(this).text());
          }).show();
        });

        // Mostrar modal Bootstrap 5
        function mostrarModal(id) {
          var el = document.getElementById(id);
          if (el) {
            var modal = new bootstrap.Modal(el);
            modal.show();
          }
        }

        // Enviar vía AJAX
        $('form').submit(function(e) {
          e.preventDefault();
          if($('input[name="idsplacas[]"]:checked').length === 0) {
            document.getElementById('modalErrorTexto').innerText = 'Por favor seleccione al menos un activo para actualizar.';
            mostrarModal('modalError');
            return false;
          }

          $.post('actualizar_se_presta_n.php', $(this).serialize(), function(resp) {
            if (resp.success) {
              mostrarModal('modalExito');
            } else {
              document.getElementById('modalErrorTexto').innerText = resp.error || 'Error al guardar los cambios.';
              mostrarModal('modalError');
            }
          }, 'json').fail(function() {
            document.getElementById('modalErrorTexto').innerText = 'Error de conexión con el servidor.';
            mostrarModal('modalError');
          });
        });
      });
    </script>

</body>
</html>