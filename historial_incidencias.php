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

$lognombre   = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$logusuario  = $usuario_azure['cedula'] ?? null;
$logcodigo   = $usuario_azure['codigoPresu'] ?? null; 

require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8mb4");
date_default_timezone_set('America/Costa_Rica');

// Obtener el ID interno del usuario desde la tabla usuarios mediante su cédula ($logusuario)
$id_usuario_db = null;
if (!empty($logusuario)) {
    $stmtUser = mysqli_prepare($link, "SELECT id, nombre FROM usuarios WHERE cedula = ? AND eliminado = 0 LIMIT 1");
    if ($stmtUser) {
        mysqli_stmt_bind_param($stmtUser, "s", $logusuario);
        mysqli_stmt_execute($stmtUser);
        $resUser = mysqli_stmt_get_result($stmtUser);
        if ($uRow = mysqli_fetch_assoc($resUser)) {
            $id_usuario_db = (int)$uRow['id'];
        }
        mysqli_stmt_close($stmtUser);
    }
}

/**
 * Función auxiliar para obtener tickets según condiciones personalizadas
 */
function obtenerTicketsConDetalles($link, $condicionWhere, $tiposParams = '', $valoresParams = []) {
    $sql = "
        SELECT 
            t.id,
            t.codigo_tkt,
            t.codigo,
            t.usuario_id,
            t.dre_id,
            t.circuito,
            t.dependencia,
            t.tipo,
            t.asunto,
            t.descripcion,
            t.estado_id,
            t.fecha_cierre,
            t.created_at,
            t.updated_at,
            te.estado AS nombre_estado,
            r.nombre AS nombre_regional,
            u.nombre AS nombre_creador,
            u.cedula AS cedula_creador
        FROM tickets t
        LEFT JOIN tickets_estados te ON t.estado_id = te.id
        LEFT JOIN regionales r ON t.dre_id = r.codigo
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE {$condicionWhere} AND t.eliminado = 0
        ORDER BY t.created_at DESC
    ";

    $tickets = [];
    $ticketIds = [];

    if (!empty($tiposParams) && !empty($valoresParams)) {
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, $tiposParams, ...$valoresParams);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        while ($fila = mysqli_fetch_assoc($resultado)) {
            $fila['historial'] = [];
            $fila['activos'] = [];
            $fila['valoracion'] = null;
            $tickets[$fila['id']] = $fila;
            $ticketIds[] = (int)$fila['id'];
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($ticketIds)) {
        return [];
    }

    $idsList = implode(',', $ticketIds);

    // 1. Obtener historial de cada ticket
    $sqlHistorial = "
        SELECT 
            th.id,
            th.ticket_id,
            th.comentario,
            th.estado_id,
            th.nivel_soporte,
            th.created_at,
            te.estado AS nombre_estado
        FROM tickets_historial th
        LEFT JOIN tickets_estados te ON th.estado_id = te.id
        WHERE th.ticket_id IN ({$idsList}) AND th.eliminado = 0
        ORDER BY th.created_at ASC
    ";
    if ($resH = mysqli_query($link, $sqlHistorial)) {
        while ($h = mysqli_fetch_assoc($resH)) {
            if (isset($tickets[$h['ticket_id']])) {
                $tickets[$h['ticket_id']]['historial'][] = $h;
            }
        }
    }

    // 2. Obtener activos asociados
    $sqlActivos = "
        SELECT 
            ta.id,
            ta.ticket_id,
            ta.id_placa,
            ta.observacion_usuario,
            ta.diagnostico_tecnico,
            ta.reparacion_realizada,
            ta.funcionando,
            ta.created_at
        FROM tickets_activos ta
        WHERE ta.ticket_id IN ({$idsList}) AND ta.eliminado = 0
        ORDER BY ta.id ASC
    ";
    if ($resA = mysqli_query($link, $sqlActivos)) {
        while ($a = mysqli_fetch_assoc($resA)) {
            if (isset($tickets[$a['ticket_id']])) {
                $tickets[$a['ticket_id']]['activos'][] = $a;
            }
        }
    }

    // 3. Obtener valoraciones
    $sqlVal = "
        SELECT 
            tv.ticket_id,
            tv.usuario_id,
            tv.puntuacion,
            tv.comentario,
            tv.created_at
        FROM tickets_valoraciones tv
        WHERE tv.ticket_id IN ({$idsList})
    ";
    if ($resV = mysqli_query($link, $sqlVal)) {
        while ($v = mysqli_fetch_assoc($resV)) {
            if (isset($tickets[$v['ticket_id']])) {
                $tickets[$v['ticket_id']]['valoracion'] = $v;
            }
        }
    }

    return array_values($tickets);
}

