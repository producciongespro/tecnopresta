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

// 1. Obtener la fuente presupuestaria seleccionada por GET
$filtro_fondo = isset($_GET['fondo']) && $_GET['fondo'] !== '' ? (int)$_GET['fondo'] : null;

// 2. Obtener lista de todas las fuentes presupuestarias disponibles en el centro para el filtro
$listaFondos = [];
$sqlFondos = "
    SELECT DISTINCT f.id_fondos, f.fondos
    FROM t_fondos f
    INNER JOIN t_placa p ON f.id_fondos = p.id_fondos
    WHERE p.codigo = ?
    ORDER BY f.fondos ASC
";
if (!empty($logcodigo)) {
    $stmtF = mysqli_prepare($link, $sqlFondos);
    if ($stmtF) {
        mysqli_stmt_bind_param($stmtF, "s", $logcodigo);
        mysqli_stmt_execute($stmtF);
        $resF = mysqli_stmt_get_result($stmtF);
        while ($rowF = mysqli_fetch_assoc($resF)) {
            $listaFondos[] = $rowF;
        }
        mysqli_stmt_close($stmtF);
    }
}

// 3. Funciones auxiliares para traducción y representación ligera de colores (sin imágenes pesadas)
function traducirHexANombreColor(string $hex): string {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return 'No definido';
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $paleta = [
        'Negro'        => [0, 0, 0],
        'Blanco'       => [255, 255, 255],
        'Gris Oscuro'  => [80, 80, 80],
        'Gris'         => [128, 128, 128],
        'Plata / Gris Claro' => [192, 192, 192],
        'Rojo'         => [220, 38, 38],
        'Vino / Rojo Oscuro' => [128, 0, 0],
        'Azul'         => [37, 99, 235],
        'Azul Marino'  => [0, 0, 128],
        'Celeste'      => [56, 189, 248],
        'Verde'        => [22, 163, 74],
        'Verde Oscuro' => [0, 100, 0],
        'Amarillo'     => [234, 179, 8],
        'Naranja'      => [249, 115, 22],
        'Dorado'       => [200, 174, 100],
        'Café / Marrón'=> [120, 63, 4],
        'Beige / Crema'=> [245, 245, 220],
        'Morado'       => [147, 51, 234],
        'Rosado'       => [236, 72, 153],
        'Turquesa'     => [20, 184, 166],
    ];

    $nombreMasCercano = 'Personalizado';
    $distanciaMinima = PHP_FLOAT_MAX;

    foreach ($paleta as $nombre => $rgb) {
        // Distancia euclidiana ponderada para percepción humana
        $rmean = ($r + $rgb[0]) / 2;
        $dr = $r - $rgb[0];
        $dg = $g - $rgb[1];
        $db = $b - $rgb[2];
        $dist = sqrt((2 + $rmean / 256) * $dr * $dr + 4 * $dg * $dg + (2 + (255 - $rmean) / 256) * $db * $db);

        if ($dist < $distanciaMinima) {
            $distanciaMinima = $dist;
            $nombreMasCercano = $nombre;
        }
    }

    return $nombreMasCercano;
}

function obtenerHexDesdeTextoColor(string $nombreColor): ?string {
    $texto = mb_strtolower(trim($nombreColor), 'UTF-8');
    $mapa = [
        'negro'    => '#111827',
        'blanco'   => '#ffffff',
        'gris'     => '#6b7280',
        'plata'    => '#c0c0c0',
        'plateado' => '#c0c0c0',
        'rojo'     => '#dc2626',
        'vino'     => '#800000',
        'azul'     => '#2563eb',
        'celeste'  => '#38bdf8',
        'verde'    => '#16a34a',
        'amarillo' => '#eab308',
        'naranja'  => '#f97316',
        'dorado'   => '#c8ae64',
        'oro'      => '#c8ae64',
        'cafe'     => '#783f04',
        'café'     => '#783f04',
        'marron'   => '#783f04',
        'marrón'   => '#783f04',
        'beige'    => '#f5f5dc',
        'crema'    => '#f5f5dc',
        'morado'   => '#9333ea',
        'violeta'  => '#8b5cf6',
        'rosado'   => '#ec4899',
        'rosa'     => '#ec4899',
        'turquesa' => '#14b8a6',
    ];

    foreach ($mapa as $clave => $hex) {
        if (mb_strpos($texto, $clave) !== false) {
            return $hex;
        }
    }
    return null;
}

