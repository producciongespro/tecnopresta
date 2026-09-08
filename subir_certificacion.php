<?php
/**
 * subir_certificacion.php
 *
 * Recibe el PDF firmado por el funcionario con GAUDI,
 * extrae y valida el JSON incrustado, verifica la firma digital
 * y guarda la certificación en la base de datos.
 *
 * VERSIÓN 2.0 - Prioriza extracción desde Attachment
 *
 * Flujo:
 *   1. Recibe el PDF firmado via POST (multipart/form-data)
 *   2. ✅ PRIORIDAD 1: Extrae JSON desde Attachment (nuevo método - SIN LÍMITE)
 *   3. ✅ PRIORIDAD 2: Extrae JSON desde BEGIN_JSON (legacy - compatible)
 *   4. Valida UUID contra certificaciones_pendientes
 *   5. Extrae info del certificado digital de la firma GAUDI
 *   6. Guarda en certificaciones + certificaciones_detalle
 *   7. Elimina la entrada en certificaciones_pendientes
 */

/** session_start(); */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

header('Content-Type: application/json; charset=utf-8');

/** if (!in_array($_SESSION['tipo'] ?? null, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
} */

require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

// ── Validar que llegó el archivo ─────────────────────────────
if (!isset($_FILES['pdf_firmado']) || $_FILES['pdf_firmado']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['pdf_firmado']['error'] ?? 'sin archivo';
    echo json_encode(['success' => false, 'message' => "Error al recibir el archivo: código $err"]);
    exit;
}

$archivo  = $_FILES['pdf_firmado'];
$tmp_path = $archivo['tmp_name'];

// Validar magic bytes PDF
$magic = file_get_contents($tmp_path, false, null, 0, 4);
if ($magic !== '%PDF') {
    echo json_encode(['success' => false, 'message' => 'El archivo no es un PDF válido']);
    exit;
}

// Limitar tamaño: 20 MB máximo
if ($archivo['size'] > 20 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido (20 MB)']);
    exit;
}

// ======================================================
// ✅ FUNCIÓN: Extraer JSON desde Attachment (NUEVO)
// ======================================================

/**
 * Extrae JSON desde archivo adjunto del PDF
 * 
 * PRIORIDAD 1 - Método más robusto y sin límite de tamaño
 * 
 * El PDF generado por el sistema incluye un archivo adjunto con
 * el nombre 'certificacion_data.json' que contiene el JSON canónico.
 * 
 * @param  string      $ruta  Ruta al archivo PDF
 * @return string|null        JSON string válido o null
 */
