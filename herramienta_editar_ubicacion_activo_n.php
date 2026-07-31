<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once("conexion.php");

$link = $mysqli;
/*
// Verificar permisos
$tienellave = in_array($_SESSION['tipo'], [1,7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos para realizar esta acción");
    window.history.back();
    </script>';
    exit();
}
*/

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

// Definir nomenclatura de lugares
$nomenclatura_lugares = [
    1 => 'BODEGA',
    2 => 'LABORATORIO', 
    3 => 'SALA DE ROBÓTICA',
    4 => 'AULAS',
    5 => 'BIBLIOTECA',
    6 => 'OFICINAS ADMINISTRATIVAS'
];

// Inicializar variables para mensajes
$mensaje = "";
$tipoMensaje = ""; // success, danger, warning
$resultados = [];
$totalRegistros = 0; // Nueva variable para el contador

// Procesar búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])) {
    $codigo = mysqli_real_escape_string($link, $_POST['codigo']);
    $id_fondos = mysqli_real_escape_string($link, $_POST['id_fondos']);
    
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
    $updates = $_POST['updates'];
    $actualizacionesExitosas = 0;
    $errores = [];
    
    foreach ($updates as $id_placa => $datos) {
        // Solo procesar si el checkbox fue marcado
        if (!isset($datos['editar'])) {
            continue;
        }
        
        $nuevo_id_lugar = mysqli_real_escape_string($link, trim($datos['nuevo_id_lugar']));
        
        // Validar que el campo no esté vacío
        if (empty($nuevo_id_lugar)) {
            $errores[] = "El campo ID Lugar no puede estar vacío para el registro ID: $id_placa";
            continue;
        }
        
        // Validar que sea numérico
        if (!is_numeric($nuevo_id_lugar)) {
            $errores[] = "El ID Lugar debe ser un valor numérico para el registro ID: $id_placa";
            continue;
        }
        
        // Validar que esté en el rango permitido (1-6)
        if ($nuevo_id_lugar < 1 || $nuevo_id_lugar > 6) {
            $errores[] = "El ID Lugar debe estar entre 1 y 6 para el registro ID: $id_placa";
            continue;
        }
        
        // Actualizar registro - solo el campo id_lugar
        $update_query = "UPDATE t_placa SET id_lugar = '$nuevo_id_lugar' WHERE id_placa = '$id_placa'";
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
        $id_fondos = mysqli_real_escape_string($link, $_POST['id_fondos']);
        
        $query = "SELECT * FROM t_placa WHERE codigo = '$codigo' AND id_fondos = '$id_fondos' ORDER BY placa";
        $result = mysqli_query($link, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $resultados = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $totalRegistros = count($resultados); // Actualizar contador después de la actualización
        }
    }
}

