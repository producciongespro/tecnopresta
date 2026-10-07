<?php
// ============================================================
// FORMULARIO: Gestión de Modelos (Combinaciones Tipo + Marca + Modelo)
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
$btnRegresar = 'navegar.php?ruta=formulario_menu_principal.php';
if (isset($_GET['subsistema_id']) && isset($_GET['modulo_id'])) {
    $btnRegresar = 'navegar.php?ruta=formulario_sub_modulos.php'
        . '&subsistema_id=' . intval($_GET['subsistema_id'])
        . '&modulo_id=' . intval($_GET['modulo_id']);
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogos - Gestión de Clases o Tipos de Activos | Modelos</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="select2/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=8">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=8">

    <style>
        #tablaCombos, #tablaCombos tbody, #tablaCombos td, #tablaCombos th { color: #2c3e50; }
        #tablaCombos thead tr { background: linear-gradient(135deg, #192952, #0035A0); color: #fff; }
        #tablaCombos thead th { color: #fff; }
        #tablaCombos tbody tr:nth-child(odd) { background: #ffffff; }
        #tablaCombos tbody tr:nth-child(even) { background: #f2f5f9; }
        #tablaCombos tbody tr:hover { background: #e8eef7; }
        #tablaCombos th.th-acciones, #tablaCombos td.td-acciones { width: 110px; white-space: nowrap; text-align: center; }

        /* ============ Modal Combinaciones (MEP elegante) ============ */
        #modalCombo .modal-content {
            border: 1px solid rgba(200, 174, 100, .35);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(30, 40, 81, .35);
        }
        #modalCombo .modal-header {
            background: linear-gradient(135deg, #1e2851 0%, #0035a0 100%);
            border-bottom: 3px solid #c8ae64;
            padding: 26px 28px;
            align-items: center;
        }
        #modalCombo .combo-header-icon {
            width: 54px; height: 54px; border-radius: 14px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(200,174,100,.65);
            color: #c8ae64; font-size: 1.6rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 18px rgba(200,174,100,.28);
            flex: 0 0 auto;
        }
        #modalCombo .modal-title { color: #fff; font-weight: 600; font-size: 1.15rem; }
        #modalCombo .combo-subtitle { color: rgba(255,255,255,.75); font-size: .82rem; margin: 0; }
        #modalCombo .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        #modalCombo .modal-body { padding: 28px; background: #f7f9fc; }

        .gestor-label {
            display: block; font-size: .74rem; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; color: #1e2851; margin-bottom: .45rem;
        }
        .field-chip {
            display: flex; align-items: stretch;
            background: #fff; border: 1px solid #d7dde8; border-radius: 12px;
            transition: border-color .2s ease, box-shadow .2s ease;
            overflow: hidden;
        }
        .field-chip:focus-within { border-color: #c8ae64; box-shadow: 0 0 0 3px rgba(200,174,100,.22); }
        .field-chip .chip-icon {
            display: flex; align-items: center; justify-content: center;
            width: 46px; background: linear-gradient(180deg, #1e2851, #27356b);
            color: #c8ae64; font-size: 1.1rem; flex: 0 0 46px;
        }
        .field-chip .select2-container { border: 0 !important; flex: 1 1 auto; }
        .field-chip .select2-container .select2-selection--single {
            height: 46px !important; border: 0 !important; border-radius: 0 !important;
            background: transparent !important; box-shadow: none !important;
            padding-left: 12px;
        }
        .field-chip .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 46px !important; color: #2c3e50 !important; padding-left: 0 !important;
        }
        .field-chip .select2-container .select2-selection--single .select2-selection__arrow {
            height: 46px !important; right: 10px !important;
        }
        .field-chip input.form-control { border: 0; border-radius: 0; height: 46px; box-shadow: none; }
        .field-chip input.form-control:focus { box-shadow: none; }

        .hint-nuevo-modelo {
            font-size: .78rem; color: #6b7280; background: #fff;
            border-left: 3px solid #c8ae64; border-radius: 0 8px 8px 0;
            padding: .5rem .7rem; margin-top: .55rem;
        }
        .hint-nuevo-modelo strong { color: #1e2851; }
        .btn-nuevo-modelo-quick {
            border: 1px dashed #c8ae64; color: #1e2851; background: rgba(200,174,100,.12);
            border-radius: 10px; font-weight: 600; font-size: .78rem; height: 46px;
            white-space: nowrap; transition: all .2s ease;
        }
        .btn-nuevo-modelo-quick:hover { background: #c8ae64; color: #1e2851; }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background: #1e2851; }
        .opt-nuevo-modelo { color: #9a7b23; font-weight: 700; }

        .edit-info-box {
            background: linear-gradient(135deg, #fff7e0, #fdf3d5);
            border: 1px solid #e6d3a3; border-radius: 12px;
            padding: .85rem 1rem; font-size: .85rem; color: #5c4a12;
        }
        .edit-confirm-box {
            background: #fff; border: 1px dashed #d33; border-radius: 12px;
            padding: .85rem 1rem;
        }
        .edit-confirm-box .form-control { border-radius: 8px; letter-spacing: .12em; font-weight: 700; text-transform: uppercase; }
        .delete-info-box {
            background: linear-gradient(135deg, #fdecec, #fbe3e3);
            border: 1px solid #efb7b7; border-radius: 12px;
            padding: .85rem 1rem; font-size: .85rem; color: #7a1f1f;
        }
        .btn-action { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
        .btn-action:disabled { opacity: .45; cursor: not-allowed; }
        .confirm-error-msg {
            color: #b02a37; font-size: .78rem; font-weight: 600; margin-top: .5rem;
            background: #fdecec; border: 1px solid #efb7b7; border-radius: 8px;
            padding: .45rem .65rem;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>
    <main class="container py-4 contenido-principal" id="appCatalogoModelos">
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-auto"><i class="bi bi-box-seam" style="font-size: 2.5rem; color: #C8A951;"></i></div>
                <div class="col">
                    <h1 class="mb-0" style="color: #fff; font-weight: 600;">Gestión de Activos | Modelos</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.8);">Combinaciones Tipo + Marca + Modelo</p>
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Combinaciones (Tipo + Marca + Modelo)</h5>
                <button class="btn btn-mep-primary btn-sm" onclick="abrirModalCombo()"><i class="bi bi-plus-lg"></i> Nueva Combinación</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table activos-table mb-0" id="tablaCombos">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Unidades físicas</th>
                                <th>Filas t_activo</th>
                                <th class="th-acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCombos">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Cargando combinaciones...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <a href="<?= htmlspecialchars($btnRegresar) ?>" class="btn-disponibilidad" style="bottom: 100px;" data-tooltip="Regresar"><i class="bi bi-arrow-left-circle-fill"></i></a>

        <div class="modal fade gestor-overlay" id="modalCombo" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg gestor-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="combo-header-icon"><i class="bi bi-box-seam"></i></div>
                            <div>
                                <h5 class="modal-title mb-0" id="modalComboTitulo"><i class="bi bi-plus-circle-fill me-2"></i>Nueva Combinación</h5>
                                <p class="combo-subtitle" id="modalComboSubtitulo">Tipo + Marca + Modelo · registro en t_activo con modelo_id en t_modelos</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="edit-info-box mb-3 d-none" id="editInfoBox">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Esta combinación tiene <strong id="editInfoFilas">0</strong> fila(s) en <code>t_activo</code>
                            y <strong id="editInfoUnidades">0</strong> unidad(es) física(s). Al guardar, quedarán asociadas a la nueva combinación.
                        </div>

                        <div class="mb-3">
                            <label class="gestor-label" for="selTipo">Tipo de Activo <span class="text-danger">*</span></label>
                            <div class="field-chip">
                                <span class="chip-icon"><i class="bi bi-pc-display"></i></span>
                                <select class="form-select" id="selTipo"></select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="gestor-label" for="selMarca">Marca <span class="text-danger">*</span></label>
                            <div class="field-chip">
                                <span class="chip-icon"><i class="bi bi-tag-fill"></i></span>
                                <select class="form-select" id="selMarca"></select>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="gestor-label" for="selModelo">Modelo <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-stretch">
                                <div class="field-chip flex-grow-1">
                                    <span class="chip-icon"><i class="bi bi-upc-scan"></i></span>
                                    <select class="form-select" id="selModelo"></select>
                                </div>
                                <button type="button" class="btn-nuevo-modelo-quick px-3" id="btnNuevoModeloRapido" title="Crear un modelo nuevo en t_modelos">
                                    <i class="bi bi-plus-lg"></i> Nuevo
                                </button>
                            </div>
                            <div class="hint-nuevo-modelo d-none" id="hintNuevoModelo">
                                <i class="bi bi-lightbulb-fill me-1" style="color:#c8ae64;"></i>
                                ¿No encuentra su modelo? La opción <strong>«Nuevo modelo…»</strong> está al inicio de la lista: seleccionarla crea un nuevo registro en la tabla <strong>t_modelos</strong>.
                            </div>
                            <div class="mt-2 d-none" id="modeloNuevoWrap">
                                <label class="gestor-label" for="modModeloNuevo">Nombre del nuevo modelo <span class="text-danger">*</span></label>
                                <div class="field-chip">
                                    <span class="chip-icon"><i class="bi bi-pencil-square"></i></span>
                                    <input type="text" class="form-control" id="modModeloNuevo" maxlength="250" placeholder="Ej: Latitude 5540">
                                </div>
                            </div>
                        </div>

                        <div class="edit-confirm-box mt-3 d-none" id="editConfirmBox">
                            <label class="gestor-label text-danger" for="editConfirmInput">
                                <i class="bi bi-shield-lock-fill me-1"></i>Confirmación de seguridad
                            </label>
                            <div class="form-text mb-2" style="font-size:.8rem;">
                                Esta acción modifica registros existentes. Escriba <strong>CONFIRMAR</strong> para habilitar el guardado.
                            </div>
                            <input type="text" class="form-control" id="editConfirmInput" placeholder="CONFIRMAR" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-primary" id="btnGuardarCombo"><i class="bi bi-check-lg"></i> <span id="btnGuardarComboTexto">Guardar</span></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade gestor-overlay" id="modalEliminar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered gestor-modal">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #7a1220, #a3202f); border-bottom: 3px solid #c8ae64;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="combo-header-icon" style="border-color:rgba(255,255,255,.5); color:#fff; box-shadow:0 0 18px rgba(255,255,255,.25);">
                                <i class="bi bi-trash3-fill"></i>
                            </div>
                            <div>
                                <h5 class="modal-title mb-0"><i class="bi bi-exclamation-octagon-fill me-2"></i>Eliminar Combinación</h5>
                                <p class="combo-subtitle" id="eliminarComboSubtitulo">Tipo + Marca + Modelo</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="delete-info-box mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            Se eliminarán <strong id="eliminarInfoFilas">0</strong> fila(s) de <code>t_activo</code>
                            correspondientes a esta combinación.
                            Unidades físicas asociadas: <strong id="eliminarInfoUnidades">0</strong>.
                        </div>
                        <div class="form-text mb-3" style="font-size:.8rem; color:#6b7280;">
                            <i class="bi bi-box-seam me-1"></i>El modelo <strong id="eliminarInfoModelo"></strong> se conserva en el catálogo <strong>t_modelos</strong>; esta acción no se puede deshacer.
                        </div>
                        <div class="edit-confirm-box" id="eliminarConfirmBox">
                            <label class="gestor-label text-danger" for="eliminarConfirmInput">
                                <i class="bi bi-shield-lock-fill me-1"></i>Confirmación de seguridad
                            </label>
                            <div class="form-text mb-2" style="font-size:.8rem;">
                                Esta acción elimina registros de forma permanente. Escriba <strong>CONFIRMAR</strong> para habilitar el botón de eliminación.
                            </div>
                            <input type="text" class="form-control" id="eliminarConfirmInput" placeholder="CONFIRMAR" autocomplete="off" spellcheck="false" aria-describedby="eliminarConfirmError">
                            <div class="confirm-error-msg d-none" id="eliminarConfirmError" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1"></i>El texto ingresado es incorrecto. Debe escribir exactamente <strong>CONFIRMAR</strong>.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-danger" id="btnConfirmarEliminarCombo" disabled><i class="bi bi-trash3-fill"></i> Eliminar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade gestor-overlay" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon warning"><i class="bi bi-exclamation-triangle"></i></div>
                        <p class="mt-3 mb-0 fw-semibold" id="modalConfirmacionTexto">¿Está seguro?</p>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="gestor-btn-danger" id="btnConfirmarAccion"><i class="bi bi-check-lg"></i> Sí, continuar</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade gestor-overlay" id="modalExito" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon success"><i class="bi bi-check-lg"></i></div>
                        <p class="mt-3 mb-0 fw-semibold" id="modalExitoTexto">Operación exitosa</p>
                    </div>
                    <div class="modal-footer centered border-0 pt-0">
                        <button type="button" class="gestor-btn-primary" data-bs-dismiss="modal">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade gestor-overlay" id="modalError" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm gestor-modal">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="gestor-modal-icon error"><i class="bi bi-x-lg"></i></div>
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
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="select2/select2.min.js"></script>
    <script>
    var modales={}, tiposData=[], marcasData=[], modelosData=[], combosData=[];
    var modoModal='crear', comboEditOriginal=null, comboEliminarOriginal=null;
    function escapeHtml(t){if(!t)return'';return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
    function mostrarModal(id){var el=document.getElementById(id);if(el){var m=bootstrap.Modal.getInstance(el)||new bootstrap.Modal(el);m.show();}}
    function ocultarModal(id){var el=document.getElementById(id);if(el){var m=bootstrap.Modal.getInstance(el);if(m)m.hide();}}
    function mostrarExito(m){document.getElementById('modalExitoTexto').textContent=m;mostrarModal('modalExito');}
    function mostrarError(m){document.getElementById('modalErrorTexto').textContent=m;mostrarModal('modalError');}
    function llenarSelects(){
        var sT=document.getElementById('selTipo'), sM=document.getElementById('selMarca'), sMo=document.getElementById('selModelo');
        if(!sT||!sM||!sMo) return;
        var haySelect2=(typeof window.jQuery==='function' && typeof jQuery.fn.select2==='function');
        var sel = [$(sT), $(sM), $(sMo)];
        if(haySelect2){ for (var k=0; k<sel.length; k++){ if (sel[k].data('select2')) { sel[k].select2('destroy'); } } }
        sT.innerHTML='<option value="">Seleccione...</option>';
        sM.innerHTML='<option value="">Seleccione...</option>';
        sMo.innerHTML='<option value="">Seleccione...</option>';
        var on=document.createElement('option'); on.value='__nuevo__'; on.textContent='Nuevo modelo…'; sMo.appendChild(on);
        if(Array.isArray(tiposData)){ for(var i=0;i<tiposData.length;i++){ var x=tiposData[i]; var o=document.createElement('option'); o.value=x.id_ag; o.textContent=x.clase; sT.appendChild(o); } }
        if(Array.isArray(marcasData)){ for(var i=0;i<marcasData.length;i++){ var x=marcasData[i]; var o=document.createElement('option'); o.value=x.id_marca; o.textContent=x.marca; sM.appendChild(o); } }
        if(Array.isArray(modelosData)){ for(var i=0;i<modelosData.length;i++){ var x=modelosData[i]; var o=document.createElement('option'); o.value=x.id_modelo; o.textContent=x.modelo; sMo.appendChild(o); } }
        function pintarModelo(d){
            if(!d.id){ return d.text; }
            if(d.id==='__nuevo__'){
                var s=$('<span class="opt-nuevo-modelo"><i class="bi bi-plus-circle-fill me-1"></i>'+d.text+' <small style="font-weight:400;color:#6b7280;">(crea registro en t_modelos)</small></span>');
                return s;
            }
            return d.text;
        }
        if(!haySelect2){
            console.warn('select2 no está disponible: se usan los selects nativos.');
            return;
        }
        var cfgModelo={placeholder:'Seleccione modelo', allowClear:true, width:'100%', dropdownParent: $('#modalCombo'),
            templateResult: pintarModelo, templateSelection: pintarModelo};
        $(sT).select2({placeholder:'Seleccione tipo', allowClear:true, width:'100%', dropdownParent: $('#modalCombo')});
        $(sM).select2({placeholder:'Seleccione marca', allowClear:true, width:'100%', dropdownParent: $('#modalCombo')});
        $(sMo).select2(cfgModelo);
    }
    async function cargarDatos(){
        try{
            var r=await fetch('sql/gestor_catalogo_modelos_n.php');
            var d=await r.json();
            if(!d.success) throw new Error(d.message||'Error');
            tiposData=d.tipos||[]; marcasData=d.marcas||[]; modelosData=d.modelos||[]; combosData=d.combos||[];
            llenarSelects();
            renderizarCombos();
        }catch(e){ mostrarError('Error al cargar datos: '+e.message); }
    }
    function pintarNuevoModeloInput(visible){
        var w=document.getElementById('modeloNuevoWrap');
        if(w){ w.classList.toggle('d-none', !visible); }
    }
    function abrirModalCombo(){
        modoModal='crear'; comboEditOriginal=null;
        $('#selTipo').val(null).trigger('change');
        $('#selMarca').val(null).trigger('change');
        $('#selModelo').val(null).trigger('change');
        document.getElementById('modModeloNuevo').value='';
        document.getElementById('editConfirmInput').value='';
        document.getElementById('editInfoBox').classList.add('d-none');
        document.getElementById('editConfirmBox').classList.add('d-none');
        document.getElementById('hintNuevoModelo').classList.remove('d-none');
        document.getElementById('btnNuevoModeloRapido').classList.remove('d-none');
        pintarNuevoModeloInput(false);
        document.getElementById('modalComboTitulo').innerHTML='<i class="bi bi-plus-circle-fill me-2"></i>Nueva Combinación';
        document.getElementById('modalComboSubtitulo').textContent='Tipo + Marca + Modelo · registro en t_activo con modelo_id en t_modelos';
        document.getElementById('btnGuardarComboTexto').textContent='Guardar';
        mostrarModal('modalCombo');
    }
    function abrirModalEditar(idx){
        var c=combosData[idx];
        if(!c){ return; }
        modoModal='editar'; comboEditOriginal=c;
        $('#selTipo').val(String(c.id_ag)).trigger('change');
        $('#selMarca').val(String(c.id_marca)).trigger('change');
        $('#selModelo').val(String(c.modelo_id)).trigger('change');
        document.getElementById('modModeloNuevo').value='';
        document.getElementById('editConfirmInput').value='';
        pintarNuevoModeloInput(false);
        document.getElementById('editInfoFilas').textContent=parseInt(c.filas_t_activo)||0;
        document.getElementById('editInfoUnidades').textContent=parseInt(c.unidades_fisicas)||0;
        document.getElementById('editInfoBox').classList.remove('d-none');
        document.getElementById('editConfirmBox').classList.remove('d-none');
        document.getElementById('hintNuevoModelo').classList.add('d-none');
        document.getElementById('btnNuevoModeloRapido').classList.add('d-none');
        document.getElementById('modalComboTitulo').innerHTML='<i class="bi bi-pencil-square me-2"></i>Editar Combinación';
        document.getElementById('modalComboSubtitulo').textContent=(c.clase||'')+' · '+(c.marca||'')+' · '+(c.modelo_nombre||'');
        document.getElementById('btnGuardarComboTexto').textContent='Guardar cambios';
        mostrarModal('modalCombo');
    }
    function abrirModalEliminar(idx){
        var c=combosData[idx];
        if(!c){ return; }
        var unidades=parseInt(c.unidades_fisicas)||0;
        if(unidades>0){ mostrarError('No se puede eliminar: la combinación tiene unidades físicas asociadas'); return; }
        comboEliminarOriginal=c;
        document.getElementById('eliminarInfoFilas').textContent=parseInt(c.filas_t_activo)||0;
        document.getElementById('eliminarInfoUnidades').textContent=unidades;
        document.getElementById('eliminarInfoModelo').textContent=c.modelo_nombre||'';
        document.getElementById('eliminarComboSubtitulo').textContent=(c.clase||'')+' · '+(c.marca||'')+' · '+(c.modelo_nombre||'');
        document.getElementById('eliminarConfirmInput').value='';
        document.getElementById('eliminarConfirmInput').classList.remove('is-invalid');
        document.getElementById('eliminarConfirmError').classList.add('d-none');
        document.getElementById('btnConfirmarEliminarCombo').disabled=true;
        mostrarModal('modalEliminar');
    }
    function validarConfirmEliminar(){
        var inp=document.getElementById('eliminarConfirmInput');
        var err=document.getElementById('eliminarConfirmError');
        var btn=document.getElementById('btnConfirmarEliminarCombo');
        if(!inp){ return false; }
        var v=(inp.value||'').trim().toUpperCase();
        var ok=(v==='CONFIRMAR');
        var vacio=(v==='');
        inp.classList.toggle('is-invalid', !ok && !vacio);
        if(err){ err.classList.toggle('d-none', ok || vacio); }
        if(btn){ btn.disabled=!ok; }
        return ok;
    }
    async function eliminarCombo(){
        if(!comboEliminarOriginal){ mostrarError('No se pudo cargar la combinación a eliminar'); return; }
        if(!validarConfirmEliminar()){ mostrarError('El texto ingresado es incorrecto. Debe escribir CONFIRMAR para autorizar la eliminación'); return; }
        var btn=document.getElementById('btnConfirmarEliminarCombo'); if(btn) btn.disabled=true;
        var fd=new FormData();
        fd.append('action','eliminar_combo');
        fd.append('id_ag',comboEliminarOriginal.id_ag);
        fd.append('id_marca',comboEliminarOriginal.id_marca);
        fd.append('id_modelo',comboEliminarOriginal.modelo_id);
        try{
            var resp=await fetch('actualizar_gestor_catalogo_modelos_n.php',{method:'POST',body:fd});
            var data=await resp.json();
            if(data.success){ ocultarModal('modalEliminar'); comboEliminarOriginal=null; mostrarExito(data.message); cargarDatos(); }
            else{ mostrarError(data.message); }
        }catch(e){ mostrarError('Error de conexión: '+e.message); }
        finally{ validarConfirmEliminar(); }
    }
    function renderizarCombos(){
        var tbody=document.getElementById('tbodyCombos');
        if(!tbody) return;
        tbody.innerHTML='';
        if(!Array.isArray(combosData)||combosData.length===0){
            tbody.innerHTML='<tr><td colspan="6" class="text-center text-muted py-4">No hay combinaciones registradas</td></tr>';
            return;
        }
        for(var i=0;i<combosData.length;i++){
            var c=combosData[i];
            var tr=document.createElement('tr');
            var unidades=parseInt(c.unidades_fisicas)||0;
            var btnEliminar = unidades>0
                ? '<button type="button" class="btn btn-sm btn-outline-secondary btn-action" disabled title="No se puede eliminar: tiene unidades físicas asociadas"><i class="bi bi-trash3-fill"></i></button>'
                : '<button type="button" class="btn btn-sm btn-outline-danger btn-action" title="Eliminar combinación" onclick="abrirModalEliminar('+i+')"><i class="bi bi-trash3-fill"></i></button>';
            tr.innerHTML='<td>'+escapeHtml(c.clase||'')+'</td><td>'+escapeHtml(c.marca||'')+'</td><td>'+escapeHtml(c.modelo_nombre||'')+'</td><td>'+unidades+'</td><td>'+(parseInt(c.filas_t_activo)||0)+'</td>'
                +'<td class="td-acciones">'
                +'<button type="button" class="btn btn-sm btn-outline-primary btn-action me-1" title="Editar combinación" onclick="abrirModalEditar('+i+')"><i class="bi bi-pencil-fill"></i></button>'
                +btnEliminar
                +'</td>';
            tbody.appendChild(tr);
        }
    }
    async function guardarCombo(){
        var id_ag=$("#selTipo").val(), id_marca=$("#selMarca").val(), id_modelo_sel=$("#selModelo").val();
        if(!id_ag||!id_marca){ mostrarError('Debe seleccionar Tipo y Marca'); return; }
        if(!id_modelo_sel){ mostrarError('Debe seleccionar o ingresar un modelo'); return; }
        var btn=document.getElementById('btnGuardarCombo'); if(btn) btn.disabled=true;
        var fd=new FormData();
        var url='actualizar_gestor_catalogo_modelos_n.php';
        if(modoModal==='editar'){
            if(!comboEditOriginal){ mostrarError('No se pudo cargar la combinación a editar'); if(btn) btn.disabled=false; return; }
            var confirmTxt=(document.getElementById('editConfirmInput').value||'').trim().toUpperCase();
            if(confirmTxt!=='CONFIRMAR'){ mostrarError('Debe escribir CONFIRMAR para autorizar la edición'); if(btn) btn.disabled=false; return; }
            if(id_modelo_sel==='__nuevo__'){ mostrarError('En modo edición debe seleccionar un modelo existente'); if(btn) btn.disabled=false; return; }
            fd.append('action','editar_combo');
            fd.append('old_id_ag',comboEditOriginal.id_ag);
            fd.append('old_id_marca',comboEditOriginal.id_marca);
            fd.append('old_id_modelo',comboEditOriginal.modelo_id);
            fd.append('id_ag',id_ag);
            fd.append('id_marca',id_marca);
            fd.append('id_modelo',id_modelo_sel);
        }else{
            fd.append('action','crear_combo');
            fd.append('id_ag',id_ag);
            fd.append('id_marca',id_marca);
            if(id_modelo_sel==='__nuevo__'){
                var txt=document.getElementById('modModeloNuevo').value.trim();
                if(!txt){ mostrarError('Ingrese nombre del modelo'); if(btn) btn.disabled=false; return; }
                fd.append('modelo_texto',txt);
            }else{ fd.append('id_modelo',id_modelo_sel); }
        }
        try{
            var resp=await fetch(url,{method:'POST',body:fd});
            var data=await resp.json();
            if(data.success){ ocultarModal('modalCombo'); mostrarExito(data.message); cargarDatos(); }
            else{ mostrarError(data.message); }
        }catch(e){ mostrarError('Error de conexión: '+e.message); }
        finally{ if(btn) btn.disabled=false; }
    }
    $(function(){
        cargarDatos();
        $('#selModelo').on('change', function(){
            if(modoModal!=='crear'){ pintarNuevoModeloInput(false); return; }
            pintarNuevoModeloInput(this.value==='__nuevo__');
        });
        $('#btnNuevoModeloRapido').on('click', function(){
            modoModal='crear';
            $('#selModelo').val('__nuevo__').trigger('change');
            setTimeout(function(){ var i=document.getElementById('modModeloNuevo'); if(i) i.focus(); }, 120);
        });
        $('#btnGuardarCombo').on('click', guardarCombo);
        $('#eliminarConfirmInput').on('input', validarConfirmEliminar);
        $('#btnConfirmarEliminarCombo').on('click', eliminarCombo);
    });
    </script>
</body>
</html>
