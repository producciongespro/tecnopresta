<?php
// Conexión a la base de datos
require_once("conexion.php");
$link = $mysqli;

// Configurar la conexión para usar UTF-8
$link->set_charset("utf8");

// Verificar si se recibió el código
if (isset($_GET['codigo'])) {
    $codigo = $_GET['codigo'];

    // Consulta para buscar la institución por código
    $stmt = $link->prepare("SELECT institucion FROM t_instituciones WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $stmt->bind_result($institucion);
    $stmt->fetch();

    if ($institucion) {
        // Enviar respuesta en formato JSON con UTF-8
        echo json_encode(['success' => true, 'institucion' => $institucion], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró la institución.'], JSON_UNESCAPED_UNICODE);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Código no proporcionado.'], JSON_UNESCAPED_UNICODE);
}

// Cerrar conexión
$link->close();
?>