// ==== DATOS DE FONDOS PARA EL SELECTOR DE NOMBRE =====
$fondos_html = '<option value="">Seleccione el Fondo...</option>';
$fondos_js = [];
$query_fondos = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos");
if ($query_fondos) {
    while ($fila_fondo = $query_fondos->fetch_assoc()) {
        $fid_fondo = intval($fila_fondo['id_fondos']);
        $fondos_js[$fid_fondo] = $fila_fondo['fondos'];
        $seleccionado = (isset($_POST['id_fondos']) && intval($_POST['id_fondos']) === $fid_fondo) ? ' selected' : '';
        $fondos_html .= '<option value="' . $fid_fondo . '"' . $seleccionado . '>' . htmlspecialchars($fila_fondo['fondos']) . '</option>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de ID Lugar</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <style>
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .header-fixed {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header-fixed th {
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: none;
            white-space: nowrap;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .contador-registros {
            background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
            border-left: 3px solid var(--mep-gold);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .contador-container {
            margin-bottom: 15px;
        }
        .nomenclatura-table {
            font-size: 0.9rem;
        }
        .nomenclatura-table th {
            background-color: #eef3f9;
            color: var(--mep-blue);
        }
        .accordion-item {
            border: 1px solid #e8ecf0;
            border-radius: 10px !important;
            overflow: hidden;
        }
        .accordion-button {
            font-weight: 600;
            color: var(--mep-blue);
        }
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
            color: #fff;
            box-shadow: none;
        }
        .accordion-button:not(.collapsed)::after {
            filter: brightness(0) invert(1);
        }

        /* ==== Panel de búsqueda MEP ==== */
        .search-panel {
            background: #f8fafd;
            border: 1px solid #e8ecf0;
            border-left: 4px solid var(--mep-blue);
            border-radius: 10px;
            padding: 1.2rem 1.2rem 1rem;
            margin-bottom: 1.25rem;
        }
        .filtro-titulo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--mep-blue);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 14px;
        }
        .search-panel .input-group-text {
            background: #eef3f9;
            border: 1px solid #dfe7f0;
            border-right: none;
            color: var(--mep-blue);
            border-radius: 8px 0 0 8px !important;
            padding: 0.55rem 0.75rem;
        }
        .search-panel .form-control,
        .search-panel .form-select {
            border: 1px solid #dfe7f0;
            border-left: none;
            border-radius: 0 8px 8px 0 !important;
            padding: 0.55rem 0.8rem;
            font-size: 0.9rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .search-panel .form-control:focus,
        .search-panel .form-select:focus {
            border-color: var(--mep-blue);
            box-shadow: 0 0 0 3px rgba(0, 56, 118, 0.1);
        }

        /* ==== Botón Actualizar MEP ==== */
        .btn-guardar-mep {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #28a745, #1e8a36);
            color: #fff;
            border: none;
            border-left: 3px solid var(--mep-gold);
            padding: 10px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 14px rgba(40, 167, 69, 0.25);
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .btn-guardar-mep:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 56, 118, 0.3);
        }
        .btn-guardar-mep:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ==== Badges de estado ==== */
        .estado-badge-activo {
            background: #e8f7ee;
            color: #1e7e34;
            font-weight: 600;
        }
        .estado-badge-inactivo {
            background: #fdecea;
            color: #c0392b;
            font-weight: 600;
        }

        /* ==== Tabla de edición ==== */
        #form-edicion .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        #form-edicion tbody tr:hover {
            background: #f8faff;
        }

        /* ==== Card sin efectos de hover de menú ==== */
        .card-edicion,
        .card-edicion:hover {
            transform: none !important;
            cursor: default !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06) !important;
        }
    </style>
</head>

