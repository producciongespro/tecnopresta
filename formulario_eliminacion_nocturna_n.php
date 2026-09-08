<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1, 2]);
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
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];
*/
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar activos</title>
    <!-- Bootstrap 5 CDN -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=9" />
    
    <!-- Bootstrap Icons -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <style>
            .table-marcado {
                background-color: #fff0f0 !important;
                border-left: 4px solid #dc3545;
            }
            .table-marcado:hover {
                background-color: #ffe0e0 !important;
            }
            .badge-marcado {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background-color: #fff3cd;
                color: #856404;
                font-size: 0.75rem;
                font-weight: 600;
                padding: 4px 10px;
                border-radius: 20px;
                border: 1px solid #ffc107;
                white-space: nowrap;
            }
            .btn-revertir {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: none;
                border: 1px solid #dc3545;
                color: #dc3545;
                font-size: 0.75rem;
                font-weight: 600;
                padding: 2px 10px;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s ease;
                line-height: 1.5;
            }
            .btn-revertir:hover {
                background-color: #dc3545;
                color: #fff;
            }
            .btn-revertir i {
                font-size: 0.85rem;
            }
            .revertiendo {
                opacity: 0.5;
                pointer-events: none;
            }
            .row-revertido {
                background-color: #d4edda !important;
                transition: background-color 0.5s ease;
            }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>
    <main class="contenido-principal">
    <div class="container mt-5">
    
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="hero-icon">
                            <i class="bi bi-trash3" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1">Eliminación Nocturna de Activos</h2>
                            <p class="mb-0 opacity-75">El proceso se ejecuta en horario nocturno y sus efectos son permanentes</p>
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
            <form action="actualizar_marcado_a_eliminar_n.php" method="POST" id="formEliminar">
                <input type="hidden" name="subsistema_id" value="<?= intval($_GET['subsistema_id'] ?? 0) ?>">
                <input type="hidden" name="modulo_id" value="<?= intval($_GET['modulo_id'] ?? 0) ?>">
                <!-- SECCIÓN: Motivos de eliminación -->
                <div class="card shadow-sm mb-4" style="border-left: 4px solid var(--mep-gold);">
                    <div class="card-body py-3">
                        <h6 class="fw-semibold mb-3" style="color: var(--mep-primary);">
                            <i class="bi bi-question-circle me-2"></i>Motivo de eliminación
                        </h6>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="radio-label-mep">
                                    <input type="radio" name="motivo" value="baja_por_obsolescencia" checked required class="radio-mep"> Baja por obsolescencia
                                </label>
                            </div>
                            <div class="col-4">
                                <label class="radio-label-mep">
                                    <input type="radio" name="motivo" value="duplicidad" class="radio-mep"> Duplicidad
                                </label>
                            </div>
                            <div class="col-4">
                                <label class="radio-label-mep">
                                    <input type="radio" name="motivo" value="error_de_digitacion" class="radio-mep"> Error de digitación
                                </label>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">* Seleccione el motivo principal para la eliminación de los activos marcados</small>
                    </div>
                </div>
                
                <div id="mostraractivos">
                    <!-- Aquí se cargará dinámicamente la tabla y el botón submit -->
                </div>
            </form>

            <!-- Botón flotante Eliminar -->
            <button type="submit" class="btn-eliminar-flotante" form="formEliminar" data-tooltip="Eliminar seleccionados">
                <i class="bi bi-trash3-fill"></i>
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
                            <p>¡Activos marcados para eliminación!</p>
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
                            <button type="button" class="btn btn-danger" id="btnConfirmarSi">Sí, eliminar</button>
                        </div>
                    </div>
                </div>
            </div>

    </div>
    </main>

    <!-- Botón flotante Volver al Dashboard -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>
    
    <?php include 'partials/footer.php'; ?>

    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
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

    document.getElementById('fondos').addEventListener('change', function() {
        var fondosId = this.value;

        if (fondosId != 0) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'datos_seleccionados_para_eliminar_n.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById('mostraractivos').innerHTML = xhr.responseText;
                }
            };
            xhr.send('fondos=' + fondosId);
        } else {
            document.getElementById('mostraractivos').innerHTML = '';
        }
    });

    document.querySelector('form').addEventListener('submit', function(event) {
        event.preventDefault();

        var checkboxes = this.querySelectorAll('input[name="idsplacas[]"]:checked');
        if (checkboxes.length === 0) {
            document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>No hay ningún activo seleccionado</p></div>';
            mostrarModal('modalError');
            return;
        }

        mostrarConfirmacion('¿Realmente desea marcar estos activos para eliminación nocturna? Podrá revertir esta acción hasta que inicie el proceso nocturno.', function() {
            var formData = new FormData(event.target);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', event.target.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                checkboxes.forEach(function(cb) {
                                    convertirFilaAMarcado(cb.value, cb.closest('tr'));
                                });
                                mostrarModal('modalExito');
                            } else {
                                document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error: ' + (response.error || 'Error desconocido') + '</p></div>';
                                mostrarModal('modalError');
                            }
                        } catch(e) {
                            document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error al procesar la respuesta del servidor</p></div>';
                            mostrarModal('modalError');
                        }
                    } else {
                        document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error de conexión al servidor</p></div>';
                        mostrarModal('modalError');
                    }
                }
            };
            xhr.send(formData);
        });
    });

    function convertirFilaAMarcado(id, tr) {
        tr.classList.add('table-marcado');

        var firstTd = tr.cells[0];
        firstTd.innerHTML = '<div class="d-flex align-items-center gap-1 flex-wrap">' +
            '<input type="checkbox" disabled class="selectall" style="display:none">' +
            '<button type="button" class="btn-revertir" onclick="revertirMarcado(' + id + ', this)" title="Revertir eliminación">' +
                '<i class="bi bi-arrow-counterclockwise"></i> Revertir' +
            '</button>' +
        '</div>';

        var lastTd = tr.cells[tr.cells.length - 1];
        lastTd.innerHTML = '<span class="badge-marcado"><i class="bi bi-exclamation-triangle-fill me-1"></i>Pendiente</span>';
    }

    function revertirMarcado(id, btn) {
        mostrarConfirmacion('¿Revertir la marcación de este activo? No será eliminado en el proceso nocturno.', function() {
            var tr = btn.closest('tr');
            tr.classList.add('revertiendo');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'revertir_marcado_a_eliminar_n.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                tr.classList.remove('table-marcado', 'revertiendo');
                                tr.classList.add('row-revertido');

                                var firstTd = tr.cells[0];
                                firstTd.innerHTML = '<input type="checkbox" class="selectall" name="idsplacas[]" value="' + id + '"/>';

                                var lastTd = tr.cells[tr.cells.length - 1];
                                if (lastTd.querySelector('.badge-marcado')) {
                                    lastTd.innerHTML = '';
                                }

                                setTimeout(function() {
                                    tr.classList.remove('row-revertido');
                                }, 2000);
                            } else {
                                document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error: ' + response.error + '</p></div>';
                                mostrarModal('modalError');
                                tr.classList.remove('revertiendo');
                            }
                        } catch(e) {
                            document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error al procesar la respuesta del servidor</p></div>';
                            mostrarModal('modalError');
                            tr.classList.remove('revertiendo');
                        }
                    } else {
                        document.getElementById('modalErrorBody').innerHTML = '<div class="alert"><p>Error de conexión al servidor</p></div>';
                        mostrarModal('modalError');
                        tr.classList.remove('revertiendo');
                    }
                }
            };
            xhr.send('id_placa=' + id);
        });
    }
    </script>

</body>
</html>