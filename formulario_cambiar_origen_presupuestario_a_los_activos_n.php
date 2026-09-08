<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1, 2, 3, 4, 7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su director(a) institucional para que se los brinde, si es usted prestador o inventariador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
    */

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

/*$logusuario = $_SESSION['cedula'];
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
    <title>Corregir origen presupuestario</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous"> -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=8"/>

    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet"> -->
    
    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <!-- <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script> -->

    <style>
            .card-custom {
                background-color: #f0f4f8; /* Color frío y suave */
                border: none;
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
            }
            .card-body {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .card-img {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 50%;
                align-self: flex-end;
            }
            .btn-custom {
                background-color: #007bff; /* Color frío */
                border: none;
                border-radius: 5px;
            }
            .icon-large {
            font-size: 2em; /* Ajusta el tamaño del ícono */
            }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">

        <div class="container mt-5">
                
            <h4>Formulario para cambiar el origen presupuestario de los activos.</h4>
            <div class="mb-3">
                <!-- Select para elegir el origen presupuestario -->
                <select class="form-select my-3 w-50" id="origenPresupuestario" name="origenPresupuestario" required>
                    <option value="0">Seleccione un origen presupuestario...</option>
                        <?php
                            // Consulta para obtener los orígenes presupuestarios
                            $queryOrigen = $link->query("SELECT * FROM t_fondos");
                            while ($origen = mysqli_fetch_array($queryOrigen)) {
                                echo '<option value="' . $origen['id_fondos'] . '">' . $origen['fondos'] . '</option>';
                            }
                        ?>
                </select>
            </div>
            <form action="actualizar_origen_presupuestario_n.php" method="POST" id="formCambiarOrigen">
                <input type="hidden" name="subsistema_id" value="<?= intval($_GET['subsistema_id'] ?? 0) ?>">
                <input type="hidden" name="modulo_id" value="<?= intval($_GET['modulo_id'] ?? 0) ?>">
                <!-- Select para elegir el nuevo fondo presupuestario -->
                <div id="fondosPresupuestarios">
                    <!-- Aquí se cargarán los fondos presupuestarios excluyendo el seleccionado -->
                </div>
                    <!-- Tabla para mostrar los activos -->
                <div id="mostrarActivos">
                    <!-- Aquí se cargarán los activos asociados al origen seleccionado -->
                </div>
                </form>
            </div>

            <!-- Botón flotante Actualizar -->
            <button type="submit" class="btn-guardar-flotante" form="formCambiarOrigen" style="bottom: 170px; display: none;" data-tooltip="Actualizar">
                <i class="bi bi-clipboard2-check-fill"></i>
            </button>
        </main>
    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
        style="bottom: 100px;" data-tooltip="Regresar">
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
                    <p class="text-muted">El origen presupuestario de los activos seleccionados fue actualizado correctamente.</p>
                    <img src="img/listo.png" style="width: 5rem; margin-top: 10px;">
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-mep-primary" id="btnAceptarExito">Aceptar</button>
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
                    <p class="fw-semibold" style="color: var(--mep-primary);" id="modalErrorTexto">Error al realizar los cambios.</p>
                    <img src="img/mensaje.png" style="width: 5rem; margin-top: 10px;">
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-mep-primary" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>
    <?php include 'partials/footer.php'; ?>

    <script>
        function mostrarModal(id) {
            var el = document.getElementById(id);
            if (el) {
                var modal = new bootstrap.Modal(el);
                modal.show();
            }
        }

        $(document).ready(function () {
            // Evento cuando se selecciona un origen presupuestario
            $('#origenPresupuestario').change(function () {
                var idOrigen = $(this).val();

                if (idOrigen != 0) {
                    // Cargar los fondos presupuestarios excluyendo el seleccionado
                    $.ajax({
                        url: 'cargar_fondos_presupuestarios_n.php',
                        type: 'POST',
                        data: { id_fondos: idOrigen },
                        success: function (response) {
                            $('#fondosPresupuestarios').html(response);
                        }
                    });

                    // Cargar los activos asociados al origen seleccionado
                    $.ajax({
                        url: 'cargar_activos_por_origen_n.php',
                        type: 'POST',
                        data: { id_fondos: idOrigen },
                        success: function (response) {
                            $('#mostrarActivos').html(response);
                            $('.btn-guardar-flotante[form="formCambiarOrigen"]').toggle(response.indexOf('<table') !== -1);
                        }
                    });
                }
            });

            // Enviar vía AJAX
            $('#formCambiarOrigen').submit(function (e) {
                e.preventDefault();

                if ($('input[name="idsplacas[]"]:checked').length === 0) {
                    document.getElementById('modalErrorTexto').innerText = 'No hay ningún activo seleccionado.';
                    mostrarModal('modalError');
                    return;
                }

                if (!$('[name="nuevoFondo"]').val()) {
                    document.getElementById('modalErrorTexto').innerText = 'No se seleccionó un nuevo fondo presupuestario.';
                    mostrarModal('modalError');
                    return;
                }

                $.post($(this).attr('action'), $(this).serialize(), function (resp) {
                    if (resp.success) {
                        mostrarModal('modalExito');
                    } else {
                        document.getElementById('modalErrorTexto').innerText = resp.error || 'Error al actualizar los activos.';
                        mostrarModal('modalError');
                    }
                }, 'json').fail(function () {
                    document.getElementById('modalErrorTexto').innerText = 'Error de conexión con el servidor.';
                    mostrarModal('modalError');
                });
            });

            // Regresar al formulario preservando la navegación tras el éxito
            $('#btnAceptarExito').on('click', function () {
                var url = 'navegar.php?ruta=formulario_cambiar_origen_presupuestario_a_los_activos_n.php';
                var subsistema = $('input[name="subsistema_id"]').val();
                var modulo = $('input[name="modulo_id"]').val();
                if (subsistema > 0 && modulo > 0) {
                    url += '&subsistema_id=' + subsistema + '&modulo_id=' + modulo;
                }
                window.location.href = url;
            });
        });
    </script>
    <script>
    // Espera a que el DOM esté completamente cargado
    document.addEventListener("DOMContentLoaded", function () {
        // Delegación de eventos para el checkbox principal
        document.addEventListener("change", function (event) {
            if (event.target && event.target.id === "selectAll") {
                const checkboxes = document.querySelectorAll(".selectall");
                checkboxes.forEach(checkbox => {
                    checkbox.checked = event.target.checked;
                });
            }
        });

        // Delegación de eventos para cargar datos dinámicamente
        document.querySelector("#cargarTabla").addEventListener("click", function () {
            // Simulación de llamada AJAX
            fetch("ruta_al_archivo_php_que_devuelve_la_tabla.php")
                .then(response => response.text())
                .then(html => {
                    // Insertar la tabla cargada dinámicamente en el contenedor
                    document.querySelector("#tablaContenedor").innerHTML = html;
                })
                .catch(error => console.error("Error al cargar los datos:", error));
        });
    });
    </script>
    
    <!-- Bootstrap 5 JS -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script> -->

</body>
</html>