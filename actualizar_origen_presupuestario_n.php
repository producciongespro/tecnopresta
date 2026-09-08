<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Verificación de permisos
/*if (!isset($_SESSION['tipo']) || !in_array($_SESSION['tipo'], [1, 2, 3, 4])) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su director(a) institucional para que se los brinde, si es usted prestador o inventariador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
*/
require_once("conexion.php");
$link = $mysqli;

// Verificar conexión y conjunto de caracteres
if ($link->connect_error) {
    die("Error de conexión a MySQL: " . $link->connect_error);
}
if (!$link->set_charset("utf8")) {
    die("Error cargando el conjunto de caracteres utf8");
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

header('Content-Type: application/json; charset=utf-8');

if (!$usuario_azure) {
    echo json_encode(['success' => false, 'error' => 'No autenticado.']);
    exit();
}

/*
// Validación de variables de sesión
$logusuario = $_SESSION['cedula'] ?? '';
$lognombre = $_SESSION['nombre'] ?? '';
$logtipo = $_SESSION['tipo'] ?? '';
$logcodigo = $_SESSION['codigo'] ?? '';
*/

// Validar selección de modelos y placas
if (empty($_POST['idsplacas'])) {
    echo json_encode(['success' => false, 'error' => 'No hay ningún activo seleccionado.']);
    exit();
}

if (empty($_POST['nuevoFondo'])) {
    echo json_encode(['success' => false, 'error' => 'No se seleccionó un nuevo fondo presupuestario.']);
    exit();
}

$id_fondos_nuevo = $_POST['nuevoFondo'];

// Preparar la consulta de actualización
$query = "UPDATE t_placa SET id_fondos = ? WHERE id_placa = ?";
$stmt = $link->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error preparando la consulta: ' . $link->error]);
    exit();
}

$errores = [];
foreach ($_POST['idsplacas'] as $idplaca) {
    // Validar que los valores no estén vacíos
    if (!empty($id_fondos_nuevo) && !empty($idplaca)) {
        $stmt->bind_param('ii', $id_fondos_nuevo, $idplaca);

        // Ejecutar la consulta
        if (!$stmt->execute()) {
            $errores[] = 'Error al actualizar el activo con id de placa ' . $idplaca;
        }
    }
}

// Cerrar la conexión
$stmt->close();
$link->close();

if (!empty($errores)) {
    echo json_encode(['success' => false, 'error' => implode(' | ', $errores)]);
    exit();
}

echo json_encode(['success' => true]);
exit();
?>