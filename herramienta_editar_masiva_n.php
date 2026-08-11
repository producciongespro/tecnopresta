<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once("conexion.php");

$link = $mysqli;

// Configurar conexión y charset
if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    die("Error cargando el conjunto de caracteres UTF-8: " . mysqli_error($link));
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

// ==== DATOS DE FONDOS PRESUPUESTARIOS (para el selector) =====
$fondos_html = '<option value="">Seleccione un fondo presupuestario...</option>';
$fondos_js = [];
$query_fondos = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos");
if ($query_fondos) {
    while ($fila_fondo = $query_fondos->fetch_assoc()) {
        $fid_fondos = intval($fila_fondo['id_fondos']);
        $fondos_js[$fid_fondos] = $fila_fondo['fondos'];
        $seleccionado = (isset($_POST['id_fondos']) && intval($_POST['id_fondos']) === $fid_fondos) ? ' selected' : '';
        $fondos_html .= '<option value="' . $fid_fondos . '"' . $seleccionado . '>' . htmlspecialchars($fila_fondo['fondos']) . '</option>';
    }
}

// Inicializar variables para mensajes
$mensaje = "";
$tipoMensaje = ""; // success, danger, warning
$resultados = [];
$totalRegistros = 0; // Nueva variable para el contador

// Procesar búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])) {
    $codigo = mysqli_real_escape_string($link, $_POST['codigo'] ?? '');
    $id_fondos = intval($_POST['id_fondos'] ?? 0);

    $query = "SELECT * FROM t_placa WHERE codigo = '$codigo' AND id_fondos = '$id_fondos' ORDER BY placa";
    $result = mysqli_query($link, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $resultados = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $totalRegistros = count($resultados); // Contar los registros encontrados
    } else {
        $mensaje = "No se encontraron registros con los criterios de búsqueda proporcionados.";
        $tipoMensaje = "warning";
        $totalRegistros = 0;
    }
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $updates = $_POST['updates'] ?? [];
    $actualizacionesExitosas = 0;
    $errores = [];

    foreach ($updates as $id_placa => $datos) {
        // Solo procesar si el checkbox fue marcado
        if (!isset($datos['editar'])) {
            continue;
        }

        $nueva_placa = mysqli_real_escape_string($link, trim($datos['nueva_placa'] ?? ''));
        $nuevo_serial = mysqli_real_escape_string($link, trim($datos['nuevo_serial'] ?? ''));

        // Validar que los campos no estén vacíos
        if (empty($nueva_placa) || empty($nuevo_serial)) {
            $errores[] = "Los campos no pueden estar vacíos para el registro ID: $id_placa";
            continue;
        }

        // Verificar si ya existe la nueva placa
        $check_placa = mysqli_query($link, "SELECT id_placa FROM t_placa WHERE placa = '$nueva_placa' AND id_placa != '$id_placa'");
        if (mysqli_num_rows($check_placa) > 0) {
            $errores[] = "La placa $nueva_placa ya existe en el sistema";
            continue;
        }

        // Verificar si ya existe el nuevo serial
        $check_serial = mysqli_query($link, "SELECT id_placa FROM t_placa WHERE serial = '$nuevo_serial' AND id_placa != '$id_placa'");
        if (mysqli_num_rows($check_serial) > 0) {
            $errores[] = "El serial $nuevo_serial ya existe en el sistema";
            continue;
        }

        // Actualizar registro
        $update_query = "UPDATE t_placa SET placa = '$nueva_placa', serial = '$nuevo_serial' WHERE id_placa = '$id_placa'";
        if (mysqli_query($link, $update_query)) {
            $actualizacionesExitosas++;
        } else {
            $errores[] = "Error al actualizar registro ID: $id_placa - " . mysqli_error($link);
        }
    }

    // Preparar mensaje de resultado
    if ($actualizacionesExitosas > 0) {
        $mensaje = "Se actualizaron exitosamente $actualizacionesExitosas registros.";
        $tipoMensaje = "success";
    }

    if (!empty($errores)) {
        $mensaje .= " Errores: " . implode(", ", $errores);
        $tipoMensaje = "danger";
    }

    // Volver a buscar para mostrar los datos actualizados
    if (!empty($_POST['codigo']) && !empty($_POST['id_fondos'])) {
        $codigo = mysqli_real_escape_string($link, $_POST['codigo']);
        $id_fondos = intval($_POST['id_fondos']);

        $query = "SELECT * FROM t_placa WHERE codigo = '$codigo' AND id_fondos = '$id_fondos' ORDER BY placa";
        $result = mysqli_query($link, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $resultados = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $totalRegistros = count($resultados); // Actualizar contador después de la actualización
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de Placas y Seriales</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=9" />
</head>

<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <main class="container py-4 contenido-principal">
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="hero-icon">
                            <i class="bi bi-pencil-square" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1">Edición de Placas y Seriales</h2>
                            <p class="mb-0 opacity-75">Actualice placas y seriales de los activos de su institución</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="prestamo-form-card">
            <div class="prestamo-form-header">
                <i class="bi bi-search me-2"></i> Criterios de Búsqueda
            </div>
            <div class="prestamo-form-body">
                <!-- Mostrar mensajes -->
                <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Formulario de búsqueda -->
                <div class="search-panel">
                    <div class="filtro-titulo">
                        <i class="bi bi-funnel-fill"></i> Buscar activos
                    </div>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label for="codigo" class="form-label required-field">Código</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" id="codigo" name="codigo" required
                                           value="<?php echo isset($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="id_fondos" class="form-label required-field">ID Fondos</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-wallet2"></i></span>
                                    <input type="number" class="form-control" id="id_fondos" name="id_fondos" required
                                           value="<?php echo isset($_POST['id_fondos']) ? htmlspecialchars($_POST['id_fondos']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label for="fondo_presupuestario" class="form-label">Fondo Presupuestario</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-diagram-3"></i></span>
                                    <select class="form-select" id="fondo_presupuestario">
                                        <?php echo $fondos_html; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="buscar" class="btn btn-mep-primary">
                                    <i class="bi bi-search me-1"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (!empty($resultados)): ?>
                <!-- Contador de registros -->
                <div class="contador-container">
                    <div class="contador-registros">
                        <i class="bi bi-database me-2"></i>
                        Registros encontrados:
                        <span id="contador"><?php echo $totalRegistros; ?></span>
                        <?php if ($totalRegistros == 1): ?>
                            registro
                        <?php else: ?>
                            registros
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Formulario de edición -->
                <form method="POST" id="form-edicion">
                    <input type="hidden" name="actualizar" value="1">
                    <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($_POST['codigo'] ?? ''); ?>">
                    <input type="hidden" name="id_fondos" value="<?php echo htmlspecialchars($_POST['id_fondos'] ?? ''); ?>">

                    <div class="table-responsive edicion-tabla-scroll">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="header-fixed">
                                <tr>
                                    <th width="5%">Editar</th>
                                    <th width="20%">Placa Actual</th>
                                    <th width="20%">Nueva Placa</th>
                                    <th width="20%">Serial Actual</th>
                                    <th width="20%">Nuevo Serial</th>
                                    <th width="15%">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $registro): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="updates[<?php echo $registro['id_placa']; ?>][editar]"
                                               class="form-check-input check-editar" value="1">
                                    </td>
                                    <td><?php echo htmlspecialchars($registro['placa']); ?></td>
                                    <td>
                                        <input type="text" name="updates[<?php echo $registro['id_placa']; ?>][nueva_placa]"
                                               class="form-control form-control-sm campo-edicion" placeholder="Nueva placa"
                                               value="<?php echo htmlspecialchars($registro['placa']); ?>" disabled>
                                    </td>
                                    <td><?php echo htmlspecialchars($registro['serial']); ?></td>
                                    <td>
                                        <input type="text" name="updates[<?php echo $registro['id_placa']; ?>][nuevo_serial]"
                                               class="form-control form-control-sm campo-edicion" placeholder="Nuevo serial"
                                               value="<?php echo htmlspecialchars($registro['serial']); ?>" disabled>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?php echo $registro['activo'] ? 'estado-badge-activo' : 'estado-badge-inactivo'; ?>">
                                            <i class="bi bi-<?php echo $registro['activo'] ? 'check-circle-fill' : 'x-circle-fill'; ?> me-1"></i>
                                            <?php echo $registro['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex align-items-center flex-wrap gap-3">
                        <button type="submit" class="btn btn-guardar-mep" id="btn-actualizar" disabled>
                            <i class="bi bi-save me-1"></i> Actualizar Seleccionados
                        </button>
                        <span class="text-muted small" id="contador-seleccionados">
                            <i class="bi bi-check2-square me-1"></i> (0 seleccionados)
                        </span>
                    </div>
                </form>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])): ?>
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle me-2"></i> No se encontraron registros con los criterios de búsqueda proporcionados.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

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

    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
        style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <?php include 'partials/footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // ==== Sincronización bidireccional: ID Fondos <-> Fondo Presupuestario ====
            function sincronizarFondoDesdeId() {
                const idFondos = $('#id_fondos').val();
                if (idFondos !== '') {
                    $('#fondo_presupuestario').val(idFondos);
                } else {
                    $('#fondo_presupuestario').val('');
                }
            }

            function sincronizarIdDesdeFondo() {
                $('#id_fondos').val($('#fondo_presupuestario').val());
            }

            // Al presionar Enter o perder el foco en ID Fondos -> cargar el Fondo en el selector
            $('#id_fondos').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sincronizarFondoDesdeId();
                }
            });
            $('#id_fondos').on('blur', sincronizarFondoDesdeId);

            // Al seleccionar un Fondo Presupuestario -> cargar el ID Fondos
            $('#fondo_presupuestario').on('change', sincronizarIdDesdeFondo);

            // Habilitar/deshabilitar campos de edición según checkbox
            $('.check-editar').change(function() {
                const fila = $(this).closest('tr');
                const habilitar = this.checked;

                fila.find('.campo-edicion').prop('disabled', !habilitar);

                // Verificar si hay algún checkbox seleccionado
                const haySeleccionados = $('.check-editar:checked').length > 0;
                $('#btn-actualizar').prop('disabled', !haySeleccionados);

                // Actualizar contador de seleccionados
                const cantidadSeleccionados = $('.check-editar:checked').length;
                $('#contador-seleccionados').text('(' + cantidadSeleccionados + ' seleccionados)');
            });

            // Validación antes de enviar
            $('#form-edicion').submit(function() {
                let errores = [];

                $('.check-editar:checked').each(function() {
                    const fila = $(this).closest('tr');
                    const nuevaPlaca = fila.find('input[name*="nueva_placa"]').val().trim();
                    const nuevoSerial = fila.find('input[name*="nuevo_serial"]').val().trim();

                    if (!nuevaPlaca) {
                        errores.push('El campo Nueva Placa no puede estar vacío');
                    }

                    if (!nuevoSerial) {
                        errores.push('El campo Nuevo Serial no puede estar vacío');
                    }
                });

                if (errores.length > 0) {
                    alert('Errores encontrados:\n' + errores.join('\n'));
                    return false;
                }

                /* Comentado: se reemplaza por el modal de confirmación (patrón herramienta_editar_ubicacion_activo_n.php)
                return confirm('¿Está seguro de que desea actualizar los registros seleccionados?');
                */
                mostrarConfirmacion('¿Está seguro de que desea actualizar los registros seleccionados?', function() {
                    document.getElementById('form-edicion').submit();
                });
                return false;
            });
        });

        // ==== Confirmación mediante modal (patrón herramienta_editar_ubicacion_activo_n.php) ====
        var confirmCallback = null;

        function mostrarConfirmacion(mensaje, callback) {
            document.getElementById('confirmacionMensaje').textContent = mensaje;
            confirmCallback = callback;
            var modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
            modal.show();
        }

        document.getElementById('btnConfirmarSi').addEventListener('click', function() {
            if (confirmCallback) {
                confirmCallback();
                confirmCallback = null;
            }
            var modalEl = document.getElementById('modalConfirmacion');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });
    </script>
</body>
</html>
