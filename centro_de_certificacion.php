<?php
/** session_start();
$tienellave = in_array($_SESSION['tipo'], [1, 2]);

if ($tienellave == false) {
    echo '<script language="javascript">
        alert("No tienes permisos");
        self.location = "index.html";
    </script>';
    exit;
} */

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

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
    exit;
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

date_default_timezone_set('America/Costa_Rica');
/** $logusuario = $_SESSION['cedula']; */
$lognombre = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
/** $logtipo = $_SESSION['tipo']; */
$logcodigo = $usuario_azure['codigoPresu'] ?? null; 
$loginstitucion = $usuario_azure['dependencia'] ?? null;
/** $logcorreo = $_SESSION['correomep'];
$regionallog = $_SESSION['direccionreg'];
$circuitolog = $_SESSION['circuito']; */

$time = time();
$fecha = date("d-m-Y", $time);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    
    <!-- Bootstrap CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

    <title>Certificación de Inventario</title>

    <style>
        @font-face {
            font-family: 'Henderson Sans';
            src: url('assets/fuentes/HendersonSansW00-BasicLight.woff2') format('woff2'),
                 url('assets/fuentes/HendersonSansW00-BasicLight.woff') format('woff');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Henderson Sans';
            src: url('assets/fuentes/HendersonSansW00-BasicSmBd.woff2') format('woff2'),
                 url('assets/fuentes/HendersonSansW00-BasicSmBd.woff') format('woff');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'Henderson Sans';
            src: url('assets/fuentes/HendersonSansW00-BasicBold.woff2') format('woff2'),
                 url('assets/fuentes/HendersonSansW00-BasicBold.woff') format('woff');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        body {
            font-family: 'Henderson Sans', sans-serif;
        }
        
        .btn-flotante-regresar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-flotante-regresar:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            color: white;
        }
        
        .btn-flotante-regresar i {
            font-size: 32px;
        }
        
        .btn-flotante-regresar::before {
            content: "Volver al menú principal";
            position: absolute;
            right: 70px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .btn-flotante-regresar:hover::before {
            opacity: 1;
            visibility: visible;
        }
        
        @media (max-width: 768px) {
            .btn-flotante-regresar {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
            }
            .btn-flotante-regresar i {
                font-size: 26px;
            }
            .btn-flotante-regresar::before {
                display: none;
            }
        }
        
        .alert-conciencia {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .btn-flotante-administrativo {
            position: fixed;
            bottom: 100px;  /* ARRIBA del otro (que está en 30px) */
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-flotante-administrativo:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            color: white;
        }
        
        .btn-flotante-administrativo i {
            font-size: 32px;
        }
        
        .btn-flotante-administrativo::before {
            content: "Administrar certificaciones";
            position: absolute;
            right: 70px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .btn-flotante-administrativo:hover::before {
            opacity: 1;
            visibility: visible;
        }
        
        @media (max-width: 768px) {
            .btn-flotante-administrativo {
                bottom: 90px;
                right: 20px;
                width: 50px;
                height: 50px;
            }
            .btn-flotante-administrativo i {
                font-size: 26px;
            }
            .btn-flotante-administrativo::before {
                display: none;
            }
        }
    </style>
</head>
<body>
<?php include 'partials/header.php'; ?>

<div class="container mb-5">
    
    <!-- NUEVO BOTÓN ADMINISTRATIVO - ARRIBA DEL FLOTANTE -->
    <a href="navegar.php?ruta=administrativo_certificaciones.php" class="btn-flotante-administrativo" id="btnAdministrativo" title="Administrar certificaciones">
        <i class="bi bi-list-ul"></i>
    </a>
    
    <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=1&modulo_id=4" class="btn-flotante-regresar" id="btnRegresar" title="Volver al menú principal">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <h1 class="mt-5 mb-4 text-center">Certificación de Inventario de Activos</h1>
    
    <div class="alert alert-warning border-start border-4 border-warning shadow-sm alert-conciencia" role="alert" style="background-color: #fff9e6;">
        <div class="d-flex align-items-start">
            <div class="flex-shrink-0">
                <i class="bi bi-shield-check" style="font-size: 2rem; color: #856404;"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h5 class="alert-heading mb-2">Certificación de Inventario | Compromiso Profesional</h5>
                <p class="mb-2">
                    Estimado(a) <strong><?php echo htmlspecialchars($lognombre); ?></strong>, usted está a punto de certificar el inventario de activos 
                    asignados a <strong><?php echo htmlspecialchars($loginstitucion); ?></strong>.
                </p>
                <p class="mb-2">
                    <i class="bi bi-check-circle-fill text-success"></i> Al marcar cada casilla <strong>"Revisado físicamente"</strong>, 
                    usted declara bajo fe de juramento haber verificado personalmente que el activo existe, 
                    que sus datos coinciden con la realidad física.
                </p>
                <p class="mb-2">
                    <i class="bi bi-phone"></i> La firma se realizará mediante <strong>GAUDI Móvil</strong> o <strong>GAUDI Desktop</strong>, 
                    el software oficial del Banco Central de Costa Rica.
                </p>
                <p class="mb-0 small text-muted">
                    <i class="bi bi-info-circle"></i> Si algún activo no existe físicamente, NO lo marque. El sistema no permitirá 
                    certificar hasta que se gestione su baja en el inventario oficial.
                </p>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <form action="navegar.php?ruta=crear_certificacion_para_firmar.php" method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fondos" class="form-label">Seleccione Fuente Presupuestaria:</label>
                            <select class="form-select" id="fondos" name="fondos" required>
                                <option value="0">Seleccione..</option>
                                <?php 
                                    $querz = $link->query("SELECT * FROM t_fondos");
                                    while ($valorez = mysqli_fetch_array($querz)) {
                                        echo '<option value="'.$valorez['id_fondos'].'">'.htmlspecialchars($valorez['fondos']).'</option>';
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Centro Educativo:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($loginstitucion); ?>" disabled>
                            <input type="hidden" name="codigo_centro" value="<?php echo htmlspecialchars($logcodigo); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Cargar Activos
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>