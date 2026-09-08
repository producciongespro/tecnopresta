<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*
$tienellave = ($_SESSION['tipo']==1 or $_SESSION['tipo']==2);
if ($tienellave == false){
echo '<script language = javascript>
alert("No tienes permisos")
self.location = "formulario_menu_inventario.html"
</script>';
}
*/
require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
  echo "Error de conexion a mysql: " . mysqli_connect_error();
}

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {

}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}
$cedula_actual = $usuario_azure['cedula'] ?? '';

// ==== CONSTRUIR RUTA DE REGRESO =====
$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';

if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $_SESSION['subsistema_id'] = intval($_GET['subsistema_id']);
    $_SESSION['modulo_id']    = intval($_GET['modulo_id']);
}

if (isset($_SESSION['subsistema_id'], $_SESSION['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . $_SESSION['subsistema_id']
    . '&modulo_id=' . $_SESSION['modulo_id'];
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}

$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logcodigo = $_SESSION['codigo'];
*/
$logcodigo = $usuario_azure['codigoPresu'] ?? '';

// Lugares con activos del centro (para los ámbitos del prestador)
$lugares_ambito = array();
$resLugares = $link->query("SELECT DISTINCT l.id_lugar, l.lugar
    FROM t_lugar l
    INNER JOIN t_placa p ON p.id_lugar = l.id_lugar
    WHERE p.codigo = '$logcodigo' AND p.activo = 1 AND l.activo = 1
      AND l.id_lugar <> 15
    ORDER BY l.id_lugar");
if ($resLugares) {
    while ($filaL = $resLugares->fetch_assoc()) {
        $lugares_ambito[] = $filaL;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>TecnoPresta Roles</title>
  <link rel="shortcut icon" href="ico/favicon.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- ICONOS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Bootstrap 5 CSS -->
  <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
  <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
  <link rel="stylesheet" href="assets/css/nueva-identidad.css">
  <link rel="stylesheet" href="css/formulario_menu_principal.css" />
  <link rel="stylesheet" href="fondoresponsive.css">
  <!-- jQuery -->
  <script src="js/jquery-3.7.1.min.js"></script>
  <script src="/css/popper.min.js"></script>
  <script src="/css/bootstrap.min.js"></script>
  <link rel="stylesheet" type="text/css" href="/css/style.css">
  <script src="css/jquery-ui.js"></script>
  <link rel="stylesheet" href="css/jquery-ui.css" /> 
  <script type="text/javascript" src="js/jquery.min.js"></script>
  <!-- SweetAlert2 -->
  <link href="sweetalert2/sweetalert2.min.css" rel="stylesheet">
  <script src="sweetalert2/sweetalert2.all.min.js"></script>
  <link rel="stylesheet" type="text/css" href="/css_reportes/ico_reportes.css">
  <style>
    .form-card {
      border: none;
      border-top: 4px solid var(--mep-accent, #CFAC65);
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.08);
      background: #fff;
      will-change: auto;
    }

    .role-hint {
      font-size: 0.82rem;
      color: #6c757d;
      margin-bottom: 14px;
    }

    .role-options {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }

    .role-card {
      flex: 1;
      min-width: 180px;
      margin: 0;
    }

    .role-card .form-check-input {
      display: none;
    }

    .role-card .form-check-label {
      display: block;
      padding: 18px 16px 14px;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-align: center;
      position: relative;
    }

    .role-card .form-check-label:hover {
      border-color: var(--mep-accent, #CFAC65);
      background: rgba(207, 172, 101, 0.04);
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }

    .role-card .form-check-input:checked + .form-check-label {
      border-color: var(--mep-accent, #CFAC65);
      background: rgba(207, 172, 101, 0.07);
      box-shadow: 0 0 0 3px rgba(207, 172, 101, 0.2);
    }

    .role-radio-indicator {
      display: inline-block;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 2px solid #adb5bd;
      position: relative;
      margin-bottom: 8px;
      transition: all 0.3s ease;
    }

    .role-card .form-check-label:hover .role-radio-indicator {
      border-color: var(--mep-accent, #CFAC65);
    }

    .role-card .form-check-input:checked + .form-check-label .role-radio-indicator {
      border-color: var(--mep-accent, #CFAC65);
      background: rgba(207, 172, 101, 0.1);
    }

    .role-radio-indicator::after {
      content: '';
      display: block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--mep-accent, #CFAC65);
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0);
      transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .role-card .form-check-input:checked + .form-check-label .role-radio-indicator::after {
      transform: translate(-50%, -50%) scale(1);
    }

    .role-icon {
      display: block;
      font-size: 2rem;
      color: var(--mep-primary, #192952);
      margin-bottom: 6px;
      transition: color 0.3s ease;
    }

    .role-card .form-check-input:checked + .form-check-label .role-icon {
      color: var(--mep-accent, #CFAC65);
    }

    .role-card .form-check-label .role-name {
      display: block;
      font-weight: 600;
      color: var(--mep-primary, #192952);
      margin-bottom: 4px;
      font-size: 0.95rem;
    }

    .role-card .form-check-label .role-desc {
      display: block;
      font-size: 0.78rem;
      color: #6c757d;
      line-height: 1.3;
    }

    .form-section-title {
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--mep-primary, #192952);
      margin-bottom: 12px;
      padding-bottom: 6px;
      border-bottom: 2px solid var(--mep-accent, #CFAC65);
    }

    .btn-mep-primary {
      background: var(--mep-primary, #192952);
      border-color: var(--mep-primary, #192952);
      color: #fff;
      padding: 12px 32px;
      font-weight: 600;
      letter-spacing: 0.5px;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .btn-mep-primary:hover {
      background: var(--mep-secondary, #0035A0);
      border-color: var(--mep-secondary, #0035A0);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(0, 53, 160, 0.3);
    }

    .table-mep thead {
      background: var(--mep-primary, #192952);
      color: #fff;
    }

    .table-mep thead th {
      font-weight: 600;
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      border: none;
      padding: 12px 10px;
    }

    .table-mep tbody tr {
      transition: background 0.2s ease;
    }

    .table-mep tbody tr:hover {
      background: rgba(25, 41, 82, 0.04);
    }

    .filter-box {
      background: rgba(255,255,255,0.8);
      border-radius: 10px;
      padding: 10px 16px;
      backdrop-filter: blur(8px);
      border: 1px solid rgba(0,0,0,0.04);
    }

    .invalid-feedback-custom {
      display: none;
      color: #dc3545;
      font-size: 0.85rem;
      margin-top: 6px;
    }

    .role-options.is-invalid + .invalid-feedback-custom {
      display: block;
    }

    .readonly-field {
      background-color: #f1f3f5 !important;
      cursor: not-allowed;
      user-select: none;
    }
  </style>
<script type="text/javascript">
$(document).ready(function () {
   (function($) {
       $('#FiltrarContenido').keyup(function () {
            var ValorBusqueda = new RegExp($(this).val(), 'i');
            $('.BusquedaRapida tr').hide();
             $('.BusquedaRapida tr').filter(function () {
                return ValorBusqueda.test($(this).text());
              }).show();
                })
      }(jQuery));
});
</script>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <div class="container py-4">
      <!-- Encabezado -->
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <div>
          <h4 class="mb-1 fw-bold" style="color: var(--mep-primary);">
            Agregar Usuarios con Permisos
          </h4>
          <!-- <small class="text-muted">
            <a href="ayuda.html#mg" class="text-decoration-none me-2"><span class="icon icon-lifebuoy"></span> Ayuda</a>
            <a href="contactenos.php?rep=Error en Agregar Modelo de Activo" class="text-decoration-none"><span class="icon icon-envelop"></span> Reportar Incidencia / Error</a>
          </small> -->
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-5">
          <!-- FORMULARIO -->
          <div class="card form-card">
            <div class="card-body p-4">
              <form name="frmrol" id="frmrol" action="guardar_rol_del_usuario_n.php" method="post" onsubmit="validarFormulario(event)" novalidate>
                
                <!-- Nivel de Usuario -->
                <div class="mb-4">
                  <div class="form-section-title">Nivel de Usuario</div>
                  <div class="role-hint"><i class="bi bi-hand-index-thumb me-1"></i> Seleccione un nivel haciendo clic en una de las opciones</div>
                  <input type="hidden" id="rol" name="rol" value="">
                  <div class="role-options" id="roleOptions">
                    <?php  
                    $regres1 = mysqli_query($link, "SELECT * FROM t_roles WHERE id_rol IN (3,4) ORDER BY id_rol") or
                          die(mysqli_error($link));
                    while ($regr1 = mysqli_fetch_array($regres1)) {
                      $icono_rol = ($regr1['id_rol'] == 3) ? 'bi bi-hand-index-thumb' : 'bi bi-clipboard-data';
                    ?>
                    <div class="role-card">
                      <input class="form-check-input" type="radio" name="rol_radio" id="rol_<?php echo $regr1['id_rol']; ?>" value="<?php echo $regr1['id_rol']; ?>">
                      <label class="form-check-label" for="rol_<?php echo $regr1['id_rol']; ?>">
                        <span class="role-radio-indicator"></span>
                        <i class="<?php echo $icono_rol; ?> role-icon"></i>
                        <span class="role-name"><?php echo htmlspecialchars($regr1['rol']); ?></span>
                        <span class="role-desc"><?php echo htmlspecialchars($regr1['descripcion']); ?></span>
                      </label>
                    </div>
                    <?php } ?>       
                  </div>
                  <div class="invalid-feedback-custom" id="rolError">Seleccione un nivel de usuario.</div>
                </div>

                <!-- Cédula -->
                <div class="mb-3">
                  <label class="form-label fw-semibold">Cédula</label>
                  <input type="text" name="cedula" id="cedula" class="form-control" placeholder="Ej: 0109670579" required>
                  <div class="invalid-feedback-custom" id="cedulaError">Este campo es requerido.</div>
                </div>

                <!-- Correo MEP -->
                <div class="mb-3">
                  <label class="form-label fw-semibold">Correo MEP</label>
                  <div class="input-group">
                    <input type="text" name="nombre" id="nombre" class="form-control" placeholder="nombre.apellido" required>
                    <span class="input-group-text bg-light text-muted fw-medium">@mep.go.cr</span>
                  </div>
                  <div class="invalid-feedback-custom" id="emailError">No incluya @ ni el dominio. Digite solo la parte antes de @mep.go.cr</div>
                  <small class="text-muted">Digite solo la parte antes de @mep.go.cr</small>
                </div>

                <!-- Código Presupuestario -->
                <div class="mb-4">
                  <label class="form-label fw-semibold">Código Presupuestario</label>
                  <div class="input-group">
                    <input type="text" name="codigop" id="codigop" class="form-control readonly-field" placeholder="Código presupuestario" value="<?php echo $logcodigo;?>" readonly required>
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock-fill"></i></span>
                  </div>
                  <div class="invalid-feedback-custom" id="codigoError">Este campo es requerido.</div>
                  <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Código de su institución — asignado automáticamente</small>
                </div>

                <!-- Ámbitos de Préstamo (solo rol Prestador) -->
                <div class="mb-4" id="ambitoSection" style="display:none;">
                  <div class="form-section-title"><i class="bi bi-diagram-3 me-1"></i> Ámbitos de Préstamo</div>
                  <div class="role-hint"><i class="bi bi-info-circle me-1"></i> S&oacute;lo aplica para el rol Prestador. Defina las categor&iacute;as (&aacute;mbitos) de lugares cuyos art&iacute;culos este funcionario podr&aacute; prestar.</div>
                  <div id="ambitosContainer"></div>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btnAgregarAmbito">
                    <i class="bi bi-plus-circle me-1"></i> Agregar &aacute;mbito
                  </button>
                  <div class="invalid-feedback-custom" id="ambitoError">Debe agregar al menos un &aacute;mbito con nombre y un lugar seleccionado.</div>
                  <input type="hidden" name="ambitos_json" id="ambitos_json" value="">
                </div>

                <input type="hidden" name="edit_id" id="edit_id" value="">

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-mep-primary flex-fill" id="btnGuardar">
                    <i class="bi bi-floppy"></i> Guardar
                  </button>
                  <a href="#" class="btn btn-outline-secondary" id="btnCancelar" style="display:none;" onclick="resetFormulario(); return false;">
                    Cancelar
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <!-- FILTRO BÚSQUEDA -->
          <div class="filter-box mb-3 d-flex align-items-center gap-2">
            <span class="fw-semibold text-muted small text-nowrap">Buscar</span>
            <input id="FiltrarContenido" type="text" class="form-control form-control-sm" placeholder="Filtrar usuarios en la tabla...">
          </div>

          <!-- TABLA -->
          <div class="table-responsive">
            <table class="table table-hover table-mep align-middle mb-0">
              <thead>
                <tr>
                  <th>C&eacute;dula o Similar</th>
                  <th>Correo MEP</th>
                  <th>Rol</th>
                  <th>&Aacute;mbito(s)</th>
                  <th class="text-center">Editar</th>
                  <th class="text-center">Eliminar</th>
                </tr>
              </thead>
              <tbody class="BusquedaRapida">
              <?php
              $consulta = mysqli_query($link, "SELECT ur.id, u.cedula, u.correo AS nombre, ur.codigo_presu, r.rol, ur.rol_id,
                      (SELECT GROUP_CONCAT(ap.nombre SEPARATOR ', ')
                       FROM t_ambitos_prestador ap
                       WHERE ap.usuarios_roles_id = ur.id AND ap.eliminado = 0) AS ambitos
                  FROM usuarios_roles ur
                      INNER JOIN usuarios u ON u.id = ur.usuario_id
                      INNER JOIN t_roles r ON r.id_rol = ur.rol_id
                  WHERE ur.codigo_presu = $logcodigo
                  AND ur.eliminado = 0
                  ORDER BY u.cedula ASC") or die(mysqli_error($link));

              while ($programas = mysqli_fetch_array($consulta)) {
                $email_parts = explode('@', $programas['nombre']);
                $email_prefix = $email_parts[0];
              ?>
                <tr data-id="<?php echo $programas['id'] ?>"
                    data-rol-id="<?php echo $programas['rol_id'] ?>"
                    data-cedula="<?php echo htmlspecialchars($programas['cedula']) ?>"
                    data-email-prefix="<?php echo htmlspecialchars($email_prefix) ?>">
                  <td><?php echo htmlspecialchars($programas['cedula']) ?></td>
                  <td><?php echo htmlspecialchars($programas['nombre']) ?></td>
                  <td><span class="badge" style="background: var(--mep-primary);"><?php echo htmlspecialchars($programas['rol']) ?></span></td>
                  <td>
                  <?php if (!empty($programas['ambitos'])): ?>
                    <span class="badge text-bg-light border text-dark text-wrap" style="white-space: normal;"><?php echo htmlspecialchars($programas['ambitos']) ?></span>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                  </td>
                  <td class="text-center">
                  <?php if ($programas['rol_id'] != 1): ?>
                    <a class="btn btn-sm btn-outline-primary border-0" href="#" onclick="cargarEditar(<?php echo $programas['id'] ?>); return false;">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                  </td>
                  <td class="text-center">
                  <?php if ($programas['rol_id'] != 1 && $programas['cedula'] != $cedula_actual): ?>
                    <a class="btn btn-sm btn-outline-danger border-0" href="#" onclick="confirmarEliminacion(<?php echo $programas['id'] ?>); return false;">
                      <span class="icon icon-bin"></span>
                    </a>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                  </td>
                </tr>
              <?php }
              mysqli_close($link);	
              ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
        style="bottom: 100px;" data-tooltip="Regresar">
          <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <script type="text/javascript">
    var rolInput = document.getElementById('rol');
    var roleOptions = document.getElementById('roleOptions');
    var rolError = document.getElementById('rolError');

    var cedulaInput = document.getElementById('cedula');
    var cedulaError = document.getElementById('cedulaError');

    var emailInput = document.getElementById('nombre');
    var emailError = document.getElementById('emailError');

    var codigoInput = document.getElementById('codigop');
    var codigoError = document.getElementById('codigoError');

    var editIdInput = document.getElementById('edit_id');
    var btnGuardar = document.getElementById('btnGuardar');
    var btnCancelar = document.getElementById('btnCancelar');

    var ambitoSection = document.getElementById('ambitoSection');
    var ambitoContainer = document.getElementById('ambitosContainer');
    var ambitoError = document.getElementById('ambitoError');
    var ambitosJsonInput = document.getElementById('ambitos_json');
    var btnAgregarAmbito = document.getElementById('btnAgregarAmbito');
    var lugaresAmbito = <?php echo json_encode($lugares_ambito); ?>;
    var contadorAmbito = 0;

    function renderLugaresCheckbox(cardIdx, seleccionados) {
      var html = '';
      var selec = (seleccionados || []).map(Number);
      lugaresAmbito.forEach(function(l) {
        var idLugar = Number(l.id_lugar);
        var checked = (selec.indexOf(idLugar) !== -1) ? ' checked' : '';
        var idCb = 'lugar_' + cardIdx + '_' + idLugar;
        html += '<div class="form-check">' +
          '<input class="form-check-input ambito-lugar" type="checkbox" value="' + idLugar + '" id="' + idCb + '"' + checked + '>' +
          '<label class="form-check-label small" for="' + idCb + '">' + l.lugar + '</label></div>';
      });
      return html;
    }

    function agregarAmbito(datos) {
      datos = datos || {};
      var idx = contadorAmbito++;
      var nombre = datos.nombre || '';
      var seleccionados = datos.lugares || [];
      var card = document.createElement('div');
      card.className = 'ambito-card border rounded p-3 mb-3';
      card.setAttribute('data-index', idx);
      card.innerHTML =
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
          '<label class="form-label fw-semibold mb-0">Ámbito #' + (idx + 1) + '</label>' +
          '<button type="button" class="btn btn-sm btn-outline-danger border-0 quitar-ambito" title="Quitar ámbito"><i class="bi bi-x-lg"></i></button>' +
        '</div>' +
        '<div class="mb-2">' +
          '<label class="form-label small mb-1">Nombre del ámbito</label>' +
          '<input type="text" class="form-control ambito-nombre" placeholder="Ej: Equipo INCO" value="' + nombre.replace(/"/g, '&quot;') + '">' +
        '</div>' +
        '<div class="small fw-semibold mb-1">Lugares (art&iacute;culos que podr&aacute; prestar)</div>' +
        renderLugaresCheckbox(idx, seleccionados);
      ambitoContainer.appendChild(card);
      card.querySelector('.quitar-ambito').addEventListener('click', function() {
        card.remove();
        reindexarAmbitos();
      });
    }

    function reindexarAmbitos() {
      var cards = document.querySelectorAll('#ambitosContainer .ambito-card');
      contadorAmbito = cards.length;
      cards.forEach(function(c, i) {
        c.querySelector('label.mb-0').textContent = 'Ámbito #' + (i + 1);
      });
    }

    function recolectarAmbitos() {
      var ambitos = [];
      document.querySelectorAll('#ambitosContainer .ambito-card').forEach(function(card) {
        var nombre = card.querySelector('.ambito-nombre').value.trim();
        var lugares = [];
        card.querySelectorAll('.ambito-lugar:checked').forEach(function(cb) {
          lugares.push(parseInt(cb.value));
        });
        ambitos.push({ nombre: nombre, lugares: lugares });
      });
      ambitosJsonInput.value = JSON.stringify(ambitos);
      return ambitos;
    }

    function limpiarAmbitos() {
      ambitoContainer.innerHTML = '';
      contadorAmbito = 0;
      ambitosJsonInput.value = '';
    }

    function toggleAmbitoSection(rolId) {
      if (String(rolId) === '3') {
        ambitoSection.style.display = '';
        if (contadorAmbito === 0) agregarAmbito();
      } else {
        ambitoSection.style.display = 'none';
        limpiarAmbitos();
      }
    }

    function cargarAmbitos(urId) {
      limpiarAmbitos();
      fetch('traer_ambitos_usuario.php?usuarios_roles_id=' + urId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
          var ambitos = data.ambitos || [];
          if (ambitos.length === 0) {
            agregarAmbito();
          } else {
            ambitos.forEach(function(a) {
              agregarAmbito({ nombre: a.nombre, lugares: a.lugares });
            });
          }
        })
        .catch(function() {
          agregarAmbito();
        });
    }

    btnAgregarAmbito.addEventListener('click', function() {
      agregarAmbito();
    });

    function cargarEditar(id) {
      var row = document.querySelector('tr[data-id="' + id + '"]');
      if (!row) return;

      var cedula = row.getAttribute('data-cedula');
      var emailPrefix = row.getAttribute('data-email-prefix');
      if (emailPrefix.indexOf('@') !== -1) {
        emailPrefix = emailPrefix.split('@')[0];
      }
      var rolId = row.getAttribute('data-rol-id');

      cedulaInput.value = cedula;
      cedulaInput.readOnly = true;
      cedulaInput.classList.add('readonly-field');
      emailInput.value = emailPrefix;
      codigoInput.value = codigoInput.value;

      document.querySelectorAll('input[name="rol_radio"]').forEach(function(radio) {
        radio.checked = (radio.value === rolId);
        if (radio.checked) {
          rolInput.value = rolId;
        }
      });

      toggleAmbitoSection(rolId);
      if (String(rolId) === '3') {
        cargarAmbitos(id);
      }

      editIdInput.value = id;
      btnGuardar.innerHTML = '<i class="bi bi-pencil-square"></i> Actualizar';
      btnCancelar.style.display = 'inline-block';

      limpiarError(cedulaInput, cedulaError);
      limpiarError(emailInput, emailError);
      limpiarError(codigoInput, codigoError);
      limpiarError(null, rolError, roleOptions);
    }

    function resetFormulario() {
      cedulaInput.value = '';
      cedulaInput.readOnly = false;
      cedulaInput.classList.remove('readonly-field');
      emailInput.value = '';
      document.querySelectorAll('input[name="rol_radio"]').forEach(function(radio) {
        radio.checked = false;
      });
      rolInput.value = '';
      editIdInput.value = '';
      btnGuardar.innerHTML = '<i class="bi bi-floppy"></i> Guardar';
      btnCancelar.style.display = 'none';

      ambitoSection.style.display = 'none';
      limpiarAmbitos();

      limpiarError(cedulaInput, cedulaError);
      limpiarError(emailInput, emailError);
      limpiarError(codigoInput, codigoError);
      limpiarError(null, rolError, roleOptions);
      emailError.textContent = 'No incluya @ ni el dominio. Digite solo la parte antes de @mep.go.cr';
    }

    function limpiarError(input, errorEl, grupo) {
      errorEl.style.display = 'none';
      if (input) input.classList.remove('is-invalid');
      if (grupo) grupo.classList.remove('is-invalid');
    }

    function mostrarError(input, errorEl, grupo) {
      errorEl.style.display = 'block';
      if (input) input.classList.add('is-invalid');
      if (grupo) grupo.classList.add('is-invalid');
    }

    function scrollAlPrimerError() {
      var primero = document.querySelector('.is-invalid');
      if (primero) {
        primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (primero.tagName.match(/^INPUT|SELECT|TEXTAREA$/)) primero.focus();
      }
    }

    function confirmarEliminacion(id) {
      Swal.fire({
        title: '\u00bfEliminar registro?',
        text: 'Esta acci\u00f3n no se puede deshacer. El usuario perder\u00e1 los permisos asignados.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'S\u00ed, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true
      }).then(function(result) {
        if (result.isConfirmed) {
          window.location.href = 'eliminar_rol_n.php?gps=' + id;
        }
      });
    }

    document.querySelectorAll('input[name="rol_radio"]').forEach(function(radio) {
      radio.addEventListener('change', function() {
        rolInput.value = this.value;
        limpiarError(null, rolError, roleOptions);
        toggleAmbitoSection(this.value);
      });
    });

    cedulaInput.addEventListener('input', function() {
      if (this.value.trim()) limpiarError(this, cedulaError);
    });

    emailInput.addEventListener('input', function() {
      if (this.value.indexOf('@') === -1) {
        limpiarError(this, emailError);
      } else {
        mostrarError(this, emailError);
      }
    });

    codigoInput.addEventListener('input', function() {
      if (this.value.trim()) limpiarError(this, codigoError);
    });

    async function verificarDuplicado(cedula, codigo) {
      try {
        var response = await fetch('sql/verificar_cedula_codigo.php?cedula=' + encodeURIComponent(cedula) + '&codigo=' + encodeURIComponent(codigo));
        var data = await response.json();
        return data.existe;
      } catch (e) {
        return false;
      }
    }

    var formularioEnviando = false;

    async function validarFormulario(event) {
      event.preventDefault();
      if (formularioEnviando) return;

      var valido = true;

      var rol = rolInput.value;
      if (!rol) {
        mostrarError(null, rolError, roleOptions);
        valido = false;
      }

      if (rol === '3') {
        var ambitos = recolectarAmbitos();
        var ambitoOk = ambitos.length > 0 && ambitos.every(function(a) {
          return a.nombre !== '' && a.lugares.length > 0;
        });
        if (!ambitoOk) {
          mostrarError(null, ambitoError, ambitoContainer);
          valido = false;
        }
      }

      var cedula = cedulaInput.value.trim();
      if (!cedula) {
        mostrarError(cedulaInput, cedulaError);
        valido = false;
      }

      var correo = emailInput.value.trim();
      if (!correo) {
        mostrarError(emailInput, emailError);
        emailError.textContent = 'Este campo es requerido.';
        valido = false;
      } else if (correo.indexOf('@') !== -1) {
        mostrarError(emailInput, emailError);
        emailError.textContent = 'No incluya @ ni el dominio. Digite solo la parte antes de @mep.go.cr';
        valido = false;
      } else {
        emailInput.value = correo + '@mep.go.cr';
      }

      var codigo = codigoInput.value.trim();
      if (!codigo) {
        mostrarError(codigoInput, codigoError);
        valido = false;
      }

      if (!valido) {
        scrollAlPrimerError();
        return;
      }

      if (editIdInput.value) {
        formularioEnviando = true;
        document.getElementById('frmrol').submit();
        return;
      }

      formularioEnviando = true;
      var existe = await verificarDuplicado(cedula, codigo);
      if (existe) {
        mostrarError(cedulaInput, cedulaError);
        cedulaError.textContent = 'Esta cédula ya tiene un rol asignado en este centro.';
        formularioEnviando = false;
        return;
      }

      document.getElementById('frmrol').submit();
    }

    <?php if ($flash): ?>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: '<?= $flash['type'] ?>',
        title: '<?= $flash['message'] ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true,
        didOpen: function(toast) {
          toast.addEventListener('mouseenter', Swal.stopTimer);
          toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
      });
    });
    <?php endif; ?>
    </script>

</body>
</html>
