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
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
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
            <form action="in_procesamiento_recepcion_acta_n.php" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="archivo" class="form-label">Subir archivo PDF:</label>
                    <input class="form-control w-50" type="file" id="archivo" name="archivo" accept=".pdf" required>
                </div>

                <div class="mb-3">
                    <label for="etiqueta" class="form-label">Etiqueta descriptiva:</label>
                    <input type="text" class="form-control w-50" id="etiqueta" name="etiqueta" placeholder="Ingrese una etiqueta o descripción" required>
                </div>

                <div class="mb-3">
                    <label for="origen" class="form-label">Origen Presupuestario</label>
                    <select class="form-select my-3 w-50" id="fondos" name="fondos" aria-label="Example select with button addon" required>
                        
                        <?php 
                        $querz = $link -> query ("SELECT * FROM t_fondos");
                        while ($valorez = mysqli_fetch_array($querz)) {
                            echo '<option value="'.$valorez['id_fondos'].'">'.$valorez['fondos'].'</option>';
                        }
                        ?>
                    </select>
                </div>

                <input type="hidden" name="codigo" value="<?php echo $logcodigo; ?>">
                <input type="hidden" name="dependencia" value="<?php echo $logdependencia; ?>">
                <input type="hidden" name="regional" value="<?php echo $logregional; ?>">
                <input type="hidden" name="circuito" value="<?php echo $logcircuito; ?>">
                <input type="hidden" name="fecha_hora" value="<?php echo $fechaHoraServidor; ?>">
            
                <button type="submit" class="btn btn-primary mb-3">
                    <i class="bi bi-save"></i> Guardar
                </button>

            </form>

            <?php
                $sql = "SELECT id_rae, etiqueta, archivo, fecha FROM t_recepcion_acta_equipo WHERE codigo = '$logcodigo' ORDER BY fecha";
                $result = $link->query($sql);

                if ($result->num_rows > 0) {
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
                        echo '<tr>';
                        echo '<td>' . $row['id_rae'] . '</td>';
                        echo '<td>' . $row['etiqueta'] . '</td>';
                        echo '<td><a href="' . $row['archivo'] . '" target="_blank">Ver Archivo</a></td>';
                        echo '<td>' . $row['fecha'] . '</td>';
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <?php include 'partials/footer.php'; ?>
</body>
</html>