function extraerJsonDesdeAttachment(string $ruta): ?string
{
    error_log("=== extraerJsonDesdeAttachment: INICIO ===");
    error_log("Archivo: " . $ruta);
    error_log("Tamaño: " . filesize($ruta));
    
    // ── MÉTODO 1: smalot/pdfparser ──
    if (class_exists('\Smalot\PdfParser\Parser')) {
        try {
            error_log("extraerJsonDesdeAttachment: Usando smalot/pdfparser");
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($ruta);
            
            // Buscar todos los objetos
            foreach ($pdf->getObjects() as $obj) {
                $content = $obj->getContent();
                
                // Buscar nuestro archivo adjunto por nombre
                if (strpos($content, 'certificacion_data.json') !== false ||
                    strpos($content, '/UF (certificacion_data.json)') !== false) {
                    
                    error_log("extraerJsonDesdeAttachment: Encontrado attachment en objeto");
                    
                    // Buscar el stream del attachment
                    if (preg_match('/\/EmbeddedFile\s+<<.*?\/Length\s+(\d+).*?>>\s*stream\s*(.*?)\s*endstream/s', $content, $m)) {
                        $length = (int)$m[1];
                        $data = substr($m[2], 0, $length);
                        error_log("extraerJsonDesdeAttachment: Length: $length, Data length: " . strlen($data));
                        
                        // Intentar descomprimir
                        $decoded = @gzuncompress($data);
                        if ($decoded !== false) {
                            $data = $decoded;
                            error_log("extraerJsonDesdeAttachment: Data descomprimida, nueva longitud: " . strlen($data));
                        }
                        
                        $json = trim($data);
                        if (!empty($json) && $json[0] === '{') {
                            json_decode($json, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                error_log("extraerJsonDesdeAttachment: ✅ JSON válido extraído correctamente");
                                return $json;
                            } else {
                                error_log("extraerJsonDesdeAttachment: JSON inválido: " . json_last_error_msg());
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('extraerJsonDesdeAttachment - smalot error: ' . $e->getMessage());
        }
    }
    
    // ── MÉTODO 2: Búsqueda binaria directa ──
    error_log("extraerJsonDesdeAttachment: Usando búsqueda binaria");
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        error_log('extraerJsonDesdeAttachment - No se pudo leer el archivo');
        return null;
    }
    
    // Buscar el nombre del archivo adjunto
    if (preg_match('/\/UF\s*\(\/certificacion_data\.json\)/s', $contenido) ||
        preg_match('/certificacion_data\.json/s', $contenido)) {
        
        error_log('extraerJsonDesdeAttachment - Encontrado referencia al attachment');
        
        // Buscar el stream del attachment
        if (preg_match('/\/EmbeddedFile\s+<<.*?\/Length\s+(\d+).*?>>\s*stream\s*(.*?)\s*endstream/s', $contenido, $m)) {
            $length = (int)$m[1];
            $data = substr($m[2], 0, $length);
            error_log("extraerJsonDesdeAttachment - Length: $length, Data length: " . strlen($data));
            
            // Intentar descomprimir
            $decoded = @gzuncompress($data);
            if ($decoded !== false) {
                $data = $decoded;
                error_log("extraerJsonDesdeAttachment - Data descomprimida, nueva longitud: " . strlen($data));
            }
            
            $json = trim($data);
            if (!empty($json) && $json[0] === '{') {
                json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    error_log('extraerJsonDesdeAttachment - ✅ JSON extraído correctamente');
                    return $json;
                } else {
                    error_log('extraerJsonDesdeAttachment - JSON inválido: ' . json_last_error_msg());
                }
            }
        }
    }
    
    error_log('extraerJsonDesdeAttachment - ❌ No se encontró el attachment');
    return null;
}

// ======================================================
// EXTRACCIÓN DE JSON - PRIORIDADES
// ======================================================

// ✅ PRIORIDAD 1: Attachment (NUEVO - sin límite de tamaño)
$json_canonico = extraerJsonDesdeAttachment($tmp_path);

// ✅ PRIORIDAD 2: BEGIN_JSON legacy (compatibilidad)
if ($json_canonico === null) {
    error_log("subir_certificacion: Falló Attachment, intentando BEGIN_JSON");
    $texto_pdf = extraerTextoPdf($tmp_path);
    $json_canonico = extraerJsonCanonico($texto_pdf, $tmp_path);
}

// ✅ PRIORIDAD 3: /Keywords (fallback adicional)
if ($json_canonico === null) {
    error_log("subir_certificacion: Falló BEGIN_JSON, intentando /Keywords");
    $json_canonico = extraerJsonDesdeKeywords($tmp_path);
}

if ($json_canonico === null) {
    error_log("subir_certificacion: ❌ No se pudo extraer JSON por ningún método");
    echo json_encode([
        'success' => false,
        'message' => 'El PDF no contiene el bloque de datos canónicos. ' .
                     'Verifique que es el PDF correcto generado por este sistema.'
    ]);
    exit;
}

error_log("subir_certificacion: ✅ JSON extraído correctamente. Longitud: " . strlen($json_canonico));

// ── Validar JSON ───────────────────────────────────────────────
$datos = json_decode($json_canonico, true);
if (!$datos || !isset($datos['uuid'], $datos['version'])) {
    echo json_encode([
        'success' => false,
        'message' => 'El JSON incrustado en el PDF no es válido o está incompleto.'
    ]);
    exit;
}

if ($datos['version'] !== 1 || $datos['tipo'] !== 'certificacion_inventario') {
    echo json_encode([
        'success' => false,
        'message' => 'El documento no es una certificación de inventario válida.'
    ]);
    exit;
}

$uuid = $datos['uuid'];
error_log("subir_certificacion: UUID extraído: " . $uuid);

// ── Validar UUID contra pendientes ───────────────────────────
$stmt_pend = $link->prepare(
    "SELECT * FROM certificaciones_pendientes
     WHERE certificacion_uuid = ? AND expira_en > NOW() LIMIT 1"
);
$stmt_pend->bind_param("s", $uuid);
$stmt_pend->execute();
$pendiente = $stmt_pend->get_result()->fetch_assoc();
$stmt_pend->close();

if (!$pendiente) {
    $stmt_dup = $link->prepare("SELECT id FROM certificaciones WHERE certificacion_uuid = ?");
    $stmt_dup->bind_param("s", $uuid);
    $stmt_dup->execute();
    $duplicado = $stmt_dup->get_result()->fetch_assoc();
    $stmt_dup->close();

    if ($duplicado) {
        echo json_encode([
            'success' => false,
            'message' => 'Esta certificación ya fue procesada anteriormente.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'El UUID del documento no es válido o el documento ha expirado (más de 24 horas). ' .
                         'Genere un nuevo PDF de certificación.'
        ]);
    }
    exit;
}

// ── Validar que el funcionario que sube es el mismo que generó ─
if ($pendiente['funcionario_id'] !== $usuario_azure['cedula']) {
    echo json_encode([
        'success' => false,
        'message' => 'Este PDF fue generado por otro funcionario. ' .
                     'Solo el funcionario que generó el PDF puede subirlo.'
    ]);
    exit;
}

// ── Hash SHA-256 del PDF ─────────────────────────────────────
$hash_sha256 = hash_file('sha256', $tmp_path);

// ── VALIDACIÓN DE FIRMA — Capa 1: detección binaria ──────────
$contenido_pdf = file_get_contents($tmp_path);
if ($contenido_pdf === false) {
    echo json_encode(['success' => false, 'message' => 'No se pudo leer el archivo PDF para validación.']);
    exit;
}

$tiene_byterange = str_contains($contenido_pdf, '/ByteRange');
$tiene_contents  = str_contains($contenido_pdf, '/Contents');
$tiene_subfilter = (
    str_contains($contenido_pdf, '/adbe.pkcs7.detached') ||
    str_contains($contenido_pdf, '/ETSI.CAdES.detached') ||
    stripos($contenido_pdf, 'adbe.pkcs7') !== false      ||
    stripos($contenido_pdf, 'ETSI.CAdES') !== false
);

if (!($tiene_byterange && $tiene_contents && $tiene_subfilter)) {
    echo json_encode([
        'success' => false,
        'message' => 'El PDF no contiene una firma digital. ' .
                     'Firme el documento con GAUDI Móvil o GAUDI Desktop y vuelva a subirlo.'
    ]);
    exit;
}
unset($contenido_pdf);

// ── VALIDACIÓN DE FIRMA — Capa 2: pdfsig ────────────────────
$pdfsig_bin = '';
if (is_executable('/usr/bin/pdfsig')) {
    $pdfsig_bin = '/usr/bin/pdfsig';
} else {
    $which = trim(shell_exec('which pdfsig 2>/dev/null') ?? '');
    if (!empty($which) && is_executable($which)) {
        $pdfsig_bin = $which;
    }
}

$pdfsig_output    = '';
$pdfsig_exit_code = -1;

if ($pdfsig_bin !== '') {
    $pdfsig_output = shell_exec(escapeshellarg($pdfsig_bin) . ' ' . escapeshellarg($tmp_path) . ' 2>&1') ?? '';
    exec(escapeshellarg($pdfsig_bin) . ' ' . escapeshellarg($tmp_path) . ' 2>/dev/null', $_, $pdfsig_exit_code);

    if ($pdfsig_exit_code === 2) {
        echo json_encode([
            'success' => false,
            'message' => 'El PDF contiene marcadores de firma pero pdfsig no detecta ninguna firma válida. ' .
                         'Asegúrese de completar el proceso de firma con GAUDI y vuelva a subir el archivo.'
        ]);
        exit;
    }
}

// ── Extraer metadatos del certificado ────────────────────────
$cert_info = extraerInfoCertificado($tmp_path, $pdfsig_output);

// ── Insertar en base de datos ────────────────────────────────
mysqli_begin_transaction($link);

try {
    $centro_id                    = $datos['centro']['id'];
    $centro_nombre                = $datos['centro']['nombre'];
    $fuente_presupuestaria_id     = (int)$datos['fuente']['id'];
    $fuente_presupuestaria_nombre = $datos['fuente']['nombre'];
    $funcionario_id               = $datos['funcionario']['cedula'];
    $funcionario_nombre           = $datos['funcionario']['nombre'];
    $fecha_certificacion          = date('Y-m-d H:i:s');
    $pdf_nombre                   = basename($archivo['name']);
    $ip_address                   = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent                   = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $fecha_firma      = $cert_info['fecha_firma']   ?? null;
    $cert_subject     = $cert_info['subject']       ?? null;
    $cert_issuer      = $cert_info['issuer']        ?? null;
    $cert_serial      = $cert_info['serial']        ?? null;
    $cert_valid_desde = $cert_info['valid_desde']   ?? null;
    $cert_valid_hasta = $cert_info['valid_hasta']   ?? null;

    // Insertar cabecera
    $stmt_ins = $link->prepare(
        "INSERT INTO certificaciones
         (certificacion_uuid, centro_id, centro_nombre, fuente_presupuestaria_id,
          fuente_presupuestaria_nombre, funcionario_id, funcionario_nombre,
          pdf_nombre, hash_sha256,
          certificado_subject, certificado_issuer, certificado_serial,
          certificado_valido_desde, certificado_valido_hasta,
          fecha_firma, fecha_certificacion, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt_ins->bind_param(
        "sssissssssssssssss",
        $uuid, $centro_id, $centro_nombre, $fuente_presupuestaria_id,
        $fuente_presupuestaria_nombre, $funcionario_id, $funcionario_nombre,
        $pdf_nombre, $hash_sha256,
        $cert_subject, $cert_issuer, $cert_serial,
        $cert_valid_desde, $cert_valid_hasta,
        $fecha_firma, $fecha_certificacion, $ip_address, $user_agent
    );

    if (!$stmt_ins->execute()) {
        throw new Exception('Error al insertar certificación: ' . $stmt_ins->error);
    }
    $certificacion_id = (int)$stmt_ins->insert_id;
    $stmt_ins->close();

    // ── Insertar detalle de activos ──────────────────────────
    $activos = $datos['activos'] ?? [];
    if (empty($activos)) {
        throw new Exception('El JSON no contiene activos. Revise el PDF.');
    }

    $lote_size  = 50;
    $lotes      = array_chunk($activos, $lote_size);

    foreach ($lotes as $lote) {
        $n           = count($lote);
        $placeholders = implode(',', array_fill(0, $n, '(?, ?, ?, ?, 1, ?)'));
        $sql_lote    = "INSERT INTO certificaciones_detalle
                        (certificacion_id, placa, serial, estado_actual, revisado, observaciones)
                        VALUES $placeholders";

        $stmt_lote = $link->prepare($sql_lote);
        if (!$stmt_lote) {
            throw new Exception('Error preparando inserción de detalle: ' . $link->error);
        }

        $params = [];
        $types  = '';
        foreach ($lote as $activo) {
            $params[] = $certificacion_id;
            $params[] = $activo['placa']       ?? '';
            $params[] = $activo['serial']      ?? '';
            $params[] = $activo['estado']      ?? '';
            $params[] = $activo['observacion'] ?? '';
            $types   .= 'issss';
        }

        $stmt_lote->bind_param($types, ...$params);

        if (!$stmt_lote->execute()) {
            throw new Exception(
                'Error insertando detalle (lote de ' . $n . ' activos): ' . $stmt_lote->error
            );
        }
        $stmt_lote->close();
    }

    // ── Eliminar de pendientes ───────────────────────────────
    $stmt_del = $link->prepare("DELETE FROM certificaciones_pendientes WHERE certificacion_uuid = ?");
    $stmt_del->bind_param("s", $uuid);
    $stmt_del->execute();
    $stmt_del->close();

    mysqli_commit($link);

    echo json_encode([
        'success'          => true,
        'message'          => 'Certificación registrada correctamente.',
        'certificacion_id' => $certificacion_id,
        'total_activos'    => count($activos),
        'uuid'             => $uuid,
        'hash_sha256'      => $hash_sha256,
        'fecha'            => $fecha_certificacion,
    ]);

} catch (Exception $e) {
    mysqli_rollback($link);
    error_log('subir_certificacion - ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

mysqli_close($link);


// ════════════════════════════════════════════════════════════════
// FUNCIONES AUXILIARES
// ════════════════════════════════════════════════════════════════

/**
 * Extrae el JSON canónico desde el campo /Keywords del diccionario /Info del PDF.
 */
function extraerJsonDesdeKeywords(string $ruta): ?string
{
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        return null;
    }

    if (!preg_match('#/Keywords\s*\((.+?)\)\s*(?:>>|/)#s', $contenido, $m)) {
        if (!preg_match('#/Keywords\s*\((.+?)\)\s*$#ms', $contenido, $m)) {
            return null;
        }
    }

    $raw = $m[1];
    $raw = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $raw);
    $raw = str_replace(["\r\n", "\r", "\n"], '', $raw);
    $raw = trim($raw);
    
    if (empty($raw) || $raw[0] !== '{') {
        return null;
    }

    $datos = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('extraerJsonDesdeKeywords - JSON inválido: ' . json_last_error_msg());
        return null;
    }

    if (!isset($datos['uuid'], $datos['version'])) {
        return null;
    }

    return $raw;
}

/**
 * Extrae el texto completo del PDF usando los métodos disponibles.
 */
function extraerTextoPdf(string $ruta): string
{
    if (class_exists('\Smalot\PdfParser\Parser')) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $texto  = $parser->parseFile($ruta)->getText();
            if (!empty(trim($texto))) {
                return $texto;
            }
        } catch (\Exception $e) {
            // continuar
        }
    }

    $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');
    if (empty($pdftotext) && is_executable('/usr/bin/pdftotext')) {
        $pdftotext = '/usr/bin/pdftotext';
    }
    if (!empty($pdftotext)) {
        $salida = shell_exec(escapeshellarg($pdftotext) . ' -layout ' . escapeshellarg($ruta) . ' - 2>/dev/null');
        if (!empty(trim($salida))) {
            return $salida;
        }
    }

    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        return '';
    }

    $texto = '';
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contenido, $streams);
    foreach ($streams[1] as $stream_data) {
        $des = @gzuncompress($stream_data);
        if ($des !== false) {
            preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $des, $tj);
            foreach ($tj[1] as $t) {
                $texto .= $t . "\n";
            }
            preg_match_all('/\[((?:[^\[\]]|\[.*?\])*)\]\s*TJ/s', $des, $tj_arr);
            foreach ($tj_arr[1] as $t) {
                preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $t, $partes);
                foreach ($partes[1] as $p) {
                    $texto .= $p;
                }
                $texto .= "\n";
            }
        }
    }

    if (empty(trim($texto))) {
        preg_match_all('/\(((?:[^()\\\\]|\\\\.){4,})\)/s', $contenido, $matches);
        foreach ($matches[1] as $m) {
            $m = str_replace(
                ['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
                ["\n",  "\r",  "\t",  "(",   ")",   "\\"],
                $m
            );
            $texto .= $m . "\n";
        }
    }

    return $texto;
}

