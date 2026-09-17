<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/*$tienellave = in_array($_SESSION['tipo'], [1, 7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su director(a) institucional para que se los brinde, si es usted prestador o inventariador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
*/
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
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
    . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];
*/
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar activos</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <style>
            .card-custom {
                background-color: #f0f4f8; /* Color frío y suave */
                border: none;
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
            }
            .card-body {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .card-img {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 50%;
                align-self: flex-end;
            }
            .btn-custom {
                background-color: #007bff; /* Color frío */
                border: none;
                border-radius: 5px;
            }
            .icon-large {
            font-size: 2em; /* Ajusta el tamaño del ícono */
            }
            .filter-form {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .btn-disponibilidad:disabled {
                opacity: 0.4;
                pointer-events: none;
            }
    </style>
</head>
<body class="layout-page">

    <?php include 'partials/header.php'; ?>
    <main class="container mt-5 contenido-principal">
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="hero-icon">
                            <i class="bi bi-pencil-square" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1">Actualización de Tipo de Activo y Fondo Presupuestario</h2>
                            <p class="mb-0 opacity-75">Actualice el ID del activo (tipo) y el fondo presupuestario de su institución</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- <h2>Usuario: <?php //echo $lognombre." ".$logcodigo;?></h2><br>
        <h4>Formulario para seleccionar activos a editar de los centros educativos.</h4> -->
        
        <!-- Formulario de Filtros -->
        <div class="filter-form">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="fondos" class="form-label">Seleccione Fondo:</label>
                        <select class="form-select" id="fondos" name="fondos" required>
                            <option value="0">Seleccione..</option>
                            <?php 
                                $querz = $link->query("SELECT * FROM t_fondos");
                                while ($valorez = mysqli_fetch_array($querz)) {
                                    echo '<option value="'.$valorez['id_fondos'].'">'.$valorez['fondos'].'</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="codigo_centro" class="form-label">Código del Centro:</label>
                        <input type="text" class="form-control" id="codigo_centro" name="codigo_centro" 
                            placeholder="Ingrese el código del centro educativo">
                        <div class="form-text small fst-italic text-muted opacity-75">El código debe ser de 4 dígitos. Si tiene 3 dígitos, agregue un cero a la izquierda (ej. 0315).</div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <button type="button" class="btn btn-primary" onclick="cargarActivos()">
                        <i class="bi bi-search"></i> Buscar Activos
                    </button>
                </div>
            </div>
        </div>

        <form action="herramienta_editar_activos_n.php" method="POST">
            <input type="hidden" name="subsistema_id" value="<?= intval($_GET['subsistema_id'] ?? 0) ?>">
            <input type="hidden" name="modulo_id" value="<?= intval($_GET['modulo_id'] ?? 0) ?>">
            <div id="mostraractivos">
                <!-- Aquí se cargará dinámicamente la tabla -->
            </div>

            <!-- Botón flotante Actualizar Seleccionados (arriba del botón Regresar) -->
            <button type="submit" class="btn-disponibilidad" id="btn-flotante-actualizar"
                    name="btnEditar" value="1" disabled
                    style="bottom: 170px;" data-tooltip="Actualizar Seleccionados (0)">
                <i class="bi bi-pencil-square"></i>
            </button>
        </form>

        
        </main>


    <!-- <footer class="bg-dark text-white pt-4 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0">Por favor, asegúrese de ingresar la información solicitada en cada instancia.</p>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12 text-center">
                    <div class="border border-light p-3">
                        <p class="mb-0">© 2024 Ministerio de Educación Pública. Todos los derechos reservados.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer> -->

    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    
    <script>
        function cargarActivos() {
            var fondosId = document.getElementById('fondos').value;
            var codigoCentro = document.getElementById('codigo_centro').value.trim();

            // Validar que al menos un filtro esté seleccionado
            if (fondosId == 0 && codigoCentro == '') {
                alert('Por favor, seleccione al menos un filtro (fondo o código del centro)');
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'herramienta_datos_seleccionados_para_editar_n.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById('mostraractivos').innerHTML = xhr.responseText;
                    // Reset del botón flotante al cargar nuevos resultados
                    var btnFlotante = document.getElementById('btn-flotante-actualizar');
                    if (btnFlotante) {
                        btnFlotante.disabled = true;
                        btnFlotante.setAttribute('data-tooltip', 'Actualizar Seleccionados (0)');
                    }
                }
            };
            
            // Enviar ambos parámetros
            var params = 'fondos=' + encodeURIComponent(fondosId) + 
                        '&codigo_centro=' + encodeURIComponent(codigoCentro);
            xhr.send(params);
        }

        document.getElementById('codigo_centro').addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                cargarActivos();
            }
        });
    </script>

    <script>
        document.addEventListener('click', function(event) {
            if (event.target && event.target.id === 'btnMarcar') {
                var confirmation = confirm("¿Realmente desea eliminar? El proceso será irreversible.");
                if (!confirmation) {
                    event.preventDefault(); // Evita que el formulario se envíe si el usuario cancela
                }
            }
        });
    </script>

    <script>
        $(document).ready(function() {
            // Actualiza estado del botón flotante y su contador (patrón herramienta_editar_masiva_n.php)
            function actualizarEstadoBoton() {
                var cantidad = $('.check-editar:checked').length;
                var btn = $('#btn-flotante-actualizar');
                btn.prop('disabled', cantidad === 0);
                btn.attr('data-tooltip', 'Actualizar Seleccionados (' + cantidad + ')');
            }

            // Habilitar/deshabilitar campos de edición según el check "Seleccionar"
            // (delegación: la tabla se carga vía AJAX)
            $(document).on('change', '.check-editar', function() {
                var fila = $(this).closest('tr');
                var habilitar = this.checked;
                fila.find('.campo-edicion').prop('disabled', !habilitar);
                actualizarEstadoBoton();
            });

            // Sincronización bidireccional por fila: Nuevo ID Fondo <-> Fondo Presupuestario
            // Desplegable -> Numérico
            $(document).on('change', '.fondo-presupuestario', function() {
                var fila = $(this).closest('tr');
                var idPlaca = $(this).data('id');
                fila.find('input[name="nuevo_id_fondos[' + idPlaca + ']"]').val($(this).val());
            });

            // Numérico -> Desplegable
            $(document).on('change', '.nuevo-id-fondo', function() {
                var fila = $(this).closest('tr');
                var select = fila.find('.fondo-presupuestario');
                var valor = $(this).val();
                if (valor !== '' && select.find('option[value="' + valor + '"]').length > 0) {
                    select.val(valor);
                } else {
                    select.val('');
                }
            });
        });
    </script>

    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <?php include 'partials/footer.php'; ?>

</body>
</html>