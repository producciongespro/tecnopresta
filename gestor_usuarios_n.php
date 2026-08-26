<?php
// ============================================================
// FORMULARIO: Administracion de Usuarios del Sistema
// ============================================================
// Proposito: Interfaz administrativa para visualizar, asignar,
// editar y eliminar roles de usuarios en el sistema.
//
// Acceso: Solo Root (rol_id = 1)
//
// Dependencias:
//   - navegar.php (define ACCESO_SEGURO)
//   - auth.php (validacion de sesion y roles)
//   - usuarioAzure.php (datos de sesion Azure AD)
//   - sql/gestor_usuario_n.php (endpoint datos)
//   - actualizar_gestor_usuarios_n.php (endpoint acciones)
// ============================================================

if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    die('Acceso directo no permitido');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/usuarioAzure.php';
require_once __DIR__ . '/auth.php';

$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header('Location: index.html');
    exit;
}
/*
if (!esUsuarioRoot()) {
    header('Location: navegar.php?ruta=formulario_menu_principal.php');
    exit;
}
*/
// Construir ruta de regreso
$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';
$sid = isset($_GET['subsistema_id']) ? (int)$_GET['subsistema_id'] : 0;
$mid = isset($_GET['modulo_id']) ? (int)$_GET['modulo_id'] : 0;
if ($sid) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=' . $sid;
    if ($mid) {
        $ruta_regreso .= '&modulo_id=' . $mid;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor del Sistema - Administracion de Usuarios</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Estilos institucionales MEP -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=8">
    <!-- Estilos del menu principal y tablas MEP -->
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=8">

    <style>
    /* ============================================================
       TABLA DE USUARIOS
       ============================================================ */

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #003876, #114c91);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .rol-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 2px 3px;
        background: rgba(0,56,118,0.08);
        color: #003876;
        border: 1px solid rgba(0,56,118,0.15);
    }

    .rol-badge.root-badge {
        background: rgba(220,53,69,0.1);
        color: #dc3545;
        border-color: rgba(220,53,69,0.2);
    }

    .accion-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.85rem;
    }
    .accion-btn.editar {
        background: rgba(0,56,118,0.08);
        color: #003876;
    }
    .accion-btn.editar:hover {
        background: #003876;
        color: #fff;
    }
    .accion-btn.eliminar {
        background: rgba(220,53,69,0.08);
        color: #dc3545;
    }
    .accion-btn.eliminar:hover {
        background: #dc3545;
        color: #fff;
    }

    .usuario-info-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .usuario-info-text .usuario-nombre {
        font-weight: 600;
        font-size: 0.9rem;
        color: #212529;
    }
    .usuario-info-text .usuario-email {
        font-size: 0.78rem;
        color: #6c757d;
    }

    .acceso-badge {
        font-size: 0.75rem;
        color: #6c757d;
    }
    .acceso-badge.sin-acceso {
        color: #adb5bd;
        font-style: italic;
    }

    /* --- Modal Asignar/Editar Rol --- */
    .modal-rol-body .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
    }

    .rol-card-option {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 12px 16px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .rol-card-option:hover {
        border-color: #003876;
        background: rgba(0,56,118,0.02);
    }
    .rol-card-option.seleccionado {
        border-color: #003876;
        background: rgba(0,56,118,0.05);
    }
    .rol-card-option .rol-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #003876, #114c91);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .rol-card-option .rol-info .rol-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #212529;
    }
    .rol-card-option .rol-info .rol-desc {
        font-size: 0.78rem;
        color: #6c757d;
    }

    .sin-resultados {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
    }

    .stats-bar {
        display: flex;
        gap: 24px;
        align-items: center;
    }
    .stats-bar .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: #495057;
    }
    .stats-bar .stat-item .stat-num {
        font-weight: 700;
        color: #003876;
    }

    /* ============================================================
       ROOT ROLE - Estilos diferenciados
       ============================================================ */
    .rol-card-option.root-card {
        border-color: #dc3545;
        background: rgba(220,53,69,0.04);
    }
    .rol-card-option.root-card:hover {
        border-color: #dc3545;
        background: rgba(220,53,69,0.08);
    }
    .rol-card-option.root-card.seleccionado {
        border-color: #dc3545;
        background: rgba(220,53,69,0.12);
    }
    .rol-card-option.root-card .rol-icon {
        background: linear-gradient(135deg, #dc3545, #a71d2a);
    }
    .rol-card-option.root-card .rol-name {
        color: #dc3545;
    }

    .root-warning-banner {
        background: linear-gradient(135deg, rgba(220,53,69,0.08), rgba(220,53,69,0.04));
        border: 1px solid rgba(220,53,69,0.25);
        border-left: 4px solid #dc3545;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        display: none;
    }
    .root-warning-banner.visible {
        display: block;
    }
    .root-warning-banner .root-warning-title {
        font-weight: 700;
        font-size: 0.9rem;
        color: #dc3545;
        margin-bottom: 4px;
    }
    .root-warning-banner .root-warning-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    .root-warning-banner .root-warning-count {
        font-size: 0.8rem;
        color: #495057;
    }
    .root-warning-banner .root-warning-count strong {
        color: #dc3545;
    }

    .root-confirm-group {
        display: none;
        margin-top: 12px;
    }
    .root-confirm-group.visible {
        display: block;
    }
    .root-confirm-group .form-label {
        color: #dc3545;
        font-weight: 700;
    }
    .root-confirm-input {
        border-color: #dc3545;
    }
    .root-confirm-input:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220,53,69,0.25);
    }
    </style>
