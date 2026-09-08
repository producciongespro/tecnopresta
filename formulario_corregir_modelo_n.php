<?php
if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
/*$tienellave = in_array($_SESSION['tipo'], [1, 2, 3, 4]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su director(a) institucional para que se los brinde, si es usted prestador o inventariador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}*/
require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];
*/
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


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corregir modelo del activo</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- ESTILOS INSTITUCIONALES -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=4" />

    <link href="css/bootstrap-icons/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <script src="js/jquery-3.7.1.min.js"></script>
</head>
<body class="layout-page">
  <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">


        <div class="container mt-5">
            <div class="hero-box mb-4 fade-enter">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3">
                            <div class="hero-icon">
                                <i class="bi bi-clipboard2-check-fill" style="font-size: 2rem;"></i>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-1">Corregir Modelo de Activos</h2>
                                <p class="mb-0 opacity-75">Gestión de corrección de modelos para el inventario nacional</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                        <a href="mailto:soporte@mep.go.cr" class="text-white text-decoration-none opacity-75 small">
                            <i class="bi bi-envelope"></i> Reportar
                        </a>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <select class="form-select select-mep my-3 w-50" id="fondos" name="fondos" aria-label="Seleccionar fondo" required>
                    <option value="0">Seleccione..</option>
                    <?php 
                        $querz = $link->query("SELECT * FROM t_fondos");
                        while ($valorez = mysqli_fetch_array($querz)) {
                            echo '<option value="'.$valorez['id_fondos'].'">'.$valorez['fondos'].'</option>';
                        }
                    ?>
                </select>
            </div>
            <form action="actualizar_modelo_de_activos_n.php" method="POST" id="formCorregir">
                <div id="modelos">
                    <!-- Aquí se cargará dinámicamente un select -->
                </div>
                <div id="mostraractivos">
                    <!-- Aquí se cargará dinámicamente la tabla y el botón submit -->
                </div>
            </form>

            <!-- Botón flotante Actualizar -->
            <button type="submit" class="btn-guardar-flotante" form="formCorregir" style="bottom: 170px;" data-tooltip="Actualizar modelos">
                <i class="bi bi-clipboard2-check-fill"></i>
            </button>

            <!-- Modal Éxito -->
            <div class="modal fade" id="modalExito" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="prestamo-modal-header">
                            <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i> Operación exitosa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="prestamo-modal-body">
                            <img src="img/listo.png" alt="Listo" width="80" class="mb-3">
                            <p>¡Modelos actualizados correctamente!</p>
                        </div>
                        <div class="prestamo-modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Error -->
            <div class="modal fade" id="modalError" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="prestamo-modal-header">
                            <h5 class="modal-title"><i class="bi bi-exclamation-circle me-2"></i> Error</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="prestamo-modal-body" id="modalErrorBody">
                        </div>
                        <div class="prestamo-modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Confirmación -->
            <div class="modal fade" id="modalConfirmacion" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="prestamo-modal-header">
                            <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i> Confirmar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="prestamo-modal-body">
                            <p id="confirmacionMensaje"></p>
                        </div>
                        <div class="prestamo-modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="btnConfirmarSi">Sí, actualizar</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Botón flotante Importar Modelo -->
        <!-- <a href="formulario_importar_modelo_general_n.php" class="btn-disponibilidad"
            style="bottom: 170px;" data-tooltip="Importar modelo">
                <i class="bi bi-journal-arrow-down"></i>
        </a> -->
        <!-- Botón flotante Volver -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
            style="bottom: 100px;" data-tooltip="Regresar">
                <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </main>
<?php include 'partials/footer.php'; ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script>
function mostrarModal(id) {
    var modal = new bootstrap.Modal(document.getElementById(id));
    modal.show();
}

var confirmCallback = null;

function mostrarConfirmacion(mensaje, callback) {
    document.getElementById('confirmacionMensaje').textContent = mensaje;
    confirmCallback = callback;
    var modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnConfirmarSi').addEventListener('click', function() {
        if (confirmCallback) {
            confirmCallback();
            confirmCallback = null;
        }
        var modalEl = document.getElementById('modalConfirmacion');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    });
});

$(document).ready(function() {
    function cargarContenidoFondo(id_fondos) {
        if (!id_fondos || id_fondos == 0) return;
        $.ajax({
            url: 'activos_a_corregir_n.php',
            type: 'POST',
            data: {id_fondos: id_fondos},
            success: function(response) {
                $('#mostraractivos').html(response);
            }
        });
        $.ajax({
            url: 'modelos_sugeridos_n.php',
            type: 'POST',
            data: {id_fondos: id_fondos},
            success: function(response) {
                $('#modelos').html(response);
            }
        });
    }

    $('#fondos').change(function() {
        var id_fondos = $(this).val();
        sessionStorage.setItem('fondo_corregir_modelo', id_fondos);
        cargarContenidoFondo(id_fondos);
    });

    var fondoGuardado = sessionStorage.getItem('fondo_corregir_modelo');
    if (fondoGuardado && fondoGuardado != '0') {
        $('#fondos').val(fondoGuardado);
        cargarContenidoFondo(fondoGuardado);
    }
    sessionStorage.removeItem('fondo_corregir_modelo');

    $('#formCorregir').on('submit', function(event) {
        event.preventDefault();

        var checkboxes = $(this).find('input[name="idsplacas[]"]:checked');
        if (checkboxes.length === 0) {
            document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>No hay ningún activo seleccionado</p></div>';
            mostrarModal('modalError');
            return;
        }

        var modeloSelect = $('#modelos_select').val();
        if (!modeloSelect || modeloSelect == 0) {
            document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>No se seleccionó un modelo</p></div>';
            mostrarModal('modalError');
            return;
        }

        mostrarConfirmacion('¿Realmente desea actualizar los modelos seleccionados?', function() {
            $.ajax({
                url: $('#formCorregir').attr('action'),
                type: 'POST',
                data: $('#formCorregir').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        sessionStorage.setItem('fondo_corregir_modelo', $('#fondos').val());
                        mostrarModal('modalExito');
                    } else {
                        document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error: ' + (response.error || 'Error desconocido') + '</p></div>';
                        mostrarModal('modalError');
                    }
                },
                error: function() {
                    document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error de conexión al servidor</p></div>';
                    mostrarModal('modalError');
                }
            });
        });
    });

    $('#modalExito').on('hidden.bs.modal', function() {
        window.location.reload();
    });
});

document.addEventListener("DOMContentLoaded", function () {
    document.addEventListener("change", function (event) {
        if (event.target && event.target.id === "selectAll") {
            const checkboxes = document.querySelectorAll(".selectall");
            checkboxes.forEach(checkbox => {
                checkbox.checked = event.target.checked;
            });
        }
    });
});
</script>

</body>
</html>
