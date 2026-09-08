<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
// Verificacion de permisos m芍s segura
$tienellave = isset($_SESSION['tipo']) && in_array($_SESSION['tipo'], [1, 2, 3, 4, 7]);
if (!$tienellave) {
    header("Location: formulario_menu_inventario.html");
    exit();
}
*/
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    die("Error de conexi車n a MySQL: " . mysqli_connect_error());
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
$ruta_regreso ='navegar.php?ruta=formulario_menu_principal.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
    . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}

// ==== RUTA PARA LIMPIAR FILTRO (misma página sin id_fondos) =====
$ruta_limpiar = 'navegar.php?ruta=formulario_editar_placa_n.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_limpiar .= '&subsistema_id=' . intval($_GET['subsistema_id'])
                   . '&modulo_id=' . intval($_GET['modulo_id']);
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}

/*
$logusuario = $_SESSION['cedula'] ?? '';
$lognombre = $_SESSION['nombre'] ?? '';
$logcodigo = $_SESSION['codigo'] ?? '';
*/
$logcodigo = $usuario_azure['codigoPresu'] ?? '';

// Obtener el valor del filtro si existe (POST o GET)
$id_fondos_filtro = isset($_POST['id_fondos']) ? intval($_POST['id_fondos']) : (isset($_GET['id_fondos']) ? intval($_GET['id_fondos']) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>TecnoPresta - Gesti&oacute;n de Placas/Series</title>
    <link rel="shortcut icon" href="ico/favicon.png">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CSS -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=7"/>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="fondoresponsive.css">
    <link rel="stylesheet" type="text/css" href="/css/style.css">
    <link rel="stylesheet" type="text/css" href="/css_reportes/ico_reportes.css">
    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .search-box {
            position: relative;
        }
        .search-box .bi {
            position: absolute;
            top: 10px;
            left: 10px;
            color: #6c757d;
        }
        .search-box input {
            padding-left: 35px;
        }
        .action-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .table-responsive .table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-responsive .table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: linear-gradient(135deg, var(--mep-primary), var(--mep-secondary));
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table-responsive .table thead th {
            background: transparent;
            color: #fff;
            border: none;
        }
        .user-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .asset-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            object-fit: cover;
        }
        .code-display {
            font-family: 'Courier New', monospace;
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body class="bg-light layout-page">
    <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">

        <!-- Mostrar alertas de éxito/error -->
        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 1050; width: 80%; max-width: 800px;">
                <i class="bi bi-check-circle-fill me-2"></i> Los cambios se guardaron correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 'duplicado'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 1050; width: 80%; max-width: 800px;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Error: La placa o serial que intenta guardar ya existen en otro registro.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 'actualizacion'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 1050; width: 80%; max-width: 800px;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Error: No se pudo actualizar el registro. Por favor intente nuevamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="container py-4">
            <!-- Sección de Filtro con Título -->
            <div class="filter-section">
                <h5 class="mb-3"><i class="bi bi-funnel"></i> Filtrar Activos por Origen Presupuestario</h5>
                <p class="text-muted mb-3">Seleccione un origen presupuestario para filtrar los activos que desea editar.</p>
                
                <form method="POST" action="" class="d-flex align-items-end">
                    <div class="me-2" style="flex: 1;">
                        <label for="origenPresupuestario" class="form-label fw-bold">Origen Presupuestario:</label>
                        <select class="form-select" id="origenPresupuestario" name="id_fondos">
                            <option value="">-- Mostrar todos los orígenes --</option>
                            <?php
                            $queryOrigen = $link->query("SELECT * FROM t_fondos ORDER BY fondos");
                            while ($origen = mysqli_fetch_array($queryOrigen)) {
                                $selected = ($id_fondos_filtro == $origen['id_fondos']) ? 'selected' : '';
                                echo '<option value="' . $origen['id_fondos'] . '" ' . $selected . '>' . 
                                    htmlspecialchars($origen['fondos']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-filter"></i> Aplicar Filtro
                    </button>
                    <?php if(!empty($id_fondos_filtro)): ?>
                        <a href="<?= htmlspecialchars($ruta_limpiar) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar Filtro
                        </a>
                    <?php endif; ?>
                </form>
                
                <?php if(!empty($id_fondos_filtro)): ?>
                    <?php 
                    // Obtener el nombre del fondo seleccionado
                    $nombre_fondo = '';
                    $consulta_fondo = $link->query("SELECT fondos FROM t_fondos WHERE id_fondos = $id_fondos_filtro");
                    if ($fondo = mysqli_fetch_array($consulta_fondo)) {
                        $nombre_fondo = $fondo['fondos'];
                    }
                    ?>
                    <div class="mt-2">
                        <span class="badge bg-info">Filtro activo: <?php echo htmlspecialchars($nombre_fondo); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <!-- Card principal -->
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="card-title mb-0"><i class="bi bi-tag me-2"></i>Gesti&oacute;n de Placas/Series</h3>
                                <div>
                                    <!-- <a href="ayuda.html#eps" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="bi bi-question-circle"></i> Ayuda
                                    </a>
                                    <a href="contactenos.php?rep=Error en Editar Placa y/o Serie del Activo" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-exclamation-triangle"></i> Reportar
                                    </a> -->
                                </div>
                            </div>

                            <!-- Búsqueda -->
                            <div class="search-box mb-4">
                                <i class="bi bi-search"></i>
                                <input type="text" id="FiltrarContenido" class="form-control form-control-lg" placeholder="Buscar por placa o serial...">
                            </div>

                            <!-- Tabla de resultados -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Placa</th>
                                            <th>Serial</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="BusquedaRapida">
                                        <?php
                                        // Construir la consulta con el filtro
                                        $where_conditions = ["codigo = '" . mysqli_real_escape_string($link, $logcodigo) . "'"];
                                        
                                        if (!empty($id_fondos_filtro)) {
                                            $where_conditions[] = "id_fondos = " . intval($id_fondos_filtro);
                                        }
                                        
                                        $where_clause = implode(' AND ', $where_conditions);
                                        $consulta = mysqli_query($link, "SELECT id_placa, placa, serial
                                                                        FROM t_placa
                                                                        WHERE $where_clause
                                                                        ORDER BY id_placa ASC") or die(mysqli_error($link));

                                        if (mysqli_num_rows($consulta) > 0) {
                                            while ($placas = mysqli_fetch_array($consulta)) { ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($placas['id_placa']); ?></td>
                                                    <td><span class="code-display"><?php echo htmlspecialchars($placas['placa']); ?></span></td>
                                                    <td><span class="code-display"><?php echo htmlspecialchars($placas['serial']); ?></span></td>
                                                    <td>
                                                        <a href="formulario_editar_ps_n.php?gps=<?php echo $placas['id_placa']; ?>" class="btn btn-sm btn-outline-primary action-btn" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php }
                                        } else { ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                    No se encontraron registros con los filtros aplicados.
                                                </td>
                                            </tr>
                                        <?php }
                                        mysqli_close($link); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center justify-content-center bg-light">
                            <img src="img/editar-placa.png" class="asset-img" alt="Ilustración de gestión de placas" loading="lazy">
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
    </main>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
    <script src="js/jquery-3.7.1.min.js"></script>
        
    <script>
        // Búsqueda mejorada con delay
        $(document).ready(function() {
            var searchTimeout;
            $('#FiltrarContenido').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    var searchText = $('#FiltrarContenido').val().toLowerCase();
                    $('.BusquedaRapida tr').each(function() {
                        var rowText = $(this).text().toLowerCase();
                        $(this).toggle(rowText.indexOf(searchText) > -1);
                    });
                }, 300);
            });
        });
    </script>
    <?php include 'partials/footer.php'; ?>
</body>
</html>