</head>
<body class="layout-page">

    <?php include 'partials/header.php'; ?>

    <!-- ============================================================
    CONTENIDO PRINCIPAL
    ============================================================ -->
    <main class="container py-4 contenido-principal" id="appUsuarios">

        <!-- Hero-box -->
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-people-fill" style="font-size: 2.5rem; color: #C8A951;"></i>
                </div>
                <div class="col">
                    <h1 class="mb-0" style="color: #fff; font-weight: 600;">Administracion de Usuarios</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.8);">
                        Asigne y gestione roles de acceso al sistema para los usuarios registrados
                    </p>
                </div>
            </div>
        </div>

        <!-- Barra de busqueda y acciones -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarUsuario"
                                   placeholder="Buscar por nombre, cedula o correo..."
                                   oninput="filtrarUsuarios()">
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-mep-primary" onclick="abrirModalAsignar()">
                            <i class="bi bi-plus-lg me-1"></i> Asignar Rol
                        </button>
                    </div>
                </div>
                <div class="stats-bar mt-3" id="statsBar">
                    <div class="stat-item">
                        <i class="bi bi-people"></i>
                        <span><span class="stat-num" id="statTotal">0</span> usuarios</span>
                    </div>
                    <div class="stat-item">
                        <i class="bi bi-shield-fill"></i>
                        <span><span class="stat-num" id="statRoles">0</span> roles asignados</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de usuarios -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table activos-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width:30%;">Usuario</th>
                                <th style="width:12%;">Cedula</th>
                                <th style="width:30%;">Roles Asignados</th>
                                <th style="width:15%;">Ultimo Acceso</th>
                                <th style="width:13%; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaUsuarios">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Cargando usuarios...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Boton volver -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>"
           class="btn-disponibilidad"
           style="bottom: 30px;"
           data-tooltip="Regresar">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>

        <!-- ============================================================
        MODALES
        ============================================================ -->

        <!-- Modal Asignar/Editar Rol -->
        <div class="modal fade gestor-overlay" id="modalAsignarRol" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered gestor-modal" style="max-width: 520px;">
                <div class="modal-content">
                    <div class="modal-header gestor-modal-header">
                        <h5 class="modal-title" id="modalAsignarTitulo">
                            <i class="bi bi-shield-plus me-2"></i>Asignar Rol
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body modal-rol-body">
                        <!-- Banner de advertencia Root -->
                        <div class="root-warning-banner" id="rootWarningBanner">
                            <div class="root-warning-title">
                                <i class="bi bi-exclamation-diamond-fill me-1"></i> Advertencia: Rol Root
                            </div>
                            <div class="root-warning-text">
                                El rol <strong>Root</strong> otorga control total del sistema. El usuario podra gestionar
                                usuarios, roles, permisos, configuraciones criticas y eliminar registros.
                            </div>
                            <div class="root-warning-count">
                                Actualmente existen <strong id="rootCountActual">0</strong> usuario(s) con rol Root.
                            </div>
                        </div>
                        <!-- Busqueda de usuario (solo para asignar) -->
                        <div id="seccionBuscarUsuario">
                            <label class="form-label">Buscar usuario por cedula</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="inputCedula"
                                       placeholder="Ingrese la cedula..." maxlength="30">
                                <button class="btn btn-mep-primary" type="button" onclick="buscarUsuarioPorCedula()">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div id="resultadoBusqueda" class="mb-3" style="display:none;">
                                <div class="p-3 bg-light rounded">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar" id="avatarUsuarioBuscado">--</div>
                                        <div>
                                            <div class="fw-bold" id="nombreUsuarioBuscado"></div>
                                            <small class="text-muted" id="correoUsuarioBuscado"></small>
                                        </div>
                                    </div>
                                    <input type="hidden" id="usuarioIdBuscado" value="">
                                </div>
                            </div>
                        </div>

                        <!-- Usuario actual (solo para editar) -->
                        <div id="seccionUsuarioActual" style="display:none;">
                            <label class="form-label">Usuario actual</label>
                            <div class="p-3 bg-light rounded mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar" id="avatarUsuarioActual">--</div>
                                    <div>
                                        <div class="fw-bold" id="nombreUsuarioActual"></div>
                                        <small class="text-muted" id="correoUsuarioActual"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Subsistema -->
                        <div class="mb-3" id="seccionSubsistema">
                            <label class="form-label">Subsistema</label>
                            <select class="form-select" id="selectSubsistema">
                                <option value="">Seleccione subsistema...</option>
                            </select>
                        </div>

                        <!-- Codigo presupuestario -->
                        <div class="mb-3" id="seccionCodigoPresu">
                            <label class="form-label">Codigo Presupuestario</label>
                            <input type="text" class="form-control" id="inputCodigoPresu"
                                   placeholder="Ingrese el codigo presupuestario..." value="">
                        </div>

                        <!-- Seleccion de rol -->
                        <label class="form-label">Seleccione el rol</label>
                        <div id="listaRoles" class="d-flex flex-column gap-2">
                            <!-- Se llena dinamicamente -->
                        </div>

                        <!-- Confirmacion Root -->
                        <div class="root-confirm-group" id="rootConfirmGroup">
                            <label class="form-label">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Para confirmar, escriba <strong>ROOT</strong> en el campo:
                            </label>
                            <input type="text" class="form-control root-confirm-input" id="inputRootConfirm"
                                   placeholder="Escriba ROOT para confirmar..." autocomplete="off"
                                   oninput="validarConfirmRoot()">
                            <div class="form-text text-danger" id="rootConfirmError" style="display:none;">
                                Debe escribir exactamente ROOT para confirmar
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-primary" id="btnGuardarAsignacion" onclick="guardarAsignacion()" disabled>
                            <i class="bi bi-check-lg me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detalle Roles -->
        <div class="modal fade gestor-overlay" id="modalDetalleRoles" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered gestor-modal" style="max-width: 520px;">
                <div class="modal-content">
                    <div class="modal-header gestor-modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-person-badge me-2"></i>Roles Asignados
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="user-avatar" id="avatarDetalle">--</div>
                                <div>
                                    <div class="fw-bold" id="nombreDetalle"></div>
                                    <small class="text-muted" id="correoDetalle"></small>
                                </div>
                            </div>
                        </div>
                        <div id="listaDetalleRoles">
                            <!-- Se llena dinamicamente -->
                        </div>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Confirmacion -->
        <div class="modal fade gestor-overlay" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <p class="mt-3 mb-0 fw-semibold" id="modalConfirmacionTexto">¿Esta seguro?</p>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-danger" id="btnConfirmarAccion">
                            <i class="bi bi-check-lg"></i> Si, continuar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Exito -->
        <div class="modal fade gestor-overlay" id="modalExito" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon success">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <p class="mt-3 mb-0 fw-semibold" id="modalExitoTexto">Operacion exitosa</p>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-primary" data-bs-dismiss="modal">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Error -->
        <div class="modal fade gestor-overlay" id="modalError" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon error">
                            <i class="bi bi-x-lg"></i>
                        </div>
                        <p class="mt-3 mb-0 fw-semibold" id="modalErrorTexto">Error al procesar la solicitud</p>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php include 'partials/footer.php'; ?>

    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.7.1.min.js"></script>

    <script>
    // ============================================================
    // VARIABLES GLOBALES
    // ============================================================
    let datos = {
        usuarios: [],
        roles: [],
        subsistemas: [],
        detalle_roles: [],
        root_count: 0
    };

    let rolSeleccionado = null;
    let modoModal = 'asignar'; // 'asignar' | 'editar'
    let usuarioRolEditando = null;

    function getIconoRol(rol) {
        if (rol && rol.imagen && rol.imagen.trim() !== '') {
            const img = rol.imagen.trim();
            if (img.startsWith('bi-')) return img;
        }
        return 'bi-shield';
    }

    // ============================================================
    // UTILIDADES
    // ============================================================

    function escapeHtml(texto) {
        if (!texto) return '';
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function mostrarModal(id) {
        const el = document.getElementById(id);
        if (el) {
            const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
            modal.show();
        }
    }

    function ocultarModal(id) {
        const el = document.getElementById(id);
        if (el) {
            const modal = bootstrap.Modal.getInstance(el);
            if (modal) modal.hide();
        }
    }

    function mostrarConfirmacion(mensaje, callback) {
        document.getElementById('modalConfirmacionTexto').textContent = mensaje;
        const btn = document.getElementById('btnConfirmarAccion');
        const handler = function() {
            ocultarModal('modalConfirmacion');
            btn.removeEventListener('click', handler);
            if (typeof callback === 'function') callback();
        };
        btn.addEventListener('click', handler);
        mostrarModal('modalConfirmacion');
    }

    function mostrarExito(mensaje) {
        document.getElementById('modalExitoTexto').textContent = mensaje;
        mostrarModal('modalExito');
    }

    function mostrarError(mensaje) {
        document.getElementById('modalErrorTexto').textContent = mensaje;
        mostrarModal('modalError');
    }

    function formatFecha(fechaStr) {
        if (!fechaStr) return '<span class="acceso-badge sin-acceso">Sin registro</span>';
        const d = new Date(fechaStr);
        const dia = String(d.getDate()).padStart(2, '0');
        const mes = String(d.getMonth() + 1).padStart(2, '0');
        const anio = d.getFullYear();
        const hora = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return '<span class="acceso-badge">' + dia + '/' + mes + '/' + anio + ' ' + hora + ':' + min + '</span>';
    }

    function getIniciales(nombre) {
        if (!nombre) return '?';
        const partes = nombre.trim().split(/\s+/);
        if (partes.length >= 2) {
            return (partes[0][0] + partes[1][0]).toUpperCase();
        }
        return partes[0].substring(0, 2).toUpperCase();
    }

    // ============================================================
    // CARGA DE DATOS
    // ============================================================

    async function cargarDatos() {
        try {
            const resp = await fetch('sql/gestor_usuario_n.php');
            const data = await resp.json();

            if (!data.success) throw new Error(data.message || 'Error al cargar datos');

            datos = {
                usuarios: data.usuarios || [],
                roles: data.roles || [],
                subsistemas: data.subsistemas || [],
                detalle_roles: data.detalle_roles || [],
                root_count: data.root_count || 0
            };

            renderizarTabla(datos.usuarios);
            actualizarEstadisticas();

        } catch (e) {
            mostrarError('Error al cargar datos: ' + e.message);
        }
    }

    // ============================================================
    // RENDERIZADO DE TABLA
    // ============================================================

    function renderizarTabla(usuarios) {
        const tbody = document.getElementById('tablaUsuarios');

        if (usuarios.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="5" class="sin-resultados">' +
                    '<i class="bi bi-people" style="font-size:2rem;"></i>' +
                    '<p class="mt-2">No se encontraron usuarios con roles asignados</p>' +
                '</td></tr>';
            return;
        }

        let html = '';
        usuarios.forEach(function(u) {
            const iniciales = getIniciales(u.nombre);
            const roles = u.roles ? u.roles.split(', ') : [];
            const rolesHtml = roles.map(function(r) {
                const clase = r === 'Root' ? 'rol-badge root-badge' : 'rol-badge';
                return '<span class="' + clase + '">' + escapeHtml(r) + '</span>';
            }).join('');

            html += '<tr data-usuario-id="' + u.id + '" data-busqueda="' +
                    escapeHtml((u.nombre + ' ' + u.cedula + ' ' + u.correo).toLowerCase()) + '">';
            html += '<td>';
            html += '<div class="usuario-info-row">';
            html += '<div class="user-avatar">' + escapeHtml(iniciales) + '</div>';
            html += '<div class="usuario-info-text">';
            html += '<div class="usuario-nombre">' + escapeHtml(u.nombre) + '</div>';
            html += '<div class="usuario-email">' + escapeHtml(u.correo) + '</div>';
            html += '</div></div></td>';
            html += '<td><span class="acceso-badge">' + escapeHtml(u.cedula) + '</span></td>';
            html += '<td>' + rolesHtml + '</td>';
            html += '<td>' + formatFecha(u.ultimo_acceso) + '</td>';
            html += '<td class="text-center">';
            html += '<button class="accion-btn editar" title="Ver detalle / Editar" onclick="verDetalle(' + u.id + ')">';
            html += '<i class="bi bi-pencil-square"></i></button> ';
            html += '<button class="accion-btn eliminar" title="Eliminar rol" onclick="eliminarRolUsuario(' + u.id + ')">';
            html += '<i class="bi bi-trash3"></i></button>';
            html += '</td></tr>';
        });

        tbody.innerHTML = html;
    }

    function actualizarEstadisticas() {
        document.getElementById('statTotal').textContent = datos.usuarios.length;
        document.getElementById('statRoles').textContent = datos.detalle_roles.length;
    }

    // ============================================================
    // FILTRADO
    // ============================================================

    function filtrarUsuarios() {
        const termino = document.getElementById('buscarUsuario').value.toLowerCase().trim();
        const filas = document.querySelectorAll('#tablaUsuarios tr[data-usuario-id]');
        let visibles = 0;

        filas.forEach(function(fila) {
            const busqueda = fila.getAttribute('data-busqueda');
            if (!termino || busqueda.indexOf(termino) !== -1) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        if (visibles === 0 && termino) {
            const tbody = document.getElementById('tablaUsuarios');
            if (!document.getElementById('filaSinResultados')) {
                const tr = document.createElement('tr');
                tr.id = 'filaSinResultados';
                tr.innerHTML = '<td colspan="5" class="sin-resultados">' +
                    '<i class="bi bi-search" style="font-size:2rem;"></i>' +
                    '<p class="mt-2">No se encontraron usuarios que coincidan con "' + escapeHtml(termino) + '"</p>' +
                    '</td>';
                tbody.appendChild(tr);
            }
        } else {
            const sinRes = document.getElementById('filaSinResultados');
            if (sinRes) sinRes.remove();
        }
    }

    // ============================================================
    // MODAL ASIGNAR ROL
    // ============================================================

    function abrirModalAsignar() {
        modoModal = 'asignar';
        rolSeleccionado = null;
        usuarioRolEditando = null;

        document.getElementById('modalAsignarTitulo').innerHTML =
            '<i class="bi bi-shield-plus me-2"></i>Asignar Rol';
        document.getElementById('btnGuardarAsignacion').innerHTML =
            '<i class="bi bi-check-lg me-1"></i> Asignar';

        // Mostrar busqueda de usuario, ocultar usuario actual
        document.getElementById('seccionBuscarUsuario').style.display = '';
        document.getElementById('seccionUsuarioActual').style.display = 'none';
        document.getElementById('resultadoBusqueda').style.display = 'none';
        document.getElementById('inputCedula').value = '';
        document.getElementById('usuarioIdBuscado').value = '';

        // Mostrar subsistema y codigo presupuestario
        document.getElementById('seccionSubsistema').style.display = '';
        document.getElementById('seccionCodigoPresu').style.display = '';

        // Llenar subsistemas
        const selectSub = document.getElementById('selectSubsistema');
        selectSub.innerHTML = '<option value="">Seleccione subsistema...</option>';
        datos.subsistemas.forEach(function(s) {
            selectSub.innerHTML += '<option value="' + s.id + '">' + escapeHtml(s.nombre) + '</option>';
        });
        document.getElementById('inputCodigoPresu').value = '';

        // Llenar roles (incluye Root)
        renderizarListaRoles();

        // Resetear advertencia Root
        document.getElementById('rootWarningBanner').classList.remove('visible');
        document.getElementById('rootConfirmGroup').classList.remove('visible');
        document.getElementById('inputRootConfirm').value = '';
        document.getElementById('rootConfirmError').style.display = 'none';

        document.getElementById('btnGuardarAsignacion').disabled = true;
        mostrarModal('modalAsignarRol');
    }

    // ============================================================
    // MODAL EDITAR ROL
    // ============================================================

    function abrirModalEditar(usuarioRolId) {
        modoModal = 'editar';
        rolSeleccionado = null;
        usuarioRolEditando = usuarioRolId;

        // Buscar el detalle del rol
        const detalle = datos.detalle_roles.find(function(d) {
            return parseInt(d.usuario_rol_id) === parseInt(usuarioRolId);
        });
        if (!detalle) {
            mostrarError('No se encontro el registro del rol');
            return;
        }

        // Buscar el usuario
        const usuario = datos.usuarios.find(function(u) {
            return parseInt(u.id) === parseInt(detalle.usuario_id);
        });

        document.getElementById('modalAsignarTitulo').innerHTML =
            '<i class="bi bi-pencil-square me-2"></i>Editar Rol';
        document.getElementById('btnGuardarAsignacion').innerHTML =
            '<i class="bi bi-check-lg me-1"></i> Actualizar';

        // Mostrar usuario actual, ocultar busqueda
        document.getElementById('seccionBuscarUsuario').style.display = 'none';
        document.getElementById('seccionUsuarioActual').style.display = '';

        if (usuario) {
            document.getElementById('avatarUsuarioActual').textContent = getIniciales(usuario.nombre);
            document.getElementById('nombreUsuarioActual').textContent = usuario.nombre;
            document.getElementById('correoUsuarioActual').textContent = usuario.correo;
        }

        // Ocultar subsistema y codigo (no se modifican en edicion)
        document.getElementById('seccionSubsistema').style.display = 'none';
        document.getElementById('seccionCodigoPresu').style.display = 'none';

        // Pre-seleccionar el rol actual
        renderizarListaRoles(detalle.rol_id);

        // Resetear/activar advertencia Root si el rol actual es Root
        const esRootActual = parseInt(detalle.rol_id) === 1;
        const banner = document.getElementById('rootWarningBanner');
        const confirmGroup = document.getElementById('rootConfirmGroup');
        const inputConfirm = document.getElementById('inputRootConfirm');
        const errorText = document.getElementById('rootConfirmError');
        if (esRootActual) {
            document.getElementById('rootCountActual').textContent = datos.root_count;
            banner.classList.add('visible');
            confirmGroup.classList.add('visible');
            inputConfirm.value = 'ROOT';
            errorText.style.display = 'none';
        } else {
            banner.classList.remove('visible');
            confirmGroup.classList.remove('visible');
            inputConfirm.value = '';
            errorText.style.display = 'none';
        }

        document.getElementById('btnGuardarAsignacion').disabled = false;
        mostrarModal('modalAsignarRol');
    }

    // ============================================================
    // RENDERIZAR LISTA DE ROLES
    // ============================================================

    function renderizarListaRoles(rolPreseleccionado) {
        const container = document.getElementById('listaRoles');
        let html = '';

        datos.roles.forEach(function(r) {
            const icono = getIconoRol(r);
            const esRoot = parseInt(r.id_rol) === 1;
            const seleccionado = rolPreseleccionado && parseInt(r.id_rol) === parseInt(rolPreseleccionado);
            let clase = 'rol-card-option';
            if (esRoot) clase += ' root-card';
            if (seleccionado) clase += ' seleccionado';

            html += '<div class="' + clase + '" data-rol-id="' + r.id_rol + '" onclick="seleccionarRol(' + r.id_rol + ', this)">';
            html += '<div class="rol-icon"><i class="bi ' + icono + '"></i></div>';
            html += '<div class="rol-info">';
            html += '<div class="rol-name">' + escapeHtml(r.rol) + (esRoot ? ' <i class="bi bi-exclamation-triangle" style="font-size:0.75rem;"></i>' : '') + '</div>';
            html += '<div class="rol-desc">' + escapeHtml(r.descripcion) + '</div>';
            html += '</div></div>';
        });

        container.innerHTML = html;

        if (rolPreseleccionado) {
            rolSeleccionado = parseInt(rolPreseleccionado);
        }
    }

    function seleccionarRol(rolId, el) {
        // Deseleccionar todos
        document.querySelectorAll('#listaRoles .rol-card-option').forEach(function(card) {
            card.classList.remove('seleccionado');
        });
        // Seleccionar este
        el.classList.add('seleccionado');
        rolSeleccionado = rolId;

        // Mostrar/ocultar advertencia y confirmacion Root
        const esRoot = parseInt(rolId) === 1;
        const banner = document.getElementById('rootWarningBanner');
        const confirmGroup = document.getElementById('rootConfirmGroup');
        const inputConfirm = document.getElementById('inputRootConfirm');
        const errorText = document.getElementById('rootConfirmError');

        if (esRoot) {
            document.getElementById('rootCountActual').textContent = datos.root_count;
            banner.classList.add('visible');
            confirmGroup.classList.add('visible');
            inputConfirm.value = '';
            errorText.style.display = 'none';
            document.getElementById('btnGuardarAsignacion').disabled = true;
        } else {
            banner.classList.remove('visible');
            confirmGroup.classList.remove('visible');
            inputConfirm.value = '';
            errorText.style.display = 'none';
            document.getElementById('btnGuardarAsignacion').disabled = false;
        }
    }

    function validarConfirmRoot() {
        const input = document.getElementById('inputRootConfirm');
        const errorText = document.getElementById('rootConfirmError');
        const btn = document.getElementById('btnGuardarAsignacion');

        if (input.value === 'ROOT') {
            errorText.style.display = 'none';
            btn.disabled = false;
        } else {
            errorText.style.display = '';
            btn.disabled = true;
        }
    }

    // ============================================================
    // BUSCAR USUARIO POR CEDULA
    // ============================================================

    async function buscarUsuarioPorCedula() {
        const cedula = document.getElementById('inputCedula').value.trim();
        if (!cedula) {
            mostrarError('Ingrese una cedula para buscar');
            return;
        }

        try {
            const resp = await fetch('sql/buscar_usuario_cedula_n.php?cedula=' + encodeURIComponent(cedula));
            const data = await resp.json();

            if (!data.success) {
                mostrarError(data.message || 'Usuario no encontrado');
                document.getElementById('resultadoBusqueda').style.display = 'none';
                document.getElementById('usuarioIdBuscado').value = '';
                return;
            }

            const u = data.usuario;
            document.getElementById('avatarUsuarioBuscado').textContent = getIniciales(u.nombre);
            document.getElementById('nombreUsuarioBuscado').textContent = u.nombre;
            document.getElementById('correoUsuarioBuscado').textContent = u.correo;
            document.getElementById('usuarioIdBuscado').value = u.id;
            document.getElementById('resultadoBusqueda').style.display = '';

        } catch (e) {
            mostrarError('Error al buscar usuario: ' + e.message);
        }
    }

    // ============================================================
    // GUARDAR ASIGNACION / EDICION
    // ============================================================

    async function guardarAsignacion() {
        if (!rolSeleccionado) {
            mostrarError('Seleccione un rol');
            return;
        }

        // Validar confirmacion Root
        const esRoot = parseInt(rolSeleccionado) === 1;
        if (esRoot) {
            const inputConfirm = document.getElementById('inputRootConfirm').value;
            if (inputConfirm !== 'ROOT') {
                document.getElementById('rootConfirmError').style.display = '';
                return;
            }
            // Confirmacion doble con SweetAlert-style
            mostrarConfirmacion(
                'ATENCION: Esta a punto de asignar el rol Root a un usuario. ' +
                'Este rol otorga control TOTAL del sistema. ¿Desea continuar?',
                async function() {
                    await ejecutarGuardado();
                }
            );
            return;
        }

        await ejecutarGuardado();
    }

    async function ejecutarGuardado() {

        if (modoModal === 'asignar') {
            // Asignar nuevo rol
            const usuarioId = document.getElementById('usuarioIdBuscado').value;
            const subsistemaId = document.getElementById('selectSubsistema').value;
            const codigoPresu = document.getElementById('inputCodigoPresu').value.trim();

            if (!usuarioId) {
                mostrarError('Busque y seleccione un usuario primero');
                return;
            }
            if (!subsistemaId) {
                mostrarError('Seleccione un subsistema');
                return;
            }

            try {
                const resp = await fetch('actualizar_gestor_usuarios_n.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion: 'asignar',
                        usuario_id: parseInt(usuarioId),
                        rol_id: rolSeleccionado,
                        subsistema_id: parseInt(subsistemaId),
                        codigo_presu: codigoPresu
                    })
                });
                const data = await resp.json();

                if (data.success) {
                    ocultarModal('modalAsignarRol');
                    mostrarExito(data.message);
                    await cargarDatos();
                } else {
                    mostrarError(data.message);
                }
            } catch (e) {
                mostrarError('Error de conexion: ' + e.message);
            }

        } else if (modoModal === 'editar') {
            // Editar rol existente
            if (!usuarioRolEditando) return;

            try {
                const resp = await fetch('actualizar_gestor_usuarios_n.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion: 'editar',
                        usuario_rol_id: parseInt(usuarioRolEditando),
                        nuevo_rol_id: rolSeleccionado
                    })
                });
                const data = await resp.json();

                if (data.success) {
                    ocultarModal('modalAsignarRol');
                    mostrarExito(data.message);
                    await cargarDatos();
                } else {
                    mostrarError(data.message);
                }
            } catch (e) {
                mostrarError('Error de conexion: ' + e.message);
            }
        }
    }

    // ============================================================
    // VER DETALLE / EDITAR
    // ============================================================

    function verDetalle(usuarioId) {
        const usuario = datos.usuarios.find(function(u) {
            return parseInt(u.id) === parseInt(usuarioId);
        });
        if (!usuario) return;

        document.getElementById('avatarDetalle').textContent = getIniciales(usuario.nombre);
        document.getElementById('nombreDetalle').textContent = usuario.nombre;
        document.getElementById('correoDetalle').textContent = usuario.correo;

        // Obtener roles de este usuario
        const rolesUsuario = datos.detalle_roles.filter(function(d) {
            return parseInt(d.usuario_id) === parseInt(usuarioId);
        });

        const container = document.getElementById('listaDetalleRoles');

        if (rolesUsuario.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-3">No tiene roles asignados</div>';
        } else {
            let html = '';
            rolesUsuario.forEach(function(r) {
                const rolInfo = datos.roles.find(function(rt) { return parseInt(rt.id_rol) === parseInt(r.rol_id); });
                const icono = getIconoRol(rolInfo);
                const sub = datos.subsistemas.find(function(s) {
                    return parseInt(s.id) === parseInt(r.subsistema_id);
                });
                const subNombre = sub ? sub.nombre : 'Subsistema ' + r.subsistema_id;

                html += '<div class="d-flex align-items-center justify-content-between p-3 bg-light rounded mb-2">';
                html += '<div class="d-flex align-items-center gap-3">';
                html += '<div class="rol-icon" style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#003876,#114c91);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;">';
                html += '<i class="bi ' + icono + '"></i></div>';
                html += '<div>';
                html += '<div class="fw-bold" style="font-size:0.9rem;">' + escapeHtml(r.rol) + '</div>';
                html += '<small class="text-muted">' + escapeHtml(subNombre) + '</small>';
                html += '</div></div>';
                html += '<button class="accion-btn editar" title="Editar rol" onclick="ocultarModal(\'modalDetalleRoles\'); abrirModalEditar(' + r.usuario_rol_id + ');">';
                html += '<i class="bi bi-pencil-square"></i></button>';
                html += '</div>';
            });
            container.innerHTML = html;
        }

        mostrarModal('modalDetalleRoles');
    }

    // ============================================================
    // ELIMINAR ROL
    // ============================================================

    function eliminarRolUsuario(usuarioId) {
        const usuario = datos.usuarios.find(function(u) {
            return parseInt(u.id) === parseInt(usuarioId);
        });
        if (!usuario) return;

        // Obtener roles del usuario
        const rolesUsuario = datos.detalle_roles.filter(function(d) {
            return parseInt(d.usuario_id) === parseInt(usuarioId);
        });

        if (rolesUsuario.length === 0) {
            mostrarError('Este usuario no tiene roles asignados');
            return;
        }

        // Si tiene un solo rol, eliminar directo
        if (rolesUsuario.length === 1) {
            const r = rolesUsuario[0];
            mostrarConfirmacion(
                '¿Desea eliminar el rol "' + r.rol + '" de ' + usuario.nombre + '?',
                async function() {
                    try {
                        const resp = await fetch('actualizar_gestor_usuarios_n.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                accion: 'eliminar',
                                usuario_rol_id: parseInt(r.usuario_rol_id)
                            })
                        });
                        const data = await resp.json();

                        if (data.success) {
                            mostrarExito(data.message);
                            await cargarDatos();
                        } else {
                            mostrarError(data.message);
                        }
                    } catch (e) {
                        mostrarError('Error de conexion: ' + e.message);
                    }
                }
            );
        } else {
            // Si tiene varios, abrir detalle para que elija cual eliminar
            verDetalle(usuarioId);
        }
    }

    // ============================================================
    // INICIALIZACION
    // ============================================================

    document.addEventListener('DOMContentLoaded', function() {
        cargarDatos();
    });
    </script>

</body>
</html>
