<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
ob_start();

require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    error_log("Error de conexion a mysql: " . mysqli_connect_error());
}
if (!mysqli_set_charset($link, "utf8")) {
    error_log("Error cargando el conjunto de caracteres utf8");
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Usuario no autenticado'];
    header("Location: index.html");
    exit();
}

// === Parámetros de retorno ===
$subsistema_id = isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : 0;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0;
$params_retorno = '';
if ($subsistema_id > 0 && $modulo_id > 0) {
    $params_retorno = '&subsistema_id=' . $subsistema_id . '&modulo_id=' . $modulo_id;
}
$ruta_admin = 'navegar.php?ruta=formulario_administracion_permisos_n.php' . $params_retorno;

// === Roles gestionables ===
$roles_gestionables = [2, 3, 4, 5];

$xeliminar = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($xeliminar <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Identificador inválido'];
    header("Location: $ruta_admin");
    exit();
}

// Verificar que el registro exista y su rol sea gestionable
$qr = mysqli_query($link, "SELECT id, rol_id FROM usuarios_roles WHERE id = $xeliminar AND eliminado = 0 LIMIT 1");
if (!$qr || mysqli_num_rows($qr) == 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Registro no encontrado'];
    header("Location: $ruta_admin");
    exit();
}
$fila = mysqli_fetch_assoc($qr);

if (!in_array(intval($fila['rol_id']), $roles_gestionables)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'No puede eliminar a este usuario'];
    header("Location: $ruta_admin");
    exit();
}

$sql = "UPDATE usuarios_roles SET eliminado = 1, updated_at = NOW() WHERE id = $xeliminar";
if (mysqli_query($link, $sql)) {
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Eliminado correctamente'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al eliminar registro: ' . mysqli_error($link)];
}

mysqli_close($link);
header("Location: $ruta_admin");
exit();
