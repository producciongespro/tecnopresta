<?php
session_start();
require_once("conexion.php");

$link = $mysqli;

// Verificar permisos
$tienellave = in_array($_SESSION['tipo'], [1,7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos para realizar esta acción");
    window.history.back();
    </script>';
    exit();
}

// Configurar conexión y charset
if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    die("Error cargando el conjunto de caracteres UTF-8: " . mysqli_error($link));
}

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
        
        $nueva_placa = mysqli_real_escape_string($link, trim($datos['nueva_placa']));
        $nuevo_serial = mysqli_real_escape_string($link, trim($datos['nuevo_serial']));
        
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
        $id_fondos = mysqli_real_escape_string($link, $_POST['id_fondos']);
        
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
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .header-fixed {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .contador-registros {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .contador-container {
            margin-bottom: 15px;
        }
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-md bg-dark navbar-dark">
        <img src="img/logodelgobierno.png" width="35" height="30" alt="" loading="lazy">
        <a class="navbar-brand" href="formulario_menu_principal.html">Tecnopresta</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="collapsibleNavbar">
            <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="herramientas.php"><i class="bi bi-reply-all"></i> Regresar</a>
            </li> 
            <li class="nav-item">
                <a class="nav-link" href="gameover.php"><i class="bi bi-door-open"></i> Cerrar Sesión</a>
            </li>  
            </ul>
        </div>  
    </nav>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edición de Placas y Seriales</h3>
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
                        <form method="POST" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="codigo" class="form-label required-field">Código</label>
                                    <input type="text" class="form-control" id="codigo" name="codigo" required 
                                           value="<?php echo isset($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="id_fondos" class="form-label required-field">ID Fondos</label>
                                    <input type="number" class="form-control" id="id_fondos" name="id_fondos" required 
                                           value="<?php echo isset($_POST['id_fondos']) ? htmlspecialchars($_POST['id_fondos']) : ''; ?>">
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="buscar" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </form>

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
                                <table class="table table-striped table-hover">
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
                                                       class="form-control campo-edicion" placeholder="Nueva placa" 
                                                       value="<?php echo htmlspecialchars($registro['placa']); ?>" disabled>
                                            </td>
                                            <td><?php echo htmlspecialchars($registro['serial']); ?></td>
                                            <td>
                                                <input type="text" name="updates[<?php echo $registro['id_placa']; ?>][nuevo_serial]" 
                                                       class="form-control campo-edicion" placeholder="Nuevo serial" 
                                                       value="<?php echo htmlspecialchars($registro['serial']); ?>" disabled>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $registro['activo'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $registro['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="actualizar" class="btn btn-success" id="btn-actualizar" disabled>
                                    <i class="bi bi-save me-1"></i> Actualizar Seleccionados
                                </button>
                                <span class="ms-2 text-muted" id="contador-seleccionados">
                                    (0 seleccionados)
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
                
                return confirm('¿Está seguro de que desea actualizar los registros seleccionados?');
            });
        });
    </script>
</body>
</html>