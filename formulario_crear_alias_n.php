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

$lognombre = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$logusuario = $usuario_azure['cedula'] ?? null;
$logcodigo  = $usuario_azure['codigoPresu'] ?? null; 

require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

// Constantes
$activado = 1;
$cero = 0;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">

    <title>TecnoPresta - Gestión de Alias</title>

    <style>
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

        .btn-flotante-guardar {
            position: fixed;
            bottom: 98px;
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
            cursor: pointer;
        }
        
        .btn-flotante-guardar:hover {
            transform: scale(1.1);
            background: #c8ae64ff;
            border-color: #1e2851ff;
            color: #fff;
            box-shadow: 0 6px 25px rgba(30, 40, 81, 0.5);
        }
        
        .btn-flotante-guardar:active {
            transform: scale(0.95);
        }
        
        .btn-flotante-guardar i { 
            font-size: 26px;
            color: #fff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            background: #ffffff;
        }

        .avatar-img { 
            width: 55px; 
            height: 55px; 
            object-fit: cover; 
            border-radius: 50%;
            border: 2px solid #dee2e6;
        }

        .image-selector { 
            cursor: pointer; 
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 8px;
            padding: 4px;
            border: 2px solid transparent;
        }

        .image-selector:hover { 
            transform: scale(1.05); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .selected-image { 
            border: 2px solid #0d6efd !important; 
            background-color: #e9f2ff;
        }

        .image-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(95px, 1fr)); 
            gap: 12px; 
        }

        .table-custom tbody tr {
            vertical-align: middle;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'partials/header.php'; ?>

<div class="container py-4">

    <!-- Botón Flotante para Guardar Alias -->
    <button type="button" id="btnGuardarAlias" class="btn-flotante-guardar" data-bs-toggle="tooltip" data-bs-placement="left" title="Guardar Alias">
        <i class="bi bi-floppy-fill"></i>
    </button>

    <!-- Botón Flotante para Regresar -->
    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=1&modulo_id=1" class="btn-flotante-regresar" data-bs-toggle="tooltip" data-bs-placement="left" title="Volver al panel general">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <!-- Alertas dinámicas -->
    <div id="alertContainer"></div>

    <div class="row g-4">
        <div class="col-lg-6">
            <!-- Formulario Crear Alias -->
            <div class="card card-custom mb-4">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4 text-primary">
                        <i class="bi bi-tags-fill me-2"></i>Crear Nuevo Alias
                    </h4>
                    
                    <form id="formCrearAlias">
                        <div class="mb-3">
                            <label for="alias" class="form-label fw-bold">Nombre del Alias <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="alias" name="alias" required
                                   placeholder="Ej: Sala de Cómputo 1"
                                   pattern="[A-Za-záéíóúÁÉÍÓÚñÑ0-9 ]+">
                            <div class="form-text">Solo letras, números y espacios.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Imagen del Alias <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-outline-secondary w-100 mb-2" 
                                    data-bs-toggle="modal" data-bs-target="#imagenModal">
                                <i class="bi bi-images me-1"></i> Seleccionar del catálogo
                            </button>
                            <input type="hidden" name="imagen" id="imagen" required>
                            
                            <!-- Vista previa -->
                            <div class="text-center mt-3 p-3 bg-light rounded border" id="imagePreviewContainer" style="display:none;">
                                <img id="imagePreview" src="" class="img-thumbnail rounded-circle" style="width:100px; height:100px; object-fit:cover;">
                                <p class="small text-muted mt-2 mb-0" id="imageName"></p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lista de alias existentes -->
            <div class="card card-custom">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 text-secondary">
                        <i class="bi bi-list-check me-2"></i>Alias Registrados
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle" id="tablaAlias">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Imagen</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT alias_id, alias, alias_imagen FROM t_alias 
                                          WHERE codigo = '".mysqli_real_escape_string($link, $logcodigo)."'
                                          ORDER BY alias_id DESC";
                                $result = mysqli_query($link, $query);
                                
                                if ($result && mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $id = htmlspecialchars($row['alias_id']);
                                        $nombre = htmlspecialchars($row['alias']);
                                        $img = htmlspecialchars($row['alias_imagen']);
                                        echo "<tr id='fila-alias-{$id}'>
                                            <td><span class='badge bg-secondary'>{$id}</span></td>
                                            <td class='alias-nombre-td'><strong>{$nombre}</strong></td>
                                            <td>
                                                <img src='img/alias/{$img}' class='avatar-img' alt='{$nombre}' onerror=\"this.src='img/alias/Alias-002.png'\">
                                            </td>
                                            <td class='text-center'>
                                                <button type='button' class='btn btn-sm btn-outline-primary me-1 btn-editar-alias' 
                                                        data-id='{$id}' data-nombre='{$nombre}' title='Editar nombre'>
                                                    <i class='bi bi-pencil-square'></i>
                                                </button>
                                                <button type='button' class='btn btn-sm btn-outline-danger btn-eliminar-alias' 
                                                        data-id='{$id}' data-nombre='{$nombre}' title='Eliminar alias'>
                                                    <i class='bi bi-trash-fill'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr id='fila-sin-alias'><td colspan='4' class='text-center text-muted py-3'>No hay alias registrados.</td></tr>";
                                }
                                mysqli_close($link);
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center">
            <div class="p-3 text-center">
                <img src="img/Alias_Mesa de trabajo 1.png" class="img-fluid rounded shadow-sm" alt="Ilustración Alias" style="max-height: 480px;">
            </div>
        </div>
    </div>

</div>

<!-- Modal de selección de imágenes -->
<div class="modal fade" id="imagenModal" tabindex="-1" aria-labelledby="imagenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="imagenModalLabel"><i class="bi bi-images me-2"></i>Catálogo de Imágenes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="image-grid">
                    <?php
                    // Generar imágenes del 002 al 031
                    for ($i = 2; $i <= 31; $i++) {
                        $num = str_pad($i, 3, '0', STR_PAD_LEFT);
                        $imgName = "Alias-$num.png";
                        echo '
                        <div class="image-selector text-center" data-image="'.$imgName.'">
                            <img src="img/alias/'.$imgName.'" class="img-thumbnail" alt="Alias '.$num.'" onerror="this.style.opacity=0.3">
                            <div class="text-center small mt-1 text-secondary">'.$num.'</div>
                        </div>';
                    }
                    ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmImageBtn">Seleccionar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edición de Alias -->
<div class="modal fade" id="modalEditarAlias" tabindex="-1" aria-labelledby="modalEditarAliasLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalEditarAliasLabel"><i class="bi bi-pencil-square me-2"></i>Editar Nombre de Alias</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarAlias">
                    <input type="hidden" id="edit_alias_id" name="alias_id">
                    <div class="mb-3">
                        <label for="edit_alias_nombre" class="form-label fw-bold">Nuevo Nombre del Alias <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_alias_nombre" name="alias" required pattern="[A-Za-záéíóúÁÉÍÓÚñÑ0-9 ]+">
                        <div class="form-text">Solo letras, números y espacios.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarEdicion">
                    <i class="bi bi-check-circle me-1"></i> Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Eliminar Alias -->
<div class="modal fade" id="modalEliminarAlias" tabindex="-1" aria-labelledby="modalEliminarAliasLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalEliminarAliasLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el alias <strong id="deleteAliasNombre"></strong>?</p>
                <div class="alert alert-warning mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Nota:</strong> Los elementos/placas vinculados a este alias quedarán libres (alias en cero).
                </div>
                <input type="hidden" id="delete_alias_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">
                    <i class="bi bi-trash-fill me-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="js/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializar tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Instancias de modales de Bootstrap
    const modalEditar = new bootstrap.Modal(document.getElementById('modalEditarAlias'));
    const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminarAlias'));
    const modalCatalogo = new bootstrap.Modal(document.getElementById('imagenModal'));

    // Función para mostrar alertas temporales
    function mostrarAlerta(mensaje, tipo = 'success') {
        const alertaHtml = `
            <div class="alert alert-${tipo} alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi ${tipo === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        $('#alertContainer').html(alertaHtml);
        setTimeout(function() {
            $('#alertContainer .alert').alert('close');
        }, 4000);
    }

    // Selección de imagen del catálogo
    let selectedImage = '';
    $('.image-selector').click(function() {
        $('.image-selector').removeClass('selected-image');
        $(this).addClass('selected-image');
        selectedImage = $(this).data('image');
    });

    $('#confirmImageBtn').click(function() {
        if (selectedImage) {
            $('#imagen').val(selectedImage);
            $('#imagePreview').attr('src', 'img/alias/' + selectedImage);
            $('#imageName').text(selectedImage.replace('.png', ''));
            $('#imagePreviewContainer').show();
            modalCatalogo.hide();
        } else {
            alert('Por favor selecciona una imagen del catálogo');
        }
    });

    // Botón flotante para guardar alias activa el submit del formulario
    $('#btnGuardarAlias').click(function() {
        $('#formCrearAlias').submit();
    });

    // 1. CREAR ALIAS (AJAX)
    $('#formCrearAlias').submit(function(e) {
        e.preventDefault();
        const aliasNombre = $('#alias').val().trim();
        const aliasImagen = $('#imagen').val().trim();

        if (!aliasNombre) {
            mostrarAlerta('Por favor ingresa el nombre del alias', 'warning');
            $('#alias').focus();
            return;
        }

        if (!aliasImagen) {
            mostrarAlerta('Por favor selecciona una imagen del catálogo', 'warning');
            return;
        }

        $('#btnGuardarAlias').prop('disabled', true).html('<span class="spinner-border spinner-border-sm text-light"></span>');

        $.ajax({
            url: 'ajax/guardar_alias.php',
            type: 'POST',
            dataType: 'json',
            data: {
                alias: aliasNombre,
                imagen: aliasImagen
            },
            success: function(res) {
                $('#btnGuardarAlias').prop('disabled', false).html('<i class="bi bi-floppy-fill"></i>');
                if (res.success) {
                    mostrarAlerta(res.message, 'success');
                    
                    // Resetear formulario
                    $('#formCrearAlias')[0].reset();
                    $('#imagen').val('');
                    $('#imagePreviewContainer').hide();
                    $('.image-selector').removeClass('selected-image');
                    selectedImage = '';

                    // Agregar o recargar fila en la tabla
                    $('#fila-sin-alias').remove();
                    const newRow = `
                        <tr id="fila-alias-${res.alias_id}">
                            <td><span class="badge bg-secondary">${res.alias_id}</span></td>
                            <td class="alias-nombre-td"><strong>${escapeHtml(aliasNombre)}</strong></td>
                            <td>
                                <img src="img/alias/${aliasImagen}" class="avatar-img" alt="${escapeHtml(aliasNombre)}" onerror="this.src='img/alias/Alias-002.png'">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-editar-alias" 
                                        data-id="${res.alias_id}" data-nombre="${escapeHtml(aliasNombre)}" title="Editar nombre">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-alias" 
                                        data-id="${res.alias_id}" data-nombre="${escapeHtml(aliasNombre)}" title="Eliminar alias">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </td>
                        </tr>`;
                    $('#tablaAlias tbody').prepend(newRow);
                } else {
                    mostrarAlerta(res.message || 'Error al guardar alias', 'danger');
                }
            },
            error: function() {
                $('#btnGuardarAlias').prop('disabled', false).html('<i class="bi bi-floppy-fill"></i>');
                mostrarAlerta('Ocurrió un error en la comunicación con el servidor', 'danger');
            }
        });
    });

    // 2. ABRIR MODAL EDITAR ALIAS
    $(document).on('click', '.btn-editar-alias', function() {
        const id = $(this).data('id');
        const nombre = $(this).attr('data-nombre');
        $('#edit_alias_id').val(id);
        $('#edit_alias_nombre').val(nombre);
        modalEditar.show();
    });

    // GUARDAR EDICIÓN (AJAX)
    $('#btnGuardarEdicion').click(function() {
        const id = $('#edit_alias_id').val();
        const nuevoNombre = $('#edit_alias_nombre').val().trim();

        if (!nuevoNombre) {
            alert('El nombre del alias no puede estar vacío');
            return;
        }

        $('#btnGuardarEdicion').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando...');

        $.ajax({
            url: 'ajax/editar_alias.php',
            type: 'POST',
            dataType: 'json',
            data: {
                alias_id: id,
                alias: nuevoNombre
            },
            success: function(res) {
                $('#btnGuardarEdicion').prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i> Guardar Cambios');
                if (res.success) {
                    modalEditar.hide();
                    mostrarAlerta(res.message, 'success');
                    
                    // Actualizar el DOM en la tabla
                    const $row = $(`#fila-alias-${id}`);
                    $row.find('.alias-nombre-td strong').text(nuevoNombre);
                    $row.find('.btn-editar-alias').attr('data-nombre', nuevoNombre);
                    $row.find('.btn-eliminar-alias').attr('data-nombre', nuevoNombre);
                } else {
                    alert(res.message || 'Error al actualizar alias');
                }
            },
            error: function() {
                $('#btnGuardarEdicion').prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i> Guardar Cambios');
                alert('Ocurrió un error al intentar actualizar el alias');
            }
        });
    });

    // 3. ABRIR MODAL ELIMINAR ALIAS
    $(document).on('click', '.btn-eliminar-alias', function() {
        const id = $(this).data('id');
        const nombre = $(this).attr('data-nombre');
        $('#delete_alias_id').val(id);
        $('#deleteAliasNombre').text(nombre);
        modalEliminar.show();
    });

    // CONFIRMAR ELIMINACIÓN (AJAX)
    $('#btnConfirmarEliminar').click(function() {
        const id = $('#delete_alias_id').val();

        $('#btnConfirmarEliminar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Eliminando...');

        $.ajax({
            url: 'ajax/eliminar_alias.php',
            type: 'POST',
            dataType: 'json',
            data: { alias_id: id },
            success: function(res) {
                $('#btnConfirmarEliminar').prop('disabled', false).html('<i class="bi bi-trash-fill me-1"></i> Eliminar');
                modalEliminar.hide();
                if (res.success) {
                    mostrarAlerta(res.message, 'success');
                    
                    // Remover fila de la tabla con animación
                    $(`#fila-alias-${id}`).fadeOut(300, function() {
                        $(this).remove();
                        if ($('#tablaAlias tbody tr').length === 0) {
                            $('#tablaAlias tbody').html('<tr id="fila-sin-alias"><td colspan="4" class="text-center text-muted py-3">No hay alias registrados.</td></tr>');
                        }
                    });
                } else {
                    mostrarAlerta(res.message || 'Error al eliminar alias', 'danger');
                }
            },
            error: function() {
                $('#btnConfirmarEliminar').prop('disabled', false).html('<i class="bi bi-trash-fill me-1"></i> Eliminar');
                modalEliminar.hide();
                mostrarAlerta('Ocurrió un error al procesar la eliminación', 'danger');
            }
        });
    });

    // Función auxiliar para escapar texto HTML
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }
});
</script>
</body>
</html>