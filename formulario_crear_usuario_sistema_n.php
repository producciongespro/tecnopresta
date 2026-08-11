<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}
if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// ==== CONSTRUIR RUTA DE REGRESO =====
$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';
$subsistema_id = isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : 0;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;

if ($subsistema_id > 0 && $modulo_id > 0) {
    $_SESSION['subsistema_id'] = $subsistema_id;
    $_SESSION['modulo_id'] = $modulo_id;
    $ruta_regreso = 'navegar.php?ruta=formulario_administracion_permisos_n.php'
    . '&subsistema_id=' . $subsistema_id
    . '&modulo_id=' . $modulo_id;
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}

// === Roles gestionables (solo crear con estos roles) ===
$roles_gestionables = [2, 3, 4, 5];
$mapa_roles = [];
$query_roles = mysqli_query($link, "SELECT id_rol, rol FROM t_roles ORDER BY id_rol");
if ($query_roles) {
    while ($fila_rol = mysqli_fetch_assoc($query_roles)) {
        $mapa_roles[intval($fila_rol['id_rol'])] = $fila_rol['rol'];
    }
}

// === Mensaje flash ===
$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=9" />
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <main class="container py-4 contenido-principal">
        <!-- Encabezado hero -->
        <div class="hero-box mb-4 fade-enter">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon">
                    <i class="bi bi-person-plus-fill" style="font-size: 2rem;"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1">Crear Usuario</h2>
                    <p class="mb-0 opacity-75">Asignar un rol a un funcionario del sistema</p>
                </div>
            </div>
        </div>

        <div class="prestamo-form-card">
            <div class="prestamo-form-header">
                <i class="bi bi-person-gear me-2"></i> Datos del usuario y rol
            </div>
            <div class="prestamo-form-body">
                <form action="guardar_rol_del_usuario2_n.php" method="POST" id="formCrearUsuario" novalidate>
                    <input type="hidden" name="subsistema_id" value="<?= $subsistema_id ?>">
                    <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="text" class="form-control" id="email" name="email"
                                       placeholder="nombre.apellido1.apellido2" required autocomplete="off">
                                <span class="input-group-text bg-light text-muted fw-medium">@mep.go.cr</span>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Digite solo la parte antes de @mep.go.cr</small>
                        </div>
                        <div class="col-md-6">
                            <label for="cedula" class="form-label">Cédula</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control" id="cedula" name="cedula"
                                       placeholder="Ej: 0109670579" required>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>El nombre del funcionario se obtiene automáticamente de Azure (MEP).</small>
                        </div>
                        <div class="col-md-6">
                            <label for="codigo_presupuestario" class="form-label">Código Presupuestario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control" id="codigo_presupuestario"
                                       name="codigo_presupuestario" placeholder="Código del centro (3 o 4 dígitos)"
                                       inputmode="numeric" maxlength="4" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="rol" class="form-label">Rol</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-gear"></i></span>
                                <select name="rol" id="rol" class="form-select" required>
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach ($roles_gestionables as $rid): ?>
                                    <option value="<?= $rid ?>"><?= htmlspecialchars($mapa_roles[$rid] ?? "Rol $rid") ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-guardar-mep">
                                <i class="bi bi-floppy me-1"></i> Guardar
                            </button>
                            <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i> Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Modal Mensaje -->
    <div class="modal fade" id="modalMensaje" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="prestamo-modal-header">
                    <h5 class="modal-title" id="mensajeTitulo">Mensaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="prestamo-modal-body">
                    <div class="alert">
                        <i id="mensajeIcono" class="bi bi-info-circle" style="font-size: 2rem; color: var(--mep-blue);"></i>
                        <p id="mensajeTexto" class="mt-2 mb-0"></p>
                    </div>
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad"
        style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <?php include 'partials/footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarMensaje(tipo, titulo, mensaje) {
            document.getElementById('mensajeTitulo').textContent = titulo;
            document.getElementById('mensajeTexto').textContent = mensaje;
            var icono = document.getElementById('mensajeIcono');
            if (tipo === 'success') {
                icono.className = 'bi bi-check-circle-fill';
                icono.style.color = '#28a745';
            } else if (tipo === 'error') {
                icono.className = 'bi bi-x-circle-fill';
                icono.style.color = '#dc3545';
            } else if (tipo === 'warning') {
                icono.className = 'bi bi-exclamation-triangle-fill';
                icono.style.color = '#ffc107';
            } else {
                icono.className = 'bi bi-info-circle-fill';
                icono.style.color = 'var(--mep-blue)';
            }
            var modal = new bootstrap.Modal(document.getElementById('modalMensaje'));
            modal.show();
        }

        <?php if ($flash): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var tipo = '<?= $flash['type'] ?>';
            var titulo = (tipo === 'success') ? 'Éxito' : (tipo === 'error' ? 'Error' : 'Atención');
            mostrarMensaje(tipo, titulo, '<?= addslashes($flash['message']) ?>');
        });
        <?php endif; ?>

        // ==== Validación del formulario ====
        var inputEmail = document.getElementById('email');
        var inputCodigo = document.getElementById('codigo_presupuestario');

        inputCodigo.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });

        inputEmail.addEventListener('input', function() {
            this.value = this.value.replace(/@.*$/, '').replace(/\s+/g, '');
        });

        document.getElementById('formCrearUsuario').addEventListener('submit', function(e) {
            var mensajeError = '';

            var usuario = inputEmail.value.trim();
            if (!usuario) {
                mensajeError = 'Debe ingresar el nombre de usuario del correo electrónico.';
            } else if (usuario.length < 3) {
                mensajeError = 'El nombre de usuario del correo es demasiado corto.';
            }

            var codigo = inputCodigo.value.trim();
            if (!mensajeError && !/^\d{3,4}$/.test(codigo)) {
                mensajeError = 'El código presupuestario debe contener únicamente números de 3 o 4 dígitos.';
            }

            if (mensajeError) {
                e.preventDefault();
                mostrarMensaje('warning', 'Validación', mensajeError);
                return false;
            }

            inputEmail.value = usuario + '@mep.go.cr';
            return true;
        });
    </script>
</body>
</html>