/**
 * Extrae y reconstruye el bloque JSON entre BEGIN_JSON y END_JSON.
 */
function extraerJsonCanonico(string $texto, string $ruta_archivo = ''): ?string
{
    $json = extraerJsonDeTexto($texto);
    if ($json !== null) {
        return $json;
    }

    if (!empty($ruta_archivo)) {
        $binario = file_get_contents($ruta_archivo);
        if ($binario !== false) {
            $json = reconstruirJsonDesdeTJ($binario);
            if ($json !== null) {
                return $json;
            }
            $json = extraerJsonDeTexto($binario);
            if ($json !== null) {
                return $json;
            }
        }
    }

    error_log('extraerJsonCanonico - no se pudo extraer JSON tras todos los intentos.');
    return null;
}

/**
 * Extrae y parsea el JSON desde un texto ya concatenado.
 */
function extraerJsonDeTexto(string $texto): ?string
{
    $inicio = strpos($texto, 'BEGIN_JSON');
    if ($inicio === false) {
        return null;
    }
    $fin = strpos($texto, 'END_JSON', $inicio);
    if ($fin === false) {
        return null;
    }

    $bloque = substr($texto, $inicio + strlen('BEGIN_JSON'), $fin - $inicio - strlen('BEGIN_JSON'));
    $bloque = trim($bloque);

    $j_ini = strpos($bloque, '{');
    $j_fin = strrpos($bloque, '}');
    if ($j_ini === false || $j_fin === false) {
        return null;
    }

    $json = substr($bloque, $j_ini, $j_fin - $j_ini + 1);
    $json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json);

    json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
    }

    $json_limpio = limpiarSaltosEnStrings($json);
    json_decode($json_limpio, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json_limpio;
    }

    $json_compacto = trim(preg_replace('/\s+/', ' ', $json));
    json_decode($json_compacto, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json_compacto;
    }

    return null;
}