function resolverDatosActivo(array $item): array {
    // Detectar si es estructura nueva (tiene modelo_id válido o no tiene modelo en texto antiguo)
    $tieneModeloId = !empty($item['modelo_id']) && (int)$item['modelo_id'] > 0;
    $tieneModeloViejo = isset($item['modelo_viejo']) && trim((string)$item['modelo_viejo']) !== '';
    $esEstructuraNueva = $tieneModeloId || !$tieneModeloViejo;

    // Resolver Modelo
    if ($tieneModeloId && !empty($item['modelo_nuevo'])) {
        $modelo = trim($item['modelo_nuevo']);
    } elseif ($tieneModeloViejo) {
        $modelo = trim($item['modelo_viejo']);
    } else {
        $modelo = 'Sin modelo';
    }

    // Resolver Color (nuevos desde t_placa.color en hex; antiguos desde t_color.color en texto)
    $colorHex = null;
    $colorTexto = 'Sin especificar';

    if ($esEstructuraNueva) {
        $rawHex = trim((string)($item['color_placa'] ?? ''));
        if ($rawHex !== '') {
            if ($rawHex[0] !== '#') {
                $rawHex = '#' . $rawHex;
            }
            if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $rawHex)) {
                $colorHex = $rawHex;
                $colorTexto = traducirHexANombreColor($rawHex);
            } else {
                $colorTexto = $rawHex;
                $colorHex = obtenerHexDesdeTextoColor($rawHex);
            }
        } elseif (!empty($item['color_viejo'])) {
            $colorTexto = trim($item['color_viejo']);
            $colorHex = obtenerHexDesdeTextoColor($colorTexto);
        }
    } else {
        if (!empty($item['color_viejo'])) {
            $colorTexto = trim($item['color_viejo']);
            $colorHex = obtenerHexDesdeTextoColor($colorTexto);
        } elseif (!empty($item['color_placa'])) {
            $rawHex = trim((string)$item['color_placa']);
            if ($rawHex[0] !== '#') {
                $rawHex = '#' . $rawHex;
            }
            if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $rawHex)) {
                $colorHex = $rawHex;
                $colorTexto = traducirHexANombreColor($rawHex);
            }
        }
    }

    return [
        'clase'        => !empty($item['nombre_clase']) ? trim($item['nombre_clase']) : 'Sin clasificar',
        'marca'        => !empty($item['nombre_marca']) ? trim($item['nombre_marca']) : 'Sin marca',
        'modelo'       => $modelo,
        'color_texto'  => $colorTexto,
        'color_hex'    => $colorHex,
        'es_nuevo'     => $esEstructuraNueva,
    ];
}

// 4. Consultar activos pertenecientes al centro educativo con o sin filtro de fondo
$activos = [];
$totalActivos = 0;
$totalActivoSi = 0;
$totalActivoNo = 0;
$totalPrestarSi = 0;
$totalPrestarNo = 0;

