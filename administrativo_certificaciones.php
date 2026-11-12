<?php
/**
 * administrativo_certificaciones.php
 *
 * Vista administrativa de certificaciones entregadas y pendientes.
 * Refactorizado: paginación real en SQL, JOIN a t_fondos,
 * manejo de errores, barra de progreso calculada en PHP,
 * resumen de estado y UX mejorada.
 */

/** session_start();

// Verificar permisos (tipos 1 y 2)
if (!in_array($_SESSION['tipo'] ?? null, [1, 2])) {
    echo '<script>alert("No tienes permisos"); self.location = "index.html";</script>';
    exit;
} */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

$logcodigo     = $usuario_azure['codigoPresu'] ?? null;
$lognombre     = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$loginstitucion = $usuario_azure['dependencia'] ?? null;

// ============================================================
// FILTROS Y PAGINACIÓN
// ============================================================
$filtro_fuente = isset($_GET['fuente']) && $_GET['fuente'] !== '' ? (int)$_GET['fuente'] : null;
$filtro_anio   = isset($_GET['anio'])   && $_GET['anio']   !== '' ? (int)$_GET['anio']   : null;
$pagina        = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina    = 15;
$offset        = ($pagina - 1) * $por_pagina;

// ============================================================
// HELPER: ejecutar prepared statement con manejo de errores
// ============================================================
function ejecutar_stmt(mysqli $link, string $sql, string $types, array $params): ?mysqli_result
{
    $stmt = $link->prepare($sql);
    if (!$stmt) {
        error_log("Error preparando consulta: " . $link->error . " | SQL: $sql");
        return null;
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("Error ejecutando consulta: " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// ============================================================
// TOTALES GLOBALES (para tarjetas de resumen, sin paginar)
// ============================================================
$sql_total_pendientes = "
    SELECT COUNT(*) AS total
    FROM certificaciones_pendientes
    WHERE centro_id = ? AND expira_en > NOW()
";
$res = ejecutar_stmt($link, $sql_total_pendientes, "s", [$logcodigo]);
$total_pendientes_global = $res ? (int)$res->fetch_assoc()['total'] : 0;

$sql_total_entregadas = "
    SELECT COUNT(*) AS total
    FROM certificaciones
    WHERE centro_id = ?
";
$res = ejecutar_stmt($link, $sql_total_entregadas, "s", [$logcodigo]);
$total_entregadas_global = $res ? (int)$res->fetch_assoc()['total'] : 0;

$sql_total_activos = "
    SELECT COALESCE(SUM(cd.total_activos), 0) AS total
    FROM certificaciones c
    LEFT JOIN (
        SELECT certificacion_id, COUNT(*) AS total_activos
        FROM certificaciones_detalle
        GROUP BY certificacion_id
    ) cd ON cd.certificacion_id = c.id
    WHERE c.centro_id = ?
";
$res = ejecutar_stmt($link, $sql_total_activos, "s", [$logcodigo]);
$total_activos_global = $res ? (int)$res->fetch_assoc()['total'] : 0;

// ============================================================
// CONSULTAS PRINCIPALES (con filtros)
// ============================================================

// Construcción dinámica de cláusulas WHERE compartidas
$where_c  = "c.centro_id = ?";
$where_p  = "p.centro_id = ? AND p.expira_en > NOW()";
$params_c = [$logcodigo];
$params_p = [$logcodigo];
$types_c  = "s";
$types_p  = "s";

if ($filtro_fuente) {
    $where_c  .= " AND c.fuente_presupuestaria_id = ?";
    $where_p  .= " AND p.fuente_presupuestaria_id = ?";
    $params_c[] = $filtro_fuente;
    $params_p[] = $filtro_fuente;
    $types_c  .= "i";
    $types_p  .= "i";
}
if ($filtro_anio) {
    $where_c  .= " AND YEAR(c.fecha_certificacion) = ?";
    $where_p  .= " AND YEAR(p.fecha_generacion) = ?";
    $params_c[] = $filtro_anio;
    $params_p[] = $filtro_anio;
    $types_c  .= "i";
    $types_p  .= "i";
}

// ----- CONTAR TOTALES FILTRADOS (para paginación real) -----
$sql_count = "
    SELECT (
        SELECT COUNT(*) FROM certificaciones c WHERE $where_c
    ) + (
        SELECT COUNT(*) FROM certificaciones_pendientes p WHERE $where_p
    ) AS total
";
$res = ejecutar_stmt(
    $link,
    $sql_count,
    $types_c . $types_p,
    array_merge($params_c, $params_p)
);
$total_registros = $res ? (int)$res->fetch_assoc()['total'] : 0;
$total_paginas   = max(1, (int)ceil($total_registros / $por_pagina));
$pagina          = min($pagina, $total_paginas);
$offset          = ($pagina - 1) * $por_pagina;

// ----- CERTIFICACIONES ENTREGADAS (JOIN a t_fondos) -----
$sql_entregadas = "
    SELECT
        c.id,
        c.certificacion_uuid,
        c.fuente_presupuestaria_id,
        f.fondos AS fuente_presupuestaria_nombre,
        c.funcionario_nombre,
        c.fecha_certificacion,
        c.pdf_nombre,
        'entregada' AS tipo,
        (SELECT COUNT(*) FROM certificaciones_detalle WHERE certificacion_id = c.id) AS total_activos
    FROM certificaciones c
    INNER JOIN t_fondos f ON f.id_fondos = c.fuente_presupuestaria_id
    WHERE $where_c
    ORDER BY c.fecha_certificacion DESC
";
$res_e     = ejecutar_stmt($link, $sql_entregadas, $types_c, $params_c);
$entregadas = $res_e ? $res_e->fetch_all(MYSQLI_ASSOC) : [];

// ----- CERTIFICACIONES PENDIENTES (JOIN a t_fondos) -----
$sql_pendientes = "
    SELECT
        p.certificacion_uuid,
        p.fuente_presupuestaria_id,
        f.fondos AS fuente_presupuestaria_nombre,
        p.funcionario_nombre,
        p.fecha_generacion AS fecha,
        'pendiente' AS tipo,
        JSON_LENGTH(p.activos_json) AS total_activos,
        p.expira_en,
        TIMESTAMPDIFF(SECOND, NOW(), p.expira_en)       AS segundos_restantes,
        TIMESTAMPDIFF(SECOND, p.fecha_generacion, p.expira_en) AS segundos_total
    FROM certificaciones_pendientes p
    INNER JOIN t_fondos f ON f.id_fondos = p.fuente_presupuestaria_id
    WHERE $where_p
    ORDER BY p.fecha_generacion DESC
";
$res_p     = ejecutar_stmt($link, $sql_pendientes, $types_p, $params_p);
$pendientes = $res_p ? $res_p->fetch_all(MYSQLI_ASSOC) : [];

// ============================================================
// AGRUPAR POR FUENTE (pendientes primero dentro de cada grupo)
// ============================================================
$grupos = [];

foreach ($pendientes as $p) {
    $fid = $p['fuente_presupuestaria_id'];
    if (!isset($grupos[$fid])) {
        $grupos[$fid] = ['nombre' => $p['fuente_presupuestaria_nombre'], 'pendientes' => [], 'entregadas' => []];
    }
    // Calcular porcentaje de tiempo restante para la barra de progreso
    $p['pct_restante'] = ($p['segundos_total'] > 0)
        ? max(0, min(100, round(($p['segundos_restantes'] / $p['segundos_total']) * 100)))
        : 0;
    $grupos[$fid]['pendientes'][] = $p;
}

foreach ($entregadas as $e) {
    $fid = $e['fuente_presupuestaria_id'];
    if (!isset($grupos[$fid])) {
        $grupos[$fid] = ['nombre' => $e['fuente_presupuestaria_nombre'], 'pendientes' => [], 'entregadas' => []];
    }
    $grupos[$fid]['entregadas'][] = $e;
}

// Ordenar grupos por nombre de fuente
uasort($grupos, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

// Aplicar paginación simple sobre grupos completos
// (se paginan registros totales, se muestran grupos que contengan registros en el rango)
$registro_inicio = $offset + 1;
$registro_fin    = min($offset + $por_pagina, $total_registros);

// ============================================================
// FILTROS: fuentes y años disponibles (desde t_fondos + datos)
// ============================================================
$sql_fuentes = "
    SELECT DISTINCT f.id_fondos, f.fondos
    FROM t_fondos f
    WHERE f.id_fondos IN (
        SELECT fuente_presupuestaria_id FROM certificaciones WHERE centro_id = ?
        UNION
        SELECT fuente_presupuestaria_id FROM certificaciones_pendientes WHERE centro_id = ? AND expira_en > NOW()
    )
    ORDER BY f.fondos
";
$res_f            = ejecutar_stmt($link, $sql_fuentes, "ss", [$logcodigo, $logcodigo]);
$fuentes_disponibles = $res_f ? $res_f->fetch_all(MYSQLI_ASSOC) : [];

$sql_anios = "
    SELECT DISTINCT YEAR(fecha_certificacion) AS anio FROM certificaciones WHERE centro_id = ?
    UNION
    SELECT DISTINCT YEAR(fecha_generacion) AS anio FROM certificaciones_pendientes WHERE centro_id = ? AND expira_en > NOW()
    ORDER BY anio DESC
";
$res_a           = ejecutar_stmt($link, $sql_anios, "ss", [$logcodigo, $logcodigo]);
$anios_disponibles = $res_a ? $res_a->fetch_all(MYSQLI_ASSOC) : [];

// ============================================================
// HELPER: construir URL de paginación conservando filtros
// ============================================================
function pag_url(int $pagina, ?int $fuente, ?int $anio): string
{
    $params = ['pagina' => $pagina];
    if ($fuente) $params['fuente'] = $fuente;
    if ($anio)   $params['anio']   = $anio;
    return '?' . http_build_query($params);
}

// ============================================================
// HELPER: color de la barra de progreso según tiempo restante
// ============================================================
function color_barra(int $pct): string
{
    if ($pct > 50) return '#3B6D11'; // verde
    if ($pct > 20) return '#BA7517'; // ámbar
    return '#A32D2D';                // rojo urgente
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
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <title>Administrativo de Certificaciones</title>
    <style>
        body {
            font-family: 'Henderson Sans', sans-serif;
            background-color: #f5f5f5;
        }

        /* ── Resumen de estado ── */
        .stat-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        .stat-card .stat-label {
            font-size: 0.78rem;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            line-height: 1;
        }

        /* ── Filtros ── */
        .filtros-card {
            background: #fff;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }

        /* ── Grupos de fuente ── */
        .grupo-fuente {
            border-left: 4px solid #0d6efd;
            margin-bottom: 2rem;
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .grupo-fuente h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }

        /* ── Subtítulos de sección ── */
        .subtitulo-grupo {
            font-size: 0.85rem;
            color: #6c757d;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.4rem;
            margin-top: 1rem;
            margin-bottom: 0.75rem;
        }

        /* ── Tarjetas de certificación ── */
        .card-certificacion {
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: box-shadow 0.15s ease;
        }
        .card-certificacion:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,.1);
        }
        .card-certificacion.pendiente {
            border-left: 4px solid #ffc107;
        }
        .card-certificacion.entregada {
            border-left: 4px solid #28a745;
        }

        /* ── Badges ── */
        .badge-pendiente { background-color: #fff3cd; color: #856404; }
        .badge-entregada { background-color: #d1e7dd; color: #0a3622; }

        /* ── Barra de tiempo restante ── */
        .barra-tiempo-wrap {
            background: #e9ecef;
            border-radius: 20px;
            height: 6px;
            width: 100%;
            overflow: hidden;
            margin-top: 5px;
        }
        .barra-tiempo-fill {
            height: 100%;
            border-radius: 20px;
            transition: width 0.3s ease;
        }
        .expira-texto {
            font-size: 0.78rem;
            color: #6c757d;
            margin-top: 2px;
        }
        .expira-texto.urgente { color: #dc3545; font-weight: 600; }
        .expira-texto.aviso   { color: #856404; }

        /* ── Identificador principal (nombre > uuid) ── */
        .cert-nombre {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .cert-uuid {
            font-family: monospace;
            font-size: 0.75rem;
            color: #6c757d;
        }
        .cert-meta-row {
            font-size: 0.82rem;
            color: #6c757d;
            margin-top: 2px;
        }

        /* ── Botón descarga ── */
        .btn-descargar {
            font-size: 0.82rem;
        }
        .nota-copia {
            font-size: 0.73rem;
            color: #6c757d;
            display: block;
            margin-top: 3px;
            text-align: end;
        }

        /* ── Estado vacío ── */
        .sin-certificaciones {
            text-align: center;
            padding: 3rem;
            background: #fff;
            border-radius: 10px;
            color: #6c757d;
        }

        /* ============================================
           BOTÓN FLOTANTE REGRESAR
           ============================================ */
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

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .btn-flotante-regresar {
                bottom: 20px;
                right: 20px;
                width: 48px;
                height: 48px;
            }
            .btn-flotante-regresar i {
                font-size: 24px;
            }
        }

        /* ── Tooltip uuid ── */
        code[title] { cursor: help; }
    </style>
</head>
<body>
<?php include 'partials/header.php'; ?>

<div class="container mb-5">

    <a href="navegar.php?ruta=centro_de_certificacion.php" class="btn-flotante-regresar" title="Volver a certificación">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h2 class="mb-0">
            <i class="bi bi-archive"></i> Administrativo de Certificaciones
        </h2>
        <span class="badge bg-secondary p-2">
            <i class="bi bi-building"></i> <?= htmlspecialchars($loginstitucion) ?>
        </span>
    </div>

    <!-- ── RESUMEN DE ESTADO ── -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-label"><i class="bi bi-hourglass-split"></i> Pendientes activas</div>
                <div class="stat-value text-warning"><?= $total_pendientes_global ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-label"><i class="bi bi-check-circle-fill"></i> Entregadas</div>
                <div class="stat-value text-success"><?= $total_entregadas_global ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-label"><i class="bi bi-pc-display"></i> Activos certificados</div>
                <div class="stat-value text-primary"><?= number_format($total_activos_global) ?></div>
            </div>
        </div>
    </div>

    <!-- ── FILTROS ── -->
    <div class="filtros-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="fuente" class="form-label">Fuente presupuestaria</label>
                <select name="fuente" id="fuente" class="form-select">
                    <option value="">Todas las fuentes</option>
                    <?php foreach ($fuentes_disponibles as $f): ?>
                        <option value="<?= $f['id_fondos'] ?>"
                            <?= ($filtro_fuente === $f['id_fondos']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['fondos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="anio" class="form-label">Año</label>
                <select name="anio" id="anio" class="form-select">
                    <option value="">Todos los años</option>
                    <?php foreach ($anios_disponibles as $a): ?>
                        <option value="<?= $a['anio'] ?>"
                            <?= ($filtro_anio === $a['anio']) ? 'selected' : '' ?>>
                            <?= $a['anio'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <?php if ($filtro_fuente || $filtro_anio): ?>
            <div class="col-md-2">
                <a href="administrativo_certificaciones.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($total_registros > 0): ?>
        <div class="mt-2 text-muted" style="font-size:.82rem;">
            Mostrando registros <?= $registro_inicio ?>–<?= $registro_fin ?> de <?= $total_registros ?> en total.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── LISTADO ── -->
    <?php if (empty($grupos)): ?>
        <div class="sin-certificaciones">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <h5>No hay certificaciones registradas para este centro</h5>
            <p class="text-muted">Genere su primera certificación desde el módulo principal.</p>
            <a href="centro_de_certificacion.php" class="btn btn-primary mt-2">
                <i class="bi bi-plus-circle"></i> Crear certificación
            </a>
        </div>
    <?php else: ?>

        <?php foreach ($grupos as $fuente_id => $grupo): ?>
        <div class="grupo-fuente">
            <h3>
                <i class="bi bi-cash-stack"></i>
                <?= htmlspecialchars($grupo['nombre']) ?>
            </h3>

            <?php if (!empty($grupo['pendientes'])): ?>
                <div class="subtitulo-grupo">
                    <i class="bi bi-clock-history"></i> Pendientes de firma / subida
                </div>

                <?php foreach ($grupo['pendientes'] as $p):
                    $pct   = $p['pct_restante'];
                    $color = color_barra($pct);
                    $clase_expira = $pct <= 20 ? 'urgente' : ($pct <= 50 ? 'aviso' : '');
                ?>
                <div class="card card-certificacion pendiente">
                    <div class="card-body">
                        <div class="row align-items-center">

                            <div class="col-md-5">
                                <span class="badge badge-pendiente mb-1">
                                    <i class="bi bi-hourglass-split"></i> Pendiente
                                </span>
                                <div class="cert-nombre">
                                    <?= htmlspecialchars($p['funcionario_nombre']) ?>
                                </div>
                                <div class="cert-uuid" title="<?= htmlspecialchars($p['certificacion_uuid']) ?>">
                                    <code title="UUID completo: <?= htmlspecialchars($p['certificacion_uuid']) ?>">
                                        <?= substr($p['certificacion_uuid'], 0, 18) ?>…
                                    </code>
                                </div>
                                <div class="cert-meta-row">
                                    <i class="bi bi-calendar"></i>
                                    Generado: <?= date('d/m/Y H:i', strtotime($p['fecha'])) ?>
                                    &nbsp;|&nbsp;
                                    <i class="bi bi-pc-display"></i> <?= (int)$p['total_activos'] ?> activos
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="expira-texto <?= $clase_expira ?>">
                                    <i class="bi bi-alarm"></i>
                                    Expira: <?= date('d/m/Y H:i', strtotime($p['expira_en'])) ?>
                                    <?php if ($pct <= 20): ?>
                                        &nbsp;<strong>¡Próximo a vencer!</strong>
                                    <?php endif; ?>
                                </div>
                                <div class="barra-tiempo-wrap mt-1">
                                    <div class="barra-tiempo-fill"
                                         style="width:<?= $pct ?>%; background-color:<?= $color ?>;"></div>
                                </div>
                                <div style="font-size:.72rem; color:#6c757d; margin-top:2px;">
                                    <?= $pct ?>% del tiempo disponible
                                </div>
                            </div>

                            <div class="col-md-3 text-md-end">
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="bi bi-file-earmark-pdf"></i> Sin PDF
                                </button>
                                <small class="d-block text-muted mt-1" style="font-size:.75rem;">
                                    <i class="bi bi-info-circle"></i>
                                    Requiere firma y subida del PDF
                                </small>
                            </div>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($grupo['entregadas'])): ?>
                <div class="subtitulo-grupo <?= empty($grupo['pendientes']) ? '' : 'mt-3' ?>">
                    <i class="bi bi-check-circle-fill text-success"></i> Certificaciones completadas
                </div>

                <?php foreach ($grupo['entregadas'] as $e): ?>
                <div class="card card-certificacion entregada">
                    <div class="card-body">
                        <div class="row align-items-center">

                            <div class="col-md-4">
                                <span class="badge badge-entregada mb-1">
                                    <i class="bi bi-check-circle"></i> Entregada
                                </span>
                                <div class="cert-nombre">
                                    <?= htmlspecialchars($e['funcionario_nombre']) ?>
                                </div>
                                <div class="cert-uuid">
                                    <code title="UUID completo: <?= htmlspecialchars($e['certificacion_uuid']) ?>">
                                        <?= substr($e['certificacion_uuid'], 0, 18) ?>…
                                    </code>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="cert-meta-row">
                                    <i class="bi bi-calendar-check"></i>
                                    Firmado: <?= date('d/m/Y H:i', strtotime($e['fecha_certificacion'])) ?>
                                </div>
                                <div class="cert-meta-row">
                                    <i class="bi bi-pc-display"></i> <?= (int)$e['total_activos'] ?> activos
                                    &nbsp;|&nbsp;
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <?= htmlspecialchars($e['pdf_nombre']) ?>
                                </div>
                            </div>

                            <div class="col-md-4 text-md-end">
                                <a href="descargar_copia_certificacion.php?id=<?= (int)$e['id'] ?>"
                                   class="btn btn-sm btn-success btn-descargar"
                                   target="_blank">
                                    <i class="bi bi-download"></i> Descargar copia PDF
                                </a>
                                <span class="nota-copia">
                                    <i class="bi bi-droplet-half"></i> Incluye marca de agua
                                </span>
                            </div>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <!-- ── PAGINACIÓN ── -->
        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación de certificaciones" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= pag_url($pagina - 1, $filtro_fuente, $filtro_anio) ?>">
                        <i class="bi bi-chevron-left"></i> Anterior
                    </a>
                </li>

                <?php
                // Mostrar máximo 5 páginas alrededor de la actual
                $inicio_rango = max(1, $pagina - 2);
                $fin_rango    = min($total_paginas, $pagina + 2);
                if ($inicio_rango > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= pag_url(1, $filtro_fuente, $filtro_anio) ?>">1</a>
                    </li>
                    <?php if ($inicio_rango > 2): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $inicio_rango; $i <= $fin_rango; $i++): ?>
                    <li class="page-item <?= ($i === $pagina) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= pag_url($i, $filtro_fuente, $filtro_anio) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($fin_rango < $total_paginas): ?>
                    <?php if ($fin_rango < $total_paginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= pag_url($total_paginas, $filtro_fuente, $filtro_anio) ?>">
                            <?= $total_paginas ?>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= pag_url($pagina + 1, $filtro_fuente, $filtro_anio) ?>">
                        Siguiente <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
            <p class="text-center text-muted" style="font-size:.82rem;">
                Página <?= $pagina ?> de <?= $total_paginas ?>
                (<?= $total_registros ?> registros totales)
            </p>
        </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>