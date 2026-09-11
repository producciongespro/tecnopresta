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

// ==== DATOS DEL PERFIL (fuente única: sesión empresarial del funcionario) ====
$foto_perfil     = $usuario_azure['fotoPerfil'] ?? 'assets/img/avatarH.svg';
$nombre_completo = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$cedula          = $usuario_azure['cedula']       ?? null;
$correo          = $usuario_azure['correo']       ?? null;
$clase_puesto    = $usuario_azure['clasePuesto']  ?? null;
$especialidad    = $usuario_azure['especialidad'] ?? null;
$regional        = $usuario_azure['regional']     ?? null;
$circuito        = $usuario_azure['circuito']     ?? null;
$dependencia     = $usuario_azure['dependencia']  ?? null;
$codigo_presu    = $usuario_azure['codigoPresu']  ?? null;

// ==== ROL (primer rol de la autorización empresarial) ====
$rol_nombre = '';
foreach (($usuario_azure['roles'] ?? []) as $rol) {
    if (!empty($rol['rol'])) {
        $rol_nombre = $rol['rol'];
        break;
    }
}

// ==== ESTADÍSTICAS: conteo de solicitudes de préstamo ====
$total_solicitudes = 0;
require_once __DIR__ . '/conexion.php';

if (isset($mysqli) && !$mysqli->connect_errno) {
    mysqli_set_charset($mysqli, "utf8");

    if ($stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) FROM t_prestamo WHERE prestamo_cedula_funcionario = ?")) {
        mysqli_stmt_bind_param($stmt, "s", $cedula);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $total_solicitudes);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_close($mysqli);
}

$total_solicitudes = max(0, (int)$total_solicitudes);

if ($total_solicitudes > 20) {
    $trofeo = "trofeo/4.png";
} elseif ($total_solicitudes > 10) {
    $trofeo = "trofeo/3.png";
} elseif ($total_solicitudes > 1) {
    $trofeo = "trofeo/2.png";
} else {
    $trofeo = "trofeo/1.png";
}