<body class="bg-light layout-page">
    <?php include 'partials/header.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow card-edicion">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2)); border-bottom: 3px solid var(--mep-gold);">
                        <h3 class="mb-0 text-white"><i class="bi bi-pencil-square me-2"></i>Edición de ID Lugar</h3>
                        <p class="mb-0 mt-1 text-white opacity-75 small"><i class="bi bi-geo-alt-fill me-1"></i>Actualice la ubicación (ID Lugar) de los activos de su institución.</p>
                    </div>
                    <div class="card-body">
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
                                <i class="bi bi-funnel-fill"></i> Criterios de búsqueda
                            </div>
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="codigo" class="form-label required-field">Código</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                            <input type="text" class="form-control" id="codigo" name="codigo" required 
                                                   value="<?php echo isset($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="id_fondos" class="form-label required-field">ID Fondos</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                            <input type="number" class="form-control" id="id_fondos" name="id_fondos" required 
                                                   value="<?php echo isset($_POST['id_fondos']) ? htmlspecialchars($_POST['id_fondos']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nombre_fondo" class="form-label">Nombre del Fondo</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-folder2-open"></i></span>
                                            <select class="form-select" id="nombre_fondo">
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

                        <!-- Acordeón con la nomenclatura de lugares -->
                        <div class="accordion mb-4" id="accordionNomenclatura">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingNomenclatura">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#collapseNomenclatura" aria-expanded="false" 
                                            aria-controls="collapseNomenclatura">
                                        <i class="bi bi-geo-alt-fill me-2"></i> Nomenclatura de Lugares (ID Lugar)
                                    </button>
                                </h2>
                                <div id="collapseNomenclatura" class="accordion-collapse collapse" 
                                     aria-labelledby="headingNomenclatura" data-bs-parent="#accordionNomenclatura">
                                    <div class="accordion-body">
                                        <p class="text-muted mb-3">Utilice los siguientes códigos para asignar la ubicación del activo:</p>
                                        <div class="table-responsive">
                                            <table class="table table-bordered nomenclatura-table">
                                                <thead>
                                                    <tr>
                                                        <th width="20%" class="text-center">ID Lugar</th>
                                                        <th width="80%">Descripción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($nomenclatura_lugares as $id => $descripcion): ?>
                                                    <tr>
                                                        <td class="text-center fw-bold"><?php echo $id; ?></td>
                                                        <td><?php echo $descripcion; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($_POST['codigo']); ?>">
                            <input type="hidden" name="id_fondos" value="<?php echo htmlspecialchars($_POST['id_fondos']); ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="header-fixed">
                                        <tr>
                                            <th width="5%">Editar</th>
                                            <th width="15%">Placa</th>
                                            <th width="15%">Serial</th>
                                            <th width="15%">ID Lugar Actual</th>
                                            <th width="15%">Nuevo ID Lugar</th>
                                            <th width="15%">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultados as $registro): 
                                            $id_lugar_actual = $registro['id_lugar'] ?? '';
                                            $descripcion_lugar = isset($nomenclatura_lugares[$id_lugar_actual]) ? $nomenclatura_lugares[$id_lugar_actual] : 'No asignado';
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="updates[<?php echo $registro['id_placa']; ?>][editar]" 
                                                       class="form-check-input check-editar" value="1">
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['placa']); ?></td>
                                            <td><?php echo htmlspecialchars($registro['serial']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($id_lugar_actual); ?>
                                                <?php if (!empty($id_lugar_actual)): ?>
                                                    <br><small class="text-muted">(<?php echo $descripcion_lugar; ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" name="updates[<?php echo $registro['id_placa']; ?>][nuevo_id_lugar]" 
                                                       class="form-control campo-edicion" placeholder="Nuevo ID Lugar" 
                                                       value="<?php echo htmlspecialchars($id_lugar_actual); ?>" 
                                                       min="1" max="6" disabled>
                                                <small class="text-muted">(1-6)</small>
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
                                <button type="submit" name="actualizar" class="btn btn-guardar-mep" id="btn-actualizar" disabled>
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
                $('#contador-seleccionados').html('<i class="bi bi-check2-square me-1"></i> (' + cantidadSeleccionados + ' seleccionados)');
            });
            
            // Validación antes de enviar
            $('#form-edicion').submit(function() {
                let errores = [];
                
                $('.check-editar:checked').each(function() {
                    const fila = $(this).closest('tr');
                    const nuevoIdLugar = fila.find('input[name*="nuevo_id_lugar"]').val().trim();
                    
                    if (!nuevoIdLugar) {
                        errores.push('El campo Nuevo ID Lugar no puede estar vacío');
                    }
                    
                    if (!$.isNumeric(nuevoIdLugar)) {
                        errores.push('El campo Nuevo ID Lugar debe ser un número');
                    }
                    
                    const idLugarNum = parseInt(nuevoIdLugar);
                    if (idLugarNum < 1 || idLugarNum > 6) {
                        errores.push('El ID Lugar debe estar entre 1 y 6');
                    }
                });
                
                if (errores.length > 0) {
                    alert('Errores encontrados:\n' + errores.join('\n'));
                    return false;
                }
                
                return confirm('¿Está seguro de que desea actualizar los registros seleccionados?');
            });
        });
    </script>

    <script>
        const fondosData = <?php echo json_encode($fondos_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const inputIdFondos = document.getElementById('id_fondos');
            const selectNombreFondo = document.getElementById('nombre_fondo');

            function cargarNombreDesdeId() {
                const id = parseInt(inputIdFondos.value, 10);
                if (!isNaN(id) && fondosData[id] !== undefined) {
                    selectNombreFondo.value = String(id);
                } else {
                    selectNombreFondo.value = '';
                }
            }

            function cargarIdDesdeNombre() {
                inputIdFondos.value = selectNombreFondo.value;
            }

            inputIdFondos.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    cargarNombreDesdeId();
                }
            });

            inputIdFondos.addEventListener('blur', cargarNombreDesdeId);
            selectNombreFondo.addEventListener('change', cargarIdDesdeNombre);
        });
    </script>
    
</body>
</html>