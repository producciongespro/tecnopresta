<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    die("Error cargando el conjunto de caracteres utf8");
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>TecnoPresta - Origen de Fondos</title>
  <link rel="shortcut icon" href="ico/favicon.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 CSS -->
  <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
  <link rel="stylesheet" href="assets/css/nueva-identidad.css">
  <link rel="stylesheet" href="css/formulario_menu_principal.css" />

  <!-- SweetAlert2 -->
  <link href="sweetalert2/sweetalert2.min.css" rel="stylesheet">
  <script src="sweetalert2/sweetalert2.all.min.js"></script>

  <style>
    .form-card {
      border: none;
      border-top: 4px solid var(--mep-accent, #CFAC65);
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.08);
      background: #fff;
      overflow: hidden;
    }

    .form-section-title {
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--mep-primary, #192952);
      margin-bottom: 16px;
      padding-bottom: 8px;
      border-bottom: 2px solid var(--mep-accent, #CFAC65);
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
      background: #fff;
      border-radius: 12px;
      padding: 8px 14px;
      border: 1px solid var(--mep-border, #D9D9D9);
      box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    }

    .action-btn {
      width: 38px;
      height: 38px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
    }

    .table-responsive {
      max-height: 520px;
      overflow-y: auto;
    }

    .empty-state {
      display: none;
      text-align: center;
      padding: 40px 20px;
      color: #6c757d;
    }

    .empty-state i {
      font-size: 2.5rem;
      display: block;
      margin-bottom: 10px;
      color: var(--mep-accent, #CFAC65);
    }

    .fila-edicion {
      background: rgba(25, 41, 82, 0.06) !important;
      box-shadow: inset 3px 0 0 var(--mep-accent, #CFAC65);
    }

    .fila-edicion td {
      background: transparent;
    }

    .input-edicion {
      min-width: 140px;
    }

    .celda-acciones .btn {
      transition: transform 0.15s ease;
    }

    .celda-acciones .btn:active {
      transform: scale(0.92);
    }
  </style>
</head>
<body class="layout-page">
  <?php include 'partials/header.php'; ?>

  <main class="container py-4 contenido-principal">

    <!-- Hero / Encabezado -->
    <div class="hero-box mb-4 fade-enter">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="d-flex align-items-center gap-3">
            <div class="hero-icon">
              <i class="bi bi-piggy-bank"></i>
            </div>
            <div>
              <h2 class="fw-bold mb-1">Origen de los Fondos</h2>
              <p class="opacity-75 mb-0">Administración de los tipos de fondos presupuestarios.</p>
            </div>
          </div>
        </div>
        <!-- <div class="col-md-4 mt-3 mt-md-0 d-flex gap-2 flex-wrap justify-content-md-end">
          <a href="ayuda.html#ofa" class="btn btn-outline-light btn-sm">
            <i class="bi bi-question-circle"></i> Ayuda
          </a>
          <a href="contactenos.php?rep=Error en Origen de fondos" class="btn btn-outline-light btn-sm">
            <i class="bi bi-envelope"></i> Reportar Incidencia
          </a>
        </div> -->
      </div>
    </div>

    <div class="row g-4">
      <!-- FORMULARIO -->
      <div class="col-lg-5">
        <div class="card form-card">
          <div class="card-body p-4">
            <div class="form-section-title">
              <i class="bi bi-plus-circle me-1"></i> Registrar nuevo origen
            </div>
            <form name="frfondo" id="frfondo" action="guardar_tipo_fondos_n.php" method="post" class="needs-validation" novalidate>
              <div class="mb-4">
                <label for="fondos" class="form-label fw-semibold">Origen del fondo</label>
                <input type="text" class="form-control" id="fondos" name="fondos" required
                       maxlength="50" pattern="[A-Za-z0-9áéíóúÁÉÍÓÚñÑ ]+"
                       oninvalid="this.setCustomValidity('Por favor ingrese un origen válido')"
                       oninput="this.setCustomValidity('')">
                <div class="invalid-feedback">Por favor ingrese un origen de fondo válido.</div>
                <small class="text-muted">
                  <i class="bi bi-info-circle me-1"></i>Antes de agregar, verifique si ya existe en la lista.
                </small>
              </div>

              <button type="submit" class="btn btn-mep-primary w-100">
                <i class="bi bi-floppy"></i> Guardar
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- LISTADO -->
      <div class="col-lg-7">
        <div class="filter-box mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-search text-muted"></i>
          <input type="text" id="FiltrarContenido" class="form-control form-control-sm"
                 placeholder="Buscar origen de fondos...">
        </div>

        <div class="card form-card">
          <div class="card-body p-4">
            <div class="form-section-title">
              <i class="bi bi-list-ul me-1"></i> Orígenes registrados
            </div>
            <div class="table-responsive">
              <table class="table table-hover table-mep align-middle mb-0">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Tipo de Fondo</th>
                    <th class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody class="BusquedaRapida">
                  <?php
                  $consulta = mysqli_query($link, "SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos ASC") or die(mysqli_error($link));

                  while ($tipos = mysqli_fetch_array($consulta)) { ?>
                  <tr data-id="<?php echo (int)$tipos['id_fondos']; ?>">
                    <td><?php echo htmlspecialchars($tipos['id_fondos']); ?></td>
                    <td class="celda-fondos">
                      <span class="fondos-texto"><?php echo htmlspecialchars($tipos['fondos']); ?></span>
                    </td>
                    <td>
                      <div class="d-flex gap-2 justify-content-center celda-acciones">
                        <button type="button" class="btn btn-sm btn-outline-primary action-btn" title="Editar"
                                onclick="iniciarEdicionFila(this)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger action-btn" title="Eliminar"
                                onclick="confirmarEliminacion('eliminar_tipofondos_n.php?gps=<?php echo $tipos['id_fondos']; ?>')">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <?php }
                  mysqli_close($link); ?>
                </tbody>
              </table>
            </div>
            <div class="empty-state" id="sinResultados">
              <i class="bi bi-search"></i>
              No se encontraron orígenes de fondos.
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Botón flotante Volver -->
  <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
    style="bottom: 100px;" data-tooltip="Regresar al módulo">
    <i class="bi bi-arrow-left-circle-fill"></i>
  </a>

  <?php include 'partials/footer.php'; ?>

  <!-- Bootstrap 5 JS Bundle with Popper -->
  <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

  <!-- jQuery -->
  <script src="js/jquery-3.7.1.min.js"></script>

  <script>
    // ==== Validación del formulario de alta + envío AJAX ====
    (function() {
      'use strict';
      var forms = document.querySelectorAll('.needs-validation');

      Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
          event.preventDefault();
          event.stopPropagation();
          form.classList.add('was-validated');
          if (form.checkValidity()) {
            guardarNuevoOrigen();
          }
        }, false);
      });
    })();

    // Filtrado de contenido
    $(document).ready(function() {
      $('#FiltrarContenido').keyup(function() {
        var ValorBusqueda = new RegExp($(this).val(), 'i');
        var filas = $('.BusquedaRapida tr');
        filas.hide();
        var visibles = filas.filter(function() {
          return ValorBusqueda.test($(this).text());
        });
        visibles.show();
        $('#sinResultados').toggle(visibles.length === 0);
      });
    });

    // Confirmación de eliminación
    function confirmarEliminacion(url) {
      Swal.fire({
        title: '¿Eliminar registro?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true
      }).then(function(result) {
        if (result.isConfirmed) {
          window.location.href = url;
        }
      });
    }

    // ==== Utilidades AJAX ====
    function enviarAjax(url, datos, callback) {
      var body = new URLSearchParams(datos).toString();
      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: body
      })
      .then(function(resp) { return resp.json(); })
      .then(function(data) { callback(data); })
      .catch(function() {
        Swal.fire({
          icon: 'error',
          title: 'Error de conexión',
          text: 'No se pudo comunicar con el servidor.'
        });
      });
    }

    function escaparHTML(texto) {
      var div = document.createElement('div');
      div.textContent = texto;
      return div.innerHTML;
    }

    function reordenarFilas() {
      var tbody = document.querySelector('.BusquedaRapida');
      var filas = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
      filas.sort(function(a, b) {
        var na = a.querySelector('.fondos-texto').textContent.toLowerCase();
        var nb = b.querySelector('.fondos-texto').textContent.toLowerCase();
        return na < nb ? -1 : na > nb ? 1 : 0;
      });
      filas.forEach(function(tr) { tbody.appendChild(tr); });
    }

    function actualizarFiltro() {
      $('#FiltrarContenido').trigger('keyup');
    }

    // ==== Alta de nuevo origen (AJAX) ====
    function guardarNuevoOrigen() {
      var input = document.getElementById('fondos');
      var form = document.getElementById('frfondo');
      var btn = form.querySelector('button[type="submit"]');
      var valor = input.value.trim();

      if (valor === '') {
        Swal.fire({
          icon: 'warning',
          title: 'Campo vacío',
          text: 'Ingrese un nombre de origen de fondo.'
        });
        input.focus();
        return;
      }

      btn.disabled = true;
      var textoOriginal = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Guardando...';

      enviarAjax('ajax/ajax_guardar_tipo_fondos_n.php', { fondos: valor }, function(resp) {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;

        if (resp.success) {
          agregarFila(resp.id, resp.fondos);
          input.value = '';
          form.classList.remove('was-validated');
          input.focus();
          Swal.fire({
            icon: 'success',
            title: 'Origen agregado',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'No se pudo guardar',
            text: resp.error || 'Error desconocido.'
          });
          input.focus();
        }
      });
    }

    function agregarFila(id, nombre) {
      var tbody = document.querySelector('.BusquedaRapida');
      var tr = document.createElement('tr');
      tr.setAttribute('data-id', id);
      tr.innerHTML =
        '<td>' + id + '</td>' +
        '<td class="celda-fondos"><span class="fondos-texto">' + escaparHTML(nombre) + '</span></td>' +
        '<td><div class="d-flex gap-2 justify-content-center celda-acciones">' +
          '<button type="button" class="btn btn-sm btn-outline-primary action-btn" title="Editar" onclick="iniciarEdicionFila(this)"><i class="bi bi-pencil"></i></button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger action-btn" title="Eliminar" onclick="confirmarEliminacion(\'eliminar_tipofondos_n.php?gps=' + id + '\')"><i class="bi bi-trash"></i></button>' +
        '</div></td>';
      tbody.appendChild(tr);
      reordenarFilas();
      actualizarFiltro();
    }

    // ==== Edición inline ====
    var filaEnEdicion = null;

    function iniciarEdicionFila(btn) {
      var tr = btn.closest('tr');
      if (filaEnEdicion) {
        cancelarEdicionFila();
      }

      var celda = tr.querySelector('.celda-fondos');
      var span = tr.querySelector('.fondos-texto');
      var valorActual = span.textContent;

      var input = document.createElement('input');
      input.type = 'text';
      input.className = 'form-control form-control-sm input-edicion';
      input.maxLength = 50;
      input.pattern = '[A-Za-z0-9áéíóúÁÉÍÓÚñÑ ]+';
      input.value = valorActual;
      input.setAttribute('data-original', valorActual);

      span.style.display = 'none';
      celda.appendChild(input);
      input.focus();
      input.select();

      var contAcciones = tr.querySelector('.celda-acciones');
      contAcciones.innerHTML =
        '<button type="button" class="btn btn-sm btn-success action-btn" title="Guardar" onclick="guardarEdicionFila(this)"><i class="bi bi-check-lg"></i></button>' +
        '<button type="button" class="btn btn-sm btn-secondary action-btn" title="Cancelar" onclick="cancelarEdicionFila()"><i class="bi bi-x-lg"></i></button>';

      tr.classList.add('fila-edicion');
      filaEnEdicion = tr;

      input.addEventListener('keydown', function(ev) {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          guardarEdicionFila();
        } else if (ev.key === 'Escape') {
          cancelarEdicionFila();
        }
      });
    }

    function guardarEdicionFila() {
      if (!filaEnEdicion) return;
      var tr = filaEnEdicion;
      var input = tr.querySelector('.input-edicion');
      var original = input.getAttribute('data-original');
      var valor = input.value.trim();

      if (valor === '') {
        Swal.fire({
          icon: 'warning',
          title: 'Campo vacío',
          text: 'El origen de fondo no puede estar vacío.'
        });
        input.focus();
        return;
      }
      if (!/^[A-Za-z0-9áéíóúÁÉÍÓÚñÑ ]+$/.test(valor)) {
        Swal.fire({
          icon: 'warning',
          title: 'Caracteres no válidos',
          text: 'El origen solo puede contener letras, números y espacios.'
        });
        input.focus();
        return;
      }
      if (valor === original) {
        cancelarEdicionFila();
        return;
      }

      enviarAjax('ajax/ajax_editar_tipofondos_n.php', {
        id_fondos: tr.getAttribute('data-id'),
        fondos: valor
      }, function(resp) {
        if (resp.success) {
          var span = tr.querySelector('.fondos-texto');
          span.textContent = valor;
          finalizarEdicionFila(tr);
          reordenarFilas();
          actualizarFiltro();
          Swal.fire({
            icon: 'success',
            title: 'Origen actualizado',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'No se pudo actualizar',
            text: resp.error || 'Error desconocido.'
          });
          input.focus();
          input.select();
        }
      });
    }

    function cancelarEdicionFila() {
      if (!filaEnEdicion) return;
      var tr = filaEnEdicion;
      var input = tr.querySelector('.input-edicion');
      var span = tr.querySelector('.fondos-texto');
      span.textContent = input.getAttribute('data-original');
      finalizarEdicionFila(tr);
    }

    function finalizarEdicionFila(tr) {
      var input = tr.querySelector('.input-edicion');
      if (input) input.remove();

      var span = tr.querySelector('.fondos-texto');
      span.style.display = '';

      var contAcciones = tr.querySelector('.celda-acciones');
      contAcciones.innerHTML =
        '<button type="button" class="btn btn-sm btn-outline-primary action-btn" title="Editar" onclick="iniciarEdicionFila(this)"><i class="bi bi-pencil"></i></button>' +
        '<button type="button" class="btn btn-sm btn-outline-danger action-btn" title="Eliminar" onclick="confirmarEliminacion(\'eliminar_tipofondos_n.php?gps=' + tr.getAttribute('data-id') + '\')"><i class="bi bi-trash"></i></button>';

      tr.classList.remove('fila-edicion');
      if (filaEnEdicion === tr) filaEnEdicion = null;
    }
  </script>
</body>
</html>
