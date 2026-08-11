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
$ruta_regreso ='navegar.php?ruta=formulario_menu_principal.php';
$subsistema_id = isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : 0;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;
if ($subsistema_id > 0 && $modulo_id > 0) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . $subsistema_id
    . '&modulo_id=' . $modulo_id;
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}

// === Roles gestionables (solo crear/editar/eliminar estos) ===
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

// Procesar búsqueda
$filtro_cedula = isset($_GET['cedula']) ? mysqli_real_escape_string($link, trim($_GET['cedula'])) : '';
$filtro_nombre = isset($_GET['nombre']) ? mysqli_real_escape_string($link, trim($_GET['nombre'])) : '';
$filtro_codigo = isset($_GET['codigo']) ? mysqli_real_escape_string($link, trim($_GET['codigo'])) : '';
$filtro_rol = isset($_GET['rol']) ? intval($_GET['rol']) : 0;

$query = "SELECT ur.id AS ur_id, u.id AS usuario_id, u.cedula, u.nombre AS nombre_usuario, u.correo, ur.rol_id, ur.codigo_presu, ur.subsistema_id
          FROM usuarios_roles ur
          INNER JOIN usuarios u ON u.id = ur.usuario_id
          WHERE ur.eliminado = 0";
if ($filtro_cedula !== '') {
    $query .= " AND u.cedula LIKE '%$filtro_cedula%'";
}
if ($filtro_nombre !== '') {
    $query .= " AND u.correo LIKE '%$filtro_nombre%'";
}
if ($filtro_codigo !== '') {
    $query .= " AND ur.codigo_presu LIKE '%$filtro_codigo%'";
}
if ($filtro_rol > 0) {
    $query .= " AND ur.rol_id = $filtro_rol";
}
$query .= " ORDER BY u.cedula ASC";

$result = mysqli_query($link, $query);
$total_resultados = $result ? mysqli_num_rows($result) : 0;