if (!empty($logcodigo)) {
    $sqlActivos = "
        SELECT 
            p.id_placa,
            p.placa,
            p.serial,
            p.fecha_ingreso,
            p.activo,
            p.prestar,
            p.codigo,
            p.color AS color_placa,
            a.id_activo,
            a.modelo AS modelo_viejo,
            a.modelo_id,
            a.id_color,
            m.modelo AS modelo_nuevo,
            ma.marca AS nombre_marca,
            ag.clase AS nombre_clase,
            c.color AS color_viejo,
            f.fondos AS nombre_fondo,
            e.estado AS nombre_estado
        FROM t_placa p
        LEFT JOIN t_activo a ON p.id_activo = a.id_activo
        LEFT JOIN t_modelos m ON a.modelo_id = m.id_modelo
        LEFT JOIN t_marca ma ON a.id_marca = ma.id_marca
        LEFT JOIN t_activo_general ag ON a.id_ag = ag.id_ag
        LEFT JOIN t_color c ON a.id_color = c.id_color
        LEFT JOIN t_fondos f ON p.id_fondos = f.id_fondos
        LEFT JOIN t_estado e ON p.id_estado = e.id_estado
        WHERE p.codigo = ?
    ";

    $paramsTypes = "s";
    $paramsValues = [$logcodigo];

    if ($filtro_fondo !== null) {
        $sqlActivos .= " AND p.id_fondos = ?";
        $paramsTypes .= "i";
        $paramsValues[] = $filtro_fondo;
    }

    $sqlActivos .= " ORDER BY p.id_placa DESC";

    $stmtA = mysqli_prepare($link, $sqlActivos);
    if ($stmtA) {
        mysqli_stmt_bind_param($stmtA, $paramsTypes, ...$paramsValues);
        mysqli_stmt_execute($stmtA);
        $resA = mysqli_stmt_get_result($stmtA);
        while ($item = mysqli_fetch_assoc($resA)) {
            $activos[] = $item;
            $totalActivos++;
            
            if ((int)$item['activo'] === 1) {
                $totalActivoSi++;
            } else {
                $totalActivoNo++;
            }

            if ((int)$item['prestar'] === 1) {
                $totalPrestarSi++;
            } else {
                $totalPrestarNo++;
            }
        }
        mysqli_stmt_close($stmtA);
    }
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

    <title>Reporte y Listado de Activos</title>

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

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #e9edf3;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-val {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .card-activos {
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
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7;
            padding: 14px 16px;
            white-space: nowrap;
        }

        .table-custom tbody tr {
            transition: background-color 0.15s ease;
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        .table-custom tbody td {
            padding: 13px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f4f8;
            font-size: 0.92rem;
        }

        .placa-badge {
            font-family: monospace;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--mep-azul);
            background-color: #f0f4ff;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #d4e0fc;
            display: inline-block;
        }

        .serie-text {
            font-family: monospace;
            color: #475569;
            font-weight: 500;
        }

        .color-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.84rem;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
        }

        .color-swatch {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            border: 1px solid rgba(0, 0, 0, 0.2);
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.35);
            flex-shrink: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .clase-badge {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--mep-azul);
            background-color: #eef2ff;
            border: 1px solid #e0e7ff;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .badge-si {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-no {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .search-box {
            position: relative;
            max-width: 340px;
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

        @media print {
            .btn-flotante-regresar, .filtro-acciones, .header-card .badge-info-user {
                display: none !important;
            }
            body {
                background-color: #ffffff;
            }
            .card-activos {
                box-shadow: none;
                border: 1px solid #ccc;
            }
        }
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/partials/header.php')) { include 'partials/header.php'; } ?>

<div class="container py-4">

   <!-- Tarjeta de Encabezado Principal -->
<div class="header-card d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-box-seam-fill me-2" style="color: var(--mep-dorado);"></i>Listado y Reporte de Activos</h2>
        <p class="mb-0 text-white-50">
                Inventario detallado y explícito de activos institucionales para el centro educativo.
        </p>
    </div>
<div class="mt-3 mt-md-0 d-flex gap-3 badge-info-user">
    <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-25">
        <small class="d-block text-black-50">Usuario Activo</small>
        <div style="color: #000;">
            <strong><?= htmlspecialchars($lognombre ?: $logusuario) ?></strong>
        </div>
    </div>
<?php if (!empty($logcodigo)): ?>
    <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-25">
        <small class="d-block text-black-50">Cód. Institución</small>
        <strong style="color: #000;"><?= htmlspecialchars($logcodigo) ?></strong>
    </div>
