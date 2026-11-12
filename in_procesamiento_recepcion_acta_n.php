<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1, 2, 3, 4]);
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
    exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
    exit();
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

/**
 * Construye la URL de redirección preservando subsistema_id y modulo_id si existen
 */
function construirRutaFormulario(): string {
    $ruta = "navegar.php?ruta=in_formulario_recibir_acta_recepcion_n.php";
    $subsistema_id = isset($_POST['subsistema_id']) ? intval($_POST['subsistema_id']) : (isset($_GET['subsistema_id']) ? intval($_GET['subsistema_id']) : 0);
    $modulo_id = isset($_POST['modulo_id']) ? intval($_POST['modulo_id']) : (isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 0);

    if ($subsistema_id > 0 && $modulo_id > 0) {
        $ruta .= "&subsistema_id=" . $subsistema_id . "&modulo_id=" . $modulo_id;
    }
    return $ruta;
}

/**
 * Muestra una alerta en pantalla y redirige de forma segura al formulario
 */
function responderConAlerta(string $mensaje, string $rutaDestino): void {
    $mensajeJson = json_encode($mensaje, JSON_UNESCAPED_UNICODE);
    $rutaJson = json_encode($rutaDestino, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo "<script>
        alert($mensajeJson);
        window.location.href = $rutaJson;
    </script>";
    exit();
}

/**
 * Intenta detectar el número real de páginas dentro de la estructura del archivo PDF
 */
function estimarPaginasPdf(string $rutaArchivo): int {
    $contenido = @file_get_contents($rutaArchivo);
    if ($contenido === false) {
        return 0;
    }
    if (preg_match_all("/\/Type\s*\/Page[^s]/", $contenido, $coincidencias)) {
        return count($coincidencias[0]);
    }
    return 0;
}

$rutaFormulario = construirRutaFormulario();

// Verificar si el formulario fue enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Detectar si el archivo superó el post_max_size del servidor (PHP vacía $_POST y $_FILES)
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        responderConAlerta(
            "El archivo seleccionado es demasiado pesado y excede el límite máximo permitido por el servidor. Por favor digitalice el documento a menor resolución (150-200 DPI) y vuelva a intentarlo.",
            $rutaFormulario
        );
    }

    // Validar cantidad de páginas proporcionada
    $cantidadPaginas = isset($_POST['cantidad_paginas']) ? intval($_POST['cantidad_paginas']) : 0;
    if ($cantidadPaginas < 1 || $cantidadPaginas > 100) {
        responderConAlerta(
            "Debe indicar una cantidad de páginas válida para el documento (entre 1 y 100 páginas).",
            $rutaFormulario
        );
    }

    // Configuración de límites de peso: 250 KB por página con tope máximo de 5 MB
    $kbPorPagina = 250;
    $maxMbAbsoluto = 5;
    $limiteBytes = min($cantidadPaginas * $kbPorPagina * 1024, $maxMbAbsoluto * 1024 * 1024);

    // Verificar existencia del archivo subido
    if (!isset($_FILES['archivo'])) {
        responderConAlerta("No se recibió ningún archivo para subir.", $rutaFormulario);
    }

    // Manejo detallado de errores de subida de PHP
    $codigoError = $_FILES['archivo']['error'];
    if ($codigoError !== UPLOAD_ERR_OK) {
        switch ($codigoError) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msgError = "El archivo supera el tamaño máximo permitido por el servidor. Por favor optimice su PDF antes de subirlo.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $msgError = "El archivo se subió solo parcialmente. Verifique su conexión e inténtelo de nuevo.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $msgError = "No se seleccionó ningún archivo PDF.";
                break;
            default:
                $msgError = "Ocurrió un error al recibir el archivo (Código: $codigoError).";
                break;
        }
        responderConAlerta($msgError, $rutaFormulario);
    }

    $archivoNombreOriginal = basename($_FILES['archivo']['name']);
    $archivoTmpNombre = $_FILES['archivo']['tmp_name'];
    $archivoPesoBytes = (int)$_FILES['archivo']['size'];

    // 1. Validar que el archivo no esté vacío
    if ($archivoPesoBytes <= 0 || !is_uploaded_file($archivoTmpNombre)) {
        responderConAlerta("El archivo subido está vacío o no es válido.", $rutaFormulario);
    }

    // 2. Validar extensión .pdf
    $extension = strtolower(pathinfo($archivoNombreOriginal, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        responderConAlerta(
            "Formato de archivo no válido (.$extension). Únicamente se permiten archivos en formato PDF (.pdf).",
            $rutaFormulario
        );
    }

    // 3. Validar tipo MIME real y cabecera mágica (%PDF-) para evitar archivos renombrados o corruptos
    $esMimeValido = true;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeReal = finfo_file($finfo, $archivoTmpNombre);
            finfo_close($finfo);
            if ($mimeReal !== 'application/pdf' && $mimeReal !== 'application/x-pdf') {
                $esMimeValido = false;
            }
        }
    }

    $handle = @fopen($archivoTmpNombre, 'rb');
    $cabecera = $handle ? fread($handle, 5) : '';
    if ($handle) {
        fclose($handle);
    }

    if (!$esMimeValido || $cabecera !== '%PDF-') {
        responderConAlerta(
            "El archivo seleccionado no es un documento PDF auténtico o se encuentra dañado.",
            $rutaFormulario
        );
    }

    // 4. Validar el peso en proporción a la cantidad de páginas declarada
    if ($archivoPesoBytes > $limiteBytes) {
        $pesoActualKB = round($archivoPesoBytes / 1024);
        $limiteKB = round($limiteBytes / 1024);
        responderConAlerta(
            "El archivo pesa {$pesoActualKB} KB y excede el límite máximo de {$limiteKB} KB permitido para {$cantidadPaginas} página(s) ({$kbPorPagina} KB por página). Por favor digitalice o comprima el PDF a un tamaño optimizado.",
            $rutaFormulario
        );
    }

    // 5. Verificar que el usuario no declare muchas páginas falsas para subir un PDF de pocas páginas muy pesado
    $paginasDetectadas = estimarPaginasPdf($archivoTmpNombre);
    if ($paginasDetectadas > 0) {
        $limiteRealBytes = min($paginasDetectadas * $kbPorPagina * 1024, $maxMbAbsoluto * 1024 * 1024);
        if ($archivoPesoBytes > $limiteRealBytes) {
            $pesoActualKB = round($archivoPesoBytes / 1024);
            $limiteRealKB = round($limiteRealBytes / 1024);
            responderConAlerta(
                "El documento PDF contiene {$paginasDetectadas} página(s) y pesa {$pesoActualKB} KB, superando el límite optimizado de {$limiteRealKB} KB ({$kbPorPagina} KB por página). Por favor reduzca la resolución del escaneo (150-200 DPI).",
                $rutaFormulario
            );
        }
    }

    // Obtener y limpiar los valores del formulario (priorizando los datos de sesión de Azure por seguridad)
    $etiqueta = trim($_POST['etiqueta'] ?? '');
    $fondos = trim($_POST['fondos'] ?? '');
    $codigo = trim($usuario_azure['codigoPresu'] ?? ($_POST['codigo'] ?? ''));
    $dependencia = trim($usuario_azure['dependencia'] ?? ($_POST['dependencia'] ?? ''));
    $regional = trim($usuario_azure['regional'] ?? ($_POST['regional'] ?? ''));
    $circuito = trim($usuario_azure['circuito'] ?? ($_POST['circuito'] ?? ''));
    date_default_timezone_set('America/Costa_Rica');
    $fechaHora = !empty($_POST['fecha_hora']) ? trim($_POST['fecha_hora']) : date('Y-m-d H:i:s');

    if ($etiqueta === '' || $fondos === '') {
        responderConAlerta("Por favor complete la etiqueta descriptiva y el origen presupuestario.", $rutaFormulario);
    }

    // Sanitizar el nombre del archivo para evitar errores 404 por caracteres como #, ?, %, espacios o tildes
    $nombreSinExtension = pathinfo($archivoNombreOriginal, PATHINFO_FILENAME);
    $nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreSinExtension);
    $nombreLimpio = preg_replace('/_+/', '_', trim($nombreLimpio, '_'));
    if ($nombreLimpio === '') {
        $nombreLimpio = 'acta';
    }
    $nombreLimpio = substr($nombreLimpio, 0, 60);

    $codigoLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '', $codigo);
    $archivoNuevoNombre = time() . '_' . ($codigoLimpio !== '' ? $codigoLimpio . '_' : '') . $nombreLimpio . '.pdf';

    // Asegurar que el directorio de destino exista
    $directorioDestino = __DIR__ . '/in_recepcion_actas';
    if (!is_dir($directorioDestino)) {
        @mkdir($directorioDestino, 0755, true);
    }

    $rutaFisicaDestino = $directorioDestino . '/' . $archivoNuevoNombre;
    $archivoDestinoBD = 'in_recepcion_actas/' . $archivoNuevoNombre;

    if (move_uploaded_file($archivoTmpNombre, $rutaFisicaDestino)) {
        // Insertar registro usando consulta preparada para prevenir inyección SQL y errores por comillas
        $sql = "INSERT INTO t_recepcion_acta_equipo (archivo, etiqueta, id_fondos, codigo, dependencia, regional, circuito, fecha)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $link->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                "ssssssss",
                $archivoDestinoBD,
                $etiqueta,
                $fondos,
                $codigo,
                $dependencia,
                $regional,
                $circuito,
                $fechaHora
            );

            if ($stmt->execute()) {
                $stmt->close();
                $link->close();
                responderConAlerta("Guardado correctamente.", $rutaFormulario);
            } else {
                $stmt->close();
                // Si falla la BD, eliminar el archivo recién subido para no dejar archivos huérfanos ocupando espacio
                @unlink($rutaFisicaDestino);
                $link->close();
                responderConAlerta("Error al almacenar los datos en la base de datos.", $rutaFormulario);
            }
        } else {
            @unlink($rutaFisicaDestino);
            $link->close();
            responderConAlerta("Error al preparar el registro en la base de datos.", $rutaFormulario);
        }
    } else {
        $link->close();
        responderConAlerta("Error al mover el archivo al directorio del servidor.", $rutaFormulario);
    }
} else {
    $link->close();
    responderConAlerta("No se ha enviado ningún formulario.", $rutaFormulario);
}
?>
