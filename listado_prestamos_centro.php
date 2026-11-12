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

$lognombre = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$logusuario         = $usuario_azure['cedula'] ?? null;
$logcodigo    = $usuario_azure['codigoPresu'] ?? null; 

require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

// Obtener parámetros de la URL para mantener la navegación
$subsistema_id = isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : null;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : null;
$formulario_id = isset($_GET['formulario_id']) ? intval($_GET['formulario_id']) : null;

// Variables para filtros
$fecha_inicio = isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio']) 
    ? $_GET['fecha_inicio'] 
    : date('Y-m-d', strtotime('-30 days'));

$fecha_fin = isset($_GET['fecha_fin']) && !empty($_GET['fecha_fin']) 
    ? $_GET['fecha_fin'] 
    : date('Y-m-d');

// Validar que fecha_inicio <= fecha_fin
if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
    $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = date('Y-m-d');
}

// Obtener fondos seleccionados (múltiple)
$fondos_seleccionados = isset($_GET['id_fondos']) && !empty($_GET['id_fondos']) 
    ? (is_array($_GET['id_fondos']) ? $_GET['id_fondos'] : [$_GET['id_fondos']])
    : [2]; // Por defecto fondo 2

// Limpiar y validar fondos seleccionados
$fondos_seleccionados = array_filter($fondos_seleccionados, function($f) {
    return is_numeric($f) && $f > 0;
});
$fondos_seleccionados = array_map('intval', $fondos_seleccionados);

// Si no hay fondos seleccionados, usar el fondo 2 por defecto
if (empty($fondos_seleccionados)) {
    $fondos_seleccionados = [2];
}

// Obtener lista de fondos para el selector
$sql_fondos = "SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos";
$result_fondos = mysqli_query($link, $sql_fondos);
$fondos_options = [];
$fondos_nombres = [];
if ($result_fondos) {
    while ($row = mysqli_fetch_assoc($result_fondos)) {
        $fondos_options[] = $row;
        $fondos_nombres[$row['id_fondos']] = $row['fondos'];
    }
}

// Construir la lista de nombres de fondos seleccionados para mostrar
$nombres_fondos_seleccionados = [];
foreach ($fondos_seleccionados as $fid) {
    if (isset($fondos_nombres[$fid])) {
        $nombres_fondos_seleccionados[] = $fondos_nombres[$fid];
    }
}

// Contar cuántos fondos tenemos
$count_fondos = count($fondos_seleccionados);

// Construir los placeholders para la consulta
$placeholders = implode(',', array_fill(0, $count_fondos, '?'));

// =====================================================
// NUEVA ESTRATEGIA: Construir la consulta de una manera
// más simple y robusta
// =====================================================

// Construir las subconsultas por fondo de manera dinámica
$subconsultas_activos = [];
$subconsultas_detalle = [];

foreach ($fondos_seleccionados as $fid) {
    $subconsultas_activos[] = "
        (SELECT COUNT(*)
         FROM t_prestamo_detalle pd{$fid}
         INNER JOIN t_placa pl{$fid} ON pd{$fid}.prestamo_detalle_id_placa = pl{$fid}.id_placa
         WHERE pd{$fid}.prestamo_Id = p.prestamo_Id
           AND pl{$fid}.id_fondos = {$fid}) AS total_activos_fondo_{$fid}
    ";
    
    $subconsultas_detalle[] = "
        (SELECT GROUP_CONCAT(DISTINCT CONCAT(pl{$fid}.placa, ' (', pl{$fid}.serial, ')') SEPARATOR ' | ')
         FROM t_prestamo_detalle pd{$fid}
         INNER JOIN t_placa pl{$fid} ON pd{$fid}.prestamo_detalle_id_placa = pl{$fid}.id_placa
         WHERE pd{$fid}.prestamo_Id = p.prestamo_Id
           AND pl{$fid}.id_fondos = {$fid}) AS detalle_fondo_{$fid}
    ";
}