$param_retorno = '';
if ($subsistema_id > 0 && $modulo_id > 0) {
    $param_retorno = '&subsistema_id=' . $subsistema_id . '&modulo_id=' . $modulo_id;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Permisos</title>
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
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="hero-icon">
                            <i class="bi bi-shield-lock" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1">Administración de Usuarios</h2>
                            <p class="mb-0 opacity-75">Gestión de usuarios de Centros Educativos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a class="btn btn-mep-primary" href="navegar.php?ruta=formulario_crear_usuario_sistema_n.php<?= $param_retorno ?>">
                        <i class="bi bi-person-plus-fill me-1"></i> Crear usuario
                    </a>
                </div>
            </div>
        </div>

        <div class="prestamo-form-card">
            <div class="prestamo-form-header">
                <i class="bi bi-search me-2"></i> Criterios de Búsqueda
            </div>
            <div class="prestamo-form-body">
                <div class="search-panel">
                    <div class="filtro-titulo">
                        <i class="bi bi-funnel-fill"></i> Buscar funcionarios
                    </div>
                    <form method="GET" action="navegar.php">
                        <input type="hidden" name="ruta" value="formulario_administracion_permisos_n.php">
                        <input type="hidden" name="subsistema_id" value="<?= $subsistema_id ?>">
                        <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="cedula" class="form-label">Cédula</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" class="form-control" id="cedula" name="cedula"
                                           placeholder="Cédula" value="<?= htmlspecialchars($filtro_cedula) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="nombre" class="form-label">Correo MEP</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="text" class="form-control" id="nombre" name="nombre"
                                           placeholder="Correo MEP del funcionario" value="<?= htmlspecialchars($filtro_nombre) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="codigo" class="form-label">Código del Centro</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control" id="codigo" name="codigo"
                                           placeholder="Código del Centro" value="<?= htmlspecialchars($filtro_codigo) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="rol" class="form-label">Rol</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-gear"></i></span>
                                    <select name="rol" id="rol" class="form-select">
                                        <option value="">Todos los roles</option>
                                        <?php foreach ($roles_gestionables as $rid): ?>
                                        <option value="<?= $rid ?>" <?= ($filtro_rol === $rid) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($mapa_roles[$rid] ?? "Rol $rid") ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-mep-primary">
                                    <i class="bi bi-search me-1"></i> Buscar
                                </button>
                                <a href="navegar.php?ruta=formulario_administracion_permisos_n.php&subsistema_id=<?= $subsistema_id ?>&modulo_id=<?= $modulo_id ?>"
                                   class="btn btn-outline-secondary ms-2">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Contador de registros -->
                <div class="contador-container">
                    <div class="contador-registros">
                        <i class="bi bi-database me-2"></i>
                        Registros encontrados:
                        <span><?= $total_resultados ?></span>
                        <?= ($total_resultados == 1) ? 'registro' : 'registros' ?>
                    </div>
                </div>

                <!-- Tabla de resultados -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover activos-table align-middle mb-0">
                        <thead class="header-fixed">
                            <tr>
                                <th>Cédula</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Código del Centro</th>
                                <th>Rol</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    $id_registro = intval($row['ur_id']);
                                    $id_rol_registro = intval($row['rol_id']);
                                    $es_gestionable = in_array($id_rol_registro, $roles_gestionables);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['cedula']) ?></td>
                                    <td><?= htmlspecialchars($row['nombre_usuario']) ?></td>
                                    <td><?= htmlspecialchars($row['correo']) ?></td>
                                    <td><?= htmlspecialchars($row['codigo_presu']) ?></td>
                                    <td>
                                        <?php if ($es_gestionable): ?>
                                        <span class="badge rounded-pill" style="background: var(--mep-primary);">
                                            <?= htmlspecialchars($mapa_roles[$id_rol_registro] ?? 'Rol') ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge rounded-pill estado-badge-inactivo">
                                            <?= htmlspecialchars($mapa_roles[$id_rol_registro] ?? 'Rol') ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($es_gestionable): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary border-0"
                                                title="Editar rol"
                                                onclick="abrirEditarRol(<?= $id_registro ?>, <?= $id_rol_registro ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0"
                                                title="Eliminar"
                                                onclick="confirmarEliminar(<?= $id_registro ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox me-1"></i> No se encontraron funcionarios con los criterios de búsqueda.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Confirmar Eliminación -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="prestamo-modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i> Confirmar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="prestamo-modal-body">
                    <p id="confirmacionMensaje"></p>
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarSi">Sí, eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Rol -->
    <div class="modal fade" id="modalEditarRol" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="guardar_rol_del_usuario2_n.php" id="formEditarRol">
                    <input type="hidden" name="edit_id" id="edit_id" value="">
                    <input type="hidden" name="subsistema_id" value="<?= $subsistema_id ?>">
                    <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
                    <div class="prestamo-modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Editar rol</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="prestamo-modal-body">
                        <div class="text-start">
                            <div class="alert" id="infoUsuarioEditar"></div>
                            <label for="rol_editar" class="form-label fw-semibold">Nuevo rol</label>
                            <select name="rol" id="rol_editar" class="form-select">
                                <?php foreach ($roles_gestionables as $rid): ?>
                                <option value="<?= $rid ?>"><?= htmlspecialchars($mapa_roles[$rid] ?? "Rol $rid") ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="prestamo-modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
        var urlEliminarBase = 'eliminar_rol2_n.php?id=';
        var paramRetorno = '<?= $param_retorno ?>';
        var mapRoles = {
            <?php foreach ($roles_gestionables as $rid): ?>
            <?= $rid ?>: '<?= addslashes($mapa_roles[$rid] ?? '') ?>',
            <?php endforeach; ?>
        };

        // ==== Confirmación mediante modal ====
        var confirmCallback = null;

        function mostrarConfirmacion(mensaje, callback) {
            document.getElementById('confirmacionMensaje').textContent = mensaje;
            confirmCallback = callback;
            var modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
            modal.show();
        }

        document.getElementById('btnConfirmarSi').addEventListener('click', function() {
            if (confirmCallback) {
                confirmCallback();
                confirmCallback = null;
            }
            var modalEl = document.getElementById('modalConfirmacion');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });

        function confirmarEliminar(id) {
            mostrarConfirmacion('¿Está seguro de que desea eliminar este usuario y sus permisos? Esta acción no se puede deshacer.', function() {
                window.location.href = urlEliminarBase + id + (paramRetorno ? '?' + paramRetorno.substring(1) : '');
            });
        }

        // ==== Modal de mensaje ====
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

        // ==== Modal de edición de rol ====
        function abrirEditarRol(id, rolActual) {
            document.getElementById('edit_id').value = id;
            var selectRol = document.getElementById('rol_editar');
            selectRol.value = rolActual;
            document.getElementById('infoUsuarioEditar').innerHTML =
                '<strong>Cédula:</strong> ' + '<span id="infoCedula"></span><br>' +
                '<strong>Rol actual:</strong> ' + (mapRoles[rolActual] || rolActual);
            var fila = event.currentTarget.closest('tr');
            var celdas = fila.querySelectorAll('td');
            document.getElementById('infoCedula').textContent = celdas[0].textContent.trim();
            var modal = new bootstrap.Modal(document.getElementById('modalEditarRol'));
            modal.show();
        }

        document.getElementById('formEditarRol').addEventListener('submit', function(e) {
            if (!document.getElementById('rol_editar').value) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
