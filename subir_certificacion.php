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

// ── Garantizar siempre respuesta JSON limpia (incluso ante errores fatales o expiración de sesión) ──
ob_start();
@ini_set('display_errors', '0');
@ini_set('memory_limit', '256M');
@set_time_limit(120);

$GLOBALS['__json_respuesta_enviada'] = false;

function responderJson(array $payload, int $http_code = 200): void
{
    $GLOBALS['__json_respuesta_enviada'] = true;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    if (!empty($GLOBALS['__json_respuesta_enviada'])) {
        return;
    }
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log("subir_certificacion [FATAL]: {$err['message']} en {$err['file']}:{$err['line']}");
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'El servidor encontró un error interno al procesar el archivo PDF (' . $err['message'] . ').'
        ], JSON_UNESCAPED_UNICODE);
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    responderJson([
        'success'         => false,
        'sesion_expirada' => true,
        'message'         => 'Su sesión ha expirado por inactividad mientras firmaba el documento. ' .
                             'Por favor inicie sesión nuevamente y vuelva a subir este mismo PDF firmado en el Paso 3 (no necesita volver a generarlo ni firmarlo).'
    ], 401);
}

header('Content-Type: application/json; charset=utf-8');

/** if (!in_array($_SESSION['tipo'] ?? null, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
} */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/certificacion_pdf_helper.php';
require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

// ── Detectar si el servidor descartó $_FILES y $_POST por exceder post_max_size ──
if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $max_post = ini_get('post_max_size');
    responderJson([
        'success' => false,
        'message' => "El archivo firmado supera el tamaño máximo de envío permitido por el servidor ({$max_post})."
    ]);
}

// ── Validar que llegó el archivo ─────────────────────────────
if (!isset($_FILES['pdf_firmado']) || $_FILES['pdf_firmado']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['pdf_firmado']['error'] ?? 'sin archivo';
    $mensajes_upload = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo permitido por la configuración del servidor (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo permitido por el formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente (corte de red). Intente subirlo nuevamente.',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo PDF.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo temporal en disco.',
    ];
    $msg_err = $mensajes_upload[$err] ?? "Error al recibir el archivo (código: $err).";
    responderJson(['success' => false, 'message' => $msg_err]);
}

$archivo  = $_FILES['pdf_firmado'];
$tmp_path = $archivo['tmp_name'];

// Validar magic bytes PDF
$magic = file_get_contents($tmp_path, false, null, 0, 4);
if ($magic !== '%PDF') {
    responderJson(['success' => false, 'message' => 'El archivo no es un PDF válido']);
}

// Limitar tamaño: 20 MB máximo
if ($archivo['size'] > 20 * 1024 * 1024) {
    responderJson(['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido (20 MB)']);
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
// Nota: extraerJsonDesdeAttachment() se carga desde certificacion_pdf_helper.php

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
    responderJson([
        'success' => false,
        'message' => 'El PDF no contiene el bloque de datos canónicos. ' .
                     'Verifique que es el PDF correcto generado por este sistema.'
    ]);
}

error_log("subir_certificacion: ✅ JSON extraído correctamente. Longitud: " . strlen($json_canonico));

// ── Validar JSON ───────────────────────────────────────────────
$datos = json_decode($json_canonico, true);
if (!$datos || !isset($datos['uuid'], $datos['version'])) {
    responderJson([
        'success' => false,
        'message' => 'El JSON incrustado en el PDF no es válido o está incompleto.'
    ]);
}

if ($datos['version'] !== 1 || $datos['tipo'] !== 'certificacion_inventario') {
    responderJson([
        'success' => false,
        'message' => 'El documento no es una certificación de inventario válida.'
    ]);
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
        responderJson([
            'success' => false,
            'message' => 'Esta certificación ya fue procesada anteriormente.'
        ]);
    } else {
        responderJson([
            'success' => false,
            'message' => 'El UUID del documento no es válido o el documento ha expirado (más de 24 horas). ' .
                         'Genere un nuevo PDF de certificación.'
        ]);
    }
}

// ── Validar que el funcionario que sube es el mismo que generó ─
if ($pendiente['funcionario_id'] !== $usuario_azure['cedula']) {
    responderJson([
        'success' => false,
        'message' => 'Este PDF fue generado por otro funcionario. ' .
                     'Solo el funcionario que generó el PDF puede subirlo.'
    ]);
}

// ── Hash SHA-256 del PDF ─────────────────────────────────────
$hash_sha256 = hash_file('sha256', $tmp_path);

// ── VALIDACIÓN DE FIRMA — Capa 1: detección binaria ──────────
$contenido_pdf = file_get_contents($tmp_path);
if ($contenido_pdf === false) {
    responderJson(['success' => false, 'message' => 'No se pudo leer el archivo PDF para validación.']);
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
    responderJson([
        'success' => false,
        'message' => 'El PDF no contiene una firma digital. ' .
                     'Firme el documento con GAUDI Móvil o GAUDI Desktop y vuelva a subirlo.'
    ]);
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
        responderJson([
            'success' => false,
            'message' => 'El PDF contiene marcadores de firma pero pdfsig no detecta ninguna firma válida. ' .
                         'Asegúrese de completar el proceso de firma con GAUDI y vuelva a subir el archivo.'
        ]);
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
    mysqli_close($link);

    responderJson([
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
    mysqli_close($link);
    error_log('subir_certificacion - ' . $e->getMessage());
    responderJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}


// ════════════════════════════════════════════════════════════════
// FUNCIONES AUXILIARES (cargadas desde certificacion_pdf_helper.php)
// ════════════════════════════════════════════════════════════════