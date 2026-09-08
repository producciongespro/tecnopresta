<?php
/**
 * generar_pdf_certificacion.php - VERSIÓN FINAL CON ATTACHMENT
 * 
 * El JSON se guarda como Attachment usando Annotation
 * (método compatible con todas las versiones de TCPDF)
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


// ── LIMPIAR BUFFER DE SALIDA ──
if (ob_get_length()) {
    ob_end_clean();
}

/** $tienellave = in_array($_SESSION['tipo'], [1, 2]);

if (!$tienellave) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
} */

require_once("conexion.php");

$link = $mysqli;

if (!mysqli_set_charset($link, "utf8")) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error UTF8']);
    exit;
}

date_default_timezone_set('America/Costa_Rica');

require __DIR__ . '/vendor/autoload.php';

if (!class_exists('TCPDF')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'TCPDF no encontrado']);
    exit;
}

// ======================================================
// RECIBIR DATOS
// ======================================================

$id_fondos = isset($_POST['id_fondos']) ? (int)$_POST['id_fondos'] : 0;
$codigo_centro = isset($_POST['codigo_centro']) ? trim($_POST['codigo_centro']) : '';
$activos_json = isset($_POST['activos_json']) ? $_POST['activos_json'] : '[]';

if ($id_fondos <= 0 || empty($codigo_centro)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// ======================================================
// DECODIFICAR ACTIVOS
// ======================================================

$activos = json_decode($activos_json, true);

if (!is_array($activos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$sinRevisar = array_filter($activos, function ($a) {
    return !isset($a['revisado']) || $a['revisado'] !== 'TRUE';
});

if (count($sinRevisar) > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Hay activos sin revisar']);
    exit;
}

// ======================================================
// DATOS SESIÓN
// ======================================================

$logusuario = $usuario_azure['cedula'] ?? null;
$lognombre = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
$loginstitucion = $usuario_azure['dependencia'] ?? null;

// ======================================================
// OBTENER DATOS DE BD
// ======================================================

$stmt = $link->prepare("SELECT fondos FROM t_fondos WHERE id_fondos = ?");
$stmt->bind_param("i", $id_fondos);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$fuente_nombre = $res ? $res['fondos'] : "Fondo {$id_fondos}";

$stmt = $link->prepare("SELECT institucion FROM t_instituciones WHERE codigo = ?");
$stmt->bind_param("s", $codigo_centro);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$centro_nombre = $res ? $res['institucion'] : $loginstitucion;

// ======================================================
// UUID
// ======================================================

$uuid = sprintf(
    '%s-%s-%s-%s-%s',
    bin2hex(random_bytes(4)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(6))
);

$fecha_generacion = date('Y-m-d H:i:s');
$fecha_iso = date('c');

// ======================================================
// JSON CANÓNICO
// ======================================================

$activos_para_json = array_map(function ($a) {
    return [
        'placa' => $a['placa'] ?? '',
        'serial' => $a['serial'] ?? '',
        'estado' => $a['estado'] ?? '',
        'ubicacion' => $a['ubicacion'] ?? '',
        'observacion' => $a['observacion'] ?? ''
    ];
}, $activos);

$json_data = [
    'version' => 1,
    'tipo' => 'certificacion_inventario',
    'uuid' => $uuid,
    'centro' => ['id' => $codigo_centro, 'nombre' => $centro_nombre],
    'fuente' => ['id' => $id_fondos, 'nombre' => $fuente_nombre],
    'funcionario' => ['cedula' => $logusuario, 'nombre' => $lognombre],
    'activos' => $activos_para_json,
    'totales' => ['total' => count($activos)],
    'fecha' => $fecha_iso
];

$json_compacto = json_encode($json_data);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error generando JSON']);
    exit;
}

// ======================================================
// GUARDAR PENDIENTE
// ======================================================

$expira_en = date('Y-m-d H:i:s', strtotime('+24 hours'));

$stmt = $link->prepare("
    INSERT INTO certificaciones_pendientes (
        certificacion_uuid, centro_id, centro_nombre,
        fuente_presupuestaria_id, fuente_presupuestaria_nombre,
        funcionario_id, funcionario_nombre,
        activos_json, json_canonico,
        fecha_generacion, expira_en
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssississss",
    $uuid, $codigo_centro, $centro_nombre,
    $id_fondos, $fuente_nombre,
    $logusuario, $lognombre,
    $activos_json, $json_compacto,
    $fecha_generacion, $expira_en
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit;
}
$stmt->close();

// ======================================================
// CREAR PDF TCPDF
// ======================================================

try {
    $pdf = new TCPDF('P', 'mm', 'Letter', true, 'UTF-8', false);
    
    // Configuración
    $pdf->SetCreator('Sistema Inventario');
    $pdf->SetAuthor($lognombre);
    $pdf->SetTitle('Certificación Inventario');
    $pdf->SetSubject('Inventario');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 11);
    
    // ======================================================
    // CONTENIDO VISIBLE DEL PDF
    // ======================================================
    
    $html = '
    <h1 style="color:#003087;">CERTIFICACIÓN DE INVENTARIO</h1>
    <hr>
    <p><strong>Centro:</strong> ' . htmlspecialchars($centro_nombre) . '</p>
    <p><strong>Código:</strong> ' . htmlspecialchars($codigo_centro) . '</p>
    <p><strong>Fuente:</strong> ' . htmlspecialchars($fuente_nombre) . '</p>
    <p><strong>Funcionario:</strong> ' . htmlspecialchars($lognombre) . '</p>
    <p><strong>Cédula:</strong> ' . htmlspecialchars($logusuario) . '</p>
    <p><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>
    <p><strong>UUID:</strong> ' . $uuid . '</p>
    <br>
    <h3>ACTIVOS CERTIFICADOS</h3>
    <table border="1" cellpadding="4">
        <tr style="font-weight:bold;background-color:#dddddd;">
            <td width="8%">#</td>
            <td width="18%">Placa</td>
            <td width="22%">Serial</td>
            <td width="15%">Estado</td>
            <td width="20%">Ubicación</td>
            <td width="17%">Observación</td>
        </tr>';
    
    foreach ($activos as $i => $a) {
        $html .= '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . htmlspecialchars($a['placa']) . '</td>
            <td>' . htmlspecialchars($a['serial']) . '</td>
            <td>' . htmlspecialchars($a['estado']) . '</td>
            <td>' . htmlspecialchars($a['ubicacion']) . '</td>
            <td>' . htmlspecialchars($a['observacion'] ?? '') . '</td>
        </tr>';
    }
    
    $html .= '</table>
    <br><br>
    <p>El funcionario declara haber revisado físicamente los activos listados en este documento.</p>
    <br><br>
    <table border="1" cellpadding="8">
        <tr><td align="center" height="80">ESPACIO PARA FIRMA DIGITAL</td></tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // ======================================================
    // ✅ GUARDAR JSON COMO ATTACHMENT (MÉTODO CON ANNOTATION)
    // ======================================================
    // Este método es compatible con TODAS las versiones de TCPDF
    // y NO tiene límite de tamaño
    
    // Crear archivo temporal con el JSON
    $temp_json = tempnam(sys_get_temp_dir(), 'cert_data_');
    if ($temp_json !== false) {
        file_put_contents($temp_json, $json_compacto);
        
        if (file_exists($temp_json) && filesize($temp_json) > 0) {
            // Usar Annotation para FileAttachment
            // Esto funciona en TCPDF 6.x y versiones anteriores
            $pdf->Annotation(
                0, 0, 0, 0,  // x, y, w, h (0 = invisible)
                'certificacion_data.json',
                [
                    'Subtype' => 'FileAttachment',
                    'FS' => $temp_json,
                    'UF' => 'certificacion_data.json'
                ]
            );
        }
        @unlink($temp_json);
    }
    
    // ======================================================
    // ✅ REGISTRO LEGACY (compatibilidad con versiones anteriores)
    // ======================================================
    // Este registro es OPCIONAL y sirve para compatibilidad
    // con versiones anteriores del sistema.
    // El Attachment es el método principal.
    
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 1);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->MultiCell(0, 1, 'BEGIN_JSON' . $json_compacto . 'END_JSON', 0, 'L', false, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 11);
    
    // ======================================================
    // SALIDA
    // ======================================================
    
    $nombre_archivo = 'certificacion_' . $codigo_centro . '_' . date('Ymd_His') . '.pdf';
    
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    $pdf->Output($nombre_archivo, 'D');
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generando PDF: ' . $e->getMessage()
    ]);
    exit;
}