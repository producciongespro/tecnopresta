<?php
header('Content-Type: application/json; charset=utf-8');

// Configuración de conexión solicitada
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo json_encode([
        'success' => false,
        'error' => "Error de conexión a MySQL: " . mysqli_connect_error()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!mysqli_set_charset($link, "utf8")) {
    echo json_encode([
        'success' => false,
        'error' => "Error cargando el conjunto de caracteres utf8"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Consultar si hay un anuncio activo
$sqlAnuncio = "SELECT id, titulo, descripcion, duracion_slide_ms FROM cine_anuncios WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$resAnuncio = mysqli_query($link, $sqlAnuncio);

if (!$resAnuncio || mysqli_num_rows($resAnuncio) === 0) {
    echo json_encode([
        'success' => true,
        'activo' => false,
        'mensaje' => 'No hay anuncios activos actualmente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$anuncio = mysqli_fetch_assoc($resAnuncio);
$anuncioId = (int)$anuncio['id'];

// Obtener las diapositivas correspondientes ordenadas
$sqlSlides = "SELECT orden, imagen_url, titulo_slide, texto_slide FROM cine_diapositivas WHERE anuncio_id = $anuncioId ORDER BY orden ASC LIMIT 10";
$resSlides = mysqli_query($link, $sqlSlides);

$diapositivas = [];
if ($resSlides) {
    while ($row = mysqli_fetch_assoc($resSlides)) {
        $diapositivas[] = [
            'orden' => (int)$row['orden'],
            'imagen_url' => $row['imagen_url'],
            'titulo' => $row['titulo_slide'],
            'texto' => $row['texto_slide']
        ];
    }
}

echo json_encode([
    'success' => true,
    'activo' => count($diapositivas) > 0,
    'anuncio' => [
        'id' => (int)$anuncio['id'],
        'titulo' => $anuncio['titulo'],
        'descripcion' => $anuncio['descripcion'],
        'duracion_slide_ms' => (int)$anuncio['duracion_slide_ms'],
        'diapositivas' => $diapositivas
    ]
], JSON_UNESCAPED_UNICODE);

mysqli_close($link);
?>
