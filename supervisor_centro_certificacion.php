<?php
/**
 * supervisor_centro_certificacion.php
 *
 * Vista principal para el Supervisor de Educación (Rol 15 / Root / Jefatura).
 * Permite actuar como jefatura inmediata cuando un centro educativo:
 *   A) No cuenta con Director nombrado y el Supervisor debe revisar, generar y firmar la certificación.
 *   B) El centro educativo (o el mismo Supervisor) ya generó el PDF de certificación pero el Director
 *      presenta problemas técnicos para firmar, por lo que el Supervisor solo necesita subir el PDF
 *      firmado con su firma digital GAUDI.
 *
 * Dividido ergonómicamente en DOS APARTADOS (Pestañas):
 *   1. APARTADO 1: Generar Nueva Certificación por Centro Educativo
 *      (Selección de Dirección Regional -> Circuito -> Centro Educativo -> Fuente Presupuestaria + Declaración Jurada)
 *   2. APARTADO 2: Subir PDF de Certificación Ya Firmado con GAUDI
 *      (Subida directa con registro de compromiso de supervisión, más listado de pendientes de su circuito)
 */

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
mysqli_set_charset($link, "utf8mb4");
date_default_timezone_set('America/Costa_Rica');

$logusuario     = $usuario_azure['cedula'] ?? null;
$lognombre      = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$logcorreo      = $usuario_azure['correo'] ?? ($usuario_azure['correomep'] ?? '');
$esRoot         = !empty($usuario_azure['esRoot']);

// Datos de Dirección Regional y Circuito que ya vienen en la sesión de Azure (usuarioAzure.php)
$sesionIdRegional     = isset($usuario_azure['idRegional']) ? (int)$usuario_azure['idRegional'] : 0;
$sesionNombreRegional = trim((string)($usuario_azure['regional'] ?? ''));
$sesionCircuito       = trim((string)($usuario_azure['circuito'] ?? ''));
$sesionCodigoPresu    = trim((string)($usuario_azure['codigoPresu'] ?? ''));

// ── Verificación de Permisos: Root (esRoot === true o rol_id = 1) NUNCA se deniega ──
// También se permite a Supervisor de Educación (rol_id = 15) y roles de supervisión/auditoría/jefatura (12, 13, 14)
$tienePermisoSupervisor = $esRoot;
$subsistemaDetectado    = 3; // Por defecto subsistema 3 (Supervisión, según usuarios_roles.sql)

if (!empty($usuario_azure['roles']) && is_array($usuario_azure['roles'])) {
    foreach ($usuario_azure['roles'] as $rItem) {
        $idR = is_array($rItem) ? (int)($rItem['rol_id'] ?? $rItem['id_rol'] ?? $rItem['id'] ?? 0) : (int)$rItem;
        if (in_array($idR, [1, 12, 13, 14, 15], true)) {
            $tienePermisoSupervisor = true;
            if (is_array($rItem) && !empty($rItem['subsistema_id'])) {
                $subsistemaDetectado = (int)$rItem['subsistema_id'];
            }
            break;
        }
    }
}

