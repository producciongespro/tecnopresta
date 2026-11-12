<?php
/**
 * Panel de Administración - Cine-Mini
 * Gestión y activación/desactivación de anuncios y diapositivas
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
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

require_once("conexion.php");
$link = $mysqli;
if (function_exists('mysqli_set_charset')) {
    mysqli_set_charset($link, "utf8");
}
date_default_timezone_set('America/Costa_Rica');

$mensaje = "";
$error = "";

/* ============================================================
 * NUEVO: Reconstrucción de la URL segura del enrutador
 * ============================================================ */
$subsistema_id = isset($_GET['subsistema_id']) ? (int)$_GET['subsistema_id'] : 4;
$modulo_id     = isset($_GET['modulo_id'])     ? (int)$_GET['modulo_id']     : 19;
$formulario_id = isset($_GET['formulario_id']) ? (int)$_GET['formulario_id'] : 88;

// URL base del enrutador (para redirecciones y action del form)
$rutaBase = "navegar.php?ruta=admin_cinemini.php"
          . "&subsistema_id={$subsistema_id}"
          . "&modulo_id={$modulo_id}"
          . "&formulario_id={$formulario_id}";

// Helper para construir URLs con parámetros extra
function urlRuta($rutaBase, $params = []) {
    if (empty($params)) return $rutaBase;
    return $rutaBase . '&' . http_build_query($params);
}

// Crear directorio si no existe
$uploadDir = __DIR__ . '/uploads/cinemini/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Acción: Toggle activar/desactivar anuncio
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $nuevoEstado = (int)$_GET['estado'];
    
    if ($nuevoEstado === 1) {
        mysqli_query($link, "UPDATE cine_anuncios SET activo = 0");
    }
    
    $update = mysqli_query($link, "UPDATE cine_anuncios SET activo = $nuevoEstado WHERE id = $id");
    if ($update) {
        // CAMBIO: redirigir por el enrutador seguro
        header("Location: " . urlRuta($rutaBase, ['msg' => 'estado_actualizado']));
        exit;
    } else {
        $error = "Error al actualizar estado: " . mysqli_error($link);
    }
}

// Acción: Guardar nuevo anuncio con 5 diapositivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_anuncio'])) {
    $titulo = mysqli_real_escape_string($link, trim($_POST['titulo']));
    $descripcion = mysqli_real_escape_string($link, trim($_POST['descripcion']));
    $activo = isset($_POST['activo']) ? 1 : 0;
    $duracion = !empty($_POST['duracion']) ? (int)$_POST['duracion'] : 5000;

    if (empty($titulo)) {
        $error = "El título del anuncio es obligatorio.";
    } else {
        if ($activo === 1) {
            mysqli_query($link, "UPDATE cine_anuncios SET activo = 0");
        }

        $sqlInsAnuncio = "INSERT INTO cine_anuncios (titulo, descripcion, activo, duracion_slide_ms) VALUES ('$titulo', '$descripcion', $activo, $duracion)";
        if (mysqli_query($link, $sqlInsAnuncio)) {
            $nuevoId = mysqli_insert_id($link);

            for ($i = 1; $i <= 5; $i++) {
                $slideTitulo = mysqli_real_escape_string($link, trim($_POST["slide_titulo_$i"] ?? ''));
                $slideTexto = mysqli_real_escape_string($link, trim($_POST["slide_texto_$i"] ?? ''));
                $imagenUrl = '';

                if (isset($_FILES["slide_img_$i"]) && $_FILES["slide_img_$i"]['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES["slide_img_$i"]['tmp_name'];
                    $fileName = $_FILES["slide_img_$i"]['name'];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $fileTmp);
                    finfo_close($finfo);

                    if ($ext === 'webp' || $mime === 'image/webp') {
                        $nuevoNombre = "slide_" . $nuevoId . "_" . $i . "_" . time() . ".webp";
                        $destino = $uploadDir . $nuevoNombre;
                        if (move_uploaded_file($fileTmp, $destino)) {
                            $imagenUrl = "uploads/cinemini/" . $nuevoNombre;
                        }
                    } else {
                        if (function_exists('imagewebp')) {
                            $img = null;
                            if ($ext === 'jpg' || $ext === 'jpeg' || $mime === 'image/jpeg') {
                                $img = @imagecreatefromjpeg($fileTmp);
                            } elseif ($ext === 'png' || $mime === 'image/png') {
                                $img = @imagecreatefrompng($fileTmp);
                            }
                            if ($img) {
                                $nuevoNombre = "slide_" . $nuevoId . "_" . $i . "_" . time() . ".webp";
                                $destino = $uploadDir . $nuevoNombre;
                                imagewebp($img, $destino, 80);
                                imagedestroy($img);
                                $imagenUrl = "uploads/cinemini/" . $nuevoNombre;
                            }
                        }
                    }
                }

                if (empty($imagenUrl) && !empty($_POST["slide_url_$i"])) {
                    $imagenUrl = mysqli_real_escape_string($link, trim($_POST["slide_url_$i"]));
                }

                if (!empty($imagenUrl)) {
                    $sqlSlide = "INSERT INTO cine_diapositivas (anuncio_id, orden, imagen_url, titulo_slide, texto_slide) 
                                 VALUES ($nuevoId, $i, '$imagenUrl', '$slideTitulo', '$slideTexto')";
                    mysqli_query($link, $sqlSlide);
                }
            }

            // CAMBIO: redirigir por el enrutador seguro
            header("Location: " . urlRuta($rutaBase, ['msg' => 'anuncio_creado']));
            exit;
        } else {
            $error = "Error al registrar el anuncio: " . mysqli_error($link);
        }
    }
}