// ==== UTILIDAD PARA MOSTRAR VALORES DE FORMA SEGURA ====
function presentar($valor): string {
    $valor = trim((string)($valor ?? ''));
    return $valor === '' ? '&mdash;' : htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// ==== VERIFICA SI UN DATO EXISTE (para ocultar tarjetas vacías) ====
function tieneValor($valor): bool {
    return trim((string)($valor ?? '')) !== '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Mi Perfil | TecnoPresta</title>
  <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 CSS -->
  <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="css/bootstrap-icons.css" rel="stylesheet">
  <!-- Nueva Identidad Gráfica Gobierno de Costa Rica -->
  <link rel="stylesheet" href="assets/css/nueva-identidad.css">
  <!-- Componentes MEP -->
  <link rel="stylesheet" href="css/formulario_menu_principal.css">

  <style>
    :root {
      --mep-primary:   #192952;
      --mep-secondary: #0035A0;
      --mep-accent:    #CFAC65;
      --mep-gold:      #C8A951;
      --mep-blue:      #003876;
      --mep-blue2:     #114c91;
      --mep-text:      #2C3E50;
    }

    body {
      background: linear-gradient(180deg, #f4f7fb 0%, #eef2f7 100%);
      min-height: 100vh;
    }

    /* ── Animación de entrada ── */
    .fade-up { animation: perfilFade .5s ease both; }
    .delay-1  { animation-delay: .08s; }
    .delay-2  { animation-delay: .16s; }
    .delay-3  { animation-delay: .24s; }

    @keyframes perfilFade {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Encabezado de la sección ── */
    .perfil-titulo h4 { color: var(--mep-primary); }

    /* ── HERO ── */
    .perfil-hero {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, var(--mep-primary) 0%, var(--mep-secondary) 100%);
      color: #fff;
      border-radius: 24px;
      padding: 40px 40px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, .14);
      margin-bottom: 28px;
    }
    .perfil-hero::after {
      content: '';
      position: absolute;
      top: -45%;
      right: -8%;
      width: 420px;
      height: 420px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .05);
    }
    .perfil-hero::before {
      content: '';
      position: absolute;
      bottom: -40%;
      left: -6%;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(200, 169, 81, .10);
    }
    .perfil-avatar-wrap {
      position: relative;
      width: 150px;
      height: 150px;
      margin-inline: auto;
    }
    .perfil-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
      border: 4px solid var(--mep-gold);
      background: #fff;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .30);
      display: block;
    }
    .perfil-avatar-wrap::after {
      content: '';
      position: absolute;
      inset: -8px;
      border-radius: 50%;
      border: 1px dashed rgba(255, 255, 255, .35);
      animation: perfilGiro 18s linear infinite;
    }
    @keyframes perfilGiro {
      to { transform: rotate(360deg); }
    }
    .perfil-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, .14);
      backdrop-filter: blur(6px);
      border: 1px solid rgba(255, 255, 255, .22);
      color: #fff;
      padding: 5px 14px;
      border-radius: 30px;
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .3px;
    }
    .perfil-chip .bi { color: var(--mep-gold); }
    .perfil-nombre {
      font-weight: 700;
      font-size: 1.9rem;
      margin: 12px 0 6px;
      line-height: 1.2;
      position: relative;
      z-index: 1;
    }
    .perfil-sub {
      opacity: .85;
      font-size: .95rem;
      margin: 0;
      position: relative;
      z-index: 1;
    }
    .perfil-sub .separador {
      margin: 0 8px;
      opacity: .45;
    }

    @media (max-width: 767.98px) {
      .perfil-hero { padding: 28px 20px; }
      .perfil-nombre { font-size: 1.45rem; }
      .perfil-avatar-wrap { width: 120px; height: 120px; }
    }

    /* ── TARJETAS DE ESTADÍSTICAS ── */
    .stat-card {
      background: rgba(255, 255, 255, .92);
      backdrop-filter: blur(10px);
      border-radius: 14px;
      border: none;
      border-bottom: 3px solid var(--mep-primary);
      box-shadow: 0 8px 25px rgba(0, 0, 0, .06);
      padding: 18px;
      display: flex;
      align-items: center;
      gap: 14px;
      height: 100%;
      transition: all .25s ease;
    }
    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(0, 56, 118, .12);
    }
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      color: #fff;
      background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
      flex-shrink: 0;
    }
    .stat-icon.oro {
      background: linear-gradient(135deg, #a8892f, var(--mep-gold));
    }
    .stat-icon.acento {
      background: linear-gradient(135deg, var(--mep-primary), var(--mep-secondary));
    }
    .stat-body { min-width: 0; }
    .stat-value {
      display: block;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--mep-primary);
      line-height: 1.1;
      font-variant-numeric: tabular-nums;
    }
    .stat-label {
      display: block;
      font-size: .78rem;
      color: #5a6f82;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .stat-trofeo {
      height: 38px;
      object-fit: contain;
      display: block;
    }

    /* ── TÍTULO DE SECCIÓN ── */
    .section-title-wrap { margin: 6px 0 16px; }
    .section-title-wrap .bi {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--mep-blue), var(--mep-blue2));
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      margin-right: 10px;
    }
    .section-title-wrap h5 {
      color: var(--mep-primary);
      font-weight: 700;
      margin: 0;
      display: inline-flex;
      align-items: center;
    }

    /* ── TARJETAS DE DATOS ── */
    .detail-card {
      background: #fff;
      border: 1px solid #e8ecf0;
      border-left: 3px solid var(--mep-gold);
      border-radius: 12px;
      padding: 16px 18px;
      height: 100%;
      box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
      transition: all .25s ease;
    }
    .detail-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 26px rgba(0, 56, 118, .10);
      border-left-color: var(--mep-primary);
    }
    .detail-label {
      display: block;
      font-size: .72rem;
      font-weight: 700;
      color: #8a9aa8;
      text-transform: uppercase;
      letter-spacing: .7px;
      margin-bottom: 6px;
    }
    .detail-value {
      display: block;
      font-size: .95rem;
      color: #1f3b57;
      font-weight: 600;
      line-height: 1.45;
      word-break: break-word;
    }
    .detail-value a {
      color: var(--mep-secondary);
      text-decoration: none;
      font-weight: 600;
    }
    .detail-value a:hover { text-decoration: underline; }

    /* ── ACCIONES ── */
    .perfil-acciones .btn {
      border-radius: 10px;
      padding: 10px 26px;
      font-weight: 600;
      font-size: .9rem;
    }
  </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <main class="container contenido-principal py-4">

        <!-- ══════════ ENCABEZADO PÁGINA ══════════ -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 perfil-titulo">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-person-badge me-2"></i>Mi Perfil
                </h4>
                <p class="text-muted mb-0 small">Información del funcionario registrada en el Sistema TecnoPresta</p>
            </div>
            </div>

        <!-- ══════════ HERO DEL PERFIL ══════════ -->
        <section class="perfil-hero fade-up">
            <div class="row align-items-center g-4 position-relative">
                <div class="col-md-auto">
                    <div class="perfil-avatar-wrap">
                        <img id="fotoPerfilHero"
                             class="perfil-avatar foto-perfil"
                             src="<?= htmlspecialchars($foto_perfil, ENT_QUOTES, 'UTF-8') ?>"
                             alt="Fotografía de perfil de <?= presentar($nombre_completo) ?>">
                    </div>
                </div>
                <div class="col-md">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if (!empty($rol_nombre)): ?>
                            <span class="perfil-chip">
                                <i class="bi bi-shield-check"></i><?= presentar($rol_nombre) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (tieneValor($dependencia)): ?>
                            <span class="perfil-chip">
                                <i class="bi bi-building"></i><?= presentar($dependencia) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1 class="perfil-nombre"><?= presentar($nombre_completo) ?></h1>
                    <?php if (!empty($regional) && !empty($circuito)): ?>
                        <p class="perfil-sub">
                            <i class="bi bi-geo-alt"></i> <?= presentar($regional) ?>
                            <span class="separador">|</span>
                            <i class="bi bi-diagram-3"></i> <?= presentar($circuito) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ══════════ ESTADÍSTICAS ══════════ -->
        <section class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card fade-up delay-1">
                    <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="stat-body">
                        <span class="stat-value"><?= (int)$total_solicitudes ?></span>
                        <span class="stat-label">Solicitudes de préstamo</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="stat-card fade-up delay-2">
                    <div class="stat-icon oro"><i class="bi bi-trophy-fill"></i></div>
                    <div class="stat-body">
                        <img class="stat-trofeo" src="<?= htmlspecialchars($trofeo, ENT_QUOTES, 'UTF-8') ?>" alt="Nivel de reconocimiento del funcionario">
                        <span class="stat-label">Nivel de logro</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <?php if (tieneValor($rol_nombre)): ?>
                <div class="stat-card fade-up delay-3">
                    <div class="stat-icon acento"><i class="bi bi-shield-check"></i></div>
                    <div class="stat-body text-truncate">
                        <span class="stat-value text-truncate" title="<?= presentar($rol_nombre) ?>"><?= presentar($rol_nombre) ?></span>
                        <span class="stat-label">Rol en el sistema</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ══════════ INFORMACIÓN FUNCIONAL ══════════ -->
        <section class="mb-2">
            <div class="section-title-wrap">
                <h5><span class="bi bi-person-lines-fill"></span>Información funcional</h5>
            </div>

            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($cedula)): ?>
                    <div class="detail-card fade-up">
                        <span class="detail-label"><i class="bi bi-person-vcard me-1"></i>Cédula</span>
                        <span class="detail-value"><?= presentar($cedula) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($clase_puesto)): ?>
                    <div class="detail-card fade-up delay-1">
                        <span class="detail-label"><i class="bi bi-briefcase me-1"></i>Clase de puesto</span>
                        <span class="detail-value"><?= presentar($clase_puesto) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($especialidad)): ?>
                    <div class="detail-card fade-up delay-2">
                        <span class="detail-label"><i class="bi bi-mortarboard me-1"></i>Especialidad</span>
                        <span class="detail-value"><?= presentar($especialidad) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($regional)): ?>
                    <div class="detail-card fade-up">
                        <span class="detail-label"><i class="bi bi-geo-alt me-1"></i>Dirección Regional</span>
                        <span class="detail-value"><?= presentar($regional) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($circuito)): ?>
                    <div class="detail-card fade-up delay-1">
                        <span class="detail-label"><i class="bi bi-diagram-3 me-1"></i>Circuito</span>
                        <span class="detail-value"><?= presentar($circuito) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($dependencia)): ?>
                    <div class="detail-card fade-up delay-2">
                        <span class="detail-label"><i class="bi bi-building me-1"></i>Dependencia</span>
                        <span class="detail-value"><?= presentar($dependencia) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($codigo_presu)): ?>
                    <div class="detail-card fade-up">
                        <span class="detail-label"><i class="bi bi-hash me-1"></i>Código de centro educativo</span>
                        <span class="detail-value"><?= presentar($codigo_presu) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-lg-4">
                    <?php if (tieneValor($correo)): ?>
                    <div class="detail-card fade-up delay-1">
                        <span class="detail-label"><i class="bi bi-envelope me-1"></i>Correo institucional</span>
                        <span class="detail-value">
                            <a href="mailto:<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ══════════ ACCIONES ══════════ -->
        <section class="perfil-acciones d-flex flex-wrap justify-content-center gap-3 my-4">
            <a class="btn btn-mep-primary" href="navegar.php?ruta=formulario_menu_principal.php">
                <i class="bi bi-grid-3x3-gap-fill"></i> Regresar al Menú Principal
            </a>
        </section>
    </main>

    <?php include 'partials/footer.php'; ?>

    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

    <!-- ══════════ FOTO DEL PERFIL (misma fuente que el encabezado) ══════════ -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        var avatarDefault = "assets/img/avatarH.svg";
        var fotos = document.querySelectorAll(".foto-perfil");

        if (!fotos.length) { return; }

        var fotoGuardada = localStorage.getItem("fotoPerfil");

        fotos.forEach(function (img) {
            if (fotoGuardada && fotoGuardada.trim() !== "") {
                img.src = fotoGuardada;
            }
            img.onerror = function () {
                this.src = avatarDefault;
            };
        });
    });
    </script>
</body>
</html>