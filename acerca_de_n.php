<?php
// === BLOQUEAR ACCESO DIRECTO =====
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit("Acceso directo no permitido");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === SECCIÓN A MOSTRAR (seleccionada desde la card del menú principal) ===
// 'acerca' => información institucional | 'manual' => manual de usuario
$seccion = strtolower(trim($_GET['seccion'] ?? 'acerca'));
if (!in_array($seccion, ['acerca', 'manual'], true)) {
    $seccion = 'acerca';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Acerca de | TecnoPresta</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/nueva-identidad.css">
  <link rel="stylesheet" href="css/formulario_menu_principal.css" />
  <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <script src="js/jquery-3.7.1.min.js"></script>
  <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
  <style>
    :root {
      --mep-primary: #192952;
      --mep-secondary: #0035A0;
      --mep-accent: #CFAC65;
      --mep-gold: #C8A951;
      --mep-blue: #003876;
      --mep-blue2: #114c91;
      --mep-text: #2C3E50;
    }

    html { scroll-behavior: smooth; }

    /* ══════ Hero ══════ */
    .about-hero {
      background: linear-gradient(135deg, var(--mep-blue) 0%, var(--mep-blue2) 100%);
      color: #fff;
      border-radius: 24px;
      padding: 48px 40px;
      margin-top: 28px;
      margin-bottom: 36px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.12);
      position: relative;
      overflow: hidden;
    }
    .about-hero::after {
      content: '';
      position: absolute;
      top: -40%;
      right: -10%;
      width: 420px;
      height: 420px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
    }
    .about-hero h1 {
      font-weight: 700;
      font-size: 2.2rem;
      margin-bottom: 8px;
      position: relative;
      z-index: 1;
    }
    .about-hero p {
      font-size: 1.05rem;
      opacity: 0.85;
      margin: 0;
      position: relative;
      z-index: 1;
    }
    .about-hero .hero-icon {
      width: 72px;
      height: 72px;
      border-radius: 20px;
      background: rgba(255,255,255,0.14);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      backdrop-filter: blur(8px);
      margin-bottom: 8px;
    }

    /* ══════ Cards de información ══════ */
    .about-card {
      border: none;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 4px 16px rgba(0,0,0,0.05);
      transition: all 0.3s ease;
      height: 100%;
      border-top: 3px solid transparent;
    }
    .about-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 14px 36px rgba(0,56,118,0.12);
      border-top-color: var(--mep-gold);
    }
    .about-card .about-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      background: rgba(0,56,118,0.08);
      color: var(--mep-blue);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 14px;
    }
    .about-card .card-title { font-weight: 600; color: var(--mep-primary); font-size: 1.05rem; }
    .about-card .card-text { color: var(--mep-text); font-size: 0.9rem; line-height: 1.6; }

    /* ══════ Secciones del manual ══════ */
    .manual-section {
      margin-bottom: 28px;
    }
    .manual-section .manual-head {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }
    .manual-section .manual-num {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: var(--mep-gold);
      color: #fff;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      flex-shrink: 0;
    }
    .manual-section h4 { font-weight: 600; color: var(--mep-primary); margin: 0; font-size: 1.1rem; }
    .manual-section ul { color: var(--mep-text); font-size: 0.92rem; line-height: 1.7; padding-left: 1.2rem; }
    .manual-section li { margin-bottom: 6px; }

    /* ══════ Equipo de desarrollo ══════ */
    .team-block { padding-top: 2px; }
    .team-rol {
      display: inline-block;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      color: #7b8899;
      margin-bottom: 8px;
      background: rgba(0,56,118,0.05);
      padding: 4px 10px;
      border-radius: 20px;
    }
    .team-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .team-list li {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 0;
      color: var(--mep-text);
      font-weight: 500;
      font-size: 0.9rem;
      line-height: 1.4;
      border-bottom: 1px dashed rgba(0,56,118,0.08);
    }
    .team-list li:last-child { border-bottom: none; }
    .team-list li i {
      color: var(--mep-gold);
      font-size: 1rem;
      flex-shrink: 0;
    }

    /* Separación sutil entre el equipo actual y el de la versión anterior */
    .team-divider {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin: 16px 0;
      color: #8a97a8;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      text-align: center;
    }
    .team-divider::before,
    .team-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: linear-gradient(to right, rgba(0,56,118,0.16), rgba(207,172,101,0.45));
    }
    .team-divider::before {
      background: linear-gradient(to right, rgba(0,56,118,0.16), rgba(207,172,101,0.45));
    }
    .team-divider::after {
      background: linear-gradient(to left, rgba(0,56,118,0.16), rgba(207,172,101,0.45));
    }

    .section-divider {
      height: 1px;
      background: linear-gradient(to right, transparent, var(--mep-gold), transparent);
      margin: 28px 0;
      opacity: 0.4;
    }

    /* ══════ Botones flotantes ══════ */
    .btn-to-top {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--mep-primary);
      color: #fff;
      border: none;
      font-size: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
      transition: all 0.3s ease;
      z-index: 1030;
      cursor: pointer;
      text-decoration: none;
      opacity: 0;
      visibility: hidden;
      transform: translateY(12px);
    }
    .btn-to-top.show {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }
    .btn-to-top:hover { transform: scale(1.1); background: var(--mep-secondary); color: #fff; }
    .btn-to-subsistemas {
      position: fixed;
      bottom: 96px;
      right: 30px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--mep-gold);
      color: #fff;
      border: none;
      font-size: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
      transition: all 0.3s ease;
      z-index: 1030;
      cursor: pointer;
      text-decoration: none;
    }
    .btn-to-subsistemas:hover { transform: scale(1.1); background: var(--mep-primary); color: #fff; }
    .btn-to-top::before,
    .btn-to-subsistemas::before {
      position: absolute;
      right: 64px;
      background: rgba(0,0,0,0.8);
      color: #fff;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 0.75rem;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
    }
    .btn-to-subsistemas::before { content: "Volver a Menú Principal"; }
    .btn-to-top::before { content: "Volver arriba"; }
    .btn-to-subsistemas:hover::before,
    .btn-to-top:hover::before { opacity: 1; }

    @media (max-width: 768px) {
      .about-hero { padding: 32px 20px; }
      .about-hero h1 { font-size: 1.6rem; }
      .btn-to-subsistemas { width: 48px; height: 48px; font-size: 20px; bottom: 78px; right: 20px; }
      .btn-to-top { width: 48px; height: 48px; font-size: 18px; bottom: 20px; right: 20px; }
      .btn-to-subsistemas::before, .btn-to-top::before { display: none; }
    }
  </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <div class="container contenido-principal py-3">

        <!-- ══════ HERO ══════ -->
        <div class="about-hero d-flex align-items-center gap-4">
            <div class="hero-icon flex-shrink-0">
                <?php if ($seccion === 'manual'): ?>
                    <i class="bi bi-journal-bookmark-fill"></i>
                <?php else: ?>
                    <i class="bi bi-info-circle-fill"></i>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($seccion === 'manual'): ?>
                    <h1>Manual de usuario</h1>
                    <p>Gu&iacute;a de uso del sistema TecnoPresta</p>
                <?php else: ?>
                    <h1>Acerca de...</h1>
                    <p>Informaci&oacute;n general del sistema TecnoPresta</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($seccion === 'manual'): ?>

            <!-- ══════ CONTENIDO: MANUAL DE USUARIO ══════ -->
            <div class="manual-section">
                <div class="manual-head">
                    <div class="manual-num">1</div>
                    <h4>Acceso al sistema</h4>
                </div>
                <ul>
                    <li>Ingrese con sus credenciales institucionales en la pantalla de inicio de sesi&oacute;n.</li>
                    <li>Tras autenticarse ver&aacute; el <strong>Men&uacute; Principal</strong> con todos los m&oacute;dulos disponibles seg&uacute;n su perfil.</li>
                    <li>Para salir, use la opci&oacute;n <strong>Cerrar Sesión</strong> del men&uacute; del usuario.</li>
                </ul>
            </div>

            <div class="manual-section">
                <div class="manual-head">
                    <div class="manual-num">2</div>
                    <h4>Men&uacute; y subm&oacute;dulos</h4>
                </div>
                <ul>
                    <li>El <strong>Men&uacute; Principal</strong> organiza los subsistemas como tarjetas.</li>
                    <li>Cada subsistema contiene <strong>m&oacute;dulos</strong> y estos a su vez los <strong>formularios</strong> de gesti&oacute;n.</li>
                    <li>Use el buscador dentro de cada m&oacute;dulo para localizar r&aacute;pidamente un formulario.</li>
                </ul>
            </div>

            <div class="manual-section">
                <div class="manual-head">
                    <div class="manual-num">3</div>
                    <h4>Operaciones frecuentes</h4>
                </div>
                <ul>
                    <li><strong>Inventario de activos:</strong> consulte el listado de placas, series y estado de los equipos.</li>
                    <li><strong>Mantenimiento y software:</strong> registre y consulte los mantenimientos y licencias de software.</li>
                    <li><strong>Reportes:</strong> genere listados por dependencia, clase o fuente de financiamiento.</li>
                    <li><strong>Pr&eacute;stamo:</strong> registre solicitudes de equipo y administre los pr&eacute;stamos pendientes.</li>
                </ul>
            </div>

            <div class="manual-section">
                <div class="manual-head">
                    <div class="manual-num">4</div>
                    <h4>Videos de ayuda</h4>
                </div>
                <ul>
                    <li>Visite el <strong>Centro de Ayuda</strong> del sistema para ver gu&iacute;as en video por m&oacute;dulo.</li>
                    <li>Cada video muestra el flujo paso a paso de las tareas m&aacute;s comunes.</li>
                </ul>
            </div>

            <div class="manual-section">
                <div class="manual-head">
                    <div class="manual-num">5</div>
                    <h4>Soporte y contacto</h4>
                </div>
                <ul>
                    <li>Consulte la opci&oacute;n <strong>Cont&aacute;ctenos</strong> para reportar incidencias o solicitar asistencia.</li>
                    <li>Mantenga actualizada su informaci&oacute;n de perfil (fotograf&iacute;a y datos personales).</li>
                </ul>
            </div>

        <?php else: ?>

            <!-- ══════ CONTENIDO: ACERCA DE ══════ -->
            <div class="text-center mb-4">
                <img src="assets/img/logo-mep.svg" alt="Logo MEP" style="height: 64px; opacity: 0.9;">
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card about-card p-4">
                        <div class="about-icon"><i class="bi bi-laptop"></i></div>
                        <h5 class="card-title">Sistema TecnoPresta</h5>
                        <p class="card-text mb-0">
                            Plataforma nacional para la gesti&oacute;n de activos tecnol&oacute;gicos:
                            inventarios, pr&eacute;stamo de equipos, gesti&oacute;n de soporte, reportes, auditorias.
                            <strong>Versi&oacute;n: 1.1</strong>
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card about-card p-4">
                        <div class="about-icon"><i class="bi bi-building"></i></div>
                        <h5 class="card-title">Ministerio de Educaci&oacute;n P&uacute;blica</h5>
                        <p class="card-text mb-0">
                            Sistema desarrollado para la Direcci&oacute;n de <strong>Recursos Tecnol&oacute;gicos en Educaci&oacute;n (DRTE)</strong>.
                            del Ministerio de Educaci&oacute;n P&uacute;blica (MEP) de Costa Rica, con el objetivo de mejorar la gesti&oacute;n de los activos tecnol&oacute;gicos en los
                            centros educativos del pa&iacute;s.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card about-card p-4">
                        <div class="about-icon"><i class="bi bi-people-fill"></i></div>
                        <h5 class="card-title">Equipo de Desarrollo</h5>
                        <!-- <p class="card-text mb-0">
                            Profesionales que participaron en el desarrollo y evoluci&oacute;n del sistema TecnoPresta.
                        </p> -->
                        <div class="team-block mt-3">
                            <span class="team-rol">Desarrollo de la versi&oacute;n actual</span>
                            <ul class="team-list">
                                <li><i class="bi bi-person-check"></i>Ing. Sandro Yee Vasquez</li>
                                <li><i class="bi bi-person-check"></i>Ing. Mauricio Bermudez Vargas</li>
                                <li><i class="bi bi-person-check"></i>Ing. Erick Cerdas Gonz&aacute;lez</li>
                            </ul>
                            <div class="team-divider">
                                <span>Colaboraci&oacute;n en versi&oacute;n anterior</span>
                            </div>
                            <ul class="team-list">
                                <li><i class="bi bi-person-plus"></i>MSc. Franklin Jim&eacute;nez Montero</li>
                                <li><i class="bi bi-person-plus"></i>Ing. Grettel Romero Morales</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- ══════ BOTONES FLOTANTES ══════ -->
    <a class="btn-to-subsistemas" href="navegar.php?ruta=formulario_menu_principal.php" aria-label="Volver a Menú Principal">
        <i class="bi bi-grid-3x3-gap-fill"></i>
    </a>
    <button class="btn-to-top" id="btnToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Volver arriba">
        <i class="bi bi-chevron-up"></i>
    </button>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        var btn = document.getElementById("btnToTop");
        window.addEventListener("scroll", function () {
            btn.classList.toggle("show", window.scrollY > 400);
        });
    });
    </script>

    <?php include 'partials/footer.php'; ?>
</body>
</html>