<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

// Extracción de datos del usuario
$lognombre  = $usuario_azure ? trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? '')) : ($_SESSION['nombre'] ?? 'Usuario');
$logusuario = $usuario_azure['cedula'] ?? ($_SESSION['cedula'] ?? '');
$logcodigo  = $usuario_azure['codigoPresu'] ?? ($_SESSION['codigo'] ?? '');

require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

// Obtener id_ins de la institución
$stmt_ins = mysqli_prepare($link, "SELECT id_ins FROM t_instituciones WHERE codigo = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_ins, "s", $logcodigo);
mysqli_stmt_execute($stmt_ins);
$res_ins = mysqli_stmt_get_result($stmt_ins);
$row_ins = mysqli_fetch_assoc($res_ins);
$id_instituciones = (int)($row_ins['id_ins'] ?? 0);
mysqli_stmt_close($stmt_ins);

// Cargar catálogos para filtros iniciales (Orígenes presupuestarios, Clases, Estados, Lugares, Alias, Ámbitos)
$res_fondos = mysqli_query($link, "SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos ASC");
$res_clases = mysqli_query($link, "SELECT id_ag, clase FROM t_activo_general ORDER BY clase ASC");
$res_estados = mysqli_query($link, "SELECT id_estado, estado FROM t_estado ORDER BY estado ASC");
$res_lugares = mysqli_query($link, "SELECT id_lugar, lugar FROM t_lugar WHERE activo = 1 AND id_lugar <> 15 ORDER BY lugar ASC");

// Catálogo de ámbitos de la institución
$res_ambitos = false;
if ($id_instituciones > 0) {
    $stmt_amb = mysqli_prepare($link, "SELECT id_ambito, nombre FROM t_ambitos_prestador WHERE id_instituciones = ? AND eliminado = 0 ORDER BY nombre ASC");
    mysqli_stmt_bind_param($stmt_amb, "i", $id_instituciones);
    mysqli_stmt_execute($stmt_amb);
    $res_ambitos = mysqli_stmt_get_result($stmt_amb);
}

$stmt_alias_init = mysqli_prepare($link, "SELECT alias_id, alias, alias_imagen FROM t_alias WHERE codigo = ? ORDER BY alias ASC");
mysqli_stmt_bind_param($stmt_alias_init, "s", $logcodigo);
mysqli_stmt_execute($stmt_alias_init);
$res_alias_init = mysqli_stmt_get_result($stmt_alias_init);
$catalogo_alias = [];
while ($al = mysqli_fetch_assoc($res_alias_init)) {
    $catalogo_alias[] = $al;
}
mysqli_stmt_close($stmt_alias_init);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';">
    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css';">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <!-- SweetAlert2 para modales y confirmaciones elegantes -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="sweetalert2/sweetalert2.min.css" rel="stylesheet">


    <title>Gestión de Alias y Numeración de Activos</title>

    <style>
        :root {
            --tp-primary: #1e2851ff;
            --tp-primary-light: #2c3b75;
            --tp-accent: #c8ae64ff;
            --tp-accent-hover: #b39b55;
            --tp-bg-subtle: #f8f9fa;
        }

        body {
            background-color: #f4f6f9;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1 0 auto;
        }

        /* Botón flotante regresar de Plantilla.php */
        .btn-flotante-regresar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #1e2851ff;
            color: #fff;
            border: 2px solid #c8ae64ff;
            box-shadow: 0 4px 15px rgba(30, 40, 81, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .btn-flotante-regresar:hover {
            transform: scale(1.1);
            background: #c8ae64ff;
            border-color: #1e2851ff;
            color: #fff;
            box-shadow: 0 6px 25px rgba(30, 40, 81, 0.5);
        }
        
        .btn-flotante-regresar:active {
            transform: scale(0.95);
        }
        
        .btn-flotante-regresar i { 
            font-size: 30px;
            color: #fff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* Tarjetas y Contenedores */
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background: #fff;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .header-section {
            background: linear-gradient(135deg, var(--tp-primary) 0%, var(--tp-primary-light) 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 6px 16px rgba(30, 40, 81, 0.15);
            border-bottom: 3px solid var(--tp-accent);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Resumen horizontal de contenedores */
        .alias-chips-container {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 6px 2px 14px 2px;
            scrollbar-width: thin;
        }

        .alias-chip {
            flex: 0 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            user-select: none;
        }

        .alias-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            border-color: var(--tp-accent);
        }

        .alias-chip.active {
            background: #ebf0ff;
            border-color: var(--tp-primary);
            box-shadow: 0 0 0 2px var(--tp-primary);
        }

        .alias-chip-img {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            background: #fff;
        }

        .alias-chip-info {
            display: flex;
            flex-direction: column;
        }

        .alias-chip-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
            max-width: 140px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .alias-chip-badge {
            font-size: 0.72rem;
            padding: 2px 6px;
            border-radius: 20px;
            font-weight: 700;
            background: #e2e8f0;
            color: #475569;
            width: fit-content;
        }

        .alias-chip.active .alias-chip-badge {
            background: var(--tp-primary);
            color: #ffffff;
        }

        /* Badges de estado y contenedor en tabla */
        .badge-alias-container {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.83rem;
            font-weight: 600;
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge-alias-img {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            object-fit: cover;
        }

        .badge-sin-alias {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }

        .badge-con-alias {
            background-color: #e8eefa;
            color: var(--tp-primary);
            border: 1px solid #c7d7f7;
        }

        .badge-fondo {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: 600;
        }

        /* Inputs de numeración */
        .number-input-field {
            width: 75px;
            text-align: center;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s;
        }

        .number-input-field:disabled {
            background-color: #f8fafc;
            color: #94a3b8;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }

        .number-input-field:focus {
            border-color: var(--tp-accent);
            box-shadow: 0 0 0 0.2rem rgba(200, 174, 100, 0.25);
        }

        /* Barra de acciones flotante contextual (Sticky Bottom) */
        .floating-action-bar {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            background: #ffffff;
            border: 2px solid var(--tp-accent);
            border-radius: 50px;
            padding: 10px 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22);
            z-index: 1050;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-width: 92vw;
        }

        .floating-action-bar.show {
            transform: translateX(-50%) translateY(0);
        }

        .btn-action-primary {
            background: var(--tp-primary);
            color: #ffffff;
            border: none;
            border-radius: 30px;
            padding: 7px 18px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-action-primary:hover {
            background: var(--tp-primary-light);
            color: #fff;
            box-shadow: 0 3px 8px rgba(30, 40, 81, 0.3);
        }

        .btn-action-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            border-radius: 30px;
            padding: 7px 18px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-action-danger:hover {
            background: #dc2626;
            color: #ffffff;
        }

        .custom-chk {
            width: 1.15em;
            height: 1.15em;
            cursor: pointer;
        }

        /* Selector de alias modal */
        .alias-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            max-height: 360px;
            overflow-y: auto;
            padding: 8px 4px;
        }

        .alias-card-option {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
        }

        .alias-card-option:hover {
            border-color: var(--tp-accent);
            transform: translateY(-2px);
        }

        .alias-card-option.selected {
            border-color: var(--tp-primary);
            background: #f0f4ff;
            box-shadow: 0 0 0 2px var(--tp-primary);
        }

        .alias-card-option img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 6px;
            border: 1px solid #cbd5e1;
        }

        .table > tbody > tr.selected-row {
            background-color: #f0f7ff !important;
        }
    </style>
</head>
<body>

<?php 
if (file_exists(__DIR__ . '/partials/header.php')) {
    include 'partials/header.php'; 
}
?>

<div class="main-content container-fluid px-lg-5 py-4">

    <!-- Botón Flotante Regresar -->
    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=1&modulo_id=1" class="btn-flotante-regresar" title="Volver al panel general">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <!-- Header / Banner de Título -->
    <div class="header-section d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="header-title mb-1">
                <i class="bi bi-boxes"></i> Asignación y Gestión de Alias / Numeración
            </h1>
            <p class="mb-0 text-white-50 small">
                Gestione la pertenencia de activos a contenedores institucionales y sus correlativos (activos asignados y libres en un solo lugar).
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-person-badge me-1"></i> <?php echo htmlspecialchars($lognombre); ?>
            </span>
            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" id="btnRecargarTodo" title="Recargar datos">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- 1. Resumen y Conteo Rápido por Contenedor (Chips Superiores) -->
    <div class="card card-custom p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-secondary small text-uppercase">
                <i class="bi bi-grid-fill me-1"></i> Resumen de Contenedores
            </span>
            <span class="badge bg-secondary-subtle text-secondary" id="totalGeneralBadge">Cargando...</span>
        </div>
        <div class="alias-chips-container" id="contenedorChipsAlias">
            <div class="text-muted small py-2"><i class="bi bi-hourglass-split"></i> Cargando resumen de alias...</div>
        </div>
    </div>

    <!-- 2. Barra de Filtros Avanzada -->
    <div class="card card-custom p-3 mb-4">
        <div class="row g-3 align-items-end">
            
            <!-- Buscador de texto -->
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-search me-1"></i> Buscar
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="filtroSearch" class="form-control border-start-0 ps-0" placeholder="Placa, serial, modelo...">
                </div>
            </div>

            <!-- Filtro: Ámbito de Préstamo (t_ambitos_prestador) -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-diagram-3 me-1"></i> Ámbito
                </label>
                <select id="filtroAmbito" class="form-select">
                    <option value="">Todos los ámbitos</option>
                    <option value="sin_ambito">⚠️ Sin ámbito asignado</option>
                    <?php 
                    if ($res_ambitos) {
                        while ($amb = mysqli_fetch_assoc($res_ambitos)) { ?>
                            <option value="<?php echo htmlspecialchars($amb['id_ambito']); ?>">
                                <?php echo htmlspecialchars($amb['nombre']); ?>
                            </option>
                        <?php } 
                    } ?>
                </select>
            </div>

            <!-- Filtro: Lugar -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-geo-alt me-1"></i> Lugar Físico
                </label>
                <select id="filtroLugar" class="form-select">
                    <option value="">Todos los lugares</option>
                    <?php while ($l = mysqli_fetch_assoc($res_lugares)) { ?>
                        <option value="<?php echo htmlspecialchars($l['id_lugar']); ?>">
                            <?php echo htmlspecialchars($l['lugar']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- Filtro: Contenedor / Alias -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-archive me-1"></i> Contenedor
                </label>
                <select id="filtroAlias" class="form-select">
                    <option value="">Todos los activos</option>
                    <option value="sin_alias">📦 Sin contenedor (Libres)</option>
                    <option value="con_alias">📁 Asignados a algún alias</option>
                    <optgroup label="Alias Específicos">
                        <?php foreach ($catalogo_alias as $al) { ?>
                            <option value="<?php echo htmlspecialchars($al['alias_id']); ?>">
                                <?php echo htmlspecialchars($al['alias']); ?>
                            </option>
                        <?php } ?>
                    </optgroup>
                </select>
            </div>

            <!-- Filtro: Clase de Activo -->
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-tag me-1"></i> Clase
                </label>
                <select id="filtroClase" class="form-select">
                    <option value="">Todas</option>
                    <?php while ($c = mysqli_fetch_assoc($res_clases)) { ?>
                        <option value="<?php echo htmlspecialchars($c['id_ag']); ?>">
                            <?php echo htmlspecialchars($c['clase']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- Filtro: Origen Presupuestario (t_fondos) -->
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold text-secondary">
                    <i class="bi bi-cash-stack me-1"></i> Fondo
                </label>
                <select id="filtroFondos" class="form-select">
                    <option value="">Todos</option>
                    <?php while ($f = mysqli_fetch_assoc($res_fondos)) { ?>
                        <option value="<?php echo htmlspecialchars($f['id_fondos']); ?>">
                            <?php echo htmlspecialchars($f['fondos']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- Botón Limpiar Filtros -->
            <div class="col-12 col-md-1 d-grid">
                <button type="button" class="btn btn-outline-secondary" id="btnLimpiarFiltros" title="Restablecer todos los filtros">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
            </div>

        </div>
    </div>

    <!-- 3. Tabla Principal de Activos -->
    <div class="card card-custom overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaActivos">
                <thead class="table-light border-bottom">
                    <tr>
                        <th style="width: 45px;" class="text-center">
                            <input type="checkbox" id="checkSelectAll" class="form-check-input custom-chk" title="Seleccionar todos los visibles">
                        </th>
                        <th>Placa</th>
                        <th>Clase / Tipo</th>
                        <th>Marca & Modelo</th>
                        <th>Color</th>
                        <th>Serial</th>
                        <th>Origen Fondo</th>
                        <th>Ubicación & Ámbito</th>
                        <th>Contenedor (Alias) Actual</th>
                        <th style="width: 140px;" class="text-center" title="Correlativo dentro del contenedor">N.º en Contenedor</th>
                    </tr>
                </thead>
                <tbody id="tbodyActivos">
                    <!-- Filas inyectadas dinámicamente con AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Feedback cuando no hay registros -->
        <div id="sinResultadosMensaje" class="text-center py-5 d-none">
            <i class="bi bi-inbox text-muted display-4"></i>
            <p class="text-muted mt-2">No se encontraron activos con los filtros aplicados.</p>
        </div>

        <!-- Paginación y Contador de Registros -->
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center py-3">
            <div class="text-secondary small mb-2 mb-md-0" id="infoPaginacion">
                Mostrando 0 registros
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-secondary text-nowrap">Por página:</label>
                    <select id="selectLimit" class="form-select form-select-sm" style="width: 80px;">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="ulPaginacion">
                        <!-- Links de paginación generados por JS -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

</div>

<!-- 4. Barra Flotante Contextual Sticky (Aparece al seleccionar 1 o más activos) -->
<div class="floating-action-bar" id="barraFlotanteAcciones">
    <div class="d-flex align-items-center gap-2 pe-3 border-end">
        <span class="badge bg-dark rounded-pill fs-6 px-3 py-2" id="contadorSeleccionados">0 seleccionados</span>
    </div>

    <!-- Botón Primario: Asignar a Contenedor -->
    <button type="button" class="btn btn-action-primary" id="btnAbrirModalAsignar">
        <i class="bi bi-folder-plus"></i> Asignar a Contenedor
    </button>

    <!-- Botón Secundario/Destructivo: Desasignar / Quitar de Contenedor -->
    <button type="button" class="btn btn-action-danger" id="btnQuitarContenedor">
        <i class="bi bi-box-arrow-left"></i> Quitar de Contenedor
    </button>

    <!-- Cancelar Selección -->
    <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" id="btnCancelarSeleccion">
        <i class="bi bi-x-lg"></i> Cancelar
    </button>
</div>

<!-- Modal: Seleccionar Contenedor Destino -->
<div class="modal fade" id="modalAsignarAlias" tabindex="-1" aria-labelledby="modalAsignarAliasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--tp-primary); color: #fff;">
                <h5 class="modal-title" id="modalAsignarAliasLabel">
                    <i class="bi bi-folder-symlink me-2"></i> Seleccionar Contenedor Destino
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small mb-3">
                    Seleccione el contenedor al que desea mover los <strong id="modalCantActivos">0</strong> activos marcados:
                </p>
                <div class="alias-select-grid" id="gridOpcionesAlias">
                    <?php foreach ($catalogo_alias as $al) { 
                        $img_src = !empty($al['alias_imagen']) ? 'img/alias/' . htmlspecialchars($al['alias_imagen']) : 'img/Alias-000.png';
                    ?>
                        <div class="alias-card-option" data-alias-id="<?php echo htmlspecialchars($al['alias_id']); ?>" data-alias-name="<?php echo htmlspecialchars($al['alias']); ?>">
                            <img src="<?php echo $img_src; ?>" alt="Alias" onerror="this.src='icons/favicon.ico';">
                            <div class="fw-bold small text-truncate" title="<?php echo htmlspecialchars($al['alias']); ?>">
                                <?php echo htmlspecialchars($al['alias']); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary px-4" id="btnConfirmarAsignacion" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Asignar Ahora
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
if (file_exists(__DIR__ . '/partials/footer.php')) {
    include 'partials/footer.php'; 
}
?>

<!-- Scripts -->
<script src="js/jquery-3.7.1.min.js" onerror="this.onerror=null;this.src='https://code.jquery.com/jquery-3.7.1.min.js';"></script>
<script src="bootstrap5/js/bootstrap.bundle.min.js" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';"></script>
<script src="js/sweetalert2@11.js" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/sweetalert2@11';"></script>

<script>
$(document).ready(function() {

    // Estado global de la vista
    const state = {
        page: 1,
        limit: 50,
        selectedIds: new Set(),
        selectedAliasDestino: null,
        filtros: {
            search: '',
            id_ambito: '',
            id_fondos: '',
            alias_id: '',
            id_ag: '',
            id_estado: '',
            id_lugar: ''
        }
    };

    const modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignarAlias'));

    // Generador de color determinista por alias_id para badges
    function getAliasColor(aliasId) {
        if (!aliasId || aliasId === 0) return { bg: '#f1f5f9', text: '#64748b', border: '#cbd5e1' };
        const hue = (aliasId * 137.5) % 360;
        return {
            bg: `hsl(${hue}, 80%, 94%)`,
            text: `hsl(${hue}, 85%, 25%)`,
            border: `hsl(${hue}, 70%, 80%)`
        };
    }

    // Cargar datos principales vía AJAX apuntando a carpeta ajax/
    function cargarActivos(resetPage = false) {
        if (resetPage) state.page = 1;

        state.filtros.search = $('#filtroSearch').val().trim();
        state.filtros.id_ambito = $('#filtroAmbito').val();
        state.filtros.id_fondos = $('#filtroFondos').val();
        state.filtros.alias_id = $('#filtroAlias').val();
        state.filtros.id_ag = $('#filtroClase').val();
        state.filtros.id_lugar = $('#filtroLugar').val();
        state.limit = parseInt($('#selectLimit').val()) || 50;

        $('#tbodyActivos').html(`
            <tr>
                <td colspan="10" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Cargando activos...
                </td>
            </tr>
        `);

        $.ajax({
            url: 'ajax/ajax_listar_activos_alias.php',
            method: 'GET',
            dataType: 'json',
            data: {
                page: state.page,
                limit: state.limit,
                search: state.filtros.search,
                id_ambito: state.filtros.id_ambito,
                id_fondos: state.filtros.id_fondos,
                alias_id: state.filtros.alias_id,
                id_ag: state.filtros.id_ag,
                id_lugar: state.filtros.id_lugar
            },
            success: function(response) {
                if (!response.success) {
                    Swal.fire('Error', response.error || 'No se pudieron cargar los datos', 'error');
                    return;
                }
                renderizarChipsResumen(response.data.resumen);
                renderizarTabla(response.data.activos);
                renderizarPaginacion(response.data.paginacion);
            },
            error: function(xhr) {
                console.error('Error al cargar activos:', xhr);
                let detalle = 'Error al conectar con el servidor.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    detalle = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed.error) detalle = parsed.error;
                    } catch(e) {
                        detalle = xhr.responseText.substring(0, 300);
                    }
                }
                $('#tbodyActivos').html(`
                    <tr>
                        <td colspan="10" class="text-center py-4 text-danger">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> ${detalle}
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Renderizar Chips/Tarjetas de Resumen Superior
    function renderizarChipsResumen(resumen) {
        $('#totalGeneralBadge').text(`${resumen.total_general} Activos Registrados`);

        let html = `
            <div class="alias-chip ${state.filtros.alias_id === '' ? 'active' : ''}" data-filter-alias="">
                <i class="bi bi-collection-fill fs-4 text-primary"></i>
                <div class="alias-chip-info">
                    <span class="alias-chip-name">Todos</span>
                    <span class="alias-chip-badge">${resumen.total_general}</span>
                </div>
            </div>
            <div class="alias-chip ${state.filtros.alias_id === 'sin_alias' ? 'active' : ''}" data-filter-alias="sin_alias">
                <i class="bi bi-box fs-4 text-secondary"></i>
                <div class="alias-chip-info">
                    <span class="alias-chip-name">Sin Contenedor</span>
                    <span class="alias-chip-badge bg-warning-subtle text-dark">${resumen.total_sin_contenedor}</span>
                </div>
            </div>
        `;

        resumen.alias.forEach(al => {
            const isActive = state.filtros.alias_id == al.alias_id ? 'active' : '';
            const imgSrc = al.alias_imagen ? `img/alias/${al.alias_imagen}` : 'img/Alias-000.png';
            html += `
                <div class="alias-chip ${isActive}" data-filter-alias="${al.alias_id}" title="${al.alias}">
                    <img src="${imgSrc}" class="alias-chip-img" onerror="this.src='icons/favicon.ico';">
                    <div class="alias-chip-info">
                        <span class="alias-chip-name">${al.alias}</span>
                        <span class="alias-chip-badge">${al.total_activos}</span>
                    </div>
                </div>
            `;
        });

        $('#contenedorChipsAlias').html(html);
    }

    // Renderizar Filas de la Tabla
    function renderizarTabla(activos) {
        if (!activos || activos.length === 0) {
            $('#tbodyActivos').empty();
            $('#sinResultadosMensaje').removeClass('d-none');
            $('#checkSelectAll').prop('checked', false);
            return;
        }

        $('#sinResultadosMensaje').addClass('d-none');
        let html = '';

        activos.forEach(item => {
            const isChecked = state.selectedIds.has(item.id_placa) ? 'checked' : '';
            const isRowSelected = isChecked ? 'selected-row' : '';
            const hasAlias = item.alias_id > 0;
            const aliasColor = getAliasColor(item.alias_id);

            const aliasBadge = hasAlias ? `
                <span class="badge-alias-container" style="background-color: ${aliasColor.bg}; color: ${aliasColor.text}; border: 1px solid ${aliasColor.border};" title="${item.alias}">
                    <img src="img/alias/${item.alias_imagen || 'Alias-000.png'}" class="badge-alias-img" onerror="this.src='icons/favicon.ico';">
                    <span>${item.alias}</span>
                </span>
            ` : `
                <span class="badge-alias-container badge-sin-alias">
                    <i class="bi bi-slash-circle"></i> Sin contenedor
                </span>
            `;

            const numberDisabled = hasAlias ? '' : 'disabled';
            const numberVal = item.numero_activo !== null ? item.numero_activo : '';
            const numberTooltip = hasAlias ? 'Correlativo del activo dentro del contenedor' : 'Debe asignar un contenedor para habilitar la numeración';

            const badgeAmbito = item.ambito_nombre ? `
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1 d-inline-flex align-items-center gap-1" style="font-size: 0.73rem;">
                    <i class="bi bi-diagram-3"></i> ${item.ambito_nombre}
                </span>
            ` : `
                <span class="badge bg-warning-subtle text-dark border border-warning-subtle mt-1 d-inline-flex align-items-center gap-1" style="font-size: 0.73rem;" title="Ubicación sin ámbito asociado">
                    <i class="bi bi-exclamation-triangle"></i> Sin ámbito
                </span>
            `;

            html += `
                <tr class="${isRowSelected}" data-id-placa="${item.id_placa}">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input custom-chk chk-placa" value="${item.id_placa}" ${isChecked}>
                    </td>
                    <td><strong class="font-monospace text-primary">${item.placa}</strong></td>
                    <td>
                        <span class="fw-semibold">${item.clase}</span>
                    </td>
                    <td>
                        <div>${item.marca}</div>
                        <small class="text-muted">${item.modelo || '-'}</small>
                    </td>
                    <td><small>${item.color || '-'}</small></td>
                    <td><code class="text-dark">${item.serial || '-'}</code></td>
                    <td>
                        <span class="badge-fondo" title="Origen Presupuestario">${item.fondos}</span>
                    </td>
                    <td>
                        <div><small class="text-muted"><i class="bi bi-geo-alt me-1"></i>${item.lugar}</small></div>
                        <div>${badgeAmbito}</div>
                    </td>
                    <td>${aliasBadge}</td>
                    <td class="text-center">
                        <input type="number" 
                               class="form-control form-control-sm number-input-field mx-auto input-numeracion" 
                               value="${numberVal}" 
                               data-id-placa="${item.id_placa}" 
                               data-original-val="${numberVal}"
                               ${numberDisabled} 
                               title="${numberTooltip}" 
                               placeholder="-">
                    </td>
                </tr>
            `;
        });

        $('#tbodyActivos').html(html);
        actualizarEstadoSelectAll();
    }

    // Renderizar Paginación
    function renderizarPaginacion(pag) {
        const total = pag.total_registros;
        const totalPags = pag.total_paginas;
        const curPage = pag.pagina_actual;

        $('#infoPaginacion').html(`Total: <strong>${total}</strong> activos encontrados | Página <strong>${curPage}</strong> de <strong>${totalPags}</strong>`);

        let html = '';
        if (totalPags > 1) {
            html += `
                <li class="page-item ${curPage === 1 ? 'disabled' : ''}">
                    <a class="page-link btn-page" href="#" data-page="${curPage - 1}">Anterior</a>
                </li>
            `;

            let startPage = Math.max(1, curPage - 2);
            let endPage = Math.min(totalPags, curPage + 2);

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === curPage ? 'active' : ''}">
                        <a class="page-link btn-page" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }

            html += `
                <li class="page-item ${curPage === totalPags ? 'disabled' : ''}">
                    <a class="page-link btn-page" href="#" data-page="${curPage + 1}">Siguiente</a>
                </li>
            `;
        }
        $('#ulPaginacion').html(html);
    }

    // Manejo de Selección de Checkboxes y Barra Flotante
    function actualizarBarraFlotante() {
        const count = state.selectedIds.size;
        $('#contadorSeleccionados').text(`${count} activo${count === 1 ? '' : 's'} seleccionado${count === 1 ? '' : 's'}`);
        $('#modalCantActivos').text(count);

        if (count > 0) {
            $('#barraFlotanteAcciones').addClass('show');
        } else {
            $('#barraFlotanteAcciones').removeClass('show');
        }
    }

    function actualizarEstadoSelectAll() {
        const totalCheckboxes = $('.chk-placa').length;
        const checkedCheckboxes = $('.chk-placa:checked').length;
        $('#checkSelectAll').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
        actualizarBarraFlotante();
    }

    $(document).on('change', '.chk-placa', function() {
        const id = parseInt($(this).val());
        const tr = $(this).closest('tr');
        if (this.checked) {
            state.selectedIds.add(id);
            tr.addClass('selected-row');
        } else {
            state.selectedIds.delete(id);
            tr.removeClass('selected-row');
        }
        actualizarEstadoSelectAll();
    });

    $('#checkSelectAll').on('change', function() {
        const isChecked = this.checked;
        $('.chk-placa').each(function() {
            const id = parseInt($(this).val());
            const tr = $(this).closest('tr');
            $(this).prop('checked', isChecked);
            if (isChecked) {
                state.selectedIds.add(id);
                tr.addClass('selected-row');
            } else {
                state.selectedIds.delete(id);
                tr.removeClass('selected-row');
            }
        });
        actualizarBarraFlotante();
    });

    $('#btnCancelarSeleccion').on('click', function() {
        state.selectedIds.clear();
        $('.chk-placa').prop('checked', false);
        $('#checkSelectAll').prop('checked', false);
        $('#tablaActivos tbody tr').removeClass('selected-row');
        actualizarBarraFlotante();
    });

    // Filtros y Búsqueda con Debounce
    let searchTimeout;
    $('#filtroSearch').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            cargarActivos(true);
        }, 350);
    });

    $('#filtroAmbito, #filtroFondos, #filtroAlias, #filtroClase, #filtroLugar, #selectLimit').on('change', function() {
        cargarActivos(true);
    });

    $('#btnLimpiarFiltros').on('click', function() {
        $('#filtroSearch').val('');
        $('#filtroAmbito').val('');
        $('#filtroFondos').val('');
        $('#filtroAlias').val('');
        $('#filtroClase').val('');
        $('#filtroLugar').val('');
        cargarActivos(true);
    });

    // Clic en Chips de Resumen Superior
    $(document).on('click', '.alias-chip', function() {
        const filterVal = $(this).data('filter-alias');
        $('#filtroAlias').val(filterVal);
        cargarActivos(true);
    });

    // Clic en Paginación
    $(document).on('click', '.btn-page', function(e) {
        e.preventDefault();
        const targetPage = parseInt($(this).data('page'));
        if (targetPage && targetPage !== state.page) {
            state.page = targetPage;
            cargarActivos(false);
            $('html, body').animate({ scrollTop: $('#tablaActivos').offset().top - 80 }, 200);
        }
    });

    $('#btnRecargarTodo').on('click', function() {
        cargarActivos(false);
    });

    // Auto-actualización de Numeración (on blur / change) hacia ajax/
    $(document).on('blur', '.input-numeracion', function() {
        const input = $(this);
        const idPlaca = input.data('id-placa');
        const originalVal = input.data('original-val');
        const newVal = input.val().trim();

        if (originalVal == newVal) return;

        input.prop('disabled', true);
        $.ajax({
            url: 'ajax/ajax_actualizar_alias.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'actualizar_numeracion',
                id_placa: idPlaca,
                numero_activo: newVal
            }),
            success: function(resp) {
                input.prop('disabled', false);
                if (resp.success) {
                    input.data('original-val', newVal);
                    input.addClass('is-valid');
                    setTimeout(() => input.removeClass('is-valid'), 1500);
                } else {
                    input.val(originalVal);
                    Swal.fire('Atención', resp.error || 'No se pudo guardar la numeración', 'warning');
                }
            },
            error: function() {
                input.prop('disabled', false);
                input.val(originalVal);
                Swal.fire('Error', 'Fallo de conexión al actualizar la numeración', 'error');
            }
        });
    });

    // Modal de Selección de Alias
    $('#btnAbrirModalAsignar').on('click', function() {
        if (state.selectedIds.size === 0) return;
        state.selectedAliasDestino = null;
        $('.alias-card-option').removeClass('selected');
        $('#btnConfirmarAsignacion').prop('disabled', true);
        modalAsignar.show();
    });

    $(document).on('click', '.alias-card-option', function() {
        $('.alias-card-option').removeClass('selected');
        $(this).addClass('selected');
        state.selectedAliasDestino = {
            id: $(this).data('alias-id'),
            name: $(this).data('alias-name')
        };
        $('#btnConfirmarAsignacion').prop('disabled', false);
    });

    // Confirmar Asignación en Lote hacia ajax/
    $('#btnConfirmarAsignacion').on('click', function() {
        if (!state.selectedAliasDestino || state.selectedIds.size === 0) return;

        const count = state.selectedIds.size;
        const aliasNombre = state.selectedAliasDestino.name;
        const aliasId = state.selectedAliasDestino.id;

        modalAsignar.hide();

        Swal.fire({
            title: '¿Confirmar asignación?',
            html: `Vas a asignar <strong>${count}</strong> activo(s) al contenedor <strong>"${aliasNombre}"</strong>.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1e2851ff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, asignar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Asignando activos...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: 'ajax/ajax_actualizar_alias.php',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        action: 'asignar_alias',
                        alias_id: aliasId,
                        ids_placas: Array.from(state.selectedIds)
                    }),
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('¡Asignación exitosa!', resp.message, 'success');
                            state.selectedIds.clear();
                            actualizarBarraFlotante();
                            cargarActivos(false);
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo completar la asignación', 'error');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Fallo al procesar la solicitud en el servidor.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            msg = xhr.responseJSON.error;
                        } else if (xhr.responseText) {
                            try {
                                const parsed = JSON.parse(xhr.responseText);
                                if (parsed.error) msg = parsed.error;
                            } catch(e) {
                                // Si es un error PHP html, mostrar texto limpio
                                console.error('Respuesta cruda del servidor:', xhr.responseText);
                            }
                        }
                        Swal.fire('Error', msg, 'error');
                    }
                });
            }
        });
    });

    // Acción Destructiva: Quitar de Contenedor hacia ajax/
    $('#btnQuitarContenedor').on('click', function() {
        if (state.selectedIds.size === 0) return;
        const count = state.selectedIds.size;

        Swal.fire({
            title: '¿Quitar de contenedor?',
            html: `Estás a punto de desvincular <strong>${count}</strong> activo(s) de sus contenedores actuales.<br><small class="text-danger">Quedarán marcados como "Sin contenedor" y su número correlativo será reseteado.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, desvincular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Desvinculando...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: 'ajax/ajax_actualizar_alias.php',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({
                        action: 'quitar_alias',
                        ids_placas: Array.from(state.selectedIds)
                    }),
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('Desvinculados', resp.message, 'success');
                            state.selectedIds.clear();
                            actualizarBarraFlotante();
                            cargarActivos(false);
                        } else {
                            Swal.fire('Error', resp.error || 'No se pudo desvincular los activos', 'error');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Fallo al procesar la solicitud en el servidor.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            msg = xhr.responseJSON.error;
                        } else if (xhr.responseText) {
                            try {
                                const parsed = JSON.parse(xhr.responseText);
                                if (parsed.error) msg = parsed.error;
                            } catch(e) {
                                console.error('Respuesta cruda del servidor:', xhr.responseText);
                            }
                        }
                        Swal.fire('Error', msg, 'error');
                    }
                });
            }
        });
    });

    // Carga inicial
    cargarActivos(true);

});
</script>
</body>
</html>