<?php endif; ?>
</div>

    <!-- Tarjetas de Resumen Numérico -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-layers-fill"></i>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalActivos) ?></div>
                    <div class="stat-label">Total de Activos</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalActivoSi) ?></div>
                    <div class="stat-label">Activos: Sí (Operativos)</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-dash-circle-fill"></i>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalActivoNo) ?></div>
                    <div class="stat-label">Activos: No (Inactivos)</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalPrestarSi) ?></div>
                    <div class="stat-label">Disponibles Préstamo</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="card p-3 mb-4 border-0 shadow-sm filtro-acciones" style="border-radius: 12px;">
        <form method="GET" action="navegar.php" class="row g-3 align-items-center">
            <!-- Parámetros protegidos del sistema -->
            <input type="hidden" name="ruta" value="reporte_de_activos_del_centro.php">
            <input type="hidden" name="subsistema_id" value="1">
            <input type="hidden" name="modulo_id" value="4">
            <input type="hidden" name="formulario_id" value="87">

            <div class="col-md-5 col-lg-4">
                <label for="selectFondo" class="form-label fw-bold text-secondary small mb-1">
                    <i class="bi bi-cash-stack me-1 text-primary"></i>Fuente Presupuestaria (Fondo):
                </label>
                <select name="fondo" id="selectFondo" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Todas las fuentes presupuestarias --</option>
                    <?php foreach ($listaFondos as $f): ?>
                        <option value="<?= htmlspecialchars($f['id_fondos']) ?>" <?= ($filtro_fondo !== null && $filtro_fondo == $f['id_fondos']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['fondos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 col-lg-5">
                <label for="buscarActivo" class="form-label fw-bold text-secondary small mb-1">
                    <i class="bi bi-search me-1 text-primary"></i>Búsqueda Rápida en Pantalla:
                </label>
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="buscarActivo" class="form-control" placeholder="Buscar por placa, serie, marca, modelo o color...">
                </div>
            </div>

            <div class="col-md-3 col-lg-3 text-md-end mt-md-4 pt-md-2">
                <button type="button" id="btnDescargarPDF" class="btn btn-danger me-1 shadow-sm" onclick="generarPDFReporte()" title="Preparando reporte PDF..." disabled>
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Cargando PDF...
                </button>
                <?php if ($filtro_fondo !== null): ?>
                    <a href="navegar.php?ruta=reporte_de_activos_del_centro.php&subsistema_id=1&modulo_id=4&formulario_id=87" class="btn btn-outline-danger" title="Limpiar filtro de fondo">
                        <i class="bi bi-x-circle me-1"></i> Quitar Filtro
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla con Listado de Activos -->
    <div class="card card-activos">
        <?php if (empty($activos)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox d-block"></i>
                <h5>No se encontraron activos</h5>
                <p class="text-muted">
                    No existen activos registrados para el centro <strong><?= htmlspecialchars($logcodigo ?: 'N/A') ?></strong>
                    <?= ($filtro_fondo !== null) ? ' bajo la fuente presupuestaria seleccionada.' : '.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle" id="tablaActivos">
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Serie</th>
                            <th>Clase / Tipo</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Color</th>
                            <th>Fecha Ingreso</th>
                            <th>Fuente Presupuestaria</th>
                            <th class="text-center">Activo</th>
                            <th>Estado</th>
                            <th class="text-center">Prestar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activos as $act): 
                            $info = resolverDatosActivo($act);
                            $fechaFormateada = !empty($act['fecha_ingreso']) ? date('d/m/Y', strtotime($act['fecha_ingreso'])) : '-';
                        ?>
                            <tr 
                                data-placa="<?= htmlspecialchars($act['placa'] ?: 'S/P') ?>"
                                data-serial="<?= htmlspecialchars($act['serial'] ?: 'Sin serie') ?>"
                                data-clase="<?= htmlspecialchars($info['clase']) ?>"
                                data-marca="<?= htmlspecialchars($info['marca']) ?>"
                                data-modelo="<?= htmlspecialchars($info['modelo']) ?>"
                                data-color-texto="<?= htmlspecialchars($info['color_texto']) ?>"
                                data-color-hex="<?= htmlspecialchars($info['color_hex'] ?? '') ?>"
                                data-fecha="<?= htmlspecialchars($fechaFormateada) ?>"
                                data-fondo="<?= htmlspecialchars($act['nombre_fondo'] ?: 'Sin asignar') ?>"
                                data-activo="<?= ((int)$act['activo'] === 1) ? 'Sí' : 'No' ?>"
                                data-estado="<?= htmlspecialchars($act['nombre_estado'] ?: '-') ?>"
                                data-prestar="<?= ((int)$act['prestar'] === 1) ? 'Sí' : 'No' ?>"
                            >
                                <td>
                                    <span class="placa-badge"><?= htmlspecialchars($act['placa'] ?: 'S/P') ?></span>
                                </td>
                                <td>
                                    <span class="serie-text"><?= htmlspecialchars($act['serial'] ?: 'Sin serie') ?></span>
                                </td>
                                <td>
                                    <span class="clase-badge"><?= htmlspecialchars($info['clase']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($info['marca']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-secondary"><?= htmlspecialchars($info['modelo']) ?></span>
                                </td>
                                <td>
                                    <span class="color-chip" title="<?= htmlspecialchars($info['color_hex'] ? 'Código: ' . strtoupper($info['color_hex']) : $info['color_texto']) ?>">
                                        <?php if (!empty($info['color_hex'])): ?>
                                            <span class="color-swatch" style="background-color: <?= htmlspecialchars($info['color_hex']) ?>;"></span>
                                        <?php else: ?>
                                            <i class="bi bi-palette text-muted" style="font-size: 0.8rem;"></i>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($info['color_texto']) ?></span>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($fechaFormateada !== '-'): ?>
                                        <span class="fw-semibold text-dark"><?= $fechaFormateada ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-secondary">
                                        <?= htmlspecialchars($act['nombre_fondo'] ?: 'Sin asignar') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$act['activo'] === 1): ?>
                                        <span class="badge-si"><i class="bi bi-check-circle-fill"></i> Sí</span>
                                    <?php else: ?>
                                        <span class="badge-no"><i class="bi bi-x-circle-fill"></i> No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($act['nombre_estado'])): ?>
                                        <span class="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold">
                                            <?= htmlspecialchars($act['nombre_estado']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$act['prestar'] === 1): ?>
                                        <span class="badge-si"><i class="bi bi-check-circle-fill"></i> Sí</span>
                                    <?php else: ?>
                                        <span class="badge-no"><i class="bi bi-x-circle-fill"></i> No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Botón Flotante para Regresar -->
    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=1&modulo_id=4" class="btn-flotante-regresar" title="Volver al panel general">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

</div>

<?php if (file_exists(__DIR__ . '/partials/footer.php')) { include 'partials/footer.php'; } ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<!-- Librerías locales con respaldo automático en CDN si fallan -->
<script src="js/jspdf.umd.min.js" onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';"></script>
<script src="js/jspdf.plugin.autotable.min.js" onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js';"></script>
<script>
    const tieneActivosEnTabla = <?= !empty($activos) ? 'true' : 'false' ?>;
    let generandoPDFEnCurso = false;

    // Carga dinámica de script auxiliar en caso de requerir respaldo secuencial desde CDN
    function cargarScriptRespaldo(url) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = url;
            s.async = false;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('No se pudo cargar: ' + url));
            document.head.appendChild(s);
        });
    }

    // Verifica que jsPDF y AutoTable estén 100% cargados (local o CDN) antes de habilitar el botón
    async function asegurarLibreriasPDF() {
        try {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                await cargarScriptRespaldo('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
            }
            const proto = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF.API : null;
            if (!proto || typeof proto.autoTable !== 'function') {
                await cargarScriptRespaldo('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js');
            }
            return !!(window.jspdf && window.jspdf.jsPDF && window.jspdf.jsPDF.API && typeof window.jspdf.jsPDF.API.autoTable === 'function');
        } catch (err) {
            console.error('Error cargando librerías PDF:', err);
            return false;
        }
    }

    function actualizarEstadoBotonPDF(estado) {
        const btn = document.getElementById('btnDescargarPDF');
        if (!btn) return;

        if (!tieneActivosEnTabla) {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar PDF';
            btn.title = 'No hay activos para exportar';
            return;
        }

        if (estado === 'cargando') {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Cargando PDF...';
            btn.title = 'Cargando librerías y datos de inventario...';
        } else if (estado === 'generando') {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generando PDF...';
            btn.title = 'Procesando documento PDF, por favor espere...';
        } else if (estado === 'error') {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Reintentar PDF';
            btn.title = 'Reintentar carga de librerías PDF';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar PDF';
            btn.title = 'Generar y descargar PDF en tamaño Carta';
        }
    }

    // Esperar a que todo el DOM (incluyendo inventarios extensos) y las librerías estén listos
    window.addEventListener('load', async function() {
        if (!tieneActivosEnTabla) {
            actualizarEstadoBotonPDF('listo');
            return;
        }
        const ok = await asegurarLibreriasPDF();
        actualizarEstadoBotonPDF(ok ? 'listo' : 'error');
    });

    // Filtro en tiempo real con debounce para no bloquear el navegador en inventarios extensos
    const inputBuscar = document.getElementById('buscarActivo');
    let timeoutBusqueda = null;
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const valor = this.value;
            timeoutBusqueda = setTimeout(() => {
                const busqueda = valor.toLowerCase().trim();
                const filas = document.querySelectorAll('#tablaActivos tbody tr');

                filas.forEach(fila => {
                    const textoFila = fila.innerText.toLowerCase();
                    fila.style.display = textoFila.includes(busqueda) ? '' : 'none';
                });
            }, 120);
        });
    }

    // Convierte un código HEX (#RRGGBB) a arreglo RGB [r, g, b] para jsPDF
    function hexToRgbArray(hex) {
        if (!hex) return null;
        let clean = hex.replace('#', '').trim();
        if (clean.length === 3) {
            clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
        }
        if (!/^[0-9a-fA-F]{6}$/.test(clean)) return null;
        return [
            parseInt(clean.substring(0, 2), 16),
            parseInt(clean.substring(2, 4), 16),
            parseInt(clean.substring(4, 6), 16)
        ];
    }

    // Genera y descarga el reporte en PDF sin congelar la interfaz en inventarios extensos
    async function generarPDFReporte() {
        if (generandoPDFEnCurso) return;
        generandoPDFEnCurso = true;
        actualizarEstadoBotonPDF('generando');

        // Ceder un ciclo de renderizado al navegador para mostrar el spinner antes del cálculo pesado
        await new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 40)));

        try {
            const libreriasListas = await asegurarLibreriasPDF();
            if (!libreriasListas) {
                alert('No fue posible cargar las librerías para generar el PDF. Verifique su conexión e intente de nuevo.');
                actualizarEstadoBotonPDF('error');
                generandoPDFEnCurso = false;
                return;
            }

            const filasVisibles = Array.from(document.querySelectorAll('#tablaActivos tbody tr'))
                .filter(tr => tr.style.display !== 'none');

            if (filasVisibles.length === 0) {
                alert('No hay activos visibles en la tabla para exportar al PDF.');
                actualizarEstadoBotonPDF('listo');
                generandoPDFEnCurso = false;
                return;
            }

            const { jsPDF } = window.jspdf;
            // Hoja estándar tamaño Carta (Letter: 279.4 x 215.9 mm) en orientación horizontal
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'letter',
                compress: true
            });

            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const marginX = 12;

            // Datos de contexto institucional
            const usuarioReporte = <?= json_encode($lognombre ?: $logusuario ?: 'N/A') ?>;
            const codigoCentro   = <?= json_encode($logcodigo ?: 'N/A') ?>;
            const selectFondo    = document.getElementById('selectFondo');
            const textoFondo     = (selectFondo && selectFondo.value !== '')
                ? selectFondo.options[selectFondo.selectedIndex].text.trim()
                : 'Todas las fuentes presupuestarias';
            const filtroBusqueda = inputBuscar ? inputBuscar.value.trim() : '';
            const fechaGeneracion = new Date().toLocaleString('es-CR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });

            // Procesar filas en lotes (chunks) si el inventario es muy extenso para evitar que el navegador se cuelgue
            let expTotal = filasVisibles.length;
            let expOperativos = 0;
            let expInactivos = 0;
            let expPrestamo = 0;

            const bodyRows = new Array(expTotal);
            const rowColorsRGB = new Array(expTotal);
            const TAMANO_LOTE = 400;

            for (let i = 0; i < expTotal; i++) {
                const d = filasVisibles[i].dataset;
                if (d.activo === 'Sí') expOperativos++; else expInactivos++;
                if (d.prestar === 'Sí') expPrestamo++;

                rowColorsRGB[i] = hexToRgbArray(d.colorHex || '');
                bodyRows[i] = [
                    d.placa || 'S/P',
                    d.serial || 'Sin serie',
                    d.clase || 'Sin clasificar',
                    d.marca || 'Sin marca',
                    d.modelo || 'Sin modelo',
                    '    ' + (d.colorTexto || 'Sin especificar'), // Espacio reservado para el círculo de color
                    d.fecha || '-',
                    d.fondo || 'Sin asignar',
                    d.activo || 'No',
                    d.estado || '-',
                    d.prestar || 'No'
                ];

                // Ceder el hilo brevemente cada 400 registros en inventarios masivos
                if (i > 0 && i % TAMANO_LOTE === 0) {
                    await new Promise(r => setTimeout(r, 0));
                }
            }

            // 1. Encabezado Institucional (Banner superior)
            doc.setFillColor(30, 40, 81); // --mep-azul (#1e2851)
            doc.rect(marginX, 10, pageWidth - (marginX * 2), 20, 'F');

            // Franja dorada izquierda
            doc.setFillColor(200, 174, 100); // --mep-dorado (#c8ae64)
            doc.rect(marginX, 10, 3, 20, 'F');

            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.text('REPORTE Y LISTADO DE ACTIVOS DEL CENTRO EDUCATIVO', marginX + 7, 18);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(220, 226, 240);
            doc.text('Inventario detallado de activos institucionales (Formato Carta)', marginX + 7, 24.5);

            // Metadatos a la derecha del banner
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8.5);
            doc.setTextColor(255, 255, 255);
            doc.text(`Cód. Institución: ${codigoCentro}`, pageWidth - marginX - 4, 16, { align: 'right' });
            doc.setFont('helvetica', 'normal');
            doc.text(`Usuario: ${usuarioReporte}`, pageWidth - marginX - 4, 21, { align: 'right' });
            doc.text(`Generado: ${fechaGeneracion}`, pageWidth - marginX - 4, 26, { align: 'right' });

            // 2. Barra de Resumen y Filtros Aplicados
            doc.setFillColor(244, 246, 249);
            doc.setDrawColor(218, 224, 233);
            doc.roundedRect(marginX, 33, pageWidth - (marginX * 2), 11, 1.5, 1.5, 'FD');

            doc.setFontSize(8.5);
            doc.setTextColor(30, 41, 59);
            doc.setFont('helvetica', 'bold');
            const textoResumen = `Total exportados: ${expTotal}   |   Operativos (Sí): ${expOperativos}   |   Inactivos (No): ${expInactivos}   |   Disponibles Préstamo: ${expPrestamo}`;
            doc.text(textoResumen, marginX + 4, 39.8);

            doc.setFont('helvetica', 'normal');
            doc.setTextColor(71, 85, 105);
            let infoFiltro = `Fuente: ${textoFondo}`;
            if (filtroBusqueda !== '') {
                infoFiltro += `  |  Filtro: "${filtroBusqueda}"`;
            }
            doc.text(infoFiltro, pageWidth - marginX - 4, 39.8, { align: 'right' });

            // 3. Tabla Maquetada con AutoTable ajustada exactamente al ancho de hoja Carta (255.4 mm útiles)
            doc.autoTable({
                startY: 47,
                margin: { left: marginX, right: marginX, top: 15, bottom: 15 },
                head: [[
                    'Placa',
                    'Serie',
                    'Clase / Tipo',
                    'Marca',
                    'Modelo',
                    'Color',
                    'Ingreso',
                    'Fuente Presupuestaria',
                    'Activo',
                    'Estado',
                    'Prestar'
                ]],
                body: bodyRows,
                theme: 'grid',
                styles: {
                    font: 'helvetica',
                    fontSize: 7.8,
                    cellPadding: 2.2,
                    textColor: [51, 65, 85],
                    lineColor: [226, 232, 240],
                    lineWidth: 0.2,
                    valign: 'middle',
                    overflow: 'linebreak'
                },
                headStyles: {
                    fillColor: [30, 40, 81],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold',
                    fontSize: 8,
                    halign: 'left',
                    cellPadding: 2.8
                },
                alternateRowStyles: {
                    fillColor: [248, 250, 252]
                },
                columnStyles: {
                    0:  { cellWidth: 21, fontStyle: 'bold', textColor: [30, 40, 81] }, // Placa
                    1:  { cellWidth: 25 }, // Serie
                    2:  { cellWidth: 24 }, // Clase / Tipo
                    3:  { cellWidth: 22, fontStyle: 'bold' }, // Marca
                    4:  { cellWidth: 32 }, // Modelo
                    5:  { cellWidth: 28 }, // Color (con círculo gráfico)
                    6:  { cellWidth: 18, halign: 'center' }, // Fecha Ingreso
                    7:  { cellWidth: 37 }, // Fuente Presupuestaria
                    8:  { cellWidth: 14, halign: 'center', fontStyle: 'bold' }, // Activo
                    9:  { cellWidth: 19, halign: 'center' }, // Estado
                    10: { cellWidth: 15.4, halign: 'center', fontStyle: 'bold' } // Prestar
                },
                didParseCell: function(data) {
                    if (data.section === 'head' && [6, 8, 9, 10].includes(data.column.index)) {
                        data.cell.styles.halign = 'center';
                    }
                    if (data.section === 'body') {
                        // Colorear texto de columnas Activo (8) y Prestar (10)
                        if (data.column.index === 8 || data.column.index === 10) {
                            if (data.cell.raw === 'Sí') {
                                data.cell.styles.textColor = [6, 95, 70]; // Verde oscuro
                            } else {
                                data.cell.styles.textColor = [153, 27, 27]; // Rojo oscuro
                            }
                        }
                    }
                },
                didDrawCell: function(data) {
                    // Dibujar el círculo con el color real precalculado en la columna Color (índice 5)
                    if (data.section === 'body' && data.column.index === 5) {
                        const rgb = rowColorsRGB[data.row.index];
                        const circleX = data.cell.x + 3.6;
                        const circleY = data.cell.y + (data.cell.height / 2);

                        if (rgb) {
                            doc.setFillColor(rgb[0], rgb[1], rgb[2]);
                            doc.setDrawColor(148, 163, 184);
                            doc.setLineWidth(0.2);
                            doc.circle(circleX, circleY, 1.6, 'FD');
                        } else {
                            doc.setDrawColor(180, 180, 180);
                            doc.setLineWidth(0.2);
                            doc.circle(circleX, circleY, 1.6, 'S');
                        }
                    }
                },
                didDrawPage: function(data) {
                    // Pie de página en cada hoja
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(8);
                    doc.setTextColor(120, 130, 145);
                    doc.text(
                        `Reporte Institucional de Activos - Centro Educativo ${codigoCentro}`,
                        marginX,
                        pageHeight - 7
                    );
                }
            });

            // Numeración total de páginas en el pie de página
            const totalPaginas = doc.internal.getNumberOfPages();
            for (let i = 1; i <= totalPaginas; i++) {
                doc.setPage(i);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.setTextColor(100, 116, 139);
                doc.text(
                    `Página ${i} de ${totalPaginas}`,
                    pageWidth - marginX,
                    pageHeight - 7,
                    { align: 'right' }
                );
            }

            // Descargar el archivo PDF
            const fechaArchivo = new Date().toISOString().slice(0, 10);
            const codigoLimpio = String(codigoCentro).replace(/[^a-zA-Z0-9_-]/g, '') || 'Centro';
            doc.save(`Reporte_Activos_${codigoLimpio}_${fechaArchivo}.pdf`);
        } catch (error) {
            console.error('Error al generar el PDF:', error);
            alert('Ocurrió un error al construir el documento PDF. Por favor intente nuevamente.');
        } finally {
            generandoPDFEnCurso = false;
            actualizarEstadoBotonPDF('listo');
        }
    }
</script>
</body>
</html>
