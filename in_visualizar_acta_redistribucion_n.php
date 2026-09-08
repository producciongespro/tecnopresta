<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// Contexto de navegación (se conserva para respetar la ruta seguida por el usuario)
$subsistema_id = isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : 0;
$modulo_id     = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;
$formulario_id = isset($_GET['formulario_id']) ? intval($_GET['formulario_id']) : 0;

$volver_redistribucion = 'navegar.php?ruta=in_formulario_redistribuir_n.php';
if ($subsistema_id > 0) {
    $volver_redistribucion .= '&subsistema_id=' . $subsistema_id
        . '&modulo_id=' . $modulo_id
        . '&formulario_id=' . $formulario_id;
}
$volver_redistribucion_js = htmlspecialchars($volver_redistribucion, ENT_QUOTES, 'UTF-8');

// Conexión a la base de datos
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}
if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
    exit();
}


if (isset($_GET['id_redistribucion'])) {
    // Obtener el valor del parámetro y almacenarlo en una variable
    $actabuscada = $_GET['id_redistribucion'];


} else {
    // En caso de que no se pase el parámetro, muestra un mensaje de error o redirige
    echo "No se ha recibido el ID de Redistribución.";
}

// Consulta a la tabla `redistribucion_activos`
$query_acta = "SELECT * FROM redistribucion_activos WHERE id_redistribucion = ?";
$stmt_acta = $link->prepare($query_acta);
$stmt_acta->bind_param("i", $actabuscada);
$stmt_acta->execute();
$result_acta = $stmt_acta->get_result();

if ($result_acta->num_rows === 0) {
    echo '<script language="javascript">
    alert("No se encontró el acta de redistribución.");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}

// Obtener los datos y asignarlos a variables
$row_acta = $result_acta->fetch_assoc();

$titulo = $row_acta['titulo'];
$fecha_hora = $row_acta['fecha_hora'];
$codigo_origen = $row_acta['codigo_origen'];
$codigo_destino = $row_acta['codigo_destino'];
$fondos = $row_acta['fondos'];
$responsable = $row_acta['responsable'];
$id_placa_str = $row_acta['id_placa'];
$descripcion = $row_acta['descripcion'];
$estado = $row_acta['estado'];
$codigo_anterior = $row_acta['codigo_anterior'];
$nuevo_codigo = $row_acta['nuevo_codigo'];
$observaciones = $row_acta['observaciones'];

// Convertir el string `id_placa` en un array para usarlo en la consulta
$id_placas_array = explode(',', $id_placa_str);
$id_placas_list = implode(',', array_map('intval', $id_placas_array)); // Asegurar formato correcto

// Segunda consulta para obtener los datos de las placas
$query_placas = "
    SELECT 
        t_placa.id_placa,
        t_placa.placa,
        t_placa.serial,
        t_activo.modelo,
        t_activo_general.clase,
        CASE 
            t_placa.id_estado
            WHEN 1 THEN 'Muy bueno'
            WHEN 2 THEN 'Bueno'
            WHEN 3 THEN 'Regular'
            WHEN 4 THEN 'Malo'
            WHEN 5 THEN 'Hurtado'
            ELSE 'Desconocido'
        END AS estado
    FROM 
        t_placa
    INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
    INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag
    WHERE 
        t_placa.id_placa IN ($id_placas_list)
";

$result_placas = mysqli_query($link, $query_placas);

if (!$result_placas) {
    die("Error en la consulta de placas: " . mysqli_error($link));
}

// Generar la página HTML con la información del acta y la tabla
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Acta de Redistribución</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        @media print {
            .btn-primary {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Detalle del Acta de Redistribución</h1>
    <div class="mb-4">
        <h3>Información del Acta</h3>
        <ul class="list-group">
            <li class="list-group-item"><strong>Asunto:</strong> <?php echo htmlspecialchars($titulo); ?></li>
            <li class="list-group-item"><strong>Fecha y Hora:</strong> <?php echo htmlspecialchars($fecha_hora); ?></li>
            <li class="list-group-item"><strong>Código Origen:</strong> <?php echo htmlspecialchars($codigo_origen); ?></li>
            <li class="list-group-item"><strong>Código Destino:</strong> <?php echo htmlspecialchars($codigo_destino); ?></li>
            <li class="list-group-item"><strong>Fondos:</strong> <?php echo htmlspecialchars($fondos); ?></li>
            <li class="list-group-item"><strong>Responsable:</strong> <?php echo htmlspecialchars($responsable); ?></li>
            <li class="list-group-item"><strong>Descripción:</strong> <?php echo htmlspecialchars($descripcion); ?></li>
            <li class="list-group-item"><strong>Estado:</strong> <?php echo htmlspecialchars($estado); ?></li>
            <li class="list-group-item"><strong>Observaciones:</strong> <?php echo htmlspecialchars($observaciones); ?></li>
        </ul>
    </div>
    <div>
        <h3>Detalle de los activos</h3>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID Placa</th>
                    <th>Placa</th>
                    <th>Serial</th>
                    <th>Modelo</th>
                    <th>Clase</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result_placas)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id_placa']); ?></td>
                        <td><?php echo htmlspecialchars($row['placa']); ?></td>
                        <td><?php echo htmlspecialchars($row['serial']); ?></td>
                        <td><?php echo htmlspecialchars($row['modelo']); ?></td>
                        <td><?php echo htmlspecialchars($row['clase']); ?></td>
                        <td><?php echo htmlspecialchars($row['estado']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="container d-flex justify-content-center align-items-center" style="height: 10vh;">
        <button type="button" class="btn btn-primary" onclick="imprimirYRedirigir()">
            <i class="bi bi-printer"></i> Imprimir
        </button>
    </div>
    <div class="container d-flex justify-content-center align-items-center" style="height: 10vh;">
        <button type="button" class="btn btn-primary" onclick="redirigir()">
            <i class="bi bi-arrow-right-circle"></i> Cerrar
        </button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script>
    function imprimirYRedirigir() {
        // Abrir el cuadro de impresión
        window.print();
    }
</script>
<script>
    function redirigir() {
        // Redirige a la página in_formulario_redistribuir_n.php conservando el contexto
        window.location.href = "<?php echo $volver_redistribucion_js; ?>";
    }
</script>
</body>
</html>

<?php
// Liberar recursos y cerrar conexiones
$result_acta->free();
mysqli_free_result($result_placas);
mysqli_close($link);
?>