// 1. Obtener tickets del usuario activo:
// Para usuarios multicentro, se filtra por su id de usuario (obtenido de la tabla usuarios)
// y se restringe al centro educativo actual ($logcodigo) en el que se encuentra logueado.
$ticketsUsuario = [];
if (!empty($id_usuario_db)) {
    if (!empty($logcodigo)) {
        $ticketsUsuario = obtenerTicketsConDetalles(
            $link,
            "t.usuario_id = ? AND t.codigo = ?",
            "is",
            [$id_usuario_db, $logcodigo]
        );
    } else {
        $ticketsUsuario = obtenerTicketsConDetalles(
            $link,
            "t.usuario_id = ?",
            "i",
            [$id_usuario_db]
        );
    }
}

// 2. Obtener todos los tickets del centro educativo activo ($logcodigo)
$ticketsCentro = [];
if (!empty($logcodigo)) {
    $ticketsCentro = obtenerTicketsConDetalles(
        $link,
        "t.codigo = ?",
        "s",
        [$logcodigo]
    );
}

// Función para badges de estados
function renderBadgeEstado($estadoNombre) {
    $estadoNombreTrim = trim($estadoNombre ?? '');
    $estadoLower = mb_strtolower($estadoNombreTrim, 'UTF-8');

    $badgeClass = 'bg-secondary';
    $icono = 'bi-record-circle';

    if (strpos($estadoLower, 'abierto') !== false || strpos($estadoLower, 'nuevo') !== false || strpos($estadoLower, 'ingresado') !== false) {
        $badgeClass = 'bg-primary text-white';
        $icono = 'bi-folder-plus';
    } elseif (strpos($estadoLower, 'proceso') !== false || strpos($estadoLower, 'asignado') !== false || strpos($estadoLower, 'atención') !== false || strpos($estadoLower, 'revision') !== false) {
        $badgeClass = 'bg-warning text-dark';
        $icono = 'bi-hourglass-split';
    } elseif (strpos($estadoLower, 'resuelto') !== false || strpos($estadoLower, 'solucionado') !== false) {
        $badgeClass = 'bg-info text-white';
        $icono = 'bi-check2-circle';
    } elseif (strpos($estadoLower, 'cerrado') !== false || strpos($estadoLower, 'finalizado') !== false) {
        $badgeClass = 'bg-success text-white';
        $icono = 'bi-check-all';
    } elseif (strpos($estadoLower, 'rechazado') !== false || strpos($estadoLower, 'anulado') !== false || strpos($estadoLower, 'cancelado') !== false) {
        $badgeClass = 'bg-danger text-white';
        $icono = 'bi-x-circle';
    }

    return sprintf(
        '<span class="badge %s px-2.5 py-1.5 rounded-pill fw-semibold"><i class="bi %s me-1"></i>%s</span>',
        $badgeClass,
        $icono,
        htmlspecialchars($estadoNombreTrim ?: 'Sin estado')
    );
}

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

    <title>Historial de Tickets y Estados</title>

    <style>
        :root {
            --mep-azul: #1e2851;
            --mep-dorado: #c8ae64;
            --mep-dorado-hover: #b39b55;
            --mep-azul-suave: #2a386c;
        }

        body {
            background-color: #f4f6f9;
            color: #333;
        }

        .header-card {
            background: linear-gradient(135deg, var(--mep-azul) 0%, var(--mep-azul-suave) 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 24px 30px;
            box-shadow: 0 4px 18px rgba(30, 40, 81, 0.15);
            margin-bottom: 25px;
            border-left: 6px solid var(--mep-dorado);
        }

        .header-card h2 {
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .nav-pills-custom {
            background: #ffffff;
            padding: 6px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            display: inline-flex;
            gap: 6px;
        }

        .nav-pills-custom .nav-link {
            border-radius: 9px;
            color: #4a5568;
            font-weight: 600;
            padding: 10px 22px;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-pills-custom .nav-link:hover {
            color: var(--mep-azul);
            background-color: #edf2f7;
        }

        .nav-pills-custom .nav-link.active {
            background-color: var(--mep-azul);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(30, 40, 81, 0.25);
        }

        .nav-pills-custom .nav-link .badge-counter {
            background-color: rgba(0,0,0,0.08);
            color: #4a5568;
            border-radius: 20px;
            font-size: 0.78rem;
            padding: 3px 8px;
        }

        .nav-pills-custom .nav-link.active .badge-counter {
            background-color: var(--mep-dorado);
            color: #1e2851;
            font-weight: 700;
        }

        .card-tickets {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            background-color: #ffffff;
            overflow: hidden;
        }

        .table-custom {
            margin-bottom: 0;
        }

        .table-custom thead th {
            background-color: #fafbfc;
            color: #5a6a85;
            font-weight: 600;
            font-size: 0.83rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7;
            padding: 14px 18px;
            white-space: nowrap;
        }

        .table-custom tbody tr {
            transition: background-color 0.15s ease;
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        .table-custom tbody td {
            padding: 14px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f4f8;
            font-size: 0.92rem;
        }

        .ticket-code {
            font-weight: 700;
            color: var(--mep-azul);
            font-size: 0.95rem;
            font-family: monospace;
        }

        .btn-detalle {
            background-color: #f0f4ff;
            color: var(--mep-azul);
            border: 1px solid #d4e0fc;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-detalle:hover {
            background-color: var(--mep-azul);
            color: #ffffff;
            border-color: var(--mep-azul);
            box-shadow: 0 3px 8px rgba(30, 40, 81, 0.25);
        }

        /* Timeline en Modal */
        .timeline {
            position: relative;
            padding-left: 28px;
            list-style: none;
            margin: 0;
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 6px;
            bottom: 6px;
            width: 3px;
            background: #e2e8f0;
            border-radius: 2px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 22px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            left: -28px;
            top: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffffff;
            border: 3px solid var(--mep-azul);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .timeline-marker.active {
            border-color: var(--mep-dorado);
            background: var(--mep-dorado);
        }

        .timeline-content {
            background: #f8fafc;
            border: 1px solid #e9edf3;
            border-radius: 8px;
            padding: 12px 16px;
        }

        .timeline-date {
            font-size: 0.78rem;
            color: #718096;
            margin-bottom: 4px;
        }

        .timeline-comment {
            font-size: 0.9rem;
            color: #2d3748;
            margin: 0;
            white-space: pre-line;
        }

        /* Modal styling */
        .modal-header-custom {
            background: linear-gradient(135deg, var(--mep-azul) 0%, var(--mep-azul-suave) 100%);
            color: #ffffff;
            border-bottom: 3px solid var(--mep-dorado);
        }

        .modal-header-custom .btn-close {
            filter: brightness(0) invert(1);
        }

        .info-pill {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }

        .info-pill-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-pill-value {
            font-size: 0.92rem;
            font-weight: 600;
            color: #1e293b;
        }

        .star-rating {
            color: #f59e0b;
            font-size: 1.1rem;
        }

        .btn-flotante-regresar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #1e2851;
            color: #fff;
            border: 2px solid #c8ae64;
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
            background: #c8ae64;
            border-color: #1e2851;
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

        .search-box {
            position: relative;
            max-width: 320px;
            width: 100%;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            padding-left: 38px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
        }

        .search-box input:focus {
            border-color: var(--mep-azul);
            box-shadow: 0 0 0 3px rgba(30, 40, 81, 0.15);
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 12px;
        }
        
        .header-card .bg-white.bg-opacity-10 small,
        .header-card .bg-white.bg-opacity-10 strong {
            color: #000000 !important;
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/partials/header.php')) { include 'partials/header.php'; } ?>

<div class="container py-4">

    <!-- Tarjeta de Encabezado Principal -->
    <div class="header-card d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2><i class="bi bi-ticket-perforated-fill me-2" style="color: var(--mep-dorado);"></i>Historial de Tickets y Estados</h2>
            <p class="mb-0 text-white-50">
                Consulta y seguimiento en tiempo real del estado de tus solicitudes y las del centro educativo.
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-3">
            <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-25">
                <small class="d-block text-white-50">Usuario Activo</small>
                <strong><?= htmlspecialchars($lognombre ?: $logusuario) ?></strong>
            </div>
            <?php if (!empty($logcodigo)): ?>
            <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-25">
                <small class="d-block text-white-50">Cód. Presupuestario</small>
                <strong><?= htmlspecialchars($logcodigo) ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navegación por Pestañas y Buscador -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <ul class="nav nav-pills-custom" id="ticketTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mis-tickets-tab" data-bs-toggle="pill" data-bs-target="#mis-tickets" type="button" role="tab" aria-controls="mis-tickets" aria-selected="true">
                    <i class="bi bi-person-fill"></i>
                    <span>Mis Tickets</span>
                    <span class="badge-counter"><?= count($ticketsUsuario) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="centro-tickets-tab" data-bs-toggle="pill" data-bs-target="#centro-tickets" type="button" role="tab" aria-controls="centro-tickets" aria-selected="false">
                    <i class="bi bi-building"></i>
                    <span>Historial del Centro Educativo</span>
                    <span class="badge-counter"><?= count($ticketsCentro) ?></span>
                </button>
            </li>
        </ul>

        <!-- Buscador en tiempo real -->
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="filtroTickets" class="form-control form-control-sm" placeholder="Buscar por código, asunto o estado...">
        </div>
    </div>

    <!-- Contenido de las Pestañas -->
    <div class="tab-content" id="ticketTabsContent">
        
        <!-- PESTAÑA 1: Mis Tickets (Usuario_id) -->
        <div class="tab-pane fade show active" id="mis-tickets" role="tabpanel" aria-labelledby="mis-tickets-tab">
            <div class="card card-tickets">
                <?php if (empty($ticketsUsuario)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox d-block"></i>
                        <h5>No has registrado ningún ticket aún</h5>
                        <p class="text-muted">Cuando reportes un requerimiento o falla técnica, aparecerá registrado en este apartado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle tabla-busqueda">
                            <thead>
                                <tr>
                                    <th>Cód. Ticket</th>
                                    <th>Fecha</th>
                                    <th>Asunto</th>
                                    <th>DRE / Circuito</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ticketsUsuario as $idx => $tkt): ?>
                                <tr>
                                    <td>
                                        <span class="ticket-code"><?= htmlspecialchars($tkt['codigo_tkt'] ?: ('#' . $tkt['id'])) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= !empty($tkt['created_at']) ? date('d/m/Y', strtotime($tkt['created_at'])) : '-' ?></div>
                                        <small class="text-muted"><?= !empty($tkt['created_at']) ? date('h:i A', strtotime($tkt['created_at'])) : '' ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($tkt['asunto']) ?>">
                                            <?= htmlspecialchars($tkt['asunto']) ?>
                                        </div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 280px;">
                                            <?= htmlspecialchars($tkt['dependencia'] ?: 'Sin dependencia especificada') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div><i class="bi bi-geo-alt text-muted me-1"></i><?= htmlspecialchars($tkt['nombre_regional'] ?: ('DRE ' . $tkt['dre_id'])) ?></div>
                                        <?php if (!empty($tkt['circuito'])): ?>
                                            <small class="text-muted">Circuito: <?= htmlspecialchars($tkt['circuito']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= renderBadgeEstado($tkt['nombre_estado']) ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-detalle" onclick='abrirDetalleTicket(<?= json_encode($tkt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-eye"></i> Ver Detalle
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PESTAÑA 2: Tickets del Centro Educativo (codigo) -->
        <div class="tab-pane fade" id="centro-tickets" role="tabpanel" aria-labelledby="centro-tickets-tab">
            <div class="card card-tickets">
                <?php if (empty($ticketsCentro)): ?>
                    <div class="empty-state">
                        <i class="bi bi-building-slash d-block"></i>
                        <h5>No hay registros para este Centro Educativo</h5>
                        <p class="text-muted">
                            <?php if (!empty($logcodigo)): ?>
                                No se encontraron tickets asociados al código presupuestario <strong><?= htmlspecialchars($logcodigo) ?></strong>.
                            <?php else: ?>
                                Su usuario no tiene configurado un código presupuestario válido.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle tabla-busqueda">
                            <thead>
                                <tr>
                                    <th>Cód. Ticket</th>
                                    <th>Fecha</th>
                                    <th>Usuario / Cédula</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ticketsCentro as $idx => $tkt): ?>
                                <tr>
                                    <td>
                                        <span class="ticket-code"><?= htmlspecialchars($tkt['codigo_tkt'] ?: ('#' . $tkt['id'])) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= !empty($tkt['created_at']) ? date('d/m/Y', strtotime($tkt['created_at'])) : '-' ?></div>
                                        <small class="text-muted"><?= !empty($tkt['created_at']) ? date('h:i A', strtotime($tkt['created_at'])) : '' ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($tkt['nombre_creador'] ?: ('Usuario #' . $tkt['usuario_id'])) ?>
                                        </div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 220px;">
                                            <?= !empty($tkt['cedula_creador']) ? ('Céd: ' . htmlspecialchars($tkt['cedula_creador'])) : htmlspecialchars($tkt['dependencia'] ?: '') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($tkt['asunto']) ?>">
                                            <?= htmlspecialchars($tkt['asunto']) ?>
                                        </div>
                                        <small class="text-muted">Centro: <?= htmlspecialchars($tkt['codigo']) ?></small>
                                    </td>
                                    <td>
                                        <?= renderBadgeEstado($tkt['nombre_estado']) ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-detalle" onclick='abrirDetalleTicket(<?= json_encode($tkt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-eye"></i> Ver Detalle
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Botón Flotante para Regresar -->
    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=1&modulo_id=3" class="btn-flotante-regresar" title="Volver al panel general">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

</div>

<!-- MODAL PARA DETALLE DEL TICKET -->
<div class="modal fade" id="modalDetalleTicket" tabindex="-1" aria-labelledby="modalDetalleTicketLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalDetalleTicketLabel">
                    <i class="bi bi-ticket-detailed-fill" style="color: var(--mep-dorado);"></i>
                    Detalle del Ticket <span id="m_codigo_tkt" class="badge bg-light text-dark fw-bold ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                
                <!-- Datos Generales -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="info-pill">
                            <div class="info-pill-label">Estado Actual</div>
                            <div id="m_estado" class="info-pill-value">-</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="info-pill">
                            <div class="info-pill-label">Fecha Creación</div>
                            <div id="m_fecha_creacion" class="info-pill-value">-</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="info-pill">
                            <div class="info-pill-label">Fecha Cierre</div>
                            <div id="m_fecha_cierre" class="info-pill-value">-</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="info-pill">
                            <div class="info-pill-label">Regional / DRE</div>
                            <div id="m_regional" class="info-pill-value">-</div>
                        </div>
                    </div>
                </div>

                <!-- Asunto y Descripción -->
                <div class="card mb-4 border-0" style="background-color: #f8fafc; border-left: 4px solid var(--mep-azul) !important;">
                    <div class="card-body">
                        <h6 class="fw-bold text-dark mb-1" id="m_asunto">-</h6>
                        <div class="text-muted small mb-2" id="m_dependencia"></div>
                        <hr class="my-2 text-muted">
                        <p class="mb-0 text-secondary" id="m_descripcion" style="white-space: pre-line;">-</p>
                    </div>
                </div>

                <!-- Historial de Estados y Comentarios -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-clock-history text-primary me-2"></i>Historial de Estados y Seguimiento
                    </h6>
                    <div id="m_timeline_container">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                <!-- Activos Asociados -->
                <div class="mb-4" id="m_activos_section">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-laptop text-primary me-2"></i>Activos Relacionados
                    </h6>
                    <div id="m_activos_container" class="table-responsive">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                <!-- Valoración del Servicio -->
                <div id="m_valoracion_section" class="card border-0" style="background-color: #fff9e6; border: 1px solid #fed7aa !important;">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2 text-dark">
                            <i class="bi bi-star-fill text-warning me-1"></i> Valoración del Usuario
                        </h6>
                        <div id="m_valoracion_estrellas" class="star-rating mb-2"></div>
                        <p class="mb-0 text-secondary fst-italic" id="m_valoracion_comentario"></p>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/partials/footer.php')) { include 'partials/footer.php'; } ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script>
    // Filtro en tiempo real de tablas
    document.getElementById('filtroTickets').addEventListener('keyup', function() {
        const busqueda = this.value.toLowerCase().trim();
        const filas = document.querySelectorAll('.tabla-busqueda tbody tr');

        filas.forEach(fila => {
            const textoFila = fila.innerText.toLowerCase();
            if (textoFila.includes(busqueda)) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });
    });

    // Función para poblar y abrir el Modal de Detalle
    function abrirDetalleTicket(ticket) {
        // Códigos y Título
        document.getElementById('m_codigo_tkt').innerText = ticket.codigo_tkt ? ticket.codigo_tkt : ('#' + ticket.id);
        document.getElementById('m_asunto').innerText = ticket.asunto || 'Sin Asunto';
        document.getElementById('m_dependencia').innerText = (ticket.dependencia ? ('Dependencia: ' + ticket.dependencia) : '') + (ticket.circuito ? (' | Circuito: ' + ticket.circuito) : '');
        document.getElementById('m_descripcion').innerText = ticket.descripcion || 'Sin descripción adicional proporcionada.';
        
        // Datos generales
        document.getElementById('m_estado').innerText = ticket.nombre_estado || 'N/A';
        document.getElementById('m_fecha_creacion').innerText = ticket.created_at ? formatearFecha(ticket.created_at) : 'N/A';
        document.getElementById('m_fecha_cierre').innerText = ticket.fecha_cierre ? formatearFecha(ticket.fecha_cierre) : 'En atención';
        document.getElementById('m_regional').innerText = ticket.nombre_regional || (ticket.dre_id ? 'DRE ' + ticket.dre_id : 'N/A');

        // Renderizar Timeline de Historial
        const timelineContainer = document.getElementById('m_timeline_container');
        if (ticket.historial && ticket.historial.length > 0) {
            let htmlTimeline = '<ul class="timeline">';
            ticket.historial.forEach((item, index) => {
                const isLast = (index === ticket.historial.length - 1);
                htmlTimeline += `
                    <li class="timeline-item">
                        <div class="timeline-marker ${isLast ? 'active' : ''}"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-secondary">${escapeHtml(item.nombre_estado || 'Estado registrado')}</span>
                                <span class="timeline-date"><i class="bi bi-clock me-1"></i>${formatearFecha(item.created_at)}</span>
                            </div>
                            <p class="timeline-comment">${escapeHtml(item.comentario || 'Actualización de estado.')}</p>
                            ${item.nivel_soporte ? `<small class="text-muted d-block mt-1"><i class="bi bi-person-gear me-1"></i>Soporte Nivel ${item.nivel_soporte}</small>` : ''}
                        </div>
                    </li>
                `;
            });
            htmlTimeline += '</ul>';
            timelineContainer.innerHTML = htmlTimeline;
        } else {
            timelineContainer.innerHTML = `
                <div class="text-muted small fst-italic p-2 bg-light rounded">
                    No se registran transiciones de historial adicionales para este ticket.
                </div>
            `;
        }

        // Renderizar Activos
        const activosSection = document.getElementById('m_activos_section');
        const activosContainer = document.getElementById('m_activos_container');
        if (ticket.activos && ticket.activos.length > 0) {
            activosSection.style.display = 'block';
            let htmlActivos = `
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Placa</th>
                            <th>Observación Usuario</th>
                            <th>Diagnóstico Técnico</th>
                            <th>Reparación</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            ticket.activos.forEach(act => {
                const funcionaBadge = (parseInt(act.funcionando) === 1)
                    ? '<span class="badge bg-success"><i class="bi bi-check me-1"></i>Operativo</span>'
                    : '<span class="badge bg-danger"><i class="bi bi-x me-1"></i>No Funciona</span>';

                htmlActivos += `
                    <tr>
                        <td class="fw-bold text-primary">${escapeHtml(act.id_placa)}</td>
                        <td><small>${escapeHtml(act.observacion_usuario || '-')}</small></td>
                        <td><small>${escapeHtml(act.diagnostico_tecnico || '-')}</small></td>
                        <td><small>${escapeHtml(act.reparacion_realizada || '-')}</small></td>
                        <td class="text-center">${funcionaBadge}</td>
                    </tr>
                `;
            });
            htmlActivos += '</tbody></table>';
            activosContainer.innerHTML = htmlActivos;
        } else {
            activosSection.style.display = 'none';
        }

        // Renderizar Valoración
        const valSection = document.getElementById('m_valoracion_section');
        const valEstrellas = document.getElementById('m_valoracion_estrellas');
        const valComentario = document.getElementById('m_valoracion_comentario');

        if (ticket.valoracion) {
            valSection.style.display = 'block';
            const puntos = parseInt(ticket.valoracion.puntuacion) || 0;
            let estrellasHtml = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= puntos) {
                    estrellasHtml += '<i class="bi bi-star-fill text-warning"></i> ';
                } else {
                    estrellasHtml += '<i class="bi bi-star text-muted"></i> ';
                }
            }
            valEstrellas.innerHTML = estrellasHtml + ` <span class="text-muted small">(${puntos}/5)</span>`;
            valComentario.innerText = ticket.valoracion.comentario ? `"${ticket.valoracion.comentario}"` : 'Sin comentarios adicionales.';
        } else {
            valSection.style.display = 'none';
        }

        // Mostrar Modal
        const modal = new bootstrap.Modal(document.getElementById('modalDetalleTicket'));
        modal.show();
    }

    function formatearFecha(fechaStr) {
        if (!fechaStr) return '-';
        const d = new Date(fechaStr.replace(/-/g, "/"));
        if (isNaN(d.getTime())) return fechaStr;
        return d.toLocaleDateString('es-CR', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function escapeHtml(texto) {
        if (!texto) return '';
        return String(texto)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
</body>
</html>
