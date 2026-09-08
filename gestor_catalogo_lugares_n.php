<?php
// ============================================================
// FORMULARIO: Gestion de Lugares (Catalogo Maestro)
// ============================================================
// Proposito: CRUD del catalogo de lugares fisicos (t_lugar)
// donde se ubican los activos.
//
// Reglas de negocio:
//   - Eliminado logico: desactivar (activo = 0).
//   - Para desactivar un lugar no debe haber placas (activos)
//     asociadas a el.
//   - Unicidad del nombre ignorando mayusculas y tildes.
//
// Acceso: Usuarios con permiso sobre la tarjeta "Gestion de
// Ubicacion" (Catálogos). Root tiene acceso total.
//
// Dependencias:
//   - navegar.php (define ACCESO_SEGURO)
//   - auth.php (validacion de sesion y roles)
//   - usuarioAzure.php (datos de sesion Azure AD)
//   - sql/gestor_catalogo_lugares_n.php (endpoint datos)
//   - actualizar_gestor_catalogo_lugares_n.php (endpoint accion)
// ============================================================

// Verificar acceso seguro desde navegar.php
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    die('Acceso directo no permitido');
}

// Iniciar sesion si no existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar sesion
require_once __DIR__ . '/usuarioAzure.php';
require_once __DIR__ . '/auth.php';

$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header('Location: index.html');
    exit;
}

// Validar permiso de la tarjeta
validarPermisoRuta('gestor_catalogo_lugares_n.php');

// Construir ruta de regreso
$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
        . '&subsistema_id=' . intval($_GET['subsistema_id'])
        . '&modulo_id=' . intval($_GET['modulo_id']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogos - Gestión de Ubicación</title>

    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Estilos institucionales MEP -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=8">
    <!-- Estilos del menu principal y tablas MEP -->
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=8">

    <style>
        /* Respaldo: garantiza visibilidad de la tabla en cualquier tema */
        #tablaLugares,
        #tablaLugares tbody,
        #tablaLugares td,
        #tablaLugares th {
            color: #2c3e50;
        }
        #tablaLugares thead tr {
            background: linear-gradient(135deg, #192952, #0035A0);
            color: #fff;
        }
        #tablaLugares thead th {
            color: #fff;
        }
        #tablaLugares tbody tr {
            background: #fff;
        }
        /* Efecto cebra: una fila blanca y la siguiente mas oscura */
        #tablaLugares tbody tr:nth-child(odd) {
            background: #ffffff;
        }
        #tablaLugares tbody tr:nth-child(even) {
            background: #f2f5f9;
        }
        #tablaLugares tbody tr:hover {
            background: #e8eef7;
        }
        /* Filas inactivas conservan el tono rojizo */
        #tablaLugares tbody tr.table-danger:nth-child(odd),
        #tablaLugares tbody tr.table-danger:nth-child(even) {
            background: #fdf6f5;
        }
        #tablaLugares tbody tr.table-danger:hover {
            background: #fbeeec;
        }
        /* Columna Activos Asociados: angosta, texto centrado */
        #tablaLugares th.th-activos {
            width: 100px;
            text-align: center;
        }
        #tablaLugares td.td-activos {
            width: 100px;
            text-align: center;
        }
        /* Columna Acciones: ancha para que los botones quepan en una fila */
        #tablaLugares th.th-acciones,
        #tablaLugares td.td-acciones {
            width: 220px;
            white-space: nowrap;
        }
    </style>
