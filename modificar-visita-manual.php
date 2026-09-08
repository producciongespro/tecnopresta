<?php
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
 
if (!$usuario_azure) {
   header("Location: index.html");
   exit();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
 
if (!$usuario_azure) {
   header("Location: index.html");
   exit();
}

?>
<?php include 'partials/header.php';?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoPresta - Modificar visita</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/nueva-identidad.css?v=2">
    <style>
        .btn-flotante-regresar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,.25);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: transform 0.2s;
            z-index: 1000;
        }
        .btn-flotante-regresar:hover {
            transform: scale(1.1);
            color: #fff;
        }
        .btn-flotante-regresar i { font-size: 28px; }
    </style>
</head>
<body>

<a href="navegar.php?ruta=coordinacion_visitas.php&subsistema_id=2&modulo_id=8&formulario_id=25" class="btn-flotante-regresar" title="Volver al panel general">
    <i class="bi bi-arrow-left-circle-fill"></i>
</a>

<div class="container">

    <h4 class="text-center mt-2">Modificar Visita Técnica</h4>

    <div class="row">
        <div class="col text-center my-2">
            <div id="divError" class="alert alert-danger" style="display: none;"></div>
        </div>
    </div>

    <form id="formModificarVisita" class="card">
        <div class="card-body">
            <input type="hidden" id="id_visita">

            <h5>Datos de institución</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="codigo_institucion" class="form-label">Código de institución</label>
                    <input type="text" class="form-control" id="codigo_institucion" required>
                </div>
                <div class="col-md-8">
                    <label for="nombre_institucion" class="form-label">Nombre de institución</label>
                    <input type="text" class="form-control" id="nombre_institucion" required>
                </div>
                <div class="col-md-4">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" required>
                </div>
                <div class="col-md-4">
                    <label for="correo_institucional" class="form-label">Correo institucional</label>
                    <input type="email" class="form-control" id="correo_institucional" required>
                </div>
                <div class="col-md-4">
                    <label for="persona_contacto" class="form-label">Persona de contacto</label>
                    <input type="text" class="form-control" id="persona_contacto" required>
                </div>
                <div class="col-12">
                    <label for="direccion" class="form-label">Dirección</label>
                    <textarea class="form-control" id="direccion" rows="2" required></textarea>
                </div>
            </div>

            <h5 class="mt-4">Datos de la visita</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="fecha_visita" class="form-label">Fecha de visita</label>
                    <input type="date" class="form-control" id="fecha_visita" required>
                </div>
                <div class="col-md-6">
                    <label for="hora_visita" class="form-label">Hora de visita</label>
                    <input type="time" class="form-control" id="hora_visita" required>
                </div>
                <div class="col-12">
                    <label for="descripcion_problema" class="form-label">Descripción del problema</label>
                    <textarea class="form-control" id="descripcion_problema" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <label for="labor_realizar" class="form-label">Labor a realizar</label>
                    <textarea class="form-control" id="labor_realizar" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observaciones" rows="3"></textarea>
                </div>
            </div>

            <h5 class="mt-4">Orígenes presupuestarios</h5>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Seleccione uno o varios orígenes presupuestarios</label>
                    <div id="fondos" class="border rounded p-2" style="max-height: 220px; overflow-y: auto;"></div>
                </div>
            </div>

            <h5 class="mt-4">Soportistas asignados</h5>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Seleccione uno o varios soportistas</label>
                    <div id="soportistas" class="border rounded p-2" style="max-height: 220px; overflow-y: auto;"></div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notificar_centro">
                        <label class="form-check-label" for="notificar_centro">Enviar Notificación al Centro Educativo</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notificar_soportistas">
                        <label class="form-check-label" for="notificar_soportistas">Enviar Notificación a los Ingenieros Asignados</label>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
            </div>
        </div>
    </form>

</div>

<?php include 'partials/footer.php'; ?>

