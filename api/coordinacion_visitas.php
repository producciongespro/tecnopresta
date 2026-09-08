<?php include 'partials/header.php';?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoPresta - Coordinador</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/nueva-identidad.css">
    <style>

        .no-access {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 20px;
        }

        .no-access i {
            font-size: 64px;
            color: var(--mep-accent);
            margin-bottom: 20px;
        }

        .btn-disponibilidad {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--mep-primary);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-disponibilidad:hover {
            transform: scale(1.1);
            background: var(--mep-secondary);
            color: white;
        }

        .btn-disponibilidad::before {
            content: "Ir al inicio";
            position: absolute;
            right: 70px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            font-family: 'Henderson Sans', Arial, sans-serif;
        }

        .btn-disponibilidad:hover::before {
            opacity: 1;
        }

        @media (max-width: 768px) {

            .btn-disponibilidad {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .btn-disponibilidad::before {
                display: none;
            }
        }

    </style>
</head>
<body>

<a class="btn btn-light mt-2" href="coordinacion_dashboard.php" role="button"><i class="bi bi-arrow-left-circle me-1"></i> Regresar</a>

<div class="container">

    <div id="aviso-permiso" class="no-access" style="display: none;">
        <i class="bi bi-shield-slash"></i>
        <h3 class="mt-3">Acceso Restringido</h3>
        <p class="text-muted">No tienes permisos asignados.</p>
        <p class="text-muted small">Verifica con tu supervisor que tu perfil esté correctamente configurado.</p>
        <a href="coordinacion_dashboard.php" class="btn btn-primary mt-3">Volver al Menú</a>
    </div>

</div>

<div id="panel-principal" class="container" style="display:none;">
    
    <div class="text-center my-3">
        <a href="crear-visita-manual.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Crear Asignación de Visita Técnica
        </a>
    </div>

    <div class="text-center my-3">
        <div class="btn-group" role="group">
            <button id="btn-filtro-asignadas" type="button" class="btn btn-primary">
                <i class="bi bi-person-check me-1"></i>Visitas Asignadas
            </button>
            <button id="btn-filtro-cerrados" type="button" class="btn btn-outline-primary">
                <i class="bi bi-check-circle me-1"></i>Casos Cerrados
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col text-center my-2">
            <div id="divError" class="alert alert-danger" style="display: none;"></div>
        </div>
    </div>

    <div class="container" id="visitas-container"></div>

</div>

<?php include 'partials/footer.php'; ?>

<!-- Botón flotante Home -->
<a href="formulario_menu_principal.html" class="btn-disponibilidad" style="bottom: 100px;" title="Ir al inicio">
    <i class="bi bi-house-fill"></i>
</a>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script>
let filtroActual = 1;

document.addEventListener('DOMContentLoaded', valida_si_es_Soportista);

async function valida_si_es_Soportista() {
    try {
        const cedula = obtener_cedula();

        if (!cedula) {
            document.getElementById('aviso-permiso').style.display = 'block';
            document.getElementById('panel-principal').style.display = 'none';
            return false;
        }

        const url = `/api/select-soportista-cedula.php?cedula=${encodeURIComponent(cedula)}`;
        const response = await fetch(url, { method: 'GET', headers: { 'Content-Type': 'application/json' } });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();

        let id_soportista = 0;
        if (data.success === true && Array.isArray(data.soportista) && data.soportista.length>0) {
            id_soportista = data.soportista[0]['id_soportista'];
        }

        if (id_soportista === 0) {
            document.getElementById('aviso-permiso').style.display = 'block';
            document.getElementById('panel-principal').style.display = 'none';
            return false;
        }

        const tienePermiso4 = await valida_grupo(id_soportista);
        if (!tienePermiso4 || (Array.isArray(tienePermiso4) && tienePermiso4.length === 0)) {
            document.getElementById('aviso-permiso').style.display = 'block';
            document.getElementById('panel-principal').style.display = 'none';
            return false;
        }

        document.getElementById('aviso-permiso').style.display = 'none';
        document.getElementById('panel-principal').style.display = 'block';

        await cargarVisitas(1);

        return true;

    } catch (error) {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
        return false;
    }
}

function obtener_cedula() {
    const userData = window.sessionStorage.getItem('sesion');
    if (userData && userData.length>0) {
        try {
            const jsonData = JSON.parse(userData);
            return jsonData['EMPCED'] || false;
        } catch (e) {
            return false;
        }
    }
    return false;
}

async function valida_grupo(id_soportista) {
    try {
        const url = `/api/select-soportista-id-clasificacion-grupo-4.php?id_soportista=${encodeURIComponent(id_soportista)}`;
        const response = await fetch(url, { method: 'GET', headers: { 'Content-Type': 'application/json' } });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();
        if (data.success === true) return data.soportistaclasificacion;
        return false;
    } catch (error) {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
        return false;
    }
}

function escaparHTML(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function mostrarErrorVisitas(mensaje) {
    const divError = document.getElementById('divError');
    divError.innerHTML = mensaje;
    divError.style.display = 'block';
}

function ocultarErrorVisitas() {
    const divError = document.getElementById('divError');
    divError.style.display = 'none';
    divError.innerHTML = '';
}

function mostrarSpinnerVisitas() {
    document.getElementById('visitas-container').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
}

document.getElementById('btn-filtro-asignadas').addEventListener('click', () => cargarVisitas(1));
document.getElementById('btn-filtro-cerrados').addEventListener('click', () => cargarVisitas(3));

async function cargarVisitas(estado) {
    filtroActual = estado;
    ocultarErrorVisitas();
    mostrarSpinnerVisitas();

    document.getElementById('btn-filtro-asignadas').className = estado === 1 ? 'btn btn-primary' : 'btn btn-outline-primary';
    document.getElementById('btn-filtro-cerrados').className = estado === 3 ? 'btn btn-primary' : 'btn btn-outline-primary';

    try {
        const response = await fetch(`/api/select-visitas-manual.php?estado=${encodeURIComponent(estado)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        const data = await response.json();

        if (data.success !== true) {
            mostrarErrorVisitas(data.message || 'Error al cargar visitas');
            return;
        }

        renderizarVisitas(data.visitas, estado);

    } catch (error) {
        console.error('Error:', error);
        mostrarErrorVisitas('Error al conectar con el servidor');
    }
}

function renderizarVisitas(visitas, estado) {
    const container = document.getElementById('visitas-container');
    container.innerHTML = '';

    if (!Array.isArray(visitas) || visitas.length === 0) {
        container.innerHTML = '<div class="col-12 text-center py-4 text-muted">No hay visitas para mostrar.</div>';
        return;
    }

    visitas.forEach(visita => {
        let botonesHTML = '';

        if (estado === 1) {
            botonesHTML = `
                <button class="btn btn-primary" data-id="${escaparHTML(visita.id_visita)}" onclick="editarVisita(this.dataset.id)">
                    Editar
                </button>
            `;
        }

        const card = document.createElement('div');
        card.className = 'card mb-3';
        card.innerHTML = `
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-12"><strong>Fecha de visita:</strong> ${escaparHTML(visita.fecha_visita)} + " " + ${escaparHTML(visita.hora_visita)}</div>
                    <div class="col-12 col-md-12"><strong>Institución:</strong> ${escaparHTML(visita.nombre_institucion)}</div>
                    <div class="col-12 col-md-12 mt-1"><strong>Descripción:</strong> ${escaparHTML(visita.observaciones)}</div>
                    <div class="col-12 col-md-12 mt-1"><strong>Descripción:</strong> ${escaparHTML(visita.descripcion_problema)}</div>
                    <div class="col-12 col-md-12 mt-3 text-center d-flex gap-2 justify-content-center">
                        ${botonesHTML}
                    </div>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

function editarVisita(idVisita) {
    window.location.href = `modificar-visita-manual.php?id_visita=${encodeURIComponent(idVisita)}`;
}
</script>
    </body>

</html>
