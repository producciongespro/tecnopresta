<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexión a MySQL: " . mysqli_connect_error();
    exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
    exit();
}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// Variables de sesión
$lognombre = $usuario_azure['nombre'] ?? '';

// Estado del proceso (para mostrar el modal)
$exito = false;
$mensaje = '';

// Contexto de navegación (se conserva para respetar la ruta seguida por el usuario)
$subsistema_id = isset($_POST['subsistema_id']) ? intval($_POST['subsistema_id']) : 0;
$modulo_id     = isset($_POST['modulo_id']) ? intval($_POST['modulo_id']) : 0;
$formulario_id = isset($_POST['formulario_id']) ? intval($_POST['formulario_id']) : 0;

$redirigir_a = 'navegar.php?ruta=in_formulario_redistribuir_n.php';
if ($subsistema_id > 0) {
    $redirigir_a .= '&subsistema_id=' . $subsistema_id
        . '&modulo_id=' . $modulo_id
        . '&formulario_id=' . $formulario_id;
}

// Variables fijas y recibidas
$titulo = "Informe de Redistribución de Activos";
$descripcion = "Equipo tecnológico";
$observaciones = "La redistribución se realiza con el objetivo de optimizar los recursos disponibles en la institución, trasladando los activos necesarios al centro destino para mejorar su operación y garantizar un uso eficiente de los bienes.";

// Recibir los valores del formulario
$id_placas = isset($_POST['id_placas']) ? $_POST['id_placas'] : '';
$codigo_origen = isset($_POST['cod_o']) ? $_POST['cod_o'] : '';
$codigo_destino = isset($_POST['cod_d']) ? $_POST['cod_d'] : '';
$fondos_id = isset($_POST['fondos']) ? $_POST['fondos'] : '';

if (!empty($id_placas)) {
    // Procesar las placas como una cadena separada por comas
    $id_placas_array = explode(',', $id_placas);
    $id_placas_str = implode(',', $id_placas_array);

    // Consultar el nombre de los fondos
    $query_fondos = "SELECT fondos FROM t_fondos WHERE id_fondos = ?";
    $stmt_fondos = $link->prepare($query_fondos);
    $stmt_fondos->bind_param("i", $fondos_id);
    $stmt_fondos->execute();
    $result_fondos = $stmt_fondos->get_result();

    if ($result_fondos->num_rows > 0) {
        $row_fondos = $result_fondos->fetch_assoc();
        $fondos_nombre = $row_fondos['fondos'];
    } else {
        $fondos_nombre = "No especificado";
    }

    // Obtener fecha y hora actual
    $fecha_hora = date("Y-m-d H:i:s");

    // Preparar la consulta de inserción en la tabla para recrear un acta de redistribución
    $query_insert = "
        INSERT INTO redistribucion_activos (
            titulo, fecha_hora, codigo_origen, codigo_destino, fondos, responsable, id_placa, descripcion, estado, codigo_anterior, nuevo_codigo, observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt_insert = $link->prepare($query_insert);

    // Valores para el registro
    $estado = "Usado";
    $codigo_anterior = $codigo_origen;
    $nuevo_codigo = $codigo_destino;

    // Asociar los parámetros
    $stmt_insert->bind_param(
        "ssssssssssss",
        $titulo,
        $fecha_hora,
        $codigo_origen,
        $codigo_destino,
        $fondos_nombre,
        $lognombre,
        $id_placas_str,
        $descripcion,
        $estado,
        $codigo_anterior,
        $nuevo_codigo,
        $observaciones
    );

    // Ejecutar la consulta de inserción
    if ($stmt_insert->execute()) {
        // Obtener el ID autoincremental del registro recién creado
        $id_redistribucion = $link->insert_id;
        $exito = true;
        $mensaje = "Redistribución registrada exitosamente.";
        $redirigir_a = "in_visualizar_acta_redistribucion_n.php?id_redistribucion=" . intval($id_redistribucion)
            . "&subsistema_id=" . $subsistema_id
            . "&modulo_id=" . $modulo_id
            . "&formulario_id=" . $formulario_id;

        // Cerrar la declaración de inserción
        $stmt_insert->close();

        // *** CAMBIO 1: Determinar si el origen es LIMBO ***
        $es_limbo = (strtoupper(trim($codigo_origen)) === 'LIMBO');

        // Actualizar el código de las placas en `t_placa`
        foreach ($id_placas_array as $id_placa) {
            // Escapar el ID y códigos
            $id_placa = mysqli_real_escape_string($link, $id_placa);
            $codigo_origen = mysqli_real_escape_string($link, $codigo_origen);
            $codigo_destino = mysqli_real_escape_string($link, $codigo_destino);

            // *** CAMBIO 2: Reemplazar el bloque de SQL según es_limbo ***
            if ($es_limbo) {
                // Caso LIMBO: busca placas con código NULL o vacío
                $sql_update = "UPDATE t_placa
                               SET codigo = ?
                               WHERE id_placa = ?
                               AND (codigo IS NULL OR codigo = '')";
            } else {
                // Caso normal: busca por código exacto
                $sql_update = "UPDATE t_placa
                               SET codigo = ?
                               WHERE id_placa = ?
                               AND codigo = ?";
            }

            // Preparar la declaración
            if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                // *** CAMBIO 3: Vincular parámetros según el caso ***
                if ($es_limbo) {
                    mysqli_stmt_bind_param($stmt_update, "si", $codigo_destino, $id_placa);
                } else {
                    mysqli_stmt_bind_param($stmt_update, "sis", $codigo_destino, $id_placa, $codigo_origen);
                }

                // Ejecutar la consulta
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            }
        }

        // Cerrar conexiones
        $stmt_fondos->close();
        $link->close();
    } else {
        $mensaje = "Error al registrar la redistribución: " . $stmt_insert->error;
        $stmt_insert->close();
        $link->close();
    }
} else {
    $mensaje = "No se han seleccionado activos.";
}

// Escape del mensaje para HTML
$mensaje_html = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
$redirigir_js = htmlspecialchars($redirigir_a, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado de Redistribución de Activos</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=3" />
    <link href="css/bootstrap-icons/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="layout-page">
  <?php include 'partials/header.php'; ?>

  <main class="contenido-principal">
    <div class="container mt-5"></div>

    <?php if ($exito): ?>
    <div class="modal fade" id="modalResultado" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="prestamo-modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i> Operación exitosa</h5>
                </div>
                <div class="prestamo-modal-body">
                    <img src="img/listo.png" alt="Listo" width="80" class="mb-3">
                    <p><?php echo $mensaje_html; ?></p>
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-primary" id="btnContinuar">Ver acta</button>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="modal fade" id="modalResultado" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="prestamo-modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-circle me-2"></i> Error</h5>
                </div>
                <div class="prestamo-modal-body" id="modalErrorBody">
                    <div class="alert"><p><?php echo $mensaje_html; ?></p></div>
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-primary" id="btnContinuar">Aceptar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
  </main>

<?php include 'partials/footer.php'; ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('modalResultado'));
    modal.show();

    document.getElementById('btnContinuar').addEventListener('click', function () {
        window.location.href = "<?php echo $redirigir_js; ?>";
    });
});
</script>
</body>
</html>