/**
 * Reconstruye el JSON desde los bytes planos de un PDF.
 */
function reconstruirJsonDesdeTJ(string $binario): ?string
{
    $ini_raw = strpos($binario, 'BEGIN_JSON');
    $fin_raw = strpos($binario, 'END_JSON', (int)$ini_raw);
    if ($ini_raw === false || $fin_raw === false) {
        return null;
    }

    $bloque = substr($binario, $ini_raw, $fin_raw - $ini_raw + strlen('END_JSON'));

    $primer_cierre = strpos($bloque, ')] TJ');
    if ($primer_cierre === false) {
        return extraerJsonDeTexto($bloque);
    }
    $frag_inicial = substr($bloque, 0, $primer_cierre);

    preg_match_all('/\[\((.*?)\)\]\s*TJ/s', $bloque, $matches);
    $frag_medio = implode('', $matches[1] ?? []);

    $ultimo_tj_pos = strrpos($bloque, ')] TJ');
    $frag_final    = '';
    if ($ultimo_tj_pos !== false) {
        $despues = substr($bloque, $ultimo_tj_pos + strlen(')] TJ'));
        preg_match_all('/\[\((.*?)\)\]\s*TJ/s', $despues, $m2);
        if (!empty($m2[1])) {
            $frag_final = implode('', $m2[1]);
        } else {
            $end_pos    = strpos($despues, 'END_JSON');
            $frag_final = $end_pos !== false ? substr($despues, 0, $end_pos) : '';
            $frag_final = preg_replace('/\b(BT|ET|Td|TJ|Tf|Tm|Tr|Tw|Tc|Tl|Ts|re|S|f|cm|q|Q)\b/', '', $frag_final);
            $frag_final = preg_replace('/[\d\.\-]+ [\d\.\-]+ [\d\.\-]+ [\d\.\-]+ [\d\.\-]+ [\d\.\-]+/', '', $frag_final);
            $frag_final = preg_replace('/\[\(|\)\]/', '', $frag_final);
            $frag_final = trim($frag_final);
        }
    }

    $texto_reconstruido = $frag_inicial . $frag_medio . $frag_final;
    return extraerJsonDeTexto($texto_reconstruido);
}

