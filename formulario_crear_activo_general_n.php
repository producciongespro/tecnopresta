<?php
// formulario_crear_activo_general_n.php (Ubicado en el directorio raíz del servidor)
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

$lognombre = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$logusuario = $usuario_azure['cedula'] ?? null;
$logcodigo = $usuario_azure['codigoPresu'] ?? null;

require_once __DIR__ . '/conexion.php';
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    
    <!-- Framework & Estilos -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">

    <title>Gestión de Tipos de Activos Generales</title>

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
        }

        .main-container {
            flex: 1 0 auto;
        }

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
        
        .btn-flotante-regresar:active {
            transform: scale(0.95);
        }
        
        .btn-flotante-regresar i { 
            font-size: 30px;
            color: #fff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* Tarjetas ergonómicas modernas */
        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(30, 40, 81, 0.08);
            background: #fff;
            transition: box-shadow 0.25s ease;
        }

        .card-custom:hover {
            box-shadow: 0 12px 30px rgba(30, 40, 81, 0.12);
        }

        .card-header-custom {
            background-color: var(--mep-azul);
            color: #fff;
            border-top-left-radius: 14px !important;
            border-top-right-radius: 14px !important;
            padding: 16px 22px;
        }

        /* Estandarización de tamaño de imágenes de activos */
        .img-thumb-container {
            width: 68px;
            height: 68px;
            min-width: 68px;
            min-height: 68px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.04);
        }

        .img-thumb-container img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .img-preview-box {
            width: 110px;
            height: 110px;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 auto;
            transition: border-color 0.2s ease;
        }

        .img-preview-box img {
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
        }

        /* Buscador ergonómico */
        .search-group {
            position: relative;
        }

        .search-group i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
        }

        .search-group input {
            padding-left: 42px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            height: 44px;
        }

        .search-group input:focus {
            border-color: var(--mep-azul);
            box-shadow: 0 0 0 3px rgba(30, 40, 81, 0.15);
        }

        /* Tabla con scroll fluido */
        .table-responsive-custom {
            max-height: 520px;
            overflow-y: auto;
            border-radius: 10px;
        }

        .table-responsive-custom table {
            margin-bottom: 0;
        }

        .table-responsive-custom thead th {
            position: sticky;
            top: 0;
            background-color: #f1f5f9;
            color: var(--mep-azul);
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            z-index: 2;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        /* Toast de notificación */
        .toast-container-custom {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1080;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>

<div class="toast-container toast-container-custom" id="toastContainer"></div>

<div class="container py-4 main-container">
    
    <!-- Encabezado -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-primary-emphasis mb-1">
                <i class="bi bi-boxes me-2" style="color: var(--mep-azul);"></i>Catálogo de Activos Generales
            </h2>
            <p class="text-muted mb-0 small">
                <i class="bi bi-person-badge me-1"></i> Sesión: <strong><?php echo htmlspecialchars($lognombre); ?></strong> 
                <?php if ($logcodigo): ?> | Código: <span class="badge bg-secondary"><?php echo htmlspecialchars($logcodigo); ?></span><?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">

        </div>
    </div>

    <div class="row g-4">
        
        <!-- Formulario de Registro Rápido -->
        <div class="col-lg-5">
            <div class="card card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-plus-circle-fill me-2"></i>Registrar Clase de Activo</span>
                    <span class="badge bg-light text-dark small">v1.1</span>
                </div>
                <div class="card-body p-4">
                    <form id="formCrearActivo" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="accion" value="guardar">
                        
                        <div class="mb-3">
                            <label for="inputClase" class="form-label fw-semibold text-secondary small text-uppercase">
                                Clase de Activo <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="inputClase" 
                                   name="clase" 
                                   required 
                                   maxlength="100" 
                                   pattern="[A-Za-z0-9áéíóúÁÉÍÓÚñÑ\.\-_ ]+" 
                                   placeholder="Ej: Computadora Portátil, Proyector...">
                            <div class="form-text small text-muted">
                                Antes de registrar, verifique que la clase no exista en la lista.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="inputImagen" class="form-label fw-semibold text-secondary small text-uppercase">
                                Imagen Ilustrativa (PNG - Máx 180 KB) <span class="text-danger">*</span>
                            </label>
                            <input type="file" 
                                   class="form-control" 
                                   id="inputImagen" 
                                   name="imagen" 
                                   accept="image/png" 
                                   required>
                        </div>

                        <!-- Previsualización Estandarizada -->
                        <div class="mb-4 text-center">
                            <label class="form-label fw-semibold text-secondary small text-uppercase d-block">Previsualización</label>
                            <div class="img-preview-box" id="boxPrevisualizar">
                                <span class="text-muted small px-2 text-center" id="textoPrevisualizar">
                                    <i class="bi bi-image fs-3 d-block text-secondary"></i>Sin imagen
                                </span>
                                <img id="imgPrevisualizar" src="" alt="Previsualización" class="d-none">
                            </div>
                            <small class="text-muted mt-1 d-block font-monospace">Tamaño normalizado automático (80px alto)</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold" id="btnGuardarActivo" style="background-color: var(--mep-azul); border-color: var(--mep-azul);">
                                <i class="bi bi-cloud-arrow-up-fill me-2"></i>Guardar Activo General
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Listado y Búsqueda Interactiva -->
        <div class="col-lg-7">
            <div class="card card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-collection-fill me-2"></i>Clases Registradas</span>
                    <span class="badge bg-warning text-dark" id="contadorActivos">0 registros</span>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Campo de búsqueda en vivo -->
                    <div class="search-group mb-3">
                        <i class="bi bi-search"></i>
                        <input type="text" id="buscadorActivos" class="form-control" placeholder="Buscar por clase de activo...">
                    </div>

                    <!-- Tabla de resultados -->
                    <div class="table-responsive table-responsive-custom border">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 70px;" class="text-center">ID</th>
                                    <th>Clase de Activo</th>
                                    <th style="width: 100px;" class="text-center">Imagen</th>
                                    <th style="width: 110px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaActivosCuerpo">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Cargando catálogo de activos...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                </div>
            </div>
        </div>

    </div>

    <!-- Botón flotante para regresar -->
    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=4&modulo_id=19" class="btn-flotante-regresar" title="Volver al panel general">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

</div>

<!-- Modal Ergonómico para Edición de Activo General -->
<div class="modal fade" id="modalEditarActivo" tabindex="-1" aria-labelledby="modalEditarActivoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header text-white" style="background-color: var(--mep-azul); border-top-left-radius: 14px; border-top-right-radius: 14px;">
                <h5 class="modal-title fw-bold" id="modalEditarActivoLabel">
                    <i class="bi bi-pencil-square me-2"></i>Editar Clase de Activo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarActivo" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="accion" value="actualizar">
                    <input type="hidden" name="id_ag" id="editIdAg">

                    <div class="mb-3">
                        <label for="editClase" class="form-label fw-semibold text-secondary small text-uppercase">Clase de Activo</label>
                        <input type="text" class="form-control" id="editClase" name="clase" required maxlength="100">
                        <div class="form-text small">Use este apartado para corregir errores de ortografía o redacción.</div>
                    </div>

                    <div class="mb-3">
                        <label for="editImagen" class="form-label fw-semibold text-secondary small text-uppercase">Cambiar Imagen (Opcional - Solo PNG)</label>
                        <input type="file" class="form-control" id="editImagen" name="imagen" accept="image/png">
                        <div class="form-text small text-muted">Si no desea cambiar la imagen actual, deje este campo vacío.</div>
                    </div>

                    <div class="text-center my-2">
                        <span class="text-secondary small fw-semibold d-block mb-1">Imagen Actual / Previsualización</span>
                        <div class="img-preview-box mx-auto">
                            <img id="imgEditPreview" src="" alt="Imagen actual">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnActualizarActivo" style="background-color: var(--mep-azul); border-color: var(--mep-azul);">
                        <i class="bi bi-check2-circle me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>

<!-- Scripts -->
<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

<script>
    // Endpoint AJAX ubicado en /ajax/ en la raíz del servidor
    const ENDPOINT_AJAX = 'ajax/activo_general_acciones_n.php';

    let catalogoActivos = [];
    let modalEditar = null;

    document.addEventListener('DOMContentLoaded', () => {
        modalEditar = new bootstrap.Modal(document.getElementById('modalEditarActivo'));
        cargarActivos();
        configurarEventos();
    });

    // Mostrar notificaciones Toast
    function notificar(mensaje, tipo = 'success') {
        const id = 'toast_' + Date.now();
        const icon = tipo === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        const bgClass = tipo === 'success' ? 'bg-success text-white' : 'bg-danger text-white';

        const toastHtml = `
            <div id="${id}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="bi ${icon} fs-5 me-2"></i>
                        <span>${mensaje}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        `;
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    // Cargar catálogo de activos vía Fetch
    async function cargarActivos() {
        const tbody = document.getElementById('tablaActivosCuerpo');
        try {
            const resp = await fetch(`${ENDPOINT_AJAX}?accion=listar`);
            const json = await resp.json();

            if (!json.success) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger"><i class="bi bi-x-circle me-1"></i>${json.message || 'Error al cargar datos'}</td></tr>`;
                return;
            }

            catalogoActivos = json.data || [];
            renderizarTabla(catalogoActivos);
        } catch (error) {
            console.error('Error al obtener activos:', error);
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger"><i class="bi bi-wifi-off me-1"></i>No fue posible conectar con el servidor.</td></tr>`;
        }
    }

    // Renderizar tabla de activos
    function renderizarTabla(lista) {
        const tbody = document.getElementById('tablaActivosCuerpo');
        const contador = document.getElementById('contadorActivos');
        contador.textContent = `${lista.length} ${lista.length === 1 ? 'registro' : 'registros'}`;

        if (lista.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-inbox me-1"></i>No se encontraron activos generales registrados.</td></tr>`;
            return;
        }

        let html = '';
        lista.forEach(a => {
            const rutaImg = a.imagen ? `img/${a.imagen}` : 'img/default.png';
            html += `
                <tr data-id="${a.id_ag}">
                    <td class="text-center text-muted fw-bold small">${a.id_ag}</td>
                    <td class="fw-semibold text-dark">${escaparHtml(a.clase)}</td>
                    <td class="text-center">
                        <div class="img-thumb-container mx-auto">
                            <img src="${rutaImg}" alt="${escaparHtml(a.clase)}" onerror="this.src='img/default.png';">
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-primary btn-action" title="Editar clase" onclick="abrirModalEditar(${a.id_ag})">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-action" title="Eliminar clase" onclick="eliminarActivo(${a.id_ag}, '${escaparHtml(a.clase)}')">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    // Configuración de eventos
    function configurarEventos() {
        const inputImagen = document.getElementById('inputImagen');
        const imgPrevisualizar = document.getElementById('imgPrevisualizar');
        const textoPrevisualizar = document.getElementById('textoPrevisualizar');

        inputImagen.addEventListener('change', (e) => {
            const archivo = e.target.files[0];
            if (archivo) {
                if (archivo.type !== 'image/png') {
                    notificar('Solo se permiten imágenes en formato PNG', 'danger');
                    inputImagen.value = '';
                    imgPrevisualizar.classList.add('d-none');
                    textoPrevisualizar.classList.remove('d-none');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (evt) => {
                    imgPrevisualizar.src = evt.target.result;
                    imgPrevisualizar.classList.remove('d-none');
                    textoPrevisualizar.classList.add('d-none');
                };
                reader.readAsDataURL(archivo);
            } else {
                imgPrevisualizar.classList.add('d-none');
                textoPrevisualizar.classList.remove('d-none');
            }
        });

        const editImagen = document.getElementById('editImagen');
        const imgEditPreview = document.getElementById('imgEditPreview');
        editImagen.addEventListener('change', (e) => {
            const archivo = e.target.files[0];
            if (archivo) {
                if (archivo.type !== 'image/png') {
                    notificar('Solo se permiten imágenes en formato PNG', 'danger');
                    editImagen.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (evt) => {
                    imgEditPreview.src = evt.target.result;
                };
                reader.readAsDataURL(archivo);
            }
        });

        // Formulario Crear Activo General
        const formCrear = document.getElementById('formCrearActivo');
        const btnGuardar = document.getElementById('btnGuardarActivo');

        formCrear.addEventListener('submit', async (e) => {
            e.preventDefault();
            const inputClase = document.getElementById('inputClase');
            if (!inputClase.value.trim() || !inputImagen.files[0]) {
                notificar('Complete la clase de activo y seleccione una imagen PNG.', 'danger');
                return;
            }

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

            const formData = new FormData(formCrear);

            try {
                const resp = await fetch(ENDPOINT_AJAX, {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    notificar(res.message || 'Clase de activo registrada.');
                    formCrear.reset();
                    imgPrevisualizar.classList.add('d-none');
                    textoPrevisualizar.classList.remove('d-none');
                    cargarActivos();
                } else {
                    notificar(res.message || 'Error al guardar', 'danger');
                }
            } catch (err) {
                notificar('Error de comunicación con el servidor', 'danger');
            } finally {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Guardar Activo General';
            }
        });

        // Formulario Editar Activo General
        const formEditar = document.getElementById('formEditarActivo');
        const btnActualizar = document.getElementById('btnActualizarActivo');

        formEditar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formEditar);

            btnActualizar.disabled = true;
            btnActualizar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Actualizando...';

            try {
                const resp = await fetch(ENDPOINT_AJAX, {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    notificar(res.message || 'Clase actualizada con éxito.');
                    modalEditar.hide();
                    cargarActivos();
                } else {
                    notificar(res.message || 'Error al actualizar', 'danger');
                }
            } catch (err) {
                notificar('Error al actualizar registro', 'danger');
            } finally {
                btnActualizar.disabled = false;
                btnActualizar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar Cambios';
            }
        });

        // Filtro de búsqueda en tiempo real
        const buscador = document.getElementById('buscadorActivos');
        buscador.addEventListener('input', (e) => {
            const termino = e.target.value.toLowerCase().trim();
            if (!termino) {
                renderizarTabla(catalogoActivos);
                return;
            }
            const filtrados = catalogoActivos.filter(a => a.clase.toLowerCase().includes(termino));
            renderizarTabla(filtrados);
        });
    }

    // Modal de edición
    async function abrirModalEditar(idAg) {
        try {
            const resp = await fetch(`${ENDPOINT_AJAX}?accion=obtener&id_ag=${idAg}`);
            const res = await resp.json();

            if (res.success && res.data) {
                document.getElementById('editIdAg').value = res.data.id_ag;
                document.getElementById('editClase').value = res.data.clase;
                document.getElementById('editImagen').value = '';
                document.getElementById('imgEditPreview').src = res.data.imagen ? `img/${res.data.imagen}` : 'img/default.png';
                modalEditar.show();
            } else {
                notificar(res.message || 'No se pudo obtener información del activo general', 'danger');
            }
        } catch (error) {
            notificar('Error al consultar datos', 'danger');
        }
    }

    // Eliminar Activo General
    async function eliminarActivo(idAg, nombreClase) {
        if (!confirm(`¿Está seguro de eliminar la clase de activo "${nombreClase}"?\nEsta acción no se puede deshacer si no cuenta con activos asociados en inventario.`)) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id_ag', idAg);

            const resp = await fetch(ENDPOINT_AJAX, {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();

            if (res.success) {
                notificar(res.message || 'Activo general eliminado satisfactoriamente.');
                cargarActivos();
            } else {
                alert(res.message || 'No se puede eliminar la clase de activo.');
            }
        } catch (error) {
            notificar('Error de conexión al intentar eliminar', 'danger');
        }
    }

    function escaparHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto || '';
        return div.innerHTML;
    }
</script>

</body>
</html>
