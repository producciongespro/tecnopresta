<?php
// ============================================================
// FORMULARIO: Asignacion de Permisos a Roles
// ============================================================
// Proposito: Interfaz administrativa para asignar y revocar
// permisos a roles mediante un arbol colapsable de subsistemas,
// modulos y formularios con badges toggle por accion.
//
// Acceso: Solo Root (rol_id = 1)
//
// Dependencias:
//   - navegar.php (define ACCESO_SEGURO)
//   - auth.php (validacion de sesion y roles)
//   - usuarioAzure.php (datos de sesion Azure AD)
//   - sql/gestor_roles.php (endpoint datos)
//   - actualizar_gestor_roles_permisos_n.php (endpoint accion)
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

if (!esUsuarioRoot()) {
    header('Location: navegar.php?ruta=formulario_menu_principal.php');
    exit;
}

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
    <title>Gestor del Sistema - Asignacion de Permisos a Roles</title>

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
       BLOQUES DE PERMISOS - Subsistemas y Modulos como cards
       ============================================================ */

    /* --- Subsistema Block --- */
    .perm-sub-block {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        border-left: 5px solid #003876;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        overflow: hidden;
        transition: box-shadow 0.2s;
    }
    .perm-sub-block:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.07);
    }

    .perm-sub-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        cursor: pointer;
        user-select: none;
        background: linear-gradient(135deg, rgba(0,56,118,0.02), rgba(0,56,118,0.005));
        border-bottom: 1px solid transparent;
        transition: background 0.2s;
    }
    .perm-sub-header:hover {
        background: rgba(0,56,118,0.04);
    }
    .perm-sub-header .perm-chevron {
        font-size: 0.7rem;
        color: #6c757d;
        transition: transform 0.25s;
        width: 16px;
        text-align: center;
    }
    .perm-sub-header .perm-chevron.collapsed {
        transform: rotate(-90deg);
    }
    .perm-sub-header .perm-sub-icon {
        font-size: 1.1rem;
        opacity: 0.85;
    }
    .perm-sub-header .perm-sub-name {
        font-weight: 700;
        font-size: 0.95rem;
        flex-grow: 1;
    }
    .perm-sub-header .perm-badge-count {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 10px;
        background: rgba(0,56,118,0.08);
        color: #495057;
    }

    .perm-sub-body {
        padding: 12px;
        transition: max-height 0.3s ease;
    }
    .perm-sub-body.collapsed {
        display: none;
    }

    /* --- Modulo Block --- */
    .perm-mod-block {
        background: #f8f9fc;
        border-radius: 10px;
        border: 1px solid #eef1f6;
        margin-bottom: 10px;
        overflow: hidden;
    }
    .perm-mod-block:last-child {
        margin-bottom: 0;
    }

    .perm-mod-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        cursor: pointer;
        user-select: none;
        transition: background 0.2s;
    }
    .perm-mod-header:hover {
        background: rgba(0,56,118,0.03);
    }
    .perm-mod-header .perm-chevron {
        font-size: 0.65rem;
        color: #6c757d;
        transition: transform 0.25s;
        width: 14px;
        text-align: center;
    }
    .perm-mod-header .perm-chevron.collapsed {
        transform: rotate(-90deg);
    }
    .perm-mod-header .perm-mod-icon {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .perm-mod-header .perm-mod-name {
        font-weight: 600;
        font-size: 0.88rem;
        flex-grow: 1;
    }
    .perm-mod-header .perm-badge-count {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 8px;
        background: rgba(0,56,118,0.06);
        color: #6c757d;
    }

    /* --- Form List (inside modulo) --- */
    .perm-form-list {
        padding: 0 8px 8px 8px;
        transition: max-height 0.3s ease;
    }
    .perm-form-list.collapsed {
        display: none;
    }

    /* --- Formulario Item --- */
    .formulario-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        transition: background 0.2s;
        flex-wrap: wrap;
        background: #fff;
        border: 1px solid #f0f0f0;
        margin-bottom: 6px;
    }
    .formulario-item:last-child {
        margin-bottom: 0;
    }
    .formulario-item:hover {
        background: #f8f9ff;
        border-color: #e0e4ef;
    }
    .formulario-item .form-check-input {
        cursor: pointer;
    }
    .formulario-item .form-check-input:checked {
        background-color: #003876;
        border-color: #003876;
    }
    .formulario-item .form-check-input.indeterminado {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    /* --- Accion Badges --- */
    .accion-badge {
        cursor: pointer;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 1.5px solid;
        user-select: none;
        display: inline-block;
    }
    .accion-badge.activa {
        color: #fff;
        border-color: transparent;
    }
    .accion-badge.inactiva {
        background: transparent !important;
        opacity: 0.55;
    }
    .accion-badge.inactiva:hover {
        opacity: 0.85;
    }
    .accion-badge.bloqueada {
        opacity: 0.30;
        cursor: help;
        border-style: dashed;
        background: transparent !important;
    }

    /* --- Floating Buttons --- */
    .btn-guardar-flotante-con-contador {
        position: fixed;
        bottom: 100px;
        right: 30px;
        z-index: 1030;
    }
    .cambios-contador {
        position: absolute;
        top: -4px;
        right: -2px;
        background: #dc3545;
        color: #fff;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 0.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .sin-resultados {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
    }
    </style>
</head>
<body class="layout-page">

    <?php include 'partials/header.php'; ?>

    <!-- ============================================================
    CONTENIDO PRINCIPAL
    ============================================================ -->
    <main class="container py-4 contenido-principal" id="appRolesPermisos">

        <!-- Hero-box -->
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: #C8A951;"></i>
                </div>
                <div class="col">
                    <h1 class="mb-0" style="color: #fff; font-weight: 600;">Asignacion de Permisos a Roles</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.8);">
                        Gestione que roles tienen acceso a que formularios con que acciones
                    </p>
                </div>
            </div>
        </div>

        <!-- Selector de rol -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="selectRol" class="form-label">Rol</label>
                        <select class="form-select" id="selectRol" onchange="onCambioRol()">
                            <option value="">Seleccione un rol...</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Resumen</label>
                        <div class="p-2 bg-light rounded" id="resumenInfo">
                            <span class="text-muted">Seleccione un rol</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="buscarFormulario" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="buscarFormulario"
                               placeholder="Buscar formulario..." oninput="onBuscar()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Arbol de permisos -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-diagram-2 me-2"></i>Permisos por Formulario
                </h5>
                <span class="badge bg-secondary" id="contadorPermisos">0 permisos</span>
            </div>
            <div class="alert alert-info mb-0 rounded-0 border-start border-info border-4">
                <i class="bi bi-lock-fill me-2"></i>
                Las acciones marcadas con candado no estan habilitadas para ese formulario.
                Para poder asignarlas a un rol, primero habilite la accion en
                <strong>Gestion de Formularios</strong> (edite el formulario y marque la accion)
                y luego regrese a esta pantalla.
            </div>
            <div class="card-body" id="arbolContainer">
                <div class="text-center text-muted py-4">
                    <i class="bi bi-arrow-up-circle" style="font-size: 2rem;"></i>
                    <p class="mt-2">Seleccione un rol para visualizar sus permisos</p>
                </div>
            </div>
        </div>

        <!-- Boton guardar flotante con contador -->
        <div class="btn-guardar-flotante-con-contador" id="btnGuardarContainer" style="display:none;">
            <button class="btn-guardar-flotante" onclick="guardarCambios();" data-tooltip="Guardar cambios">
                <i class="bi bi-floppy"></i>
                <span class="cambios-contador" id="cambiosContador">0</span>
            </button>
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

        <!-- Modal Aviso Permiso Bloqueado -->
        <div class="modal fade gestor-overlay" id="modalAvisoPermiso" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon warning">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                        <p class="mt-3 mb-0 fw-semibold" id="modalAvisoPermisoTexto">Accion no habilitada</p>
                        <p class="mt-2 mb-0 text-muted small" id="modalAvisoPermisoAyuda">
                            Debe habilitar la accion en <strong>Gestion de Formularios</strong>
                            para poder asignarla al rol.
                        </p>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <a href="navegar.php?ruta=gestor_formularios_n.php" class="gestor-btn-primary">
                            <i class="bi bi-box-arrow-up-right"></i> Ir a Gestion de Formularios
                        </a>
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
        roles: [],
        subsistemas: [],
        modulos: [],
        formularios: [],
        acciones: [],
        permisos: [],
        roles_permisos: []
    };

    let rolSeleccionado = null;
    let cambiosPendientes = []; // Array de {formulario_id, accion_id, activo}
    let totalPermisosActuales = 0;
    let hayCambiosSinGuardar = false;

    // Mapa de colores por accion
    const COLORES_ACCION = {
        1:  '#0d6efd',
        2:  '#198754',
        3:  '#fd7e14',
        4:  '#dc3545',
        5:  '#0dcaf0',
        6:  '#6f42c1',
        7:  '#1e7e34',
        8:  '#20c997',
        9:  '#6c757d',
        10: '#ffc107',
        11: '#d63384'
    };

    // Colores para subsistemas en el arbol
    const COLORES_SUBSISTEMA = [
        '#003876', '#114c91', '#1a5cb3', '#2c3e50', '#1a5276'
    ];

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

    function avisoPermisoBloqueado(el) {
        const formNombre = el.getAttribute('data-form-nombre') || '';
        const accionNombre = el.getAttribute('data-accion-nombre') || '';
        document.getElementById('modalAvisoPermisoTexto').innerHTML =
            'La accion <strong>' + escapeHtml(accionNombre) + '</strong> no esta habilitada ' +
            'para el formulario <strong>' + escapeHtml(formNombre) + '</strong>.';
        mostrarModal('modalAvisoPermiso');
    }

    // ============================================================
    // CARGA DE DATOS
    // ============================================================

    async function cargarDatos() {
        try {
            const resp = await fetch('sql/gestor_roles.php');
            const data = await resp.json();

            if (!data.success) throw new Error(data.message || 'Error al cargar datos');

            datos = {
                roles: data.roles || [],
                subsistemas: data.subsistemas || [],
                modulos: data.modulos || [],
                formularios: data.formularios || [],
                acciones: data.acciones || [],
                permisos: data.permisos || [],
                roles_permisos: data.roles_permisos || []
            };

            llenarSelectRoles();

        } catch (e) {
            mostrarError('Error al cargar datos: ' + e.message);
        }
    }

    function llenarSelectRoles() {
        const select = document.getElementById('selectRol');
        select.innerHTML = '<option value="">Seleccione un rol...</option>';
        datos.roles.forEach(function(r) {
            const op = document.createElement('option');
            op.value = r.id_rol;
            op.textContent = r.rol + (r.descripcion ? ' - ' + r.descripcion : '');
            select.appendChild(op);
        });
    }

    // ============================================================
    // CAMBIO DE ROL
    // ============================================================

    function onCambioRol() {
        const select = document.getElementById('selectRol');
        const nuevoRol = select.value ? parseInt(select.value) : null;

        // Si hay cambios sin guardar, confirmar antes de cambiar
        if (hayCambiosSinGuardar && nuevoRol !== rolSeleccionado) {
            mostrarConfirmacion(
                'Tiene cambios sin guardar. Si cambia de rol se perderan las modificaciones. ¿Desea continuar?',
                function() {
                    cambiosPendientes = [];
                    hayCambiosSinGuardar = false;
                    rolSeleccionado = nuevoRol;
                    actualizarArbol();
                }
            );
            // Restaurar el valor anterior en el select
            select.value = rolSeleccionado || '';
            return;
        }

        rolSeleccionado = nuevoRol;
        cambiosPendientes = [];
        hayCambiosSinGuardar = false;
        actualizarArbol();
    }

    // ============================================================
    // ARBOL DE PERMISOS
    // ============================================================

    function actualizarArbol() {
        const container = document.getElementById('arbolContainer');
        const contadorPermisos = document.getElementById('contadorPermisos');
        const btnContainer = document.getElementById('btnGuardarContainer');

        if (!rolSeleccionado) {
            container.innerHTML =
                '<div class="text-center text-muted py-4">' +
                    '<i class="bi bi-arrow-up-circle" style="font-size: 2rem;"></i>' +
                    '<p class="mt-2">Seleccione un rol para visualizar sus permisos</p>' +
                '</div>';
            document.getElementById('resumenInfo').innerHTML = '<span class="text-muted">Seleccione un rol</span>';
            contadorPermisos.textContent = '0 permisos';
            btnContainer.style.display = 'none';
            return;
        }

        // Construir el resumen
        const permisosDelRol = obtenerPermisosConCambios(rolSeleccionado);
        totalPermisosActuales = permisosDelRol.length;

        const subsistemasConModulos = contarSubsistemasConModulosConForms();
        const formulariosTotal = datos.formularios.length;

        document.getElementById('resumenInfo').innerHTML =
            '<strong>' + escapeHtml(obtenerNombreRol(rolSeleccionado)) + '</strong>' +
            '<br><small class="text-muted">' +
                subsistemasConModulos + ' subsistemas &middot; ' +
                formulariosTotal + ' formularios &middot; ' +
                totalPermisosActuales + ' permisos asignados' +
            '</small>';

        contadorPermisos.textContent = totalPermisosActuales + ' permisos';

        // Construir el arbol por bloques
        const busqueda = document.getElementById('buscarFormulario').value.toLowerCase().trim();
        let html = '';

        datos.subsistemas.forEach(function(sub, idx) {
            const modulosSub = datos.modulos.filter(function(m) {
                return parseInt(m.subsistema_id) === parseInt(sub.id);
            });
            if (modulosSub.length === 0) return;

            const colorSub = COLORES_SUBSISTEMA[idx % COLORES_SUBSISTEMA.length];
            const subId = 'sub-' + sub.id;

            // Contar modulos con forms visibles
            let modulosConForms = 0;
            modulosSub.forEach(function(mod) {
                const formsMod = datos.formularios.filter(function(f) {
                    return parseInt(f.modulo_id) === parseInt(mod.id);
                });
                if (formsMod.length === 0) return;
                if (busqueda) {
                    const visibles = formsMod.filter(function(f) {
                        return f.nombre.toLowerCase().indexOf(busqueda) !== -1;
                    });
                    if (visibles.length === 0) return;
                }
                modulosConForms++;
            });

            if (modulosConForms === 0) return;

            html += '<div class="perm-sub-block" style="border-left-color:' + colorSub + ';">';
            html += '<div class="perm-sub-header" onclick="toggleArbol(\'' + subId + '\')">';
            html += '<i class="bi bi-chevron-down perm-chevron" id="chevron-' + subId + '"></i>';
            html += '<i class="bi bi-grid-3x3-gap-fill perm-sub-icon" style="color:' + colorSub + ';"></i>';
            html += '<span class="perm-sub-name" style="color:' + colorSub + ';">' + escapeHtml(sub.nombre) + '</span>';
            html += '<span class="perm-badge-count">' + modulosConForms + ' módulo' + (modulosConForms > 1 ? 's' : '') + '</span>';
            html += '</div>';
            html += '<div class="perm-sub-body" id="' + subId + '">';

            modulosSub.forEach(function(mod) {
                const formsMod = datos.formularios.filter(function(f) {
                    return parseInt(f.modulo_id) === parseInt(mod.id);
                });
                if (formsMod.length === 0) return;

                const modId = 'mod-' + mod.id;
                const formsVisibles = busqueda ? formsMod.filter(function(f) {
                    return f.nombre.toLowerCase().indexOf(busqueda) !== -1;
                }) : formsMod;

                if (busqueda && formsVisibles.length === 0) return;

                html += '<div class="perm-mod-block">';
                html += '<div class="perm-mod-header" onclick="toggleArbol(\'' + modId + '\')">';
                html += '<i class="bi bi-chevron-down perm-chevron" id="chevron-' + modId + '"></i>';
                html += '<i class="bi bi-folder2-open perm-mod-icon"></i>';
                html += '<span class="perm-mod-name">' + escapeHtml(mod.nombre) + '</span>';
                html += '<span class="perm-badge-count">' + (busqueda ? formsVisibles.length : formsMod.length) + ' form' + ((busqueda ? formsVisibles.length : formsMod.length) > 1 ? 's' : '') + '</span>';
                html += '</div>';
                html += '<div class="perm-form-list" id="' + modId + '">';

                (busqueda ? formsVisibles : formsMod).forEach(function(form) {
                    html += renderizarFormularioItem(form, permisosDelRol);
                });

                html += '</div></div>';
            });

            html += '</div></div>';
        });

        if (!busqueda && datos.subsistemas.length === 0) {
            html = '<div class="text-center text-muted py-4">No hay datos disponibles</div>';
        } else if (busqueda && html.indexOf('formulario-item') === -1) {
            html = '<div class="sin-resultados">' +
                       '<i class="bi bi-search" style="font-size:2rem;"></i>' +
                       '<p class="mt-2">No se encontraron formularios que coincidan con "' + escapeHtml(busqueda) + '"</p>' +
                   '</div>';
        }

        container.innerHTML = html;
        actualizarContadorFlotante();
    }

    function renderizarFormularioItem(form, permisosDelRol) {
        // Determinar estado del formulario (usa permisos que incluyen cambios pendientes)
        const accionesAsignadas = permisosDelRol
            .filter(function(rp) { return parseInt(rp.formulario_id) === parseInt(form.id); })
            .map(function(rp) { return parseInt(rp.accion_id); });

        const accionesDisponibles = obtenerAccionesDisponibles(form.id);
        const todasLasAcciones = accionesDisponibles;
        const asignadasCount = todasLasAcciones.filter(function(aid) {
            return accionesAsignadas.indexOf(aid) !== -1;
        }).length;
        const totalCount = todasLasAcciones.length;

        let estadoCheckbox = '';
        let claseIndeterminado = '';
        if (totalCount === 0 || asignadasCount === 0) {
            estadoCheckbox = '';
            claseIndeterminado = '';
        } else if (asignadasCount === totalCount) {
            estadoCheckbox = 'checked';
            claseIndeterminado = '';
        } else {
            estadoCheckbox = 'checked';
            claseIndeterminado = 'indeterminado';
        }

        let html = '<div class="formulario-item" data-form-id="' + form.id + '" data-rol-id="' + rolSeleccionado + '">';

        // Checkbox de estado
        html += '<div class="form-check mb-0">';
        html += '<input class="form-check-input ' + claseIndeterminado + '" type="checkbox" ' +
                    estadoCheckbox + ' id="chk-form-' + form.id + '" ' +
                    'onclick="toggleFormularioCompleto(' + form.id + ', this.checked)">';
        html += '</div>';

        // Nombre del formulario
        html += '<span class="small flex-grow-1"><strong>' + escapeHtml(form.nombre) + '</strong></span>';

        // Badges de acciones
        datos.acciones.forEach(function(accion) {
            const esDisponible = accionesDisponibles.indexOf(parseInt(accion.id)) !== -1;
            const asignada = esDisponible && accionesAsignadas.indexOf(parseInt(accion.id)) !== -1;
            const color = COLORES_ACCION[accion.id] || '#6c757d';

            if (esDisponible) {
                html += '<span class="accion-badge ' + (asignada ? 'activa' : 'inactiva') + '" ' +
                            'style="' + (asignada ? 'background:' + color + ';' : 'border-color:' + color + ';color:' + color + ';') + '" ' +
                            'onclick="toggleAccion(' + form.id + ', ' + accion.id + ')" ' +
                            'title="' + escapeHtml(accion.descripcion || accion.nombre) + '">' +
                        escapeHtml(accion.nombre) +
                        '</span>';
            } else {
                html += '<span class="accion-badge bloqueada" ' +
                            'style="border-color:' + color + ';color:' + color + ';" ' +
                            'onclick="avisoPermisoBloqueado(this)" ' +
                            'data-form-nombre="' + escapeHtml(form.nombre) + '" ' +
                            'data-accion-nombre="' + escapeHtml(accion.nombre) + '" ' +
                            'title="' + escapeHtml(accion.nombre) + ': No esta habilitada para este formulario. Para asignarla aqui, primero vaya a Gestion de Formularios, edite el formulario y habilite esta accion.">' +
                        '<i class="bi bi-lock-fill" style="font-size:0.6rem;margin-right:2px;"></i>' +
                        escapeHtml(accion.nombre) +
                        '</span>';
            }
        });

        html += '</div>';
        return html;
    }

    // ============================================================
    // INTERACCION DEL ARBOL
    // ============================================================

    function toggleArbol(id) {
        const el = document.getElementById(id);
        const chevron = document.getElementById('chevron-' + id);
        if (el && chevron) {
            el.classList.toggle('collapsed');
            chevron.classList.toggle('collapsed');
        }
    }

    // ============================================================
    // LOGICA DE PERMISOS
    // ============================================================

    function obtenerPermisosDelRol(rolId) {
        // Obtener los permiso_id asignados al rol
        const permisoIds = datos.roles_permisos
            .filter(function(rp) { return parseInt(rp.rol_id) === parseInt(rolId); })
            .map(function(rp) { return parseInt(rp.permiso_id); });

        // Mapear a {formulario_id, accion_id}
        return datos.permisos
            .filter(function(p) { return permisoIds.indexOf(parseInt(p.id)) !== -1; })
            .map(function(p) { return { formulario_id: parseInt(p.formulario_id), accion_id: parseInt(p.accion_id) }; });
    }

    function obtenerPermisosConCambios(rolId) {
        // Estado base desde la BD
        const base = obtenerPermisosDelRol(rolId);
        const mapa = {};

        // Indexar estado base
        base.forEach(function(p) {
            const key = p.formulario_id + '-' + p.accion_id;
            mapa[key] = true;
        });

        // Aplicar cambios pendientes encima
        cambiosPendientes.forEach(function(c) {
            const key = c.formulario_id + '-' + c.accion_id;
            if (c.activo) {
                mapa[key] = true;
            } else {
                delete mapa[key];
            }
        });

        // Convertir de vuelta a array
        const resultado = [];
        Object.keys(mapa).forEach(function(key) {
            if (mapa[key]) {
                const partes = key.split('-');
                resultado.push({
                    formulario_id: parseInt(partes[0]),
                    accion_id: parseInt(partes[1])
                });
            }
        });

        return resultado;
    }

    function obtenerNombreRol(rolId) {
        const rol = datos.roles.find(function(r) { return parseInt(r.id_rol) === parseInt(rolId); });
        return rol ? rol.rol : 'Desconocido';
    }

    function contarSubsistemasConModulosConForms() {
        let count = 0;
        datos.subsistemas.forEach(function(sub) {
            const modulosSub = datos.modulos.filter(function(m) {
                return parseInt(m.subsistema_id) === parseInt(sub.id);
            });
            const tieneForms = modulosSub.some(function(mod) {
                return datos.formularios.some(function(f) { return parseInt(f.modulo_id) === parseInt(mod.id); });
            });
            if (tieneForms) count++;
        });
        return count;
    }

    // ============================================================
    // ACCIONES DISPONIBLES POR FORMULARIO
    // ============================================================

    function obtenerAccionesDisponibles(formularioId) {
        return datos.permisos
            .filter(function(p) { return parseInt(p.formulario_id) === parseInt(formularioId); })
            .map(function(p) { return parseInt(p.accion_id); });
    }

    // ============================================================
    // TOGGLE DE ACCIONES
    // ============================================================

    function toggleAccion(formularioId, accionId) {
        // Si la accion no esta disponible para este formulario, ignorar
        const accionesDisponibles = obtenerAccionesDisponibles(formularioId);
        if (accionesDisponibles.indexOf(parseInt(accionId)) === -1) return;
        // Determinar estado actual leyendo el badge en el DOM
        const badges = document.querySelectorAll('.formulario-item[data-form-id="' + formularioId + '"] .accion-badge');
        let actualmenteActiva = false;
        const accion = datos.acciones.find(function(a) { return parseInt(a.id) === parseInt(accionId); });
        badges.forEach(function(badge) {
            if (accion && badge.textContent.trim() === accion.nombre) {
                actualmenteActiva = badge.classList.contains('activa');
            }
        });
        const activo = !actualmenteActiva;

        // Agregar o actualizar en cambios pendientes
        const existente = cambiosPendientes.findIndex(function(c) {
            return parseInt(c.formulario_id) === parseInt(formularioId) &&
                   parseInt(c.accion_id) === parseInt(accionId);
        });

        if (existente !== -1) {
            cambiosPendientes[existente].activo = activo;
        } else {
            cambiosPendientes.push({
                formulario_id: formularioId,
                accion_id: accionId,
                activo: activo
            });
        }

        hayCambiosSinGuardar = true;

        // Actualizar el badge visualmente
        badges.forEach(function(badge) {
            if (accion && badge.textContent.trim() === accion.nombre) {
                const color = COLORES_ACCION[accionId] || '#6c757d';
                if (activo) {
                    badge.className = 'accion-badge activa';
                    badge.style.background = color;
                    badge.style.borderColor = 'transparent';
                    badge.style.color = '#fff';
                } else {
                    badge.className = 'accion-badge inactiva';
                    badge.style.background = 'transparent';
                    badge.style.borderColor = color;
                    badge.style.color = color;
                }
            }
        });

        actualizarCheckboxFormulario(formularioId);
        actualizarContadorFlotante();
    }

    function toggleFormularioCompleto(formularioId, activar) {
        const accionesDisponibles = obtenerAccionesDisponibles(formularioId);
        datos.acciones.forEach(function(accion) {
            // Saltar acciones no disponibles para este formulario
            if (accionesDisponibles.indexOf(parseInt(accion.id)) === -1) return;

            // Agregar/actualizar cambio pendiente para cada accion
            const existente = cambiosPendientes.findIndex(function(c) {
                return parseInt(c.formulario_id) === parseInt(formularioId) &&
                       parseInt(c.accion_id) === parseInt(accion.id);
            });

            const cambio = { formulario_id: formularioId, accion_id: accion.id, activo: activar };

            if (existente !== -1) {
                cambiosPendientes[existente].activo = activar;
            } else {
                cambiosPendientes.push(cambio);
            }
        });

        hayCambiosSinGuardar = true;

        // Actualizar todos los badges del formulario
        const badges = document.querySelectorAll('.formulario-item[data-form-id="' + formularioId + '"] .accion-badge');
        badges.forEach(function(badge) {
            // Saltar badges bloqueados
            if (badge.classList.contains('bloqueada')) return;

            // Buscar la accion por texto
            const accion = datos.acciones.find(function(a) { return badge.textContent.trim() === a.nombre; });
            if (accion) {
                const color = COLORES_ACCION[accion.id] || '#6c757d';
                if (activar) {
                    badge.className = 'accion-badge activa';
                    badge.style.background = color;
                    badge.style.borderColor = 'transparent';
                    badge.style.color = '#fff';
                } else {
                    badge.className = 'accion-badge inactiva';
                    badge.style.background = 'transparent';
                    badge.style.borderColor = color;
                    badge.style.color = color;
                }
            }
        });

        actualizarCheckboxFormulario(formularioId);
        actualizarContadorFlotante();
    }

    function actualizarCheckboxFormulario(formularioId) {
        const checkbox = document.getElementById('chk-form-' + formularioId);
        if (!checkbox) return;

        // Calcular cuantas acciones estarian activas (considerando cambios pendientes)
        const accionesActivas = contarAccionesActivas(formularioId);
        const totalCount = obtenerAccionesDisponibles(formularioId).length;

        checkbox.classList.remove('indeterminado');

        if (accionesActivas === 0) {
            checkbox.checked = false;
        } else if (accionesActivas === totalCount) {
            checkbox.checked = true;
        } else {
            checkbox.checked = true;
            checkbox.classList.add('indeterminado');
        }
    }

    function contarAccionesActivas(formularioId) {
        // Estado actual combinando BD + cambios pendientes, solo para acciones disponibles
        const accionesDisponibles = obtenerAccionesDisponibles(formularioId);
        const permisosRol = obtenerPermisosConCambios(rolSeleccionado);
        const activas = permisosRol
            .filter(function(p) {
                return parseInt(p.formulario_id) === parseInt(formularioId) &&
                       accionesDisponibles.indexOf(parseInt(p.accion_id)) !== -1;
            })
            .map(function(p) { return parseInt(p.accion_id); });

        return activas.length;
    }

    // ============================================================
    // BUSQUEDA
    // ============================================================

    function onBuscar() {
        // Solo actualizar la vista si hay un rol seleccionado
        if (rolSeleccionado) {
            actualizarArbol();
            // No resetear cambios pendientes
        }
    }

    // ============================================================
    // CONTADOR Y GUARDADO
    // ============================================================

    function actualizarContadorFlotante() {
        const container = document.getElementById('btnGuardarContainer');
        const contador = document.getElementById('cambiosContador');
        const count = cambiosPendientes.length;

        if (count > 0) {
            container.style.display = 'block';
            contador.textContent = count;
        } else {
            container.style.display = 'none';
            contador.textContent = '0';
        }
    }

    async function guardarCambios() {
        if (cambiosPendientes.length === 0) {
            mostrarError('No hay cambios pendientes por guardar');
            return;
        }

        mostrarConfirmacion(
            '¿Aplicar ' + cambiosPendientes.length + ' cambio(s) al rol "' + obtenerNombreRol(rolSeleccionado) + '"?',
            async function() {
                try {
                    const resp = await fetch('actualizar_gestor_roles_permisos_n.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            rol_id: rolSeleccionado,
                            cambios: cambiosPendientes
                        })
                    });
                    const data = await resp.json();

                    if (data.success) {
                        // Recargar datos completos para sincronizar estado
                        cambiosPendientes = [];
                        hayCambiosSinGuardar = false;
                        mostrarExito(data.message);
                        await cargarDatos();
                        // Restaurar seleccion de rol
                        document.getElementById('selectRol').value = rolSeleccionado;
                        actualizarArbol();
                    } else {
                        mostrarError(data.message);
                    }
                } catch (e) {
                    mostrarError('Error de conexion: ' + e.message);
                }
            }
        );
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
