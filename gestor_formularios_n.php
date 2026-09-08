<?php
// ============================================================
// FORMULARIO: Gestion de Formularios y Permisos
// ============================================================
// Proposito: Interfaz administrativa para CRUD de formularios
// y asignacion de acciones (permisos) a cada formulario.
//
// Acceso: Solo Root (rol_id = 1)
//
// Dependencias:
//   - navegar.php (define ACCESO_SEGURO)
//   - auth.php (validacion de sesion y roles)
//   - usuarioAzure.php (datos de sesion Azure AD)
//   - sql/gestor_formularios.php (endpoint datos)
//   - actualizar_gestor_formularios_n.php (endpoint accion)
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
    <title>Gestor del Sistema - Gestion de Formularios</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Estilos institucionales MEP -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=8">
    <!-- Estilos del menu principal y tablas MEP -->
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=8">
</head>
<body class="layout-page">

    <?php include 'partials/header.php'; ?>

    <!-- ============================================================
    CONTENIDO PRINCIPAL
    ============================================================ -->
    <main class="container py-4 contenido-principal" id="appGestorFormularios">

        <!-- Hero-box -->
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; color: #C8A951;"></i>
                </div>
                <div class="col">
                    <h1 class="mb-0" style="color: #fff; font-weight: 600;">Gestion de Formularios y Permisos</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.8);">
                        Administre los formularios del sistema y asigne las acciones permitidas
                    </p>
                </div>
            </div>
        </div>

        <!-- Filtros en cascada -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filtroSubsistema" class="form-label">Subsistema</label>
                        <select class="form-select" id="filtroSubsistema" onchange="onCambioSubsistema()">
                            <option value="">Todos los subsistemas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtroModulo" class="form-label">Modulo</label>
                        <select class="form-select" id="filtroModulo" onchange="onCambioModulo()">
                            <option value="">Todos los modulos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="buscarFormulario" class="form-label">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="buscarFormulario"
                                   placeholder="Nombre del formulario..." oninput="onBuscarFormulario()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-mep-primary w-100" onclick="abrirModalFormulario()">
                            <i class="bi bi-plus-lg"></i> Nuevo Formulario
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de formularios -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-layout-text-window me-2"></i>Formularios
                    <span class="badge bg-secondary ms-2" id="contadorFormularios">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table activos-table gestor-formularios-table mb-0" id="tablaFormularios">
                        <thead>
                            <tr>
                                <th class="th-nombre">Nombre del Formulario</th>
                                <th class="th-descripcion">Descripcion</th>
                                <th class="th-ruta">Ruta</th>
                                <th class="th-permisos">Permisos</th>
                                <th class="th-estado">Estado</th>
                                <th class="th-acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyFormularios">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Cargando formularios...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Boton volver flotante -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>"
           class="btn-disponibilidad" style="bottom: 100px;" data-tooltip="Regresar">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>

        <!-- ============================================================
        MODAL: CREAR/EDITAR FORMULARIO
        ============================================================ -->
        <div class="modal fade gestor-overlay" id="modalFormulario" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg gestor-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalFormularioTitulo">
                            <i class="bi bi-file-earmark-text"></i>Nuevo Formulario
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editFormularioId" value="0">

                        <!-- Banner de modulo -->
                        <div id="moduloBanner" class="modulo-banner mb-4" style="display:none;">
                            <div class="modulo-banner-icon">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </div>
                            <div class="modulo-banner-body">
                                <span class="modulo-banner-label">MODULO</span>
                                <span class="modulo-banner-name" id="moduloDisplayText">—</span>
                            </div>
                        </div>

                        <!-- Seccion 1: Identificacion -->
                        <div class="gestor-form-section gestor-form-section--alt">
                            <div class="gestor-section-title">
                                <i class="bi bi-person-badge"></i>Identificación
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="formNombre" class="gestor-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="gestor-input form-control" id="formNombre" maxlength="100" required
                                           placeholder="Ej: Registro de Activos">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label for="formDescripcion" class="gestor-label">Descripción</label>
                                <textarea class="gestor-textarea form-control" id="formDescripcion" rows="2" maxlength="300"
                                          placeholder="Breve descripcion del formulario"></textarea>
                            </div>
                        </div>

                        <!-- Seccion 2: Ubicacion -->
                        <div class="gestor-form-section">
                            <div class="gestor-section-title">
                                <i class="bi bi-folder2-open"></i>Ubicación
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formSubsistema" class="gestor-label">Subsistema <span class="text-danger">*</span></label>
                                    <select class="gestor-input form-select" id="formSubsistema" onchange="onFormSubsistemaChange()">
                                        <option value="">Seleccione un subsistema...</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formModulo" class="gestor-label">Módulo <span class="text-danger">*</span></label>
                                    <select class="gestor-input form-select" id="formModulo" onchange="onFormModuloChange()">
                                        <option value="">Seleccione un módulo...</option>
                                    </select>
                                    <div class="form-text" id="movimientoAviso" style="display:none;"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formRuta" class="gestor-label">Ruta (archivo PHP)</label>
                                    <input type="text" class="gestor-input form-control" id="formRuta" maxlength="150"
                                           placeholder="mi_formulario_n.php">
                                    <div class="form-text">Nombre del archivo PHP. Dejar vacio si aun no existe.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formImagen" class="gestor-label">Icono (SVG)</label>
                                    <input type="file" class="gestor-input form-control" id="formImagen"
                                           accept=".svg" style="padding:0.375rem 0.75rem;">
                                    <div class="form-text" id="imagenRutaHint">
                                        Subir archivo SVG. Se guardara en <code>assets/img/formularios/</code>
                                    </div>
                                    <div id="imagenActual" class="mt-1 small text-muted" style="display:none;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Seccion 3: Apariencia -->
                        <div class="gestor-form-section gestor-form-section--alt">
                            <div class="gestor-section-title">
                                <i class="bi bi-palette"></i>Apariencia
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-3 mb-3">
                                    <label for="formOrden" class="gestor-label">Orden</label>
                                    <input type="number" class="gestor-input form-control" id="formOrden" value="0" min="0" max="999">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="formColor" class="gestor-label">Color</label>
                                    <input type="color" class="gestor-input form-control form-control-color" id="formColor" value="#003876">
                                </div>
                            </div>
                        </div>

                        <!-- Seccion 4: Permisos -->
                        <div class="gestor-form-section">
                            <div class="gestor-section-title">
                                <i class="bi bi-shield-check"></i>Permisos (Acciones Disponibles)
                            </div>

                            <div class="mb-3">
                                <button type="button" class="gestor-btn-primary btn-sm me-2" onclick="seleccionarTodasAcciones(true)">
                                    <i class="bi bi-check-all"></i> Seleccionar todas
                                </button>
                                <button type="button" class="gestor-btn-secondary btn-sm" onclick="seleccionarTodasAcciones(false)">
                                    <i class="bi bi-x"></i> Limpiar
                                </button>
                            </div>

                            <div class="row g-2 gy-1 mb-0" id="accionesCheckboxContainer">
                                <!-- Se llena dinamicamente desde JS -->
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-primary" onclick="guardarFormulario()">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
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
    let modales = {};
    let subsistemasData = [];
    let modulosData = [];
    let formulariosData = [];
    let accionesData = [];
    let accionesPorFormulario = {};

    // Modulo actual en edicion (para detectar movimientos)
    let moduloOriginal = 0;

    // Colores para cada tipo de accion
    const COLORES_ACCION = {
        1:  '#0d6efd',   // ver - azul
        2:  '#198754',   // crear - verde
        3:  '#fd7e14',   // editar - naranja
        4:  '#dc3545',   // eliminar - rojo
        5:  '#0dcaf0',   // exportar - cian
        6:  '#6f42c1',   // importar - purpura
        7:  '#1e7e34',   // aprobar - verde oscuro
        8:  '#20c997',   // asignar - teal
        9:  '#6c757d',   // cerrar - gris
        10: '#ffc107',   // escalar - amarillo
        11: '#d63384'    // auditar - rosa
    };

    // ============================================================
    // UTILIDADES
    // ============================================================

    function escapeHtml(texto) {
        if (!texto) return '';
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Convierte el nombre de un modulo a formato camelCase para usarlo como subcarpeta.
     * Misma logica que nombreModuloACarpeta() en PHP.
     */
    function nombreModuloACarpetaJS(nombre) {
        if (!nombre) return 'sinModulo';
        var s = nombre.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        s = s.replace(/\b(de|del|la|las|los|el|un|una|y)\b/gi, '');
        var palabras = s.trim().split(/\s+/).filter(function(w) { return w.length > 0; });
        if (palabras.length === 0) return 'sinModulo';
        var resultado = '';
        palabras.forEach(function(p, i) {
            p = p.toLowerCase();
            resultado += (i === 0) ? p : p.charAt(0).toUpperCase() + p.slice(1);
        });
        return resultado || 'sinModulo';
    }

    function rutaHtml(f) {
        return f.ruta
            ? '<code>' + escapeHtml(f.ruta) + '</code>'
            : '<span class="badge bg-warning text-dark">Pendiente</span>';
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

    // ============================================================
    // CARGA DE DATOS
    // ============================================================

    async function cargarDatos() {
        try {
            const resp = await fetch('sql/gestor_formularios.php');
            const data = await resp.json();

            if (!data.success) throw new Error(data.message || 'Error al cargar datos');

            subsistemasData = data.subsistemas || [];
            modulosData = data.modulos || [];
            formulariosData = data.formularios || [];
            accionesData = data.acciones || [];
            accionesPorFormulario = data.acciones_por_formulario || {};

            llenarSelectSubsistemas();
            llenarSelectModulos();
            llenarSelectSubsistemaModal();
            llenarAccionesCheckbox();
            renderizarFormularios();

        } catch (e) {
            mostrarError('Error al cargar datos: ' + e.message);
        }
    }

    function llenarSelectSubsistemas() {
        const select = document.getElementById('filtroSubsistema');
        select.innerHTML = '<option value="">Todos los subsistemas</option>';
        subsistemasData.forEach(function(s) {
            const op = document.createElement('option');
            op.value = s.id;
            op.textContent = s.nombre;
            select.appendChild(op);
        });
    }

    function llenarSelectModulos(filtroSubsistemaId) {
        const select = document.getElementById('filtroModulo');
        select.innerHTML = '<option value="">Todos los modulos</option>';

        let modulosFiltrados = modulosData;
        if (filtroSubsistemaId) {
            modulosFiltrados = modulosData.filter(function(m) {
                return parseInt(m.subsistema_id) === parseInt(filtroSubsistemaId);
            });
        }

        modulosFiltrados.forEach(function(m) {
            const op = document.createElement('option');
            op.value = m.id;
            op.textContent = m.nombre;
            select.appendChild(op);
        });
    }

    // ============================================================
    // SELECTORES DE UBICACIÓN (modal) - Subsistema / Módulo
    // ============================================================

    function llenarSelectSubsistemaModal() {
        const select = document.getElementById('formSubsistema');
        select.innerHTML = '<option value="">Seleccione un subsistema...</option>';
        subsistemasData.forEach(function(s) {
            const op = document.createElement('option');
            op.value = s.id;
            op.textContent = s.nombre;
            select.appendChild(op);
        });
    }

    function llenarSelectModuloModal(subsistemaId) {
        const select = document.getElementById('formModulo');
        select.innerHTML = '<option value="">Seleccione un módulo...</option>';

        if (!subsistemaId) return;

        const modulosFiltrados = modulosData.filter(function(m) {
            return parseInt(m.subsistema_id) === parseInt(subsistemaId);
        });
        modulosFiltrados.forEach(function(m) {
            const op = document.createElement('option');
            op.value = m.id;
            op.textContent = m.nombre;
            select.appendChild(op);
        });
    }

    function onFormSubsistemaChange() {
        const subsistemaId = document.getElementById('formSubsistema').value;
        llenarSelectModuloModal(subsistemaId);
        actualizarIndicadoresUbicacion();
    }

    function onFormModuloChange() {
        actualizarIndicadoresUbicacion();
    }

    // Actualiza banner, hint del icono y aviso de movimiento segun los selects
    function actualizarIndicadoresUbicacion() {
        const subsistemaId = parseInt(document.getElementById('formSubsistema').value) || 0;
        const moduloId = parseInt(document.getElementById('formModulo').value) || 0;

        const subsistemaSel = subsistemasData.find(function(s) { return parseInt(s.id) === subsistemaId; });
        const moduloSel = modulosData.find(function(m) { return parseInt(m.id) === moduloId; });

        // Banner
        const moduloDisplayText = document.getElementById('moduloDisplayText');
        const moduloBanner = document.getElementById('moduloBanner');
        if (moduloSel) {
            let txt = moduloSel.nombre;
            if (subsistemaSel) txt = subsistemaSel.nombre + ' / ' + moduloSel.nombre;
            moduloDisplayText.textContent = txt;
            moduloBanner.style.display = 'flex';
        } else {
            moduloDisplayText.textContent = 'Modulo #' + moduloId;
            moduloBanner.style.display = moduloId ? 'flex' : 'none';
        }

        // Hint del icono
        const hintRuta = document.getElementById('imagenRutaHint');
        if (moduloSel) {
            const carpeta = nombreModuloACarpetaJS(moduloSel.nombre);
            hintRuta.innerHTML = 'Se guardara en <code>assets/img/formularios/' + escapeHtml(carpeta) + '/</code>';
        } else {
            hintRuta.innerHTML = 'Se guardara en <code>assets/img/formularios/</code>';
        }

        // Aviso de movimiento (solo edicion)
        const aviso = document.getElementById('movimientoAviso');
        const idEdit = parseInt(document.getElementById('editFormularioId').value) || 0;
        if (idEdit > 0 && moduloId > 0 && moduloId !== moduloOriginal) {
            aviso.textContent = 'Se moverá a: ' + (subsistemaSel ? subsistemaSel.nombre + ' / ' + (moduloSel ? moduloSel.nombre : '?') : '?');
            aviso.style.display = 'block';
        } else {
            aviso.style.display = 'none';
        }
    }

    function llenarAccionesCheckbox() {
        const container = document.getElementById('accionesCheckboxContainer');
        container.innerHTML = '';

        accionesData.forEach(function(a) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';

            const color = COLORES_ACCION[a.id] || '#6c757d';
            const card = document.createElement('div');
            card.className = 'gestor-accion-card';
            card.innerHTML =
                '<div class="form-check">' +
                    '<input class="form-check-input accion-checkbox" type="checkbox" ' +
                        'id="accion_' + a.id + '" value="' + a.id + '" ' +
                        'style="border-color: ' + color + ';">' +
                '</div>' +
                '<label class="form-check-label" for="accion_' + a.id + '">' +
                    '<span class="badge" style="background:' + color + '; font-size:0.68rem; padding:3px 7px; border-radius:4px;">' + escapeHtml(a.nombre) + '</span>' +
                '</label>';
            col.appendChild(card);
            container.appendChild(col);
        });
    }

    // ============================================================
    // FILTROS
    // ============================================================

    function onCambioSubsistema() {
        const subsistemaId = document.getElementById('filtroSubsistema').value;
        llenarSelectModulos(subsistemaId);
        onCambioModulo();
    }

    function onCambioModulo() {
        renderizarFormularios();
    }

    function onBuscarFormulario() {
        renderizarFormularios();
    }

    // ============================================================
    // RENDERIZADO DE FORMULARIOS
    // ============================================================

    function renderizarFormularios() {
        const tbody = document.getElementById('tbodyFormularios');
        tbody.innerHTML = '';

        const subsistemaId = document.getElementById('filtroSubsistema').value;
        const moduloId = document.getElementById('filtroModulo').value;
        const busqueda = document.getElementById('buscarFormulario').value.toLowerCase().trim();

        let filtrados = formulariosData;

        if (busqueda) {
            filtrados = filtrados.filter(function(f) {
                if (f.nombre.toLowerCase().indexOf(busqueda) !== -1) return true;
                const mod = modulosData.find(function(m) { return parseInt(m.id) === parseInt(f.modulo_id); });
                if (mod && mod.nombre.toLowerCase().indexOf(busqueda) !== -1) return true;
                return false;
            });
        } else if (moduloId) {
            filtrados = filtrados.filter(function(f) {
                return parseInt(f.modulo_id) === parseInt(moduloId);
            });
        } else if (subsistemaId) {
            const modulosDelSubsistema = modulosData
                .filter(function(m) { return parseInt(m.subsistema_id) === parseInt(subsistemaId); })
                .map(function(m) { return m.id; });
            filtrados = filtrados.filter(function(f) {
                return modulosDelSubsistema.indexOf(parseInt(f.modulo_id)) !== -1;
            });
        }

        document.getElementById('contadorFormularios').textContent = filtrados.length;

        if (filtrados.length === 0) {
            const msgBusqueda = busqueda
                ? 'No se encontraron formularios para "' + escapeHtml(busqueda) + '"'
                : 'No hay formularios';
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">' + msgBusqueda + '</td></tr>';
            return;
        }

        filtrados.forEach(function(f) {
            const activo = parseInt(f.eliminado) === 0;
            const tr = document.createElement('tr');
            tr.className = activo ? '' : 'table-danger';

            // Badges de permisos
            const accionesForm = accionesPorFormulario[f.id] || [];
            let permisosHtml = '';
            if (accionesForm.length === 0) {
                permisosHtml = '<span class="text-muted">—</span>';
            } else {
                const badges = accionesForm.map(function(aid) {
                    const accion = accionesData.find(function(a) { return parseInt(a.id) === parseInt(aid); });
                    const nombre = accion ? accion.nombre : '?';
                    const color = COLORES_ACCION[aid] || '#6c757d';
                    return '<span class="badge me-1" style="background:' + color + ';">' + escapeHtml(nombre) + '</span>';
                });
                permisosHtml = badges.join('');
            }

            tr.innerHTML =
                '<td class="td-nombre"><strong>' + escapeHtml(f.nombre) + '</strong></td>' +
                '<td class="text-muted small text-truncate td-descripcion" title="' + escapeHtml(f.descripcion || '') + '">' + escapeHtml(f.descripcion || '') + '</td>' +
                '<td class="td-ruta">' + rutaHtml(f) + '</td>' +
                '<td class="td-permisos">' + permisosHtml + '</td>' +
                '<td class="td-estado">' +
                    (activo
                        ? '<span class="badge bg-success">● Activo</span>'
                        : '<span class="badge bg-secondary">○ Inactivo</span>') +
                '</td>' +
                '<td class="td-acciones">' +
                    '<button class="btn btn-sm btn-outline-primary me-1" onclick="abrirModalFormulario(' + f.id + ')" title="Editar formulario">' +
                        '<i class="bi bi-pencil"></i>' +
                    '</button>' +
                    '<button class="btn btn-sm ' + (activo ? 'btn-outline-danger' : 'btn-outline-success') + '" ' +
                        'onclick="toggleFormulario(' + f.id + ', ' + (!activo) + ')" title="' + (activo ? 'Desactivar formulario' : 'Reactivar formulario') + '">' +
                        '<i class="bi ' + (activo ? 'bi-x-circle' : 'bi-arrow-counterclockwise') + '"></i> ' +
                        (activo ? 'Desactivar' : 'Reactivar') +
                    '</button>' +
                '</td>';

            tbody.appendChild(tr);
        });
    }

    // ============================================================
    // CRUD: FORMULARIOS
    // ============================================================

    function seleccionarTodasAcciones(seleccionar) {
        const checkboxes = document.querySelectorAll('.accion-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = seleccionar;
        });
    }

    function abrirModalFormulario(id) {
        // Si es nuevo, validar que subsistema y modulo esten seleccionados
        if (!id) {
            const subsistemaVal = document.getElementById('filtroSubsistema').value;
            const moduloVal = document.getElementById('filtroModulo').value;
            if (!subsistemaVal || !moduloVal) {
                mostrarError('Debe seleccionar un subsistema y un modulo');
                return;
            }
        }

        document.getElementById('modalFormularioTitulo').textContent = id ? 'Editar Formulario' : 'Nuevo Formulario';
        document.getElementById('editFormularioId').value = id || 0;

        // Reset checkboxes
        document.querySelectorAll('.accion-checkbox').forEach(function(cb) { cb.checked = false; });

        // Limpiar file input
        document.getElementById('formImagen').value = '';
        document.getElementById('imagenActual').style.display = 'none';

        // Reset selects de ubicacion
        document.getElementById('formSubsistema').value = '';
        llenarSelectModuloModal('');

        const moduloBanner = document.getElementById('moduloBanner');
        const moduloDisplayText = document.getElementById('moduloDisplayText');

        if (id) {
            const f = formulariosData.find(function(item) { return parseInt(item.id) === parseInt(id); });
            if (f) {
                document.getElementById('formNombre').value = f.nombre || '';
                document.getElementById('formDescripcion').value = f.descripcion || '';
                document.getElementById('formRuta').value = f.ruta || '';
                document.getElementById('formOrden').value = f.orden || 0;
                document.getElementById('formColor').value = f.color || '#003876';

                // Guardar modulo original para detectar movimiento
                moduloOriginal = parseInt(f.modulo_id) || 0;

                // Preseleccionar subsistema y modulo actuales
                const mod = modulosData.find(function(m) { return parseInt(m.id) === moduloOriginal; });
                if (mod) {
                    document.getElementById('formSubsistema').value = mod.subsistema_id;
                    llenarSelectModuloModal(mod.subsistema_id);
                    document.getElementById('formModulo').value = moduloOriginal;
                }
                actualizarIndicadoresUbicacion();

                // Mostrar imagen actual si existe
                if (f.imagen) {
                    const imgDiv = document.getElementById('imagenActual');
                    imgDiv.textContent = 'Actual: ' + f.imagen;
                    imgDiv.style.display = 'block';
                }

                // Marcar checkboxes de las acciones asignadas
                const accionesForm = accionesPorFormulario[f.id] || [];
                accionesForm.forEach(function(aid) {
                    const cb = document.getElementById('accion_' + aid);
                    if (cb) cb.checked = true;
                });
            }
        } else {
            document.getElementById('formNombre').value = '';
            document.getElementById('formDescripcion').value = '';
            document.getElementById('formRuta').value = '';
            document.getElementById('formOrden').value = '0';
            document.getElementById('formColor').value = '#003876';

            // Tomar subsistema y modulo seleccionados en el filtro
            const filtroSubsistema = document.getElementById('filtroSubsistema');
            const filtroModulo = document.getElementById('filtroModulo');
            const selectedSubsistema = filtroSubsistema.value;
            const selectedId = filtroModulo.value;
            if (selectedSubsistema) {
                document.getElementById('formSubsistema').value = selectedSubsistema;
                llenarSelectModuloModal(selectedSubsistema);
                if (selectedId) {
                    document.getElementById('formModulo').value = selectedId;
                }
            }
            moduloOriginal = 0;
            actualizarIndicadoresUbicacion();
        }

        mostrarModal('modalFormulario');
    }

    async function guardarFormulario() {
        const id = parseInt(document.getElementById('editFormularioId').value) || 0;
        const nombre = document.getElementById('formNombre').value.trim();
        const descripcion = document.getElementById('formDescripcion').value.trim();
        const subsistema_id = parseInt(document.getElementById('formSubsistema').value) || 0;
        const modulo_id = parseInt(document.getElementById('formModulo').value) || 0;
        const ruta = document.getElementById('formRuta').value.trim();
        const orden = parseInt(document.getElementById('formOrden').value) || 0;
        const color = document.getElementById('formColor').value.trim();

        // Obtener IDs de acciones seleccionadas
        const acciones = [];
        document.querySelectorAll('.accion-checkbox:checked').forEach(function(cb) {
            acciones.push(parseInt(cb.value));
        });

        if (!nombre) { mostrarError('El nombre del formulario es obligatorio'); return; }
        if (!subsistema_id) { mostrarError('Debe seleccionar un subsistema'); return; }
        if (!modulo_id) { mostrarError('Debe seleccionar un modulo'); return; }

        const formData = new FormData();
        formData.append('action', id ? 'editar' : 'crear');
        formData.append('subsistema_id', subsistema_id);
        formData.append('modulo_id', modulo_id);
        const mod = modulosData.find(function(m) { return parseInt(m.id) === modulo_id; });
        formData.append('modulo_nombre', mod ? mod.nombre : '');
        formData.append('nombre', nombre);
        formData.append('descripcion', descripcion);
        formData.append('ruta', ruta);
        formData.append('orden', orden);
        formData.append('color', color);
        formData.append('acciones', JSON.stringify(acciones));
        if (id) formData.append('id', id);

        const fileInput = document.getElementById('formImagen');
        if (fileInput.files.length > 0) {
            formData.append('imagen', fileInput.files[0]);
        }

        const ejecutarGuardado = async function() {
            try {
                const resp = await fetch('actualizar_gestor_formularios_n.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                if (data.success) {
                    ocultarModal('modalFormulario');
                    mostrarExito(data.message);
                    cargarDatos();
                } else {
                    mostrarError(data.message);
                }
            } catch (e) {
                mostrarError('Error de conexion: ' + e.message);
            }
        };

        // Si es edicion y se movio de modulo, pedir confirmacion (los permisos se mantienen)
        if (id > 0 && modulo_id !== moduloOriginal) {
            const modDestino = modulosData.find(function(m) { return parseInt(m.id) === modulo_id; });
            const subDestino = subsistemasData.find(function(s) { return parseInt(s.id) === subsistema_id; });
            const nombreDestino = (subDestino ? subDestino.nombre : '?') + ' / ' + (modDestino ? modDestino.nombre : '?');
            mostrarConfirmacion(
                'El formulario "' + nombre + '" se moverá a: ' + nombreDestino + '. Los permisos y funcionalidad se mantienen. ¿Continuar?',
                ejecutarGuardado
            );
        } else {
            ejecutarGuardado();
        }
    }

    function toggleFormulario(id, activar) {
        const nombre = formulariosData.find(function(f) { return parseInt(f.id) === parseInt(id); })?.nombre || '';
        const mensaje = activar
            ? '¿Reactivar el formulario "' + nombre + '"?'
            : '¿Desactivar el formulario "' + nombre + '"?';

        mostrarConfirmacion(mensaje, async function() {
            try {
                const resp = await fetch('actualizar_gestor_formularios_n.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle',
                        id: id,
                        active: activar
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    mostrarExito(data.message);
                    cargarDatos();
                } else {
                    mostrarError(data.message);
                }
            } catch (e) {
                mostrarError('Error de conexion: ' + e.message);
            }
        });
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