<!-- Modal resultado modificación de visita -->
<div class="modal fade" id="modalResultado" tabindex="-1" aria-labelledby="modalResultadoTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalResultadoTitulo"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="modalResultadoMensaje"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnModalAceptar" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', inicializarModificarVisita);

function mostrarErrorPagina(mensaje) {
    const divError = document.getElementById('divError');
    divError.innerHTML = mensaje;
    divError.style.display = 'block';
}

async function inicializarModificarVisita() {
    const params = new URLSearchParams(window.location.search);
    const idVisita = params.get('id_visita');

    if (!idVisita) {
        mostrarErrorPagina('No se recibió el identificador de la visita.');
        document.getElementById('formModificarVisita').style.display = 'none';
        return;
    }

    document.getElementById('id_visita').value = idVisita;

    await Promise.all([
        cargarFondos(),
        cargarSoportistas()
    ]);

    await precargarDatosVisita(idVisita);

    document.getElementById('formModificarVisita').addEventListener('submit', guardarVisita);
}

async function precargarDatosVisita(idVisita) {
    try {
        const response = await fetch(`/api/select-visita-manual-detalle.php?id_visita=${encodeURIComponent(idVisita)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();

        if (data.success !== true || !data.visita) {
            mostrarErrorPagina(data.message || 'No se pudo cargar la visita');
            document.getElementById('formModificarVisita').style.display = 'none';
            return;
        }

        const visita = data.visita;

        document.getElementById('codigo_institucion').value = visita.codigo_institucion || '';
        document.getElementById('nombre_institucion').value = visita.nombre_institucion || '';
        document.getElementById('telefono').value = visita.telefono || '';
        document.getElementById('correo_institucional').value = visita.correo_institucional || '';
        document.getElementById('direccion').value = visita.direccion || '';
        document.getElementById('persona_contacto').value = visita.persona_contacto || '';
        document.getElementById('fecha_visita').value = visita.fecha_visita || '';
        document.getElementById('hora_visita').value = visita.hora_visita || '';
        document.getElementById('descripcion_problema').value = visita.descripcion_problema || '';
        document.getElementById('labor_realizar').value = visita.labor_realizar || '';
        document.getElementById('observaciones').value = visita.observaciones || '';

        marcarSeleccionados('fondos', data.id_fondos || []);
        marcarSeleccionados('soportistas', data.id_soportistas || []);

    } catch (error) {
        console.error('Error:', error);
        mostrarErrorPagina('Error al conectar con el servidor para cargar la visita');
    }
}

function marcarSeleccionados(contenedorId, idsSeleccionados) {
    const idsTexto = idsSeleccionados.map(id => String(id));
    const contenedor = document.getElementById(contenedorId);
    contenedor.querySelectorAll('input[type="checkbox"]').forEach(input => {
        if (idsTexto.includes(input.value)) {
            input.checked = true;
        }
    });
}

function crearCheckbox(prefijo, valor, texto) {
    const wrapper = document.createElement('div');
    wrapper.className = 'form-check';

    const input = document.createElement('input');
    input.className = 'form-check-input';
    input.type = 'checkbox';
    input.value = valor;
    input.id = `${prefijo}_${valor}`;

    const label = document.createElement('label');
    label.className = 'form-check-label';
    label.setAttribute('for', input.id);
    label.textContent = texto;

    wrapper.appendChild(input);
    wrapper.appendChild(label);
    return wrapper;
}

async function cargarFondos() {
    try {
        const response = await fetch('/api/select-fondos.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();

        const contenedor = document.getElementById('fondos');
        contenedor.innerHTML = '';

        if (data.success === true && Array.isArray(data.fondos)) {
            data.fondos.forEach(fondo => {
                contenedor.appendChild(crearCheckbox('fondo', fondo.id_fondos, fondo.fondos));
            });
        } else {
            mostrarErrorPagina(data.message || 'No se pudieron cargar los orígenes presupuestarios');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarErrorPagina('Error al conectar con el servidor para cargar los orígenes presupuestarios');
    }
}

async function cargarSoportistas() {
    try {
        const response = await fetch('/api/select-soportistas.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();

        const contenedor = document.getElementById('soportistas');
        contenedor.innerHTML = '';

        if (data.success === true && Array.isArray(data.soportistas)) {
            data.soportistas.forEach(soportista => {
                contenedor.appendChild(crearCheckbox('soportista', soportista.id_soportista, soportista.nombre));
            });
        } else {
            mostrarErrorPagina(data.message || 'No se pudieron cargar los soportistas');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarErrorPagina('Error al conectar con el servidor para cargar los soportistas');
    }
}

function obtenerSeleccionados(contenedorId) {
    const contenedor = document.getElementById(contenedorId);
    return Array.from(contenedor.querySelectorAll('input[type="checkbox"]:checked')).map(input => input.value);
}

async function guardarVisita(event) {
    event.preventDefault();

    const btnGuardar = document.getElementById('btnGuardar');
    btnGuardar.disabled = true;

    try {
        const idsFondos = obtenerSeleccionados('fondos');
        const idsSoportistas = obtenerSeleccionados('soportistas');

        if (idsFondos.length === 0) {
            mostrarModalResultado(false, 'Debe seleccionar al menos un origen presupuestario.');
            btnGuardar.disabled = false;
            return;
        }

        if (idsSoportistas.length === 0) {
            mostrarModalResultado(false, 'Debe seleccionar al menos un soportista.');
            btnGuardar.disabled = false;
            return;
        }

        const payload = {
            id_visita: document.getElementById('id_visita').value,
            codigo_institucion: document.getElementById('codigo_institucion').value,
            nombre_institucion: document.getElementById('nombre_institucion').value,
            telefono: document.getElementById('telefono').value,
            correo_institucional: document.getElementById('correo_institucional').value,
            direccion: document.getElementById('direccion').value,
            persona_contacto: document.getElementById('persona_contacto').value,
            fecha_visita: document.getElementById('fecha_visita').value,
            hora_visita: document.getElementById('hora_visita').value,
            descripcion_problema: document.getElementById('descripcion_problema').value,
            labor_realizar: document.getElementById('labor_realizar').value,
            observaciones: document.getElementById('observaciones').value,
            arreglo_id_fondos: idsFondos.join(','),
            soportistas: idsSoportistas.map(id => parseInt(id, 10)),
            notificar_centro: document.getElementById('notificar_centro').checked,
            notificar_soportistas: document.getElementById('notificar_soportistas').checked
        };

        const response = await fetch('/api/modificar-visita-manual.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();

        if (data.success === true) {
            mostrarModalResultado(true, data.message || 'La visita se actualizó satisfactoriamente');
        } else {
            mostrarModalResultado(false, data.message || 'Error al actualizar la visita');
            btnGuardar.disabled = false;
        }

    } catch (error) {
        console.error('Error:', error);
        mostrarModalResultado(false, 'Error al conectar con el servidor');
        btnGuardar.disabled = false;
    }
}

function mostrarModalResultado(exito, mensaje) {
    const modalEl = document.getElementById('modalResultado');
    const titulo = document.getElementById('modalResultadoTitulo');
    const texto = document.getElementById('modalResultadoMensaje');
    const btnAceptar = document.getElementById('btnModalAceptar');

    titulo.textContent = exito ? 'Éxito' : 'Error';
    texto.textContent = mensaje;

    if (exito) {
        btnAceptar.onclick = function() {
            window.location.href = 'navegar.php?ruta=coordinacion_visitas.php&subsistema_id=2&modulo_id=8&formulario_id=25';
        };
    } else {
        btnAceptar.onclick = null;
    }

    let bsModal = bootstrap.Modal.getInstance(modalEl);
    if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
}
</script>

</body>
</html>
