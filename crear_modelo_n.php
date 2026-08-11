<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*

// �7�3 CORRECCI�0�7N: Verificar sesi��n correctamente
if (!isset($_SESSION['tipo']) || !in_array($_SESSION['tipo'], [1, 2, 3, 4])) {
    echo '<script>
        alert("No tienes permisos para realizar esta acci��n");
        window.location.href = "formulario_menu_inventario.html";
    </script>';
    exit;
}
*/
require_once("conexion.php");
require_once __DIR__ . '/funciones_modelos_n.php';
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexi��n a MySQL: " . mysqli_connect_error();
    exit;
}

mysqli_set_charset($link, "utf8");

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// �7�3 CORRECCI�0�7N: Debug para ver qu�� datos llegan
error_log("Datos recibidos en crear_modelo.php:");
error_log("id_ag: " . ($_POST['id_ag'] ?? 'NO RECIBIDO'));
error_log("id_marca: " . ($_POST['id_marca'] ?? 'NO RECIBIDO'));
error_log("id_color: " . ($_POST['id_color'] ?? 'NO RECIBIDO'));
error_log("modelo: " . ($_POST['modelo'] ?? 'NO RECIBIDO'));

// �7�3 CORRECCI�0�7N: Verificar campos requeridos de forma m��s robusta
$campos_requeridos = ['id_ag', 'id_marca', 'id_color'];
$campos_faltantes = [];

foreach ($campos_requeridos as $campo) {
    if (empty($_POST[$campo]) || $_POST[$campo] === '0') {
        $campos_faltantes[] = $campo;
    }
}

// El campo 'modelo' es especial porque viene del campo de texto
if (empty($_POST['modelo'])) {
    $campos_faltantes[] = 'modelo';
}

if (empty($campos_faltantes)) {
    // Sanitizar datos
    $id_ag    = intval($_POST['id_ag']);
    $id_marca = intval($_POST['id_marca']);
    $id_color = intval($_POST['id_color']);
    $modelo   = trim($_POST['modelo']);
    $logcodigo = $_SESSION['codigo'] ?? '';

    // Verificar si ya existe el modelo en t_activo (legado)
    $stmt = $link->prepare("SELECT id_activo FROM t_activo WHERE modelo = ? AND id_marca = ? AND id_color = ? AND id_ag = ?");
    $stmt->bind_param("siii", $modelo, $id_marca, $id_color, $id_ag);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows >= 1) {
        $stmt->close();
        echo '<script>
            alert("El modelo que intenta registrar ya existe");
            window.location.href = "formulario_busqueda_creacion_activo_n.php";
        </script>';
    } else {
        // Obtener o crear el modelo en el catalogo maestro (t_modelos)
        $id_modelo = obtenerOCrearModelo($link, $modelo, $logcodigo);

        // Insertar nuevo modelo en t_activo con referencia al catalogo
        $stmt_insert = $link->prepare("INSERT INTO t_activo (id_ag, id_marca, modelo, id_color, modelo_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("iisii", $id_ag, $id_marca, $modelo, $id_color, $id_modelo);

        if ($stmt_insert->execute()) {
            $nuevo_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            $stmt->close();

            echo '<script>
                alert("Modelo guardado correctamente con ID: ' . $nuevo_id . '");
                window.location.href = "formulario_busqueda_creacion_activo_n.php";
            </script>';
        } else {
            error_log("Error en inserci��n: " . $stmt_insert->error);
            $stmt_insert->close();
            $stmt->close();

            echo '<script>
                alert("Error al guardar el registro: ' . $stmt_insert->error . '");
                window.location.href = "formulario_busqueda_creacion_activo_n.php";
            </script>';
        }
    }
} else {
    echo '<script>
        alert("Debe completar todos los campos. Faltan: ' . implode(', ', $campos_faltantes) . '");
        window.location.href = "formulario_busqueda_creacion_activo_n.php";
    </script>';
}

$link->close();
?>