// Construir la consulta SQL completa
$sql = "
    SELECT DISTINCT
        p.prestamo_Id,
        p.prestamo_fecha,
        p.prestamo_fechaDevolucion,
        p.prestamo_fechaRetiro,
        p.prestamo_horaRetiro,
        p.prestamo_horaDevolucion,
        p.prestamo_uso,
        p.prestamo_cedula_funcionario,
        p.prestamo_nombre_funcionario,
        p.prestamo_codigo_presupuestario,
        p.prestamo_nombre_solicitante,
        p.prestamo_email_solicitante,
        p.prestamo_incidente,
        p.prestamo_incidente_comentario,
        (SELECT COUNT(*)
         FROM t_prestamo_detalle pd2
         INNER JOIN t_placa pl2 ON pd2.prestamo_detalle_id_placa = pl2.id_placa
         WHERE pd2.prestamo_Id = p.prestamo_Id
           AND pl2.id_fondos IN ({$placeholders})) AS total_activos_fondos,
        " . implode(",\n        ", $subconsultas_activos) . ",
        " . implode(",\n        ", $subconsultas_detalle) . "
    FROM t_prestamo p
    INNER JOIN t_prestamo_detalle pd ON p.prestamo_Id = pd.prestamo_Id
    INNER JOIN t_placa pl ON pd.prestamo_detalle_id_placa = pl.id_placa
    WHERE pl.id_fondos IN ({$placeholders})
      AND p.prestamo_codigo_presupuestario = ?
      AND p.prestamo_fecha BETWEEN ? AND ?
    ORDER BY p.prestamo_fecha DESC, p.prestamo_Id DESC
";

// IMPORTANTE: Los placeholders están en dos lugares:
// 1. En la subconsulta total_activos_fondos
// 2. En el WHERE principal
// Por lo tanto necesitamos pasar los fondos DOS VECES

// Construir los parámetros
$parametros = array_merge(
    $fondos_seleccionados,  // Para la subconsulta total_activos_fondos
    $fondos_seleccionados,  // Para el WHERE principal
    [$logcodigo, $fecha_inicio, $fecha_fin]  // Para el resto de condiciones
);

// Construir la cadena de tipos
$tipos = str_repeat('i', $count_fondos * 2) . 'sss';

// DEBUG: Verificar
error_log("=== DEBUG FINAL ===");
error_log("Count fondos: " . $count_fondos);
error_log("Tipos: " . $tipos . " (largo: " . strlen($tipos) . ")");
error_log("Cantidad parámetros: " . count($parametros));
error_log("Parámetros: " . print_r($parametros, true));

// Verificar que coincida
if (count($parametros) !== strlen($tipos)) {
    die("Error CRÍTICO: Número de parámetros (" . count($parametros) . ") no coincide con la cadena de tipos (" . strlen($tipos) . ")<br>
         Tipos: " . $tipos . "<br>
         Parámetros: " . print_r($parametros, true));
}

// Preparar y ejecutar
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, $tipos, ...$parametros);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_prestamos = mysqli_num_rows($result);

// Función para construir la URL base con los parámetros del sistema
function buildBaseUrl($subsistema_id, $modulo_id, $formulario_id) {
    $params = array();
    if ($subsistema_id) $params[] = "subsistema_id=" . $subsistema_id;
    if ($modulo_id) $params[] = "modulo_id=" . $modulo_id;
    if ($formulario_id) $params[] = "formulario_id=" . $formulario_id;
    return "navegar.php?ruta=listado_prestamos_centro.php" . (count($params) > 0 ? "&" . implode("&", $params) : "");
}

