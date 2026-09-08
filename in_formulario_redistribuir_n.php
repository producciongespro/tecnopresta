<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/*$tienellave = in_array($_SESSION['tipo'], [1]);
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
} else {

}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// ==== CONSTRUIR RUTA DE REGRESO =====
// Contexto de navegación (se conserva para respetar la ruta seguida por el usuario)
$subsistema_id = isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : 0;
$modulo_id     = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;
$formulario_id = isset($_GET['formulario_id']) ? intval($_GET['formulario_id']) : 0;

// Regresar a la pantalla del subsistema (p. ej. "Intervención sobre Inventario")
$ruta_regreso = 'navegar.php?ruta=formulario_modulos.php&subsistema_id=' . $subsistema_id;
if ($subsistema_id <= 0) {
    $ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
  http_response_code(403);
  exit('Acceso directo no permitido');
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú Minimalista</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <!-- Bootstrap CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        .card-custom {
            background-color: #f0f4f8; /* Color frío y no saturado */
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card-custom .btn {
            background-color: #007bff; /* Color frío para el botón */
            color: white;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <main class="contenido-principal">
        <div class="container mt-5">
            <h3>Formulario de redistribuci&oacute;n de activos</h3>
            <div class="row">
                <!-- Formulario -->
                <form action="in_formulario_para_redistribuir_n.php" method="post">
                    <input type="hidden" name="subsistema_id" value="<?php echo $subsistema_id; ?>">
                    <input type="hidden" name="modulo_id" value="<?php echo $modulo_id; ?>">
                    <input type="hidden" name="formulario_id" value="<?php echo $formulario_id; ?>">
                    <!-- Select -->
                    <div class="mb-3">
                        <label for="fondos" class="form-label">Seleccionar fondo</label>
                        <select class="form-select my-3 w-50" id="fondos" name="fondos" aria-label="Example select with button addon" required>
                            <option value="0">Seleccione..</option>
                            <?php 
                            $querz = $link->query("SELECT * FROM t_fondos");
                            while ($valorez = mysqli_fetch_array($querz)) {
                                echo '<option value="'.$valorez['id_fondos'].'">'.$valorez['fondos'].'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Inputs en columnas -->
                    <div class="row">
                        <!-- Tarjeta 1 -->
                        <div class="col-md-6">
                            <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Código de la Institución de Origen</h5>
                                <p class="card-text">
                                <strong>Descripción:</strong> En este campo, ingrese el código de la institución de la cual se tomarán los activos. Este código identifica de manera única a la institución de origen y es esencial para asegurar que los activos sean correctamente asignados desde la fuente correcta.
                                </p>
                                <p class="card-text">
                                <strong>Ejemplo:</strong> Si está tomando activos de la institución con el código "12345", debe ingresar "12345" en este campo.
                                </p>
                            </div>
                            </div>
                        </div>
                        <!-- Tarjeta 2 -->
                        <div class="col-md-6">
                            <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Código del Centro de Redistribución</h5>
                                <p class="card-text">
                                <strong>Descripción:</strong> En este campo, ingrese el código del centro donde se redistribuirán los activos. Este código es crucial para garantizar que los activos lleguen al destino correcto y se registren adecuadamente en el nuevo centro.
                                </p>
                                <p class="card-text">
                                <strong>Ejemplo:</strong> Si los activos se redistribuirán al centro con el código "67890", debe ingresar "67890" en este campo.
                                </p>
                            </div>
                            </div>
                        </div>
                        <div class="my-3"></div>
                        <!-- Primera columna -->
                        <div class="col-md-6 p-3" style="background-color: #f1f8e9;">
                            <label for="input1" class="form-label">
                                Centro de origen
                                <span class="badge bg-warning text-dark ms-2" title="Escriba LIMBO para rescatar activos sin centro asignado">
                                    ¿Activos sin centro? Escriba: LIMBO
                                </span>
                            </label>
                            <input type="text" class="form-control" id="input1" name="input1" placeholder="Código del centro o LIMBO" required>
                            <small id="result1" class="text-muted"></small> <!-- Etiqueta para el resultado -->
                        </div>
                        <!-- Segunda columna -->
                        <div class="col-md-6 p-3" style="background-color: #fbe9e7;">
                            <label for="input2" class="form-label">Centro de destino</label>
                            <input type="text" class="form-control" id="input2" name="input2" placeholder="Ingrese dato 2" required>
                            <small id="result2" class="text-muted"></small> <!-- Etiqueta para el resultado -->
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                    <button class="btn btn-primary my-3" type="submit">Siguiente</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Botón flotante Volver -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
            style="bottom: 100px;" data-tooltip="Regresar">
                <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </main>
    
<?php include 'partials/footer.php'; ?>

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input1 = document.getElementById('input1');
            const input2 = document.getElementById('input2');
            const result1 = document.getElementById('result1');
            const result2 = document.getElementById('result2');
        
            // Función para realizar la búsqueda
            const fetchInstitution = (input, result) => {
                const codigo = input.value.trim();
                if (codigo.toUpperCase() === 'LIMBO') {
                    result.textContent = '⚠️ Modo rescate: se listarán los activos sin centro asignado.';
                    result.style.color = '#e65100';
                    return;
                }
                if (codigo) {
                    fetch(`buscar_institucion_n.php?codigo=${codigo}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                result.textContent = `Institución: ${data.institucion}`;
                                result.style.color = '';
                            } else {
                                result.textContent = 'No se encontró ninguna institución.';
                                result.style.color = '';
                            }
                        })
                        .catch(error => {
                            result.textContent = 'Error en la búsqueda.';
                            console.error('Error:', error);
                        });
                } else {
                    result.textContent = '';
                    result.style.color = '';
                }
            };
        
            // Escuchar eventos en los inputs
            input1.addEventListener('input', () => fetchInstitution(input1, result1));
            input2.addEventListener('input', () => fetchInstitution(input2, result2));
        });
    </script>
</body>
</html>