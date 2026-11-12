<?php
if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
/*
$tienellave = in_array($_SESSION['tipo'], [1, 2, 3, 4]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su director(a) institucional para que se los brinde, si es usted prestador o inventariador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
    */
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

date_default_timezone_set('America/Costa_Rica'); //timezone Costa Rica
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logcodigo = $_SESSION['codigo'];
*/
$logcodigo = $usuario_azure['codigoPresu'];
$logregional = $usuario_azure['regional'];
$logdependencia = $usuario_azure['dependencia'];
$logcircuito = $usuario_azure['circuito'];
/*
$logdependencia = $_SESSION['dependencia'];
$logregional = $_SESSION['direccionreg'];
$logcircuito = $_SESSION['circuito'];*/
$fechaHoraServidor = date('Y-m-d H:i:s');

require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno())

{

echo "Error de conexion a mysql: " . mysqli_connect_error();

}

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {

}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">

    <title>Recepción</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous"> -->
    <!-- 11-6-26 <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet"> -->
    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css?v=20260924_2">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <!-- ICONOS -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> -->
    <!-- <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet"> -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">


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
    </style>
</head>
<body class="layout-page">
  <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">
    
        <div class="container mt-5">
            <h2>Formulario para el envío de archivos de actas de recepción de equipos tecnológicos</h2>

            <div class="alert alert-info border-start border-4 border-info shadow-sm my-3 w-75" role="alert">
                <h6 class="alert-heading fw-bold mb-1">
                    <i class="bi bi-info-circle-fill me-1"></i> Recomendación para optimizar el espacio en el servidor
                </h6>
                <p class="mb-1 small">
                    Para cuidar el almacenamiento disponible, le invitamos a digitalizar su acta en formato <strong>PDF optimizado</strong> (resolución recomendada de <strong>150 a 200 DPI</strong> en escala de grises o blanco y negro).
                </p>
                <p class="mb-0 small">
                    <i class="bi bi-file-earmark-pdf-fill text-danger"></i> <strong>Límite permitido:</strong> Se asignan <strong>250 KB por cada página</strong> del documento (hasta un máximo absoluto de <strong>5 MB</strong>).
                </p>
            </div>

            <form id="formSubirActa" action="in_procesamiento_recepcion_acta_n.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="cantidad_paginas" class="form-label">Cantidad de páginas del documento:</label>
                    <input type="number" class="form-control w-50" id="cantidad_paginas" name="cantidad_paginas" min="1" max="100" value="1" placeholder="Ej: 2" required>
                    <div id="infoLimitePeso" class="form-text text-primary fw-semibold">
                        Peso máximo permitido para 1 página(s): 250 KB (0.24 MB)
                    </div>
                </div>

                <div class="mb-3">
                    <label for="archivo" class="form-label">Subir archivo PDF:</label>
                    <input class="form-control w-50" type="file" id="archivo" name="archivo" accept="application/pdf,.pdf" required>
                    <div id="infoArchivoSeleccionado" class="form-text"></div>
                </div>

                <div class="mb-3">
                    <label for="etiqueta" class="form-label">Etiqueta descriptiva:</label>
                    <input type="text" class="form-control w-50" id="etiqueta" name="etiqueta" placeholder="Ingrese una etiqueta o descripción" required>
                </div>

                <div class="mb-3">
                    <label for="fondos" class="form-label">Origen Presupuestario</label>
                    <select class="form-select my-3 w-50" id="fondos" name="fondos" aria-label="Seleccione el origen presupuestario" required>
                        
                        <?php 
                        $querz = $link -> query ("SELECT * FROM t_fondos");
                        while ($valorez = mysqli_fetch_array($querz)) {
                            echo '<option value="'.$valorez['id_fondos'].'">'.$valorez['fondos'].'</option>';
                        }
                        ?>
                    </select>
                </div>

                <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($logcodigo ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="dependencia" value="<?php echo htmlspecialchars($logdependencia ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="regional" value="<?php echo htmlspecialchars($logregional ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="circuito" value="<?php echo htmlspecialchars($logcircuito ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="fecha_hora" value="<?php echo htmlspecialchars($fechaHoraServidor ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="subsistema_id" value="<?php echo intval($_GET['subsistema_id'] ?? 0); ?>">
                <input type="hidden" name="modulo_id" value="<?php echo intval($_GET['modulo_id'] ?? 0); ?>">
            
                <button type="submit" id="btnGuardarActa" class="btn btn-primary mb-3">
                    <i class="bi bi-save"></i> Guardar
                </button>

            </form>

            <?php
                $codigoEscapado = $link->real_escape_string($logcodigo);
                $sql = "SELECT id_rae, etiqueta, archivo, fecha FROM t_recepcion_acta_equipo WHERE codigo = '$codigoEscapado' ORDER BY fecha DESC";
                $result = $link->query($sql);

                if ($result && $result->num_rows > 0) {
                    echo '<table class="table table-striped">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>ID</th>';
                    echo '<th>Etiqueta</th>';
                    echo '<th>Archivo</th>';
                    echo '<th>Fecha</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    while($row = $result->fetch_assoc()) {
                        // Codificar cada segmento de la ruta para evitar errores 404 con espacios, # o caracteres especiales
                        $partesRuta = explode('/', str_replace('\\', '/', $row['archivo']));
                        $partesCodificadas = array_map('rawurlencode', $partesRuta);
                        $urlArchivoSegura = implode('/', $partesCodificadas);

                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id_rae'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($row['etiqueta'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td><a href="' . htmlspecialchars($urlArchivoSegura, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer"><i class="bi bi-file-earmark-pdf"></i> Ver Archivo</a></td>';
                        echo '<td>' . htmlspecialchars($row['fecha'], ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo "No se encontraron resultados.";
                }

                $link->close();
            ?>

        </div>

        <!-- Botón flotante Volver -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
            style="bottom: 100px;" data-tooltip="Regresar">
                <i class="bi bi-arrow-left-circle-fill"></i>
        </a>

    </main>
    <script>
    (function() {
        const KB_POR_PAGINA = 250; // 250 KB por página
        const MAX_MB_ABSOLUTO = 5; // Tope máximo de 5 MB
        const BYTES_POR_PAGINA = KB_POR_PAGINA * 1024;
        const MAX_BYTES_ABSOLUTO = MAX_MB_ABSOLUTO * 1024 * 1024;

        const inputPaginas = document.getElementById('cantidad_paginas');
        const inputArchivo = document.getElementById('archivo');
        const infoLimitePeso = document.getElementById('infoLimitePeso');
        const infoArchivo = document.getElementById('infoArchivoSeleccionado');
        const form = document.getElementById('formSubirActa');

        function obtenerLimiteBytes() {
            let paginas = parseInt(inputPaginas.value, 10);
            if (isNaN(paginas) || paginas < 1) {
                paginas = 1;
            }
            return Math.min(paginas * BYTES_POR_PAGINA, MAX_BYTES_ABSOLUTO);
        }

        function formatearPeso(bytes) {
            const kb = bytes / 1024;
            const mb = kb / 1024;
            if (kb >= 1024) {
                return kb.toFixed(0) + ' KB (' + mb.toFixed(2) + ' MB)';
            }
            return kb.toFixed(0) + ' KB (' + mb.toFixed(2) + ' MB)';
        }

        function actualizarMensajeLimite() {
            let paginas = parseInt(inputPaginas.value, 10);
            if (isNaN(paginas) || paginas < 1) {
                infoLimitePeso.textContent = 'Ingrese una cantidad válida de páginas (mínimo 1).';
                infoLimitePeso.className = 'form-text text-danger fw-semibold';
                return;
            }
            const limiteBytes = obtenerLimiteBytes();
            infoLimitePeso.textContent = 'Peso máximo permitido para ' + paginas + ' página(s): ' + formatearPeso(limiteBytes);
            infoLimitePeso.className = 'form-text text-primary fw-semibold';
            validarArchivoSeleccionado();
        }

        function validarArchivoSeleccionado() {
            infoArchivo.textContent = '';
            infoArchivo.className = 'form-text';

            if (!inputArchivo.files || inputArchivo.files.length === 0) {
                return true;
            }

            const archivo = inputArchivo.files[0];
            const nombre = archivo.name.toLowerCase();
            const esPdf = nombre.endsWith('.pdf') && (archivo.type === 'application/pdf' || archivo.type === '');

            if (!esPdf) {
                infoArchivo.textContent = 'Error: El archivo seleccionado no es un documento PDF válido (.pdf).';
                infoArchivo.className = 'form-text text-danger fw-bold';
                return false;
            }

            if (archivo.size === 0) {
                infoArchivo.textContent = 'Error: El archivo PDF está vacío o dañado (0 KB).';
                infoArchivo.className = 'form-text text-danger fw-bold';
                return false;
            }

            const limiteBytes = obtenerLimiteBytes();
            if (archivo.size > limiteBytes) {
                infoArchivo.textContent = 'El archivo pesa ' + formatearPeso(archivo.size) + ' y supera el límite permitido de ' + formatearPeso(limiteBytes) + ' para la cantidad de páginas indicada. Por favor optimice o comprima el PDF antes de subirlo.';
                infoArchivo.className = 'form-text text-danger fw-bold';
                return false;
            }

            infoArchivo.textContent = 'Archivo válido: ' + formatearPeso(archivo.size) + ' (dentro del límite permitido).';
            infoArchivo.className = 'form-text text-success fw-semibold';
            return true;
        }

        inputPaginas.addEventListener('input', actualizarMensajeLimite);
        inputPaginas.addEventListener('change', actualizarMensajeLimite);
        inputArchivo.addEventListener('change', validarArchivoSeleccionado);

        form.addEventListener('submit', function(e) {
            const paginas = parseInt(inputPaginas.value, 10);
            if (isNaN(paginas) || paginas < 1 || paginas > 100) {
                e.preventDefault();
                alert('Por favor indique una cantidad de páginas válida (entre 1 y 100).');
                inputPaginas.focus();
                return;
            }

            if (!validarArchivoSeleccionado()) {
                e.preventDefault();
                alert('No se puede subir el archivo. Verifique que sea un documento PDF válido y que su tamaño no supere el límite proporcional a las páginas indicadas.');
                inputArchivo.focus();
            }
        });
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <?php include 'partials/footer.php'; ?>
</body>
</html>