// Consultar anuncios existentes
$queryAnuncios = mysqli_query($link, "SELECT a.*, COUNT(d.id) AS total_slides FROM cine_anuncios a LEFT JOIN cine_diapositivas d ON a.id = d.anuncio_id GROUP BY a.id ORDER BY a.id DESC");
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">

    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=20260924_2">
    <link href="select2/select2.min.css" rel="stylesheet" />
    <link href="sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <title>Dashboard Cine-Mini // TecnoPresta</title>

    <style>
        :root {
            --mep-azul: #1e2851;
            --mep-dorado: #c8ae64;
            --mep-bg-light: #f4f6f9;
        }

        body {
            background-color: var(--mep-bg-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .main-container { flex: 1 0 auto; }

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
        .btn-flotante-regresar:active { transform: scale(0.95); }
        .btn-flotante-regresar i { font-size: 30px; color: #fff; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(30, 40, 81, 0.08);
            background: #fff;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header-custom {
            background-color: var(--mep-azul);
            color: #fff;
            padding: 14px 20px;
            font-weight: 600;
        }

        .badge-active {
            background-color: #198754; color: white; padding: 5px 12px; border-radius: 20px;
            font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;
        }
        .badge-inactive {
            background-color: #6c757d; color: white; padding: 5px 12px; border-radius: 20px;
            font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;
        }
        .slide-block {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 14px; margin-bottom: 12px;
        }
    </style>
</head>
<body class="layout-page">
    <?php if (file_exists('partials/header.php')) { include 'partials/header.php'; } ?>

    <main class="main-container py-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold mb-1" style="color: var(--mep-azul);">
                        <i class="bi bi-film"></i> Panel Administrativo Cine-Mini
                    </h3>
                    <p class="text-muted small mb-0">Gestión de novedades, anuncios y secuencias Ken Burns del Lobby Principal</p>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div><strong>¡Éxito!</strong> Operación completada correctamente.</div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-7">
                    <div class="card card-custom">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-collection-play me-2"></i> Anuncios Registrados</span>
                            <span class="badge bg-light text-dark"><?= ($queryAnuncios ? mysqli_num_rows($queryAnuncios) : 0) ?> Total</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Título</th>
                                            <th>Slides</th>
                                            <th>Estado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($queryAnuncios && mysqli_num_rows($queryAnuncios) > 0): ?>
                                            <?php while ($anuncio = mysqli_fetch_assoc($queryAnuncios)): ?>
                                                <tr>
                                                    <td class="fw-semibold">#<?= $anuncio['id'] ?></td>
                                                    <td>
                                                        <strong class="text-dark"><?= htmlspecialchars($anuncio['titulo']) ?></strong>
                                                        <?php if (!empty($anuncio['descripcion'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($anuncio['descripcion']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-dark">
                                                            <i class="bi bi-images me-1"></i><?= $anuncio['total_slides'] ?> slides
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($anuncio['activo'] == 1): ?>
                                                            <span class="badge-active"><i class="bi bi-check-circle-fill"></i> ACTIVO</span>
                                                        <?php else: ?>
                                                            <span class="badge-inactive"><i class="bi bi-pause-circle"></i> INACTIVO</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($anuncio['activo'] == 1): ?>
                                                            <!-- CAMBIO: usar urlRuta para los toggles -->
                                                            <a href="<?= htmlspecialchars(urlRuta($rutaBase, ['action' => 'toggle', 'id' => $anuncio['id'], 'estado' => 0])) ?>"
                                                               class="btn btn-outline-warning btn-sm" title="Desactivar este anuncio">
                                                                <i class="bi bi-pause-fill"></i> Desactivar
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?= htmlspecialchars(urlRuta($rutaBase, ['action' => 'toggle', 'id' => $anuncio['id'], 'estado' => 1])) ?>"
                                                               class="btn btn-success btn-sm text-white" title="Activar en Lobby">
                                                                <i class="bi bi-play-fill"></i> Activar
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bi bi-info-circle me-1"></i> No hay anuncios registrados actualmente.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card card-custom">
                        <div class="card-header-custom">
                            <i class="bi bi-plus-circle me-1"></i> Publicar Nuevo Anuncio Cine-Mini
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <!-- CAMBIO CLAVE: action apunta al enrutador seguro, no al archivo directo -->
                            <form method="POST" action="<?= htmlspecialchars($rutaBase) ?>" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">Título Principal del Anuncio</label>
                                    <input type="text" name="titulo" class="form-control" placeholder="Ej: ¡Actualización Mayor TecnoPresta!" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">Descripción Breve</label>
                                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Resumen informativo..."></textarea>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-7">
                                        <label class="form-label fw-bold small text-muted">Duración por Diapositiva</label>
                                        <select name="duracion" class="form-select">
                                            <option value="4000">4 segundos</option>
                                            <option value="5000" selected>5 segundos (Recomendado)</option>
                                            <option value="6500">6.5 segundos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 d-flex align-items-center pt-3">
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" class="form-check-input" id="activoSwitch" name="activo" value="1" checked>
                                            <label class="form-check-label fw-semibold text-success small" for="activoSwitch">Activar ya</label>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h6 class="fw-bold mb-3" style="color: var(--mep-azul);">
                                    <i class="bi bi-images me-1"></i> Secuencia de 5 Diapositivas (WebP 40-80 KB)
                                </h6>

                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="slide-block">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong class="text-dark small"><i class="bi bi-image me-1"></i> Diapositiva #<?= $i ?></strong>
                                        </div>
                                        <div class="mb-2">
                                            <input type="file" name="slide_img_<?= $i ?>" class="form-control form-control-sm" accept=".webp,image/webp,image/jpeg,image/png">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">WebP optimizado (40 - 80 KB). O indica la ruta abajo:</small>
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" name="slide_url_<?= $i ?>" class="form-control form-control-sm" placeholder="Opcional: uploads/cinemini/slide<?= $i ?>.webp">
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <input type="text" name="slide_titulo_<?= $i ?>" class="form-control form-control-sm" placeholder="Título diapositiva">
                                            </div>
                                            <div class="col-6">
                                                <input type="text" name="slide_texto_<?= $i ?>" class="form-control form-control-sm" placeholder="Texto descriptivo">
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>

                                <button type="submit" name="guardar_anuncio" class="btn btn-primary w-100 py-2 mt-2 fw-semibold" style="background-color: var(--mep-azul); border-color: var(--mep-azul);">
                                    <i class="bi bi-save me-1"></i> Guardar y Publicar Secuencia
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón flotante: conserva el enrutador y añade parámetros -->
        <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=<?= (int)$subsistema_id ?>&modulo_id=<?= (int)$modulo_id ?>"
           class="btn-flotante-regresar" title="Volver al Menú Principal">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </main>

    <?php if (file_exists('partials/footer.php')) { include 'partials/footer.php'; } ?>

    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
</body>
</html>