if (!empty($logusuario)) {
    $stmtRol = $link->prepare("
        SELECT ur.rol_id, ur.subsistema_id, ur.codigo_presu
        FROM usuarios u
        INNER JOIN usuarios_roles ur ON ur.usuario_id = u.id
        WHERE u.cedula = ? AND u.eliminado = 0 AND ur.eliminado = 0
        ORDER BY FIELD(ur.rol_id, 1, 15, 13, 14, 12) ASC
    ");
    if ($stmtRol) {
        $stmtRol->bind_param("s", $logusuario);
        $stmtRol->execute();
        $resRol = $stmtRol->get_result();
        while ($rowR = $resRol->fetch_assoc()) {
            $rId = (int)$rowR['rol_id'];
            if ($rId === 1) {
                $esRoot = true;
                $tienePermisoSupervisor = true;
            }
            if (in_array($rId, [1, 12, 13, 14, 15], true)) {
                $tienePermisoSupervisor = true;
                if ($rId === 15 && !empty($rowR['subsistema_id'])) {
                    $subsistemaDetectado = (int)$rowR['subsistema_id'];
                }
            }
        }
        $stmtRol->close();
    }
}

if (!$tienePermisoSupervisor) {
    echo '<script>alert("Acceso restringido: Esta vista está habilitada únicamente para Supervisores de Educación (Rol 15) o usuarios Root."); window.location.href="index.html";</script>';
    exit();
}

$subsistemaRegreso = isset($_GET['subsistema_id']) ? (int)$_GET['subsistema_id'] : $subsistemaDetectado;
$moduloRegreso     = isset($_GET['modulo_id']) ? (int)$_GET['modulo_id'] : ($subsistemaRegreso === 3 ? 14 : 4);
$urlRegresoMenu    = "navegar.php?ruta=formulario_sub_modulos.php&subsistema_id={$subsistemaRegreso}&modulo_id={$moduloRegreso}";

// ── Asegurar que la tabla de compromiso del supervisor exista ──
$sqlCrearTablaCompromiso = "
CREATE TABLE IF NOT EXISTS `certificaciones_compromiso_supervisor` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificacion_uuid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificacion_id` int(11) DEFAULT NULL,
  `supervisor_cedula` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supervisor_nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supervisor_correo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_regional` int(11) NOT NULL,
  `regional_nombre` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `circuito` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `centro_codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `centro_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fuente_presupuestaria_id` int(11) DEFAULT NULL,
  `fuente_presupuestaria_nombre` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_intervencion` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIN_DIRECTOR',
  `modalidad_verificacion` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle_fuente_verificacion` text COLLATE utf8mb4_unicode_ci,
  `acepta_compromiso_legal` tinyint(1) NOT NULL DEFAULT '1',
  `tipo_operacion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GENERACION_Y_FIRMA',
  `estado_proceso` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE_FIRMA',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_compromiso` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_completado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uuid` (`certificacion_uuid`),
  KEY `idx_centro_fuente` (`centro_codigo`, `fuente_presupuestaria_id`),
  KEY `idx_supervisor` (`supervisor_cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
@$link->query($sqlCrearTablaCompromiso);

// ── 1. Cargar Direcciones Regionales (soporta tabla `regionales` o agrupa desde `t_instituciones`) ──
$listaRegionales = [];
$resReg = @$link->query("SELECT id, numero, nombre FROM regionales ORDER BY numero ASC, nombre ASC");
if ($resReg && $resReg->num_rows > 0) {
    while ($r = $resReg->fetch_assoc()) {
        $num = isset($r['numero']) && $r['numero'] !== '' ? $r['numero'] . ' - ' : '';
        $listaRegionales[(int)$r['id']] = [
            'id'     => (int)$r['id'],
            'nombre' => $num . $r['nombre'],
            'raw'    => $r['nombre']
        ];
    }
} else {
    // Fallback si la tabla regionales tiene otro nombre o no está poblada: extraer de t_instituciones
    $resRegFallback = @$link->query("SELECT DISTINCT id_regional FROM t_instituciones WHERE id_regional IS NOT NULL ORDER BY id_regional ASC");
    if ($resRegFallback) {
        while ($r = $resRegFallback->fetch_assoc()) {
            $idR = (int)$r['id_regional'];
            $listaRegionales[$idR] = [
                'id'     => $idR,
                'nombre' => "Dirección Regional #" . $idR,
                'raw'    => "Dirección Regional #" . $idR
            ];
        }
    }
}

// ── 2. Cargar Catálogo de Centros Educativos con su Regional y Circuito ──
$catalogoCentros = [];
$sqlCentros = "
    SELECT 
        codigo,
        MIN(institucion) AS institucion,
        MAX(id_regional) AS id_regional,
        MAX(TRIM(COALESCE(circuito, ''))) AS circuito,
        GROUP_CONCAT(DISTINCT cod_saber SEPARATOR ', ') AS codigos_saber
    FROM t_instituciones
    WHERE activo = 1 AND codigo IS NOT NULL AND TRIM(codigo) <> ''
    GROUP BY codigo
    ORDER BY MIN(institucion) ASC
";
$resCentros = $link->query($sqlCentros);
if ($resCentros) {
    while ($c = $resCentros->fetch_assoc()) {
        $idReg = (int)($c['id_regional'] ?? 0);
        $circ  = trim((string)($c['circuito'] ?? ''));
        if ($circ === '') {
            $circ = 'S/C';
        }
        $catalogoCentros[] = [
            'codigo'      => (string)$c['codigo'],
            'institucion' => (string)$c['institucion'],
            'id_regional' => $idReg,
            'circuito'    => $circ,
            'cod_saber'   => (string)($c['codigos_saber'] ?? '')
        ];
        // Si algún centro tiene un id_regional no listado en la tabla regionales, agregarlo para no perderlo
        if ($idReg > 0 && !isset($listaRegionales[$idReg])) {
            $listaRegionales[$idReg] = [
                'id'     => $idReg,
                'nombre' => "Dirección Regional #" . $idReg,
                'raw'    => "Dirección Regional #" . $idReg
            ];
        }
    }
}

// ── 3. Cargar Fuentes Presupuestarias ──
$listaFondos = [];
$resFondos = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos ASC");
if ($resFondos) {
    while ($f = $resFondos->fetch_assoc()) {
        $listaFondos[] = $f;
    }
}

// ── 4. Cargar últimas certificaciones pendientes activas (para ayudar al supervisor en el Apartado 2) ──
$pendientesActivas = [];
$sqlPend = "
    SELECT 
        p.certificacion_uuid,
        p.centro_id,
        p.centro_nombre,
        p.fuente_presupuestaria_id,
        p.fuente_presupuestaria_nombre,
        p.funcionario_id,
        p.funcionario_nombre,
        p.fecha_generacion,
        p.expira_en,
        i.id_regional,
        i.circuito
    FROM certificaciones_pendientes p
    LEFT JOIN (
        SELECT codigo, MAX(id_regional) AS id_regional, MAX(circuito) AS circuito
        FROM t_instituciones
        GROUP BY codigo
    ) i ON i.codigo = p.centro_id
    WHERE p.expira_en > NOW()
    ORDER BY p.fecha_generacion DESC
    LIMIT 100
";
$resPend = @$link->query($sqlPend);
if ($resPend) {
    while ($p = $resPend->fetch_assoc()) {
        $pendientesActivas[] = $p;
    }
}

// ── 5. Cargar historial reciente de compromisos firmados por este supervisor ──
$misCompromisos = [];
if (!empty($logusuario)) {
    $stmtMis = $link->prepare("
        SELECT * FROM certificaciones_compromiso_supervisor
        WHERE supervisor_cedula = ?
        ORDER BY fecha_compromiso DESC
        LIMIT 15
    ");
    if ($stmtMis) {
        $stmtMis->bind_param("s", $logusuario);
        $stmtMis->execute();
        $resMis = $stmtMis->get_result();
        while ($m = $resMis->fetch_assoc()) {
            $misCompromisos[] = $m;
        }
        $stmtMis->close();
    }
}

$tabActiva = isset($_GET['tab']) && $_GET['tab'] === 'subir' ? 'subir' : 'generar';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">

    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/nueva-identidad.css?v=20260924_2">
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

    <title>Supervisión Circuitos — Certificación de Inventario por Jefatura</title>

    <style>
        :root {
            --mep-azul: #1e2851;
            --mep-dorado: #c8ae64;
            --mep-azul-suave: #2a386c;
        }

        body {
            font-family: 'Henderson Sans', sans-serif;
            background-color: #f4f6f9;
            padding-bottom: 90px;
        }

        .header-supervisor {
            background: linear-gradient(135deg, var(--mep-azul) 0%, var(--mep-azul-suave) 100%);
            color: #fff;
            border-radius: 14px;
            padding: 26px 30px;
            border-left: 6px solid var(--mep-dorado);
            box-shadow: 0 4px 18px rgba(30, 40, 81, 0.15);
            margin-bottom: 24px;
        }

        /* Pestañas de los dos apartados principales */
        .nav-pills-supervisor .nav-link {
            color: var(--mep-azul);
            background-color: #ffffff;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 14px 22px;
            font-weight: 700;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-pills-supervisor .nav-link:hover {
            border-color: var(--mep-azul);
            transform: translateY(-1px);
        }

        .nav-pills-supervisor .nav-link.active {
            background-color: var(--mep-azul);
            color: #ffffff;
            border-color: var(--mep-dorado);
            box-shadow: 0 4px 14px rgba(30, 40, 81, 0.25);
        }

        .compromiso-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 5px solid #d97706;
            border-radius: 12px;
            padding: 20px;
        }

        .declaracion-jurada-card {
            background-color: #f8fafc;
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            transition: border-color 0.2s ease;
        }

        .declaracion-jurada-card.aceptada {
            border-color: #198754;
            background-color: #f0fdf4;
        }

        .opcion-verificacion {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 14px 16px;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .opcion-verificacion:hover {
            border-color: var(--mep-azul);
        }

        .opcion-verificacion.seleccionada {
            border: 2px solid var(--mep-azul);
            background-color: #eff6ff;
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
        }

        .btn-flotante-regresar i {
            font-size: 30px;
            color: #fff;
        }
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/partials/header.php')) { include 'partials/header.php'; } ?>

<div class="container py-4">

    <!-- Botón flotante regresar -->
    <a href="<?= htmlspecialchars($urlRegresoMenu) ?>" class="btn-flotante-regresar" title="Volver al menú principal">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <!-- Encabezado de Supervisión -->
    <div class="header-supervisor d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-warning text-dark mb-2 px-3 py-1 fw-bold">
                <i class="bi bi-person-workspace me-1"></i> MÓDULO ESPECIAL PARA SUPERVISIÓN DE EDUCACIÓN (JEFATURA INMEDIATA)
                <?php if ($esRoot): ?>
                    &nbsp;|&nbsp; <i class="bi bi-stars"></i> ACCESO TOTAL ROOT
                <?php endif; ?>
            </span>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-shield-lock-fill me-2" style="color: var(--mep-dorado);"></i>
                Certificación de Inventario por Supervisión de Circuito
            </h2>
            <p class="mb-0 text-white-50">
                Habilitado para centros educativos sin director nombrado o cuando el director presenta impedimento técnico para firmar.
            </p>
        </div>
        <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-25">
            <small class="d-block text-white-50"><?= $esRoot ? 'Usuario Root / Supervisor(a) en Sesión' : 'Supervisor(a) en Sesión' ?></small>
            <strong><?= htmlspecialchars($lognombre ?: $logusuario) ?></strong>
            <small class="d-block text-white-50">
                Cédula: <?= htmlspecialchars($logusuario) ?>
                <?php if ($sesionNombreRegional || $sesionCircuito): ?>
                    &nbsp;|&nbsp; <?= htmlspecialchars($sesionNombreRegional ?: "Reg. $sesionIdRegional") ?><?= $sesionCircuito ? " (Circ. " . htmlspecialchars($sesionCircuito) . ")" : '' ?>
                <?php endif; ?>
            </small>
        </div>
    </div>

    <!-- Banner de Compromiso y Responsabilidad Legal del Supervisor -->
    <div class="compromiso-box mb-4 shadow-sm">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-octagon-fill text-warning fs-1 flex-shrink-0"></i>
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    Responsabilidad Administrativa y Legal del Supervisor de Circuito (Ley N.° 8454)
                </h5>
                <p class="mb-2 small text-dark">
                    Estimado(a) <strong><?= htmlspecialchars($lognombre) ?></strong>: Como jefatura inmediata del centro educativo, asumir la generación o firma de una certificación de inventario es un <strong>acto formal de fe pública</strong> que no debe tomarse a la ligera.
                </p>
                <ul class="small mb-0 text-dark">
                    <li><strong>Lo óptimo es encontrarse físicamente en el centro educativo</strong> verificando los activos junto al personal de la institución.</li>
                    <li>Si por razones de distancia o fuerza mayor no se encuentra en el sitio, usted <strong>debe haber indagado y verificado por medio de una fuente fidedigna</strong> (inventario levantado por coordinador, docente encargado, junta o administrativo del centro) el estado real de cada activo antes de certificarlo.</li>
                    <li><strong>Toda declaración realizada en este módulo quedará registrada en una bitácora de auditoría</strong> vinculada al UUID de la certificación y a su número de cédula.</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Atención:</strong>
            <?php
            $errCode = $_GET['error'];
            if ($errCode === 'faltan_datos') {
                echo 'Por favor complete la Dirección Regional, Circuito, Centro Educativo, Fuente Presupuestaria y acepte el compromiso legal.';
            } elseif ($errCode === 'sin_compromiso') {
                echo 'Es obligatorio declarar la modalidad de verificación y aceptar el compromiso de responsabilidad para continuar.';
            } else {
                echo htmlspecialchars($errCode);
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Selector de Apartados (2 Pestañas Claras y Simplificadas) -->
    <ul class="nav nav-pills nav-pills-supervisor nav-fill gap-3 mb-4" id="tabsSupervisor" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link w-100 justify-content-center <?= $tabActiva === 'generar' ? 'active' : '' ?>"
                    id="tab-generar-btn"
                    data-bs-toggle="pill"
                    data-bs-target="#panelGenerar"
                    type="button"
                    role="tab">
                <i class="bi bi-file-earmark-plus-fill fs-4"></i>
                <div class="text-start">
                    <div class="d-block">APARTADO 1: Revisar Activos y Generar PDF</div>
                    <small class="fw-normal opacity-75" style="font-size: 0.78rem;">Para centros sin director o cuando el supervisor genera el documento desde cero</small>
                </div>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link w-100 justify-content-center <?= $tabActiva === 'subir' ? 'active' : '' ?>"
                    id="tab-subir-btn"
                    data-bs-toggle="pill"
                    data-bs-target="#panelSubir"
                    type="button"
                    role="tab">
                <i class="bi bi-cloud-arrow-up-fill fs-4"></i>
                <div class="text-start">
                    <div class="d-block">APARTADO 2: Solo Subir PDF Firmado con GAUDI</div>
                    <small class="fw-normal opacity-75" style="font-size: 0.78rem;">Cuando el PDF ya fue generado (por el centro o el supervisor) y solo requiere subir el firmado</small>
                </div>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- =================================================================================
             APARTADO 1: SELECCIONAR REGIONAL, CIRCUITO, CENTRO, COMPROMISO Y CARGAR ACTIVOS
             ================================================================================= -->
        <div class="tab-pane fade <?= $tabActiva === 'generar' ? 'show active' : '' ?>" id="panelGenerar" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                        Paso Preliminar: Ubicación del Centro Educativo y Declaración de Verificación
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="supervisor_crear_certificacion.php" method="POST" id="formIniciarSupervisor" onsubmit="return validarFormularioGenerar()">

                        <!-- 1. Selección Jerárquica: Regional -> Circuito -> Centro -> Fuente -->
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-1-circle-fill me-1"></i> Identificación de la Dirección Regional, Circuito y Centro Educativo
                        </h6>

                        <div class="row g-3 mb-4">
                            <!-- Dirección Regional -->
                            <div class="col-md-4">
                                <label for="selRegional" class="form-label fw-bold small">Dirección Regional de Educación <span class="text-danger">*</span></label>
                                <select class="form-select" id="selRegional" name="id_regional" required onchange="alCambiarRegional(' selRegional', 'selCircuito', 'selCentro', 'inputRegionalNombre')">
                                    <option value="">-- Seleccione Dirección Regional --</option>
                                    <?php foreach ($listaRegionales as $reg): ?>
                                        <option value="<?= (int)$reg['id'] ?>" data-nombre="<?= htmlspecialchars($reg['nombre']) ?>">
                                            <?= htmlspecialchars($reg['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="regional_nombre" id="inputRegionalNombre" value="">
                            </div>

                            <!-- Circuito Educativo -->
                            <div class="col-md-3">
                                <label for="selCircuito" class="form-label fw-bold small">Circuito que Administra <span class="text-danger">*</span></label>
                                <select class="form-select" id="selCircuito" name="circuito" required disabled onchange="alCambiarCircuito('selRegional', 'selCircuito', 'selCentro')">
                                    <option value="">-- Primero elija Regional --</option>
                                </select>
                            </div>

                            <!-- Centro Educativo (Código + Nombre) -->
                            <div class="col-md-5">
                                <label for="selCentro" class="form-label fw-bold small">Centro Educativo (Código Presupuestario) <span class="text-danger">*</span></label>
                                <select class="form-select" id="selCentro" name="codigo_centro" required disabled onchange="alSeleccionarCentro('selCentro', 'txtCodigoRapido')">
                                    <option value="">-- Primero elija Circuito --</option>
                                </select>
                            </div>

                            <!-- Buscador rápido por código presupuestario (auto-selecciona Regional y Circuito) -->
                            <div class="col-md-5">
                                <label for="txtCodigoRapido" class="form-label small text-muted mb-1">
                                    <i class="bi bi-lightning-charge-fill text-warning"></i> ¿Conoce el código del centro? Escríbalo para autocompletar:
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input type="text" id="txtCodigoRapido" class="form-control" placeholder="Ej. 1802" oninput="buscarCentroPorCodigoRapido(this.value, 'selRegional', 'selCircuito', 'selCentro', 'inputRegionalNombre', 'feedbackCodigoRapido')">
                                </div>
                                <small id="feedbackCodigoRapido" class="text-muted d-block mt-1"></small>
                            </div>

                            <!-- Fuente Presupuestaria -->
                            <div class="col-md-7">
                                <label for="selFondos" class="form-label fw-bold small">Fuente Presupuestaria a Certificar <span class="text-danger">*</span></label>
                                <select class="form-select" id="selFondos" name="fondos" required>
                                    <option value="">-- Seleccione Fuente Presupuestaria --</option>
                                    <?php foreach ($listaFondos as $f): ?>
                                        <option value="<?= (int)$f['id_fondos'] ?>"><?= htmlspecialchars($f['fondos']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- 2. Motivo de Intervención y Modalidad de Verificación -->
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-2-circle-fill me-1"></i> Motivo de Intervención y Forma en que Verificó el Estado de los Activos
                        </h6>

                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label for="motivoIntervencion" class="form-label fw-bold small">Motivo por el que certifica el Supervisor <span class="text-danger">*</span></label>
                                <select class="form-select" id="motivoIntervencion" name="motivo_intervencion" required>
                                    <option value="SIN_DIRECTOR">Centro educativo sin Director(a) nombrado(a)</option>
                                    <option value="PROBLEMA_FIRMA_DIRECTOR">Director(a) con imposibilidad técnica / tarjeta GAUDI para firmar</option>
                                    <option value="INTERVENCION_SUPERVISION">Disposición directa de Supervisión de Circuito</option>
                                </select>
                            </div>

                            <div class="col-md-7">
                                <label class="form-label fw-bold small d-block">¿Cómo constató usted el estado de los activos a certificar? <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="opcion-verificacion w-100 d-flex align-items-start gap-2" id="lblModPresencial">
                                            <input type="radio" name="modalidad_verificacion" value="PRESENCIAL_EN_CENTRO" class="form-check-input mt-1" required onchange="actualizarModalidadGen()">
                                            <div>
                                                <strong class="d-block small text-dark"><i class="bi bi-building-check text-success"></i> Presencial en el Centro (Óptimo)</strong>
                                                <span class="text-muted" style="font-size: 0.76rem;">Me encuentro en el centro educativo o realicé visita presencial para constatar el inventario.</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="opcion-verificacion w-100 d-flex align-items-start gap-2" id="lblModFuente">
                                            <input type="radio" name="modalidad_verificacion" value="INDAGACION_FUENTE_CONFIABLE" class="form-check-input mt-1" required onchange="actualizarModalidadGen()">
                                            <div>
                                                <strong class="d-block small text-dark"><i class="bi bi-person-lines-fill text-primary"></i> Indagación de Buena Fuente</strong>
                                                <span class="text-muted" style="font-size: 0.76rem;">Verifiqué el estado de los activos mediante funcionario designado, acta o respaldo formal del centro.</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="detalleFuenteGen" class="form-label fw-bold small">
                                    Respaldo de la verificación (Nombre y cargo del funcionario consultado en el centro, número de oficio/acta o detalle de la visita) <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="detalleFuenteGen" name="detalle_fuente_verificacion" rows="2" required
                                          placeholder="Ej. Verificado en sitio junto al docente encargado Juan Pérez (Céd. 1-1111-1111) / Según reporte físico remitido por la Junta de Educación..."></textarea>
                            </div>
                        </div>

                        <!-- 3. Casilla de Compromiso Formal -->
                        <div class="declaracion-jurada-card mb-4" id="cardDeclaracionGen">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkCompromisoGen" name="acepta_compromiso_legal" value="1" required style="transform: scale(1.3); margin-top: 6px;"
                                       onchange="document.getElementById('cardDeclaracionGen').classList.toggle('aceptada', this.checked)">
                                <label class="form-check-label ms-2" for="chkCompromisoGen">
                                    <strong class="text-dark d-block mb-1">
                                        <i class="bi bi-shield-fill-check text-success"></i> Declaración Jurada y Compromiso del Supervisor de Educación
                                    </strong>
                                    <span class="small text-secondary">
                                        Yo, <strong><?= htmlspecialchars($lognombre) ?></strong> (Cédula <strong><?= htmlspecialchars($logusuario) ?></strong>), en mi calidad de Supervisor(a) de Educación y jefatura inmediata del centro seleccionado, declaro bajo fe de juramento que me encuentro en el centro educativo o he indagado y corroborado de buena fuente el estado físico real de los activos que procederé a certificar, asumiendo la responsabilidad administrativa por la veracidad de esta información.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Registrar Compromiso y Cargar Activos del Centro
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================================
             APARTADO 2: SOLO SUBIR PDF FIRMADO CON GAUDI (SIMPLIFICADO)
             ================================================================================= -->
        <div class="tab-pane fade <?= $tabActiva === 'subir' ? 'show active' : '' ?>" id="panelSubir" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-cloud-upload-fill text-success me-2"></i>
                        Subida Directa de PDF de Certificación Firmado por el Supervisor
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info small mb-4">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        <strong>¿Cuándo usar este apartado?</strong>
                        Úselo cuando el PDF de certificación <strong>ya fue generado previamente</strong> (ya sea por el propio director/encargado del centro educativo que tuvo problemas para firmar con su tarjeta, o por usted mismo en una sesión anterior) y usted ya lo firmó digitalmente con <strong>GAUDI</strong>. El sistema validará el UUID dentro del PDF, registrará su declaración de supervisión y dará por completada la certificación del centro.
                    </div>

                    <form id="formSubidaDirectaSupervisor" onsubmit="return false;">

                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-1-circle-fill me-1"></i> Indique el Centro Educativo al que pertenece el PDF firmado
                        </h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="selRegionalSub" class="form-label fw-bold small">Dirección Regional <span class="text-danger">*</span></label>
                                <select class="form-select" id="selRegionalSub" name="id_regional" required onchange="alCambiarRegional('selRegionalSub', 'selCircuitoSub', 'selCentroSub', 'inputRegionalNombreSub')">
                                    <option value="">-- Seleccione Dirección Regional --</option>
                                    <?php foreach ($listaRegionales as $reg): ?>
                                        <option value="<?= (int)$reg['id'] ?>" data-nombre="<?= htmlspecialchars($reg['nombre']) ?>">
                                            <?= htmlspecialchars($reg['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="regional_nombre" id="inputRegionalNombreSub" value="">
                            </div>

                            <div class="col-md-3">
                                <label for="selCircuitoSub" class="form-label fw-bold small">Circuito <span class="text-danger">*</span></label>
                                <select class="form-select" id="selCircuitoSub" name="circuito" required disabled onchange="alCambiarCircuito('selRegionalSub', 'selCircuitoSub', 'selCentroSub')">
                                    <option value="">-- Primero elija Regional --</option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label for="selCentroSub" class="form-label fw-bold small">Centro Educativo <span class="text-danger">*</span></label>
                                <select class="form-select" id="selCentroSub" name="codigo_centro" required disabled>
                                    <option value="">-- Primero elija Circuito --</option>
                                </select>
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-2-circle-fill me-1"></i> Declaración de Verificación y Selección del Archivo Firmado
                        </h6>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="motivoSub" class="form-label fw-bold small">Motivo por el que firma el Supervisor <span class="text-danger">*</span></label>
                                <select class="form-select" id="motivoSub" name="motivo_intervencion" required>
                                    <option value="PROBLEMA_FIRMA_DIRECTOR">Director(a) generó el PDF pero tuvo problema para firmar</option>
                                    <option value="SIN_DIRECTOR">Centro educativo sin Director(a) nombrado(a)</option>
                                    <option value="INTERVENCION_SUPERVISION">Disposición directa de Supervisión de Circuito</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="modalidadSub" class="form-label fw-bold small">Modalidad de Verificación <span class="text-danger">*</span></label>
                                <select class="form-select" id="modalidadSub" name="modalidad_verificacion" required>
                                    <option value="INDAGACION_FUENTE_CONFIABLE">Indagación de buena fuente (verificado con personal/director del centro)</option>
                                    <option value="PRESENCIAL_EN_CENTRO">Verificación presencial en el centro educativo</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="detalleSub" class="form-label fw-bold small">Fuente / Funcionario del centro consultado <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="detalleSub" name="detalle_fuente_verificacion" required
                                       placeholder="Ej. Inventario levantado por el director / docente...">
                            </div>

                            <div class="col-12">
                                <label for="inputPdfSupervisorDirecto" class="form-label fw-bold">
                                    <i class="bi bi-file-earmark-lock2-fill text-danger me-1"></i> Seleccionar archivo PDF firmado con GAUDI: <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control form-control-lg" id="inputPdfSupervisorDirecto" name="pdf_firmado" accept=".pdf" required>
                            </div>
                        </div>

                        <div class="declaracion-jurada-card mb-4" id="cardDeclaracionSub">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkCompromisoSub" name="acepta_compromiso_legal" value="1" required style="transform: scale(1.3); margin-top: 6px;"
                                       onchange="document.getElementById('cardDeclaracionSub').classList.toggle('aceptada', this.checked)">
                                <label class="form-check-label ms-2" for="chkCompromisoSub">
                                    <strong class="text-dark d-block mb-1">
                                        <i class="bi bi-shield-fill-check text-success"></i> Acepto el Compromiso de Supervisión al Subir este Documento
                                    </strong>
                                    <span class="small text-secondary">
                                        Declaro bajo fe de juramento que, antes de estampar mi firma digital GAUDI y subir este documento en representación del centro educativo indicado, verifiqué presencialmente o indagué de buena fuente que los activos contenidos en el PDF corresponden fielmente a la realidad física de la institución.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div id="estadoSubidaDirecta" style="display: none;" class="text-primary fw-bold">
                                <span class="spinner-border spinner-border-sm me-2"></span> Validando firma GAUDI y registrando certificación...
                            </div>
                            <button type="button" id="btnSubirDirecto" class="btn btn-success btn-lg px-4 fw-bold ms-auto shadow-sm" onclick="ejecutarSubidaDirectaSupervisor()">
                                <i class="bi bi-cloud-check-fill me-2"></i> Subir y Procesar Certificación Firmada
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de ayuda: Certificaciones Pendientes de Firma generadas en las últimas 24 horas -->
            <?php if (!empty($pendientesActivas)): ?>
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-secondary">
                        <i class="bi bi-hourglass-split text-warning me-1"></i>
                        Certificaciones Pendientes de Subida (Generadas en las últimas 24h)
                    </h6>
                    <span class="badge bg-warning text-dark"><?= count($pendientesActivas) ?> pendientes</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 320px;">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Código / Centro</th>
                                    <th>Circuito</th>
                                    <th>Fuente Presupuestaria</th>
                                    <th>Generado por</th>
                                    <th>Expira</th>
                                    <th class="pe-3 text-end">Autocompletar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendientesActivas as $pa): ?>
                                <tr>
                                    <td class="ps-3">
                                        <strong><?= htmlspecialchars($pa['centro_id']) ?></strong> — <?= htmlspecialchars($pa['centro_nombre']) ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">Circ. <?= htmlspecialchars($pa['circuito'] ?: 'S/C') ?></span></td>
                                    <td><?= htmlspecialchars($pa['fuente_presupuestaria_nombre']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($pa['funcionario_nombre']) ?>
                                        <small class="text-muted d-block">UUID: <code><?= htmlspecialchars(substr($pa['certificacion_uuid'], 0, 13)) ?>...</code></small>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($pa['expira_en'])) ?></td>
                                    <td class="pe-3 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="autocompletarSubidaDesdePendiente('<?= htmlspecialchars($pa['centro_id'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-arrow-up-circle"></i> Seleccionar este centro
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Historial reciente de intervenciones de este supervisor -->
    <?php if (!empty($misCompromisos)): ?>
    <div class="card shadow-sm border-0 rounded-4 mt-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-secondary">
                <i class="bi bi-journal-check text-primary me-1"></i>
                Sus últimos compromisos de supervisión registrados
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Regional / Circuito</th>
                            <th>Centro Educativo</th>
                            <th>Fuente</th>
                            <th>Modalidad Declarada</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($misCompromisos as $mc): ?>
                        <tr>
                            <td class="ps-3"><?= date('d/m/Y H:i', strtotime($mc['fecha_compromiso'])) ?></td>
                            <td><?= htmlspecialchars($mc['regional_nombre'] ?: ('Reg. ' . $mc['id_regional'])) ?> / Circ. <?= htmlspecialchars($mc['circuito']) ?></td>
                            <td><strong><?= htmlspecialchars($mc['centro_codigo']) ?></strong> - <?= htmlspecialchars($mc['centro_nombre']) ?></td>
                            <td><?= htmlspecialchars($mc['fuente_presupuestaria_nombre'] ?: '-') ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= $mc['modalidad_verificacion'] === 'PRESENCIAL_EN_CENTRO' ? 'Presencial en Centro' : 'Indagación Buena Fuente' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($mc['estado_proceso'] === 'COMPLETADO'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Completado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pendiente Firma</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal de resultado para la subida directa -->
<div class="modal fade" id="modalResultadoSubida" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="resSubHeader">
                <h5 class="modal-title" id="resSubTitulo"></h5>
            </div>
            <div class="modal-body" id="resSubMensaje"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCerrarResSub">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/partials/footer.php')) { include 'partials/footer.php'; } ?>

<script>
// Catálogo completo de centros educativos en memoria para cascada instantánea Regional -> Circuito -> Centro
const CATALOGO_CENTROS = <?= json_encode($catalogoCentros, JSON_UNESCAPED_UNICODE) ?>;
const SESION_SUPERVISOR = {
    idRegional:     <?= (int)$sesionIdRegional ?>,
    nombreRegional: <?= json_encode($sesionNombreRegional, JSON_UNESCAPED_UNICODE) ?>,
    circuito:       <?= json_encode($sesionCircuito, JSON_UNESCAPED_UNICODE) ?>
};

document.addEventListener('DOMContentLoaded', function () {
    // Si la sesión de Azure (usuarioAzure.php) ya trae idRegional y/o circuito del supervisor,
    // preseleccionarlos automáticamente en ambos apartados para mayor ergonomía, permitiendo cambiarlos libremente.
    if (SESION_SUPERVISOR.idRegional > 0) {
        ['', 'Sub'].forEach(sufijo => {
            const idReg    = sufijo ? 'selRegionalSub' : 'selRegional';
            const idCirc   = sufijo ? 'selCircuitoSub' : 'selCircuito';
            const idCentro = sufijo ? 'selCentroSub'   : 'selCentro';
            const idNom    = sufijo ? 'inputRegionalNombreSub' : 'inputRegionalNombre';

            const selReg = document.getElementById(idReg);
            if (selReg && selReg.querySelector(`option[value="${SESION_SUPERVISOR.idRegional}"]`)) {
                selReg.value = String(SESION_SUPERVISOR.idRegional);
                alCambiarRegional(idReg, idCirc, idCentro, idNom);

                if (SESION_SUPERVISOR.circuito) {
                    const selCirc = document.getElementById(idCirc);
                    const circNorm = String(SESION_SUPERVISOR.circuito).trim();
                    const circPad  = circNorm.padStart(2, '0');
                    let optMatch = selCirc.querySelector(`option[value="${circNorm}"]`) || selCirc.querySelector(`option[value="${circPad}"]`);
                    if (optMatch) {
                        selCirc.value = optMatch.value;
                        alCambiarCircuito(idReg, idCirc, idCentro);
                    }
                }
            }
        });
    }
});

function alCambiarRegional(idSelectReg, idSelectCirc, idSelectCentro, idInputRegNombre) {
    const selReg    = document.getElementById(idSelectReg.trim());
    const selCirc   = document.getElementById(idSelectCirc);
    const selCentro = document.getElementById(idSelectCentro);
    const inpNombre = document.getElementById(idInputRegNombre);

    const valReg = selReg.value ? parseInt(selReg.value, 10) : 0;
    const optSel = selReg.options[selReg.selectedIndex];
    if (inpNombre) {
        inpNombre.value = optSel && optSel.dataset.nombre ? optSel.dataset.nombre : '';
    }

    selCirc.innerHTML   = '<option value="">-- Seleccione Circuito --</option>';
    selCentro.innerHTML = '<option value="">-- Primero elija Circuito --</option>';
    selCentro.disabled  = true;

    if (!valReg) {
        selCirc.disabled = true;
        return;
    }

    // Extraer circuitos únicos pertenecientes a esta Dirección Regional
    const circuitosSet = new Set();
    CATALOGO_CENTROS.forEach(c => {
        if (c.id_regional === valReg) {
            circuitosSet.add(c.circuito);
        }
    });

    const circuitos = Array.from(circuitosSet).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    circuitos.forEach(circ => {
        const opt = document.createElement('option');
        opt.value = circ;
        opt.textContent = `Circuito ${circ}`;
        selCirc.appendChild(opt);
    });

    selCirc.disabled = false;
}

function alCambiarCircuito(idSelectReg, idSelectCirc, idSelectCentro) {
    const valReg    = parseInt(document.getElementById(idSelectReg.trim()).value || '0', 10);
    const valCirc   = document.getElementById(idSelectCirc).value;
    const selCentro = document.getElementById(idSelectCentro);

    selCentro.innerHTML = '<option value="">-- Seleccione Centro Educativo --</option>';

    if (!valReg || !valCirc) {
        selCentro.disabled = true;
        return;
    }

    const centrosFiltrados = CATALOGO_CENTROS.filter(c => c.id_regional === valReg && c.circuito === valCirc);
    centrosFiltrados.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.codigo;
        opt.textContent = `${c.codigo} — ${c.institucion}`;
        selCentro.appendChild(opt);
    });

    selCentro.disabled = false;
}

function alSeleccionarCentro(idSelectCentro, idInputRapido) {
    const selCentro = document.getElementById(idSelectCentro);
    const inpRapido = document.getElementById(idInputRapido);
    if (selCentro && inpRapido && selCentro.value) {
        inpRapido.value = selCentro.value;
    }
}

function buscarCentroPorCodigoRapido(codigoBuscado, idSelectReg, idSelectCirc, idSelectCentro, idInputRegNombre, idFeedback) {
    const codigo = (codigoBuscado || '').trim();
    const fb     = document.getElementById(idFeedback);
    if (!codigo) {
        if (fb) fb.textContent = '';
        return;
    }

    const encontrado = CATALOGO_CENTROS.find(c => c.codigo.toLowerCase() === codigo.toLowerCase());
    if (!encontrado) {
        if (fb) {
            fb.className = 'text-danger small d-block mt-1';
            fb.textContent = 'No se encontró ningún centro activo con ese código exacto.';
        }
        return;
    }

    const selReg = document.getElementById(idSelectReg);
    selReg.value = String(encontrado.id_regional);
    alCambiarRegional(idSelectReg, idSelectCirc, idSelectCentro, idInputRegNombre);

    const selCirc = document.getElementById(idSelectCirc);
    selCirc.value = encontrado.circuito;
    alCambiarCircuito(idSelectReg, idSelectCirc, idSelectCentro);

    const selCentro = document.getElementById(idSelectCentro);
    selCentro.value = encontrado.codigo;

    if (fb) {
        fb.className = 'text-success fw-semibold small d-block mt-1';
        fb.innerHTML = `<i class="bi bi-check-circle-fill"></i> Seleccionado: ${encontrado.institucion} (Circuito ${encontrado.circuito})`;
    }
}

function autocompletarSubidaDesdePendiente(codigoCentro) {
    buscarCentroPorCodigoRapido(codigoCentro, 'selRegionalSub', 'selCircuitoSub', 'selCentroSub', 'inputRegionalNombreSub', '');
    document.getElementById('inputPdfSupervisorDirecto')?.focus();
    window.scrollTo({ top: 280, behavior: 'smooth' });
}

function actualizarModalidadGen() {
    const radios = document.querySelectorAll('input[name="modalidad_verificacion"]');
    let val = '';
    radios.forEach(r => { if (r.checked) val = r.value; });

    document.getElementById('lblModPresencial')?.classList.toggle('seleccionada', val === 'PRESENCIAL_EN_CENTRO');
    document.getElementById('lblModFuente')?.classList.toggle('seleccionada', val === 'INDAGACION_FUENTE_CONFIABLE');
}

function validarFormularioGenerar() {
    const reg    = document.getElementById('selRegional').value;
    const circ   = document.getElementById('selCircuito').value;
    const centro = document.getElementById('selCentro').value;
    const fondo  = document.getElementById('selFondos').value;
    const chk    = document.getElementById('chkCompromisoGen').checked;
    const det    = document.getElementById('detalleFuenteGen').value.trim();

    if (!reg || !circ || !centro || !fondo) {
        alert('Debe seleccionar la Dirección Regional, Circuito, Centro Educativo y Fuente Presupuestaria.');
        return false;
    }
    if (!det) {
        alert('Por favor indique el respaldo de su verificación (nombre del funcionario consultado o detalle de la visita).');
        return false;
    }
    if (!chk) {
        alert('Debe aceptar la Declaración Jurada y Compromiso del Supervisor para continuar.');
        return false;
    }
    return true;
}

// ── Envío del Apartado 2: Subida Directa de PDF Firmado ──
async function ejecutarSubidaDirectaSupervisor() {
    const reg       = document.getElementById('selRegionalSub').value;
    const regNombre = document.getElementById('inputRegionalNombreSub').value;
    const circ      = document.getElementById('selCircuitoSub').value;
    const centro    = document.getElementById('selCentroSub').value;
    const motivo    = document.getElementById('motivoSub').value;
    const modalidad = document.getElementById('modalidadSub').value;
    const detalle   = document.getElementById('detalleSub').value.trim();
    const chk       = document.getElementById('chkCompromisoSub').checked;
    const fileInput = document.getElementById('inputPdfSupervisorDirecto');

    if (!reg || !circ || !centro) {
        alert('Seleccione la Dirección Regional, Circuito y Centro Educativo al que pertenece el PDF.');
        return;
    }
    if (!detalle) {
        alert('Indique la fuente o funcionario del centro con quien verificó el inventario.');
        return;
    }
    if (!fileInput.files.length) {
        alert('Seleccione el archivo PDF firmado con GAUDI.');
        return;
    }
    if (!chk) {
        alert('Es obligatorio marcar la casilla de aceptación del compromiso de supervisión.');
        return;
    }

    const btn    = document.getElementById('btnSubirDirecto');
    const estado = document.getElementById('estadoSubidaDirecta');

    btn.disabled = true;
    estado.style.display = 'block';

    const fd = new FormData();
    fd.append('pdf_firmado', fileInput.files[0]);
    fd.append('id_regional', reg);
    fd.append('regional_nombre', regNombre);
    fd.append('circuito', circ);
    fd.append('codigo_centro', centro);
    fd.append('motivo_intervencion', motivo);
    fd.append('modalidad_verificacion', modalidad);
    fd.append('detalle_fuente_verificacion', detalle);
    fd.append('acepta_compromiso_legal', '1');

    try {
        const resp = await fetch('supervisor_subir_certificacion.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const rawText = await resp.text();
        let result = null;
        try {
            result = JSON.parse(rawText);
        } catch (err) {
            result = {
                success: false,
                message: 'El servidor devolvió una respuesta inesperada. Si su sesión expiró por inactividad, recargue la página e intente de nuevo.'
            };
        }

        estado.style.display = 'none';
        const modal = new bootstrap.Modal(document.getElementById('modalResultadoSubida'));

        if (result.success) {
            document.getElementById('resSubHeader').className = 'modal-header bg-success text-white';
            document.getElementById('resSubTitulo').innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Certificación por Supervisión Registrada';
            document.getElementById('resSubMensaje').innerHTML = `
                <div class="text-center">
                    <i class="bi bi-shield-check fs-1 text-success mb-2 d-block"></i>
                    <p><strong>${result.message}</strong></p>
                    <p class="small text-muted mb-1">Centro: <strong>${result.centro_codigo} - ${result.centro_nombre || ''}</strong></p>
                    <p class="small text-muted mb-1">Activos certificados: <strong>${result.total_activos}</strong></p>
                    <p class="small text-muted mb-1">UUID: <code>${result.uuid}</code></p>
                    <hr>
                    <p class="small text-success mb-0">
                        <i class="bi bi-journal-check"></i> Su compromiso de supervisión y firma digital GAUDI quedaron registrados exitosamente.
                    </p>
                </div>
            `;
            document.getElementById('btnCerrarResSub').onclick = () => {
                window.location.href = 'navegar.php?ruta=supervisor_centro_certificacion.php&tab=subir';
            };
        } else {
            document.getElementById('resSubHeader').className = 'modal-header bg-danger text-white';
            document.getElementById('resSubTitulo').innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> No se pudo procesar el PDF';
            document.getElementById('resSubMensaje').innerHTML = `
                <div class="text-center">
                    <i class="bi bi-x-circle fs-1 text-danger mb-2 d-block"></i>
                    <p><strong>${result.message}</strong></p>
                </div>
            `;
            document.getElementById('btnCerrarResSub').onclick = null;
            btn.disabled = false;
        }

        modal.show();
    } catch (e) {
        estado.style.display = 'none';
        btn.disabled = false;
        alert('Error de conexión al subir el archivo: ' + e.message);
    }
}
</script>
</body>
</html>