/**
 * Recorre el JSON y sustituye saltos de línea dentro de strings por espacio.
 */
function limpiarSaltosEnStrings(string $json): string
{
    $resultado = '';
    $en_string = false;
    $len       = strlen($json);

    for ($i = 0; $i < $len; $i++) {
        $c = $json[$i];

        if ($en_string) {
            if ($c === '\\' && $i + 1 < $len) {
                $resultado .= $c . $json[$i + 1];
                $i++;
                continue;
            }
            if ($c === '"') {
                $en_string = false;
                $resultado .= $c;
                continue;
            }
            if ($c === "\r") {
                if ($i + 1 < $len && $json[$i + 1] === "\n") {
                    $i++;
                }
                $resultado .= ' ';
                continue;
            }
            if ($c === "\n") {
                $resultado .= ' ';
                continue;
            }
            $resultado .= $c;
        } else {
            if ($c === '"') {
                $en_string = true;
            }
            $resultado .= $c;
        }
    }

    return $resultado;
}

/**
 * Extrae metadatos del certificado digital.
 */
function extraerInfoCertificado(string $ruta, string $pdfsig_output = ''): array
{
    $info = [];

    if (!empty($pdfsig_output)) {
        if (preg_match('/Signer Certificate Common Name:\s*(.+)/i', $pdfsig_output, $m)) {
            $info['subject'] = trim($m[1]);
        }
        if (preg_match('/Issuer:\s*(.+)/i', $pdfsig_output, $m)) {
            $info['issuer'] = trim($m[1]);
        }
        if (preg_match('/Signing Time:\s*(.+)/i', $pdfsig_output, $m)) {
            $ts = trim($m[1]);
            $dt = DateTime::createFromFormat('D M j H:i:s T Y', $ts)
                ?: DateTime::createFromFormat('D M  j H:i:s T Y', $ts)
                ?: DateTime::createFromFormat('Y-m-d\TH:i:sP', $ts)
                ?: DateTime::createFromFormat('Y-m-d H:i:s', $ts);
            if ($dt) {
                $dt->setTimezone(new DateTimeZone('America/Costa_Rica'));
                $info['fecha_firma'] = $dt->format('Y-m-d H:i:s');
            }
        }
        if (preg_match('/Certificate Serial Number:\s*([0-9A-Fa-f:]+)/i', $pdfsig_output, $m)) {
            $info['serial'] = trim($m[1]);
        }
        if (preg_match('/Certificate Validity - Not Before:\s*(.+)/i', $pdfsig_output, $m)) {
            $dt = DateTime::createFromFormat('D M j H:i:s T Y', trim($m[1]));
            if ($dt) $info['valid_desde'] = $dt->format('Y-m-d H:i:s');
        }
        if (preg_match('/Certificate Validity - Not After:\s*(.+)/i', $pdfsig_output, $m)) {
            $dt = DateTime::createFromFormat('D M j H:i:s T Y', trim($m[1]));
            if ($dt) $info['valid_hasta'] = $dt->format('Y-m-d H:i:s');
        }

        if (!empty($info)) {
            $info['metodo'] = 'pdfsig';
            return $info;
        }
    }

    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        return $info;
    }

    if (!preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $contenido, $br)) {
        return $info;
    }

    if (!preg_match('/\/Contents\s*<([0-9A-Fa-f]+)>/', $contenido, $mc)) {
        return $info;
    }

    $pkcs7_bin = hex2bin($mc[1]);
    if ($pkcs7_bin === false || strlen($pkcs7_bin) < 16) {
        return $info;
    }

    $tmp_p7  = sys_get_temp_dir() . '/cert_' . bin2hex(random_bytes(6)) . '.p7b';
    if (file_put_contents($tmp_p7, $pkcs7_bin) === false) {
        return $info;
    }

    $openssl = trim(shell_exec('which openssl 2>/dev/null') ?? '');
    if (empty($openssl)) {
        $openssl = '/usr/bin/openssl';
    }

    if (is_executable($openssl)) {
        $cmd_x509 = $openssl . ' pkcs7 -inform DER -in ' . escapeshellarg($tmp_p7) .
                    ' -print_certs 2>/dev/null | ' .
                    $openssl . ' x509 -noout -subject -issuer -serial -dates 2>/dev/null';
        $x509_out = shell_exec($cmd_x509) ?? '';

        if (!empty($x509_out)) {
            if (preg_match('/subject=.+?CN\s*=\s*([^,\/\n]+)/i', $x509_out, $m)) {
                $info['subject'] = trim($m[1]);
            }
            if (preg_match('/issuer=.+?CN\s*=\s*([^,\/\n]+)/i', $x509_out, $m)) {
                $info['issuer'] = trim($m[1]);
            }
            if (preg_match('/serial=([0-9A-Fa-f]+)/i', $x509_out, $m)) {
                $info['serial'] = trim($m[1]);
            }
            if (preg_match('/notBefore=(.+)/i', $x509_out, $m)) {
                $dt = DateTime::createFromFormat('M j H:i:s Y T', trim($m[1]));
                if ($dt) $info['valid_desde'] = $dt->format('Y-m-d H:i:s');
            }
            if (preg_match('/notAfter=(.+)/i', $x509_out, $m)) {
                $dt = DateTime::createFromFormat('M j H:i:s Y T', trim($m[1]));
                if ($dt) $info['valid_hasta'] = $dt->format('Y-m-d H:i:s');
            }
        }
    }

    @unlink($tmp_p7);

    if (!empty($info)) {
        $info['metodo'] = 'openssl_pkcs7';
    }

    return $info;
}