</head>
<body class="layout-page">

    <!-- Header institucional -->
    <?php include 'partials/header.php'; ?>

    <!-- ============================================================
    CONTENIDO PRINCIPAL
    ============================================================ -->
    <main class="container py-4 contenido-principal" id="appCatalogoLugares">

        <!-- Hero-box -->
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-geo-alt" style="font-size: 2.5rem; color: #C8A951;"></i>
                </div>
                <div class="col">
                    <h1 class="mb-0" style="color: #fff; font-weight: 600;">Gestión de Ubicación</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.8);">
                        Lugar o ubicación física de los activos en el centro educativo
                    </p>
                </div>
            </div>
        </div>

        <!-- ============================================================
        SECCION: LUGARES
        ============================================================ -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-geo-alt me-2"></i>Lugares del Catálogo
                </h5>
                <button class="btn btn-mep-primary btn-sm" onclick="abrirModalLugar()">
                    <i class="bi bi-plus-lg"></i> Nuevo Lugar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table activos-table mb-0" id="tablaLugares">
                        <thead>
                            <tr>
                                <th>Lugar</th>
                                <th class="th-activos">Activos Asociados</th>
                                <th class="th-estado">Estado</th>
                                <th class="th-acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyLugares">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Cargando lugares...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Boton volver flotante -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>"
           class="btn-disponibilidad"
           style="bottom: 100px;"
           data-tooltip="Regresar">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>

        <!-- ============================================================
        MODALES
        ============================================================ -->

        <!-- Modal Lugar (Crear/Editar) -->
        <div class="modal fade gestor-overlay" id="modalLugar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered gestor-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalLugarTitulo">
                            <i class="bi bi-geo-alt"></i>Nuevo Lugar
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editLugarId" value="0">
                        <div class="mb-3">
                            <label for="modLugar" class="gestor-label">Lugar <span class="text-danger">*</span></label>
                            <input type="text" class="gestor-input form-control" id="modLugar" maxlength="50" required
                                   placeholder="Ej: Bodega">
                            <div class="form-text text-muted">
                                Máximo 50 caracteres. No se permiten duplicados (sin distinguir mayúsculas ni tildes).
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-primary" onclick="guardarLugar()">
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

    <!-- Footer institucional -->
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
    let lugaresData = [];

    // ============================================================
    // UTILIDADES
    // ============================================================

    /**
     * Escapa caracteres HTML para prevenir XSS
     */
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
     * Muestra un modal Bootstrap 5 por su ID
     */
    function mostrarModal(id) {
        const el = document.getElementById(id);
        if (el) {
            const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
            modal.show();
        }
    }

    /**
     * Oculta un modal Bootstrap 5 por su ID
     */
    function ocultarModal(id) {
        const el = document.getElementById(id);
        if (el) {
            const modal = bootstrap.Modal.getInstance(el);
            if (modal) modal.hide();
        }
    }

    /**
     * Muestra modal de confirmacion con callback
     */
    function mostrarConfirmacion(mensaje, callbackAceptar) {
        document.getElementById('modalConfirmacionTexto').textContent = mensaje;
        const btnConfirmar = document.getElementById('btnConfirmarAccion');
        const nuevaAccion = function() {
            ocultarModal('modalConfirmacion');
            if (typeof callbackAceptar === 'function') callbackAceptar();
            btnConfirmar.removeEventListener('click', nuevaAccion);
        };
        btnConfirmar.addEventListener('click', nuevaAccion);
        mostrarModal('modalConfirmacion');
    }

    /**
     * Muestra modal de exito con mensaje
     */
    function mostrarExito(mensaje) {
        document.getElementById('modalExitoTexto').textContent = mensaje;
        mostrarModal('modalExito');
    }

    /**
     * Muestra modal de error con mensaje
     */
    function mostrarError(mensaje) {
        document.getElementById('modalErrorTexto').textContent = mensaje;
        mostrarModal('modalError');
    }

    // ============================================================
    // CARGA DE DATOS
    // ============================================================

    /**
     * Carga los lugares desde el endpoint
     */
    async function cargarDatos() {
        try {
            const resp = await fetch('sql/gestor_catalogo_lugares_n.php');
            const data = await resp.json();

            if (!data.success) {
                throw new Error(data.message || 'Error al cargar datos');
            }

            lugaresData = data.lugares || [];
            renderizarLugares();
        } catch (e) {
            mostrarError('Error al cargar datos: ' + e.message);
        }
    }

    // ============================================================
    // RENDERIZADO: LUGARES
    // ============================================================

    /**
     * Renderiza la tabla de lugares
     */
    function renderizarLugares() {
        const tbody = document.getElementById('tbodyLugares');
        tbody.innerHTML = '';

        if (lugaresData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay lugares registrados en el catalogo</td></tr>';
            return;
        }

        lugaresData.forEach(function(l) {
            const activo = parseInt(l.activo) === 1;
            const tr = document.createElement('tr');
            tr.className = activo ? '' : 'table-danger';

            tr.innerHTML =
                '<td><strong>' + escapeHtml(l.lugar) + '</strong></td>' +
                '<td class="td-activos">' +
                    (parseInt(l.activos_asociados) > 0
                        ? '<span class="badge bg-info text-dark">' + l.activos_asociados + '</span>'
                        : '<span class="badge bg-success text-white">0</span>') +
                '</td>' +
                '<td class="td-estado">' +
                    (activo
                        ? '<span class="badge bg-success">● Activo</span>'
                        : '<span class="badge bg-secondary">○ Desactivado</span>') +
                '</td>' +
                '<td class="td-acciones">' +
                    '<button class="btn btn-sm btn-outline-primary me-1" onclick="abrirModalLugar(' + l.id_lugar + ')" title="Editar">' +
                        '<i class="bi bi-pencil"></i>' +
                    '</button>' +
                    '<button class="btn btn-sm ' + (activo ? 'btn-outline-danger' : 'btn-outline-success') + '" onclick="toggleLugar(' + l.id_lugar + ', ' + (!activo) + ')" title="' + (activo ? 'Desactivar' : 'Reactivar') + '">' +
                        '<i class="bi ' + (activo ? 'bi-x-circle' : 'bi-arrow-counterclockwise') + '"></i> ' +
                        (activo ? 'Desactivar' : 'Reactivar') +
                    '</button>' +
                '</td>';

            tbody.appendChild(tr);
        });
    }

    // ============================================================
    // CRUD: LUGARES
    // ============================================================

    /**
     * Abre el modal para crear o editar un lugar
     */
    function abrirModalLugar(id) {
        document.getElementById('modalLugarTitulo').textContent = id ? 'Editar Lugar' : 'Nuevo Lugar';
        document.getElementById('editLugarId').value = id || 0;

        if (id) {
            const l = lugaresData.find(function(item) { return parseInt(item.id_lugar) === parseInt(id); });
            if (l) {
                document.getElementById('modLugar').value = l.lugar || '';
            }
        } else {
            document.getElementById('modLugar').value = '';
        }

        mostrarModal('modalLugar');
    }

    /**
     * Guarda (crea o actualiza) un lugar
     */
    async function guardarLugar() {
        const id = parseInt(document.getElementById('editLugarId').value) || 0;
        const lugar = document.getElementById('modLugar').value.trim();

        if (!lugar) {
            mostrarError('El nombre del lugar es obligatorio');
            return;
        }
        if (lugar.length > 50) {
            mostrarError('El nombre del lugar no puede superar 50 caracteres');
            return;
        }

        const formData = new FormData();
        formData.append('action', id ? 'editar_lugar' : 'crear_lugar');
        formData.append('lugar', lugar);
        if (id) formData.append('id', id);

        try {
            const resp = await fetch('actualizar_gestor_catalogo_lugares_n.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.success) {
                ocultarModal('modalLugar');
                mostrarExito(data.message);
                cargarDatos();
            } else {
                mostrarError(data.message);
            }
        } catch (e) {
            mostrarError('Error de conexion: ' + e.message);
        }
    }

    /**
     * Activa o desactiva un lugar (eliminado logico)
     */
    function toggleLugar(id, activar) {
        const nombre = lugaresData.find(function(l) { return parseInt(l.id_lugar) === parseInt(id); })?.lugar || '';
        const mensaje = activar
            ? '¿Reactivar el lugar "' + nombre + '"?'
            : '¿Desactivar el lugar "' + nombre + '"? Solo se permite si no tiene activos asociados.';

        mostrarConfirmacion(mensaje, async function() {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_lugar');
                formData.append('id', id);
                formData.append('active', activar ? '1' : '0');

                const resp = await fetch('actualizar_gestor_catalogo_lugares_n.php', {
                    method: 'POST',
                    body: formData
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