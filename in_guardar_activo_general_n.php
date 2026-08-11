<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
if (!$_SESSION) {
    echo '<script language = javascript>
    alert("Usuario no autenticado")
    self.location = "index.html"
    </script>';
    exit(); // Detener la ejecución si el usuario no está autenticado
}
*/
require_once("conexion.php");
require_once __DIR__ . '/funciones_modelos_n.php';
$link = $mysqli;

// Verificar conexión a la base de datos
if (mysqli_connect_errno()) {
    echo "Error de conexión a MySQL: " . mysqli_connect_error();
    exit();
}

// Establecer el conjunto de caracteres a UTF-8
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

// Verificar si todos los campos requeridos están presentes y no están vacíos
$post = (isset($_POST['clase']) && !empty($_POST['clase'])) &&
        (isset($_POST['marca']) && !empty($_POST['marca'])) &&
        (isset($_POST['modelo']) && !empty($_POST['modelo'])) &&
        (isset($_POST['color']) && !empty($_POST['color']));

if ($post) {
    // Asignar valores a las variables
    $id_ag = $_POST['clase'];
    $id_marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $id_color = $_POST['color'];
    $logcodigo = $_SESSION['codigo'] ?? '';

    // Verificar si el modelo ya existe en la base de datos
    $stmt = $link->prepare("SELECT id_activo FROM t_activo WHERE modelo = ? AND id_marca = ? AND id_color = ?");
    $stmt->bind_param("sis", $modelo, $id_marca, $id_color);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows >= 1) {
        // Si el modelo ya existe, mostrar alerta y redirigir
        $stmt->close();
        echo '<script language = javascript>
        alert("El modelo que intenta registrar ya existe")
        self.location = "in_formulario_agregar_activo.php"
        </script>';
    } else {
        $stmt->close();
        // Obtener o crear el modelo en el catalogo maestro (t_modelos)
        $id_modelo = obtenerOCrearModelo($link, $modelo, $logcodigo);

        // Si el modelo no existe, insertar el nuevo registro con referencia al catalogo
        $stmtIns = $link->prepare("INSERT INTO t_activo (id_ag, id_marca, modelo, id_color, modelo_id) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->bind_param("iisii", $id_ag, $id_marca, $modelo, $id_color, $id_modelo);
        if ($stmtIns->execute()) {
            $stmtIns->close();
            // Si la inserción es exitosa, mostrar alerta y redirigir
            echo '<script language = javascript>
            alert("Guardado correctamente")
            self.location = "in_formulario_agregar_activo.php"
            </script>';
        } else {
            // Si hay un error en la inserción, mostrar el error
            echo "Error al guardar el registro: " . $link->error;
            $stmtIns->close();
        }
    }
} else {
    // Si faltan campos, mostrar alerta y redirigir
    echo "<script type=\"text/javascript\">
    alert(\"Debe completar todos los campos\");
    window.location=\"in_formulario_agregar_activo.php\"
    </script>";
}

// Cerrar la conexión a la base de datos
mysqli_close($link);
?>