$base_url = buildBaseUrl($subsistema_id, $modulo_id, $formulario_id);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=20260924_2">

    <title>Reporte de Préstamos por Centro y Fondos Múltiples</title>

    <style>
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
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .card-header {
            background: linear-gradient(135deg, #1e2851ff 0%, #2a3a7a 100%);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: #e9ecef;
        }
        
        .badge-centro {
            background-color: #dc3545;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            padding: 15px;
            height: 100%;
            border-left: 4px solid #c8ae64ff;
        }
        
        .total-activos-badge {
            font-size: 1.2rem;
            background-color: #198754;
        }

        .fondo-selected-badge {
            background-color: #0d6efd;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
        }

        .footer-note {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .fondo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin: 1px;
        }

        .fondo-badge-2 {
            background-color: #0d6efd;
            color: white;
        }

        .fondo-badge-3 {
            background-color: #198754;
            color: white;
        }

        .fondo-badge-4 {
            background-color: #dc3545;
            color: white;
        }

        .fondo-badge-5 {
            background-color: #fd7e14;
            color: white;
        }

        .fondo-badge-6 {
            background-color: #6f42c1;
            color: white;
        }

        .fondo-badge-default {
            background-color: #6c757d;
            color: white;
        }

        select[multiple] {
            min-height: 100px;
        }

        .badge-fondos-seleccionados {
            background-color: #e9ecef;
            color: #212529;
            padding: 5px 10px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
<?php include 'partials/header.php'; ?>

<div class="container py-5">

    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=1&modulo_id=4" class="btn-flotante-regresar" title="Volver al panel general">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>
    
    <div class="card shadow-lg border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-clipboard-data"></i> 
                Reporte de Préstamos por Centro y Fondos Múltiples
            </h4>
            <span class="badge bg-light text-dark"><?= date('d/m/Y H:i') ?></span>
        </div>
        <div class="card-body">

            <!-- Filtros -->
            <div class="filter-section">
                <form method="GET" action="<?= htmlspecialchars($base_url) ?>" class="row g-3 align-items-end">
                    <!-- Mantener los parámetros del sistema como campos ocultos -->
                    <input type="hidden" name="ruta" value="listado_prestamos_centro.php">
                    <?php if ($subsistema_id) : ?>
                        <input type="hidden" name="subsistema_id" value="<?= htmlspecialchars($subsistema_id) ?>">
                    <?php endif; ?>
                    <?php if ($modulo_id) : ?>
                        <input type="hidden" name="modulo_id" value="<?= htmlspecialchars($modulo_id) ?>">
                    <?php endif; ?>
                    <?php if ($formulario_id) : ?>
                        <input type="hidden" name="formulario_id" value="<?= htmlspecialchars($formulario_id) ?>">
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label for="id_fondos" class="form-label">
                            Fondos Presupuestarios 
                            <small class="text-muted">(Ctrl+Click para múltiple)</small>
                        </label>
                        <select class="form-select" id="id_fondos" name="id_fondos[]" multiple>
                            <?php foreach ($fondos_options as $fondo) : ?>
                                <option value="<?= $fondo['id_fondos'] ?>" 
                                    <?= in_array($fondo['id_fondos'], $fondos_seleccionados) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fondo['fondos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha de inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha de fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                               value="<?= htmlspecialchars($fecha_fin) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                    </div>
                </form>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i>
                            <strong>Filtros activos:</strong>
                            Centro presupuestario: <span class="badge badge-centro"><?= htmlspecialchars($logcodigo) ?></span>
                            Fondos: 
                            <?php if (!empty($nombres_fondos_seleccionados)) : ?>
                                <?php foreach ($nombres_fondos_seleccionados as $nombre) : ?>
                                    <span class="badge fondo-selected-badge"><?= htmlspecialchars($nombre) ?></span>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <span class="badge bg-secondary">Ninguno seleccionado</span>
                            <?php endif; ?>
                            Período: <strong><?= date('d/m/Y', strtotime($fecha_inicio)) ?></strong> - <strong><?= date('d/m/Y', strtotime($fecha_fin)) ?></strong>
                            <a href="<?= htmlspecialchars($base_url) ?>" 
                               class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="bi bi-x-circle"></i> Limpiar filtros
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6><i class="bi bi-list-check"></i> Total préstamos</h6>
                        <p class="display-6 fw-bold text-primary"><?= $total_prestamos ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6><i class="bi bi-tag"></i> Centro</h6>
                        <p class="fw-bold text-danger h4"><?= htmlspecialchars($logcodigo) ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6><i class="bi bi-calendar-range"></i> Período</h6>
                        <p class="fw-bold text-secondary small">
                            <?= date('d/m/Y', strtotime($fecha_inicio)) ?><br>
                            <?= date('d/m/Y', strtotime($fecha_fin)) ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h6><i class="bi bi-printer"></i> Acciones</h6>
                        <div class="btn-group-vertical w-100">
                            <a href="#" class="btn btn-outline-secondary btn-sm" onclick="window.print();">
                                <i class="bi bi-printer"></i> Imprimir
                            </a>
                            <?php if ($total_prestamos > 0) : ?>
                                <a href="#" class="btn btn-outline-success btn-sm mt-1" onclick="exportToCSV();">
                                    <i class="bi bi-file-earmark-excel"></i> Exportar CSV
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de resultados -->
            <?php if ($total_prestamos > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" id="reporteTable">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Préstamo</th>
                                <th>Fecha</th>
                                <th>Retiro</th>
                                <th>Devolución</th>
                                <th>Solicitante</th>
                                <th>Funcionario</th>
                                <th>Centro</th>
                                <th>Total Activos</th>
                                <?php foreach ($fondos_seleccionados as $fid) : ?>
                                    <th>Fondo <?= htmlspecialchars($fid) ?></th>
                                <?php endforeach; ?>
                                <th>Detalle por Fondo</th>
                                <th>Incidente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_activos_general = 0;
                            $activos_por_fondo = array_fill_keys($fondos_seleccionados, 0);
                            
                            while ($row = mysqli_fetch_assoc($result)) : 
                                $total_activos_general += $row['total_activos_fondos'];
                                
                                // Acumular activos por fondo
                                foreach ($fondos_seleccionados as $fid) {
                                    $campo = "total_activos_fondo_{$fid}";
                                    if (isset($row[$campo])) {
                                        $activos_por_fondo[$fid] += $row[$campo];
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['prestamo_Id']) ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($row['prestamo_fecha'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['prestamo_fechaRetiro'])) . ' ' . htmlspecialchars($row['prestamo_horaRetiro']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['prestamo_fechaDevolucion'])) . ' ' . htmlspecialchars($row['prestamo_horaDevolucion']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['prestamo_nombre_solicitante']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($row['prestamo_email_solicitante']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['prestamo_nombre_funcionario']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($row['prestamo_cedula_funcionario']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?= htmlspecialchars($row['prestamo_codigo_presupuestario']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-success total-activos-badge">
                                            <?= $row['total_activos_fondos'] ?>
                                        </span>
                                    </td>
                                    <?php foreach ($fondos_seleccionados as $fid) : ?>
                                        <td>
                                            <?php 
                                            $campo = "total_activos_fondo_{$fid}";
                                            $total = isset($row[$campo]) ? $row[$campo] : 0;
                                            ?>
                                            <span class="badge rounded-pill fondo-badge-<?= $fid % 6 ?>">
                                                <?= $total ?>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <?php 
                                        $detalles = [];
                                        foreach ($fondos_seleccionados as $fid) {
                                            $campo = "detalle_fondo_{$fid}";
                                            if (isset($row[$campo]) && !empty($row[$campo])) {
                                                $detalles[] = "<span class='fondo-badge fondo-badge-{$fid}'>Fondo {$fid}</span>: " . htmlspecialchars($row[$campo]);
                                            }
                                        }
                                        if (!empty($detalles)) {
                                            echo implode('<br>', $detalles);
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($row['prestamo_incidente'] == 1) : ?>
                                            <span class="badge bg-danger">Sí</span>
                                            <?php if (!empty($row['prestamo_incidente_comentario'])) : ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($row['prestamo_incidente_comentario']) ?></small>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="badge bg-success">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <td colspan="7" class="text-end fw-bold">TOTALES POR FONDO:</td>
                                <td><span class="badge rounded-pill bg-success fs-6"><?= $total_activos_general ?></span></td>
                                <?php foreach ($fondos_seleccionados as $fid) : ?>
                                    <td>
                                        <span class="badge rounded-pill fondo-badge-<?= $fid % 6 ?> fs-6">
                                            <?= isset($activos_por_fondo[$fid]) ? $activos_por_fondo[$fid] : 0 ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Resumen adicional -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-graph-up"></i> Resumen del período</h6>
                            <ul class="mb-0">
                                <li><strong>Centro:</strong> <?= htmlspecialchars($logcodigo) ?></li>
                                <li><strong>Fondos seleccionados:</strong> <?= count($fondos_seleccionados) ?></li>
                                <li><strong>Total préstamos:</strong> <?= $total_prestamos ?></li>
                                <li><strong>Total activos (todos los fondos):</strong> <?= $total_activos_general ?></li>
                                <?php foreach ($fondos_seleccionados as $fid) : ?>
                                    <li>
                                        <strong>Fondo <?= htmlspecialchars($fid) ?>:</strong> 
                                        <?= isset($activos_por_fondo[$fid]) ? $activos_por_fondo[$fid] : 0 ?> activos
                                    </li>
                                <?php endforeach; ?>
                                <li><strong>Promedio activos/préstamo:</strong> <?= $total_prestamos > 0 ? number_format($total_activos_general / $total_prestamos, 2) : 0 ?></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-lightbulb"></i> Información</h6>
                            <p class="mb-0 small">
                                <strong>Usuario:</strong> <?= htmlspecialchars($lognombre) ?><br>
                                <strong>Cédula:</strong> <?= htmlspecialchars($logusuario) ?><br>
                                <strong>Relación:</strong> t_placa.codigo ↔ t_prestamo.prestamo_codigo_presupuestario<br>
                                <strong>Filtros:</strong> Centro de sesión y fondos seleccionados<br>
                                <strong>Nota:</strong> Cada préstamo se muestra una sola vez, independientemente de cuántos fondos tenga
                            </p>
                        </div>
                    </div>
                </div>

            <?php else : ?>
                <div class="alert alert-warning text-center">
                    <i class="bi bi-exclamation-triangle"></i> 
                    No se encontraron préstamos para el centro <?= htmlspecialchars($logcodigo) ?> 
                    y los fondos seleccionados en el período indicado.
                    <br><small>Intente con otros fondos o rango de fechas.</small>
                </div>
            <?php endif; ?>

            <!-- Nota final -->
            <div class="footer-note mt-4 p-2 border-top">
                <p class="mb-0"><i class="bi bi-info-circle"></i> 
                <strong>Reporte generado para el centro presupuestario:</strong> <?= htmlspecialchars($logcodigo) ?> | 
                <strong>Fondos:</strong> <?= implode(', ', $nombres_fondos_seleccionados) ?>
                </p>
                <p class="mb-0 mt-1"><i class="bi bi-check-circle"></i> 
                <strong>Deduplicación:</strong> Cada préstamo aparece una sola vez, mostrando el detalle de activos por cada fondo seleccionado.
                </p>
            </div>

            <?php
            mysqli_stmt_close($stmt);
            mysqli_free_result($result);
            if (isset($result_fondos)) mysqli_free_result($result_fondos);
            mysqli_close($link);
            ?>

        </div>
    </div>

</div>

<?php include 'partials/footer.php'; ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

<script>
// Función para exportar a CSV
function exportToCSV() {
    const table = document.getElementById('reporteTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Encabezados
    const headers = [];
    const headerCells = rows[0].querySelectorAll('th');
    headerCells.forEach(cell => {
        headers.push(cell.innerText.trim());
    });
    csv.push(headers.join(','));
    
    // Datos (excluyendo el footer)
    for (let i = 1; i < rows.length - 1; i++) {
        const row = rows[i];
        const cells = row.querySelectorAll('td');
        const rowData = [];
        cells.forEach(cell => {
            let text = cell.innerText.trim().replace(/,/g, ';');
            // Limpiar saltos de línea
            text = text.replace(/\n/g, ' ');
            rowData.push(text);
        });
        csv.push(rowData.join(','));
    }
    
    // Crear y descargar archivo CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    
    // Nombre del archivo con los fondos seleccionados
    const fondosStr = '<?= implode("_", $fondos_seleccionados) ?>';
    link.setAttribute('download', `prestamos_centro_<?= htmlspecialchars($logcodigo) ?>_fondos_${fondosStr}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

</body>
</html>