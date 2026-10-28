<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Costa_Rica');
mb_internal_encoding('UTF-8');

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    exit('Error de conexión a MySQL: ' . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    exit('Error cargando el conjunto de caracteres UTF-8.');
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

require_once __DIR__ . '/sql/consulta_activos_helper.php';

$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';

if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
        . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
        . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}

function cag_cargar_autoload(): bool
{
    static $resultado = null;

    if ($resultado !== null) {
        return $resultado;
    }

    $ruta = __DIR__ . '/vendor/autoload.php';

    if (!is_file($ruta)) {
        $resultado = false;
        return false;
    }

    require_once $ruta;
    $resultado = true;

    return true;
}

/**
 * Construye una URL de navegación conservando el contexto del formulario.
 */
function cag_url(array $parametros = []): string
{
    $base = ['ruta' => 'consulta_activos_n.php'];

    if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
        $base['subsistema_id'] = intval($_GET['subsistema_id']);
        $base['modulo_id'] = intval($_GET['modulo_id']);
    }

    return 'navegar.php?' . http_build_query(array_merge($base, $parametros));
}

/**
 * Detalle tabular de las sedes cuando el código presupuestario agrupa más de
 * una institución.
 */
function cag_html_detalle_sedes(array $instituciones, string $tamanoFuente = '7.5pt'): string
{
    if (count($instituciones) <= 1) {
        return '';
    }

    $html = '<table style="width:100%;border-collapse:collapse;font-size:' . $tamanoFuente . ';margin-top:2mm;">'
        . '<thead><tr>'
        . '<th style="background:#eef3f9;border:1px solid #cfd8e3;padding:2px;text-align:left;">Institución</th>'
        . '<th style="background:#eef3f9;border:1px solid #cfd8e3;padding:2px;text-align:left;">Código SABER</th>'
        . '<th style="background:#eef3f9;border:1px solid #cfd8e3;padding:2px;text-align:left;">Regional</th>'
        . '<th style="background:#eef3f9;border:1px solid #cfd8e3;padding:2px;text-align:left;">Circuito</th>'
        . '<th style="background:#eef3f9;border:1px solid #cfd8e3;padding:2px;text-align:left;">Estado</th>'
        . '</tr></thead><tbody>';

    foreach ($instituciones as $institucion) {
        $regional = trim((string)($institucion['regional'] ?? ''));
        $circuito = trim((string)($institucion['circuito'] ?? ''));
        $codigoSaber = trim((string)($institucion['cod_saber'] ?? ''));
        $activo = (int)($institucion['activo'] ?? 0);

        $html .= '<tr>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cag_h($institucion['institucion'] ?? '') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cag_h($codigoSaber !== '' ? $codigoSaber : 'Sin dato') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cag_h($regional !== '' ? $regional : 'Sin regional') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cag_h($circuito !== '' ? $circuito : 'Sin circuito') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . ($activo === 1 ? 'Activo' : 'Inactivo') . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table>';
}

/**
 * Bloque de resumen usado en el PDF y en la vista de impresión.
 */
function cag_html_resumen(array $contexto, string $tamanoFuente = '8pt'): string
{
    $modo = $contexto['modo'];
    $codigo = (string)$contexto['codigo'];
    $instituciones = $contexto['instituciones'];
    $fuenteNombre = (string)$contexto['fuenteNombre'];
    $total = (int)$contexto['total'];
    $fechaGeneracion = (string)$contexto['fechaGeneracion'];
    $incluirBaja = !empty($contexto['incluirBaja']);

    $etiqueta = (string)($modo['etiqueta'] ?? 'Consulta de activos');
    $tituloDoc = (string)($modo['titulo_doc'] ?? 'CONSULTA DE ACTIVOS');

    $html = '<table style="width:100%;border-collapse:collapse;font-size:' . $tamanoFuente . ';margin-bottom:3mm;">'
        . '<tr>'
        . '<td style="width:20%;background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Tipo de consulta</td>'
        . '<td style="width:30%;border:1px solid #cfd8e3;padding:2mm;">' . cag_h($etiqueta) . '</td>'
        . '<td style="width:20%;background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Fuente presupuestaria</td>'
        . '<td style="width:30%;border:1px solid #cfd8e3;padding:2mm;">' . cag_h($fuenteNombre) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Código presupuestario</td>'
        . '<td style="border:1px solid #cfd8e3;padding:2mm;">' . cag_h($codigo) . '</td>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Total de activos</td>'
        . '<td style="border:1px solid #cfd8e3;padding:2mm;">' . cag_num($total) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Centro(s)</td>'
        . '<td colspan="3" style="border:1px solid #cfd8e3;padding:2mm;">' . cag_h(cag_resumen_centros($codigo, $instituciones)) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Fecha de generación</td>'
        . '<td style="border:1px solid #cfd8e3;padding:2mm;">' . cag_h($fechaGeneracion) . '</td>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Incluye dados de baja</td>'
        . '<td style="border:1px solid #cfd8e3;padding:2mm;">' . ($incluirBaja ? 'Sí' : 'No') . '</td>'
        . '</tr>'
        . '</table>';

    if (count($instituciones) === 1) {
        $institucion = $instituciones[0];
        $regional = trim((string)($institucion['regional'] ?? ''));
        $circuito = trim((string)($institucion['circuito'] ?? ''));
        $codigoSaber = trim((string)($institucion['cod_saber'] ?? ''));
        $activo = (int)($institucion['activo'] ?? 0);

        $html .= '<div style="font-size:' . $tamanoFuente . ';margin-bottom:2mm;">'
            . '<strong>' . cag_h($tituloDoc) . '</strong>'
            . ' &nbsp;|&nbsp; <strong>Código SABER:</strong> ' . cag_h($codigoSaber !== '' ? $codigoSaber : 'Sin dato')
            . ' &nbsp;|&nbsp; <strong>Regional:</strong> ' . cag_h($regional !== '' ? $regional : 'Sin regional')
            . ' &nbsp;|&nbsp; <strong>Circuito:</strong> ' . cag_h($circuito !== '' ? $circuito : 'Sin circuito')
            . ' &nbsp;|&nbsp; <strong>Estado:</strong> ' . ($activo === 1 ? 'Activo' : 'Inactivo')
            . '</div>';
    }

    if ($codigo !== '' && empty($instituciones)) {
        $html .= '<div style="font-size:' . $tamanoFuente . ';color:#b02a37;font-weight:bold;margin-bottom:2mm;">'
            . 'No se encontró una institución registrada para el código ' . cag_h($codigo) . '.'
            . '</div>';
    }

    return $html . cag_html_detalle_sedes($instituciones, $tamanoFuente);
}

/**
 * Encabezados de las columnas del reporte (pantalla, Excel, PDF e impresión).
 */
function cag_columnas_reporte(): array
{
    return [
        'Tipo',
        'Marca',
        'Modelo',
        'Placa',
        'Serial',
        'Estado',
        'Ubicación',
        'Donación',
        'Robados',
        'Prestables',
        'En uso',
        'Fuente presupuestaria',
    ];
}

// =====================================================================
// Exportadores
// =====================================================================

function cag_exportar_excel(
    mysqli $link,
    array $contexto,
    array $filtros,
    string $nombreArchivo
): void {
    if (!cag_cargar_autoload()) {
        http_response_code(500);
        exit('La librería PhpSpreadsheet no está disponible.');
    }

    @ini_set('display_errors', '0');
    @set_time_limit(0);

    $filas = cag_obtener_activos_todos($link, $filtros);

    if (empty($filas)) {
        http_response_code(500);
        exit('No fue posible obtener los datos para exportar.');
    }

    $modo = $contexto['modo'];
    $codigo = (string)$contexto['codigo'];
    $instituciones = $contexto['instituciones'];
    $fuenteNombre = (string)$contexto['fuenteNombre'];
    $total = (int)$contexto['total'];
    $indicadores = $contexto['indicadores'];
    $incluirBaja = !empty($contexto['incluirBaja']);
    $fechaGeneracion = date('d/m/Y H:i');

    $etiqueta = (string)($modo['etiqueta'] ?? 'Consulta de activos');
    $tituloDoc = (string)($modo['titulo_doc'] ?? 'CONSULTA DE ACTIVOS');

    $documento = new Spreadsheet();
    $hoja = $documento->getActiveSheet();
    $hoja->setTitle('Consulta Activos');

    $documento->getProperties()
        ->setCreator('Sistema TecnoPresta')
        ->setTitle($tituloDoc)
        ->setSubject('Consulta de activos por código presupuestario')
        ->setDescription($etiqueta . ' por fuente presupuestaria y código presupuestario');

    $estiloEncabezado = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'name' => 'Arial',
            'size' => 9,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F4E79'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '9FB3C8'],
            ],
        ],
    ];

    $estiloDatos = [
        'font' => [
            'name' => 'Arial',
            'size' => 9,
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CFD8E3'],
            ],
        ],
    ];

    $hoja->mergeCells('A1:L1');
    $hoja->setCellValue('A1', 'MINISTERIO DE EDUCACIÓN PÚBLICA');
    $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Arial');
    $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->mergeCells('A2:L2');
    $hoja->setCellValue('A2', 'Dirección de Recursos Tecnológicos en Educación');
    $hoja->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->setName('Arial');
    $hoja->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->mergeCells('A3:L3');
    $hoja->setCellValue('A3', 'Sistema TecnoPresta');
    $hoja->getStyle('A3')->getFont()->setSize(9)->setName('Arial');
    $hoja->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->mergeCells('A4:L4');
    $hoja->setCellValue('A4', $tituloDoc);
    $hoja->getStyle('A4')->getFont()->setBold(true)->setSize(12)->setName('Arial');
    $hoja->getStyle('A4')->getFont()->getColor()->setRGB('FFFFFF');
    $hoja->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
    $hoja->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    // Las celdas de metadatos se escriben como texto explicito: el codigo
    // presupuestario puede empezar en cero (por ejemplo 0959) y no debe
    // convertirse a numero.
    $hoja->setCellValueExplicit('A5', 'Tipo de consulta:', DataType::TYPE_STRING);
    $hoja->mergeCells('B5:E5');
    $hoja->setCellValueExplicit('B5', $etiqueta, DataType::TYPE_STRING);
    $hoja->setCellValueExplicit('F5', 'Fuente presupuestaria:', DataType::TYPE_STRING);
    $hoja->mergeCells('G5:L5');
    $hoja->setCellValueExplicit('G5', $fuenteNombre, DataType::TYPE_STRING);

    $hoja->setCellValueExplicit('A6', 'Código presupuestario:', DataType::TYPE_STRING);
    $hoja->mergeCells('B6:E6');
    $hoja->setCellValueExplicit('B6', $codigo, DataType::TYPE_STRING);
    $hoja->setCellValueExplicit('F6', 'Total de activos:', DataType::TYPE_STRING);
    $hoja->setCellValue('G6', $total);
    $hoja->getStyle('G6')->getNumberFormat()->setFormatCode('#,##0');

    $hoja->setCellValueExplicit('A7', 'Centro(s):', DataType::TYPE_STRING);
    $hoja->mergeCells('B7:L7');
    $hoja->setCellValueExplicit('B7', cag_resumen_centros($codigo, $instituciones), DataType::TYPE_STRING);

    $hoja->setCellValueExplicit('A8', 'Fecha de generación:', DataType::TYPE_STRING);
    $hoja->mergeCells('B8:C8');
    $hoja->setCellValueExplicit('B8', $fechaGeneracion, DataType::TYPE_STRING);
    $hoja->setCellValueExplicit('D8', 'Incluye dados de baja:', DataType::TYPE_STRING);
    $hoja->mergeCells('E8:G8');
    $hoja->setCellValueExplicit('E8', $incluirBaja ? 'Sí' : 'No', DataType::TYPE_STRING);

    $hoja->getStyle('A5:A8')->getFont()->setBold(true)->setName('Arial')->setSize(9);
    $hoja->getStyle('B5:L7')->getAlignment()->setWrapText(true);
    $hoja->getStyle('B5:L7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $hoja->getStyle('B8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $hoja->getRowDimension(1)->setRowHeight(22);
    $hoja->getRowDimension(2)->setRowHeight(18);
    $hoja->getRowDimension(3)->setRowHeight(18);
    $hoja->getRowDimension(4)->setRowHeight(24);
    $hoja->getRowDimension(7)->setRowHeight(count($instituciones) > 1 ? 45 : 20);

    $encabezados = cag_columnas_reporte();
    $columna = 'A';

    foreach ($encabezados as $encabezado) {
        $hoja->setCellValue($columna . '10', $encabezado);
        $columna++;
    }

    $hoja->getStyle('A10:L10')->applyFromArray($estiloEncabezado);

    $especificaciones = cag_especificacion_indicadores();
    $filaExcel = 11;

    foreach ($filas as $fila) {
        $estadoInfo = cag_badge_estado($fila);

        $hoja->setCellValueExplicit('A' . $filaExcel, (string)$fila['tipo'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('B' . $filaExcel, (string)$fila['marca'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('C' . $filaExcel, (string)$fila['modelo'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('D' . $filaExcel, (string)$fila['placa'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('E' . $filaExcel, (string)$fila['serial'], DataType::TYPE_STRING);

        // Estado: conserva la condición y añade la marca de baja cuando aplica.
        $textoEstado = $estadoInfo['baja']
            ? 'BAJA · ' . $estadoInfo['estado']['texto']
            : $estadoInfo['estado']['texto'];

        $hoja->setCellValueExplicit('F' . $filaExcel, $textoEstado, DataType::TYPE_STRING);
        $hoja->getStyle('F' . $filaExcel)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(cag_color_tinte($estadoInfo['baja'] ? 'dark' : $estadoInfo['estado']['clase']));
        $hoja->getStyle('F' . $filaExcel)->getFont()->getColor()->setRGB(cag_color_fuente_excel());

        if ($estadoInfo['baja']) {
            $hoja->getStyle('F' . $filaExcel)->getFont()->setBold(true);
        }

        $hoja->setCellValueExplicit('G' . $filaExcel, (string)$fila['ubicacion'], DataType::TYPE_STRING);

        $columna = 'H';

        foreach (['donacion', 'robados', 'prestables', 'en_uso'] as $claveIndicador) {
            $spec = $especificaciones[$claveIndicador];
            $badge = cag_badge_indicador($fila, $spec);

            $hoja->setCellValueExplicit($columna . $filaExcel, $badge['texto'], DataType::TYPE_STRING);
            $hoja->getStyle($columna . $filaExcel)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($badge['tenue'] ? 'E2E8F0' : cag_color_tinte($badge['clase']));
            $hoja->getStyle($columna . $filaExcel)->getFont()->getColor()->setRGB(cag_color_fuente_excel());

            $columna++;
        }

        $hoja->setCellValueExplicit('L' . $filaExcel, (string)$fila['fuente'], DataType::TYPE_STRING);
        $filaExcel++;
    }

    $ultimaFila = $filaExcel - 1;

    $hoja->getStyle('A11:L' . $ultimaFila)->applyFromArray($estiloDatos);
    $hoja->getStyle('C11:C' . $ultimaFila)->getAlignment()->setWrapText(true);
    $hoja->getStyle('G11:L' . $ultimaFila)->getAlignment()->setWrapText(true);
    $hoja->getStyle('D11:F' . $ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('H11:K' . $ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('L11:L' . $ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Fila de totales de indicadores.
    $filaTotales = $ultimaFila + 1;
    $hoja->mergeCells('A' . $filaTotales . ':G' . $filaTotales);
    $hoja->setCellValue('A' . $filaTotales, 'TOTALES DE INDICADORES');
    $hoja->getStyle('A' . $filaTotales . ':G' . $filaTotales)->getFont()->setBold(true)->setName('Arial')->setSize(9);
    $hoja->getStyle('A' . $filaTotales . ':G' . $filaTotales)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $totales = [
        'H' => (int)($indicadores['cnt_donar'] ?? 0),
        'I' => (int)($indicadores['cnt_robado'] ?? 0),
        'J' => (int)($indicadores['cnt_prestable'] ?? 0),
        'K' => (int)($indicadores['cnt_en_uso'] ?? 0),
    ];

    foreach ($totales as $columnaTotal => $valorTotal) {
        $hoja->setCellValueExplicit($columnaTotal . $filaTotales, (string)$valorTotal, DataType::TYPE_STRING);
        $hoja->getStyle($columnaTotal . $filaTotales)->getFont()->setBold(true)->setName('Arial')->setSize(9);
        $hoja->getStyle($columnaTotal . $filaTotales)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $hoja->setCellValueExplicit('L' . $filaTotales, (string)(int)($indicadores['cnt_baja'] ?? 0) . ' bajas', DataType::TYPE_STRING);
    $hoja->getStyle('L' . $filaTotales)->getFont()->setItalic(true)->setName('Arial')->setSize(8);
    $hoja->getStyle('L' . $filaTotales)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->getStyle('A' . $filaTotales . ':L' . $filaTotales)->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('EEF3F9');

    $anchos = [
        'A' => 18, 'B' => 16, 'C' => 26, 'D' => 14, 'E' => 20, 'F' => 16,
        'G' => 20, 'H' => 11, 'I' => 10, 'J' => 11, 'K' => 11, 'L' => 24,
    ];

    foreach ($anchos as $letra => $ancho) {
        $hoja->getColumnDimension($letra)->setWidth($ancho);
    }

    $hoja->setAutoFilter('A10:L' . $ultimaFila);
    $hoja->freezePane('A11');
    $hoja->setShowGridLines(false);
    $hoja->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $hoja->getPageSetup()->setFitToWidth(1);
    $hoja->getPageSetup()->setFitToHeight(0);

    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $writer = new Xlsx($documento);
    $writer->save('php://output');
    exit;
}

function cag_exportar_pdf(
    mysqli $link,
    array $contexto,
    array $filtros,
    string $nombreArchivo
): void {
    if (!cag_cargar_autoload()) {
        http_response_code(500);
        exit('La librería mPDF no está disponible.');
    }

    @ini_set('display_errors', '0');
    @ini_set('memory_limit', '2048M');
    @set_time_limit(0);

    $filas = cag_obtener_activos_todos($link, $filtros);

    if (empty($filas)) {
        http_response_code(500);
        exit('No fue posible obtener los datos para exportar.');
    }

    $modo = $contexto['modo'];
    $tituloDoc = (string)($modo['titulo_doc'] ?? 'CONSULTA DE ACTIVOS');
    $etiqueta = (string)($modo['etiqueta'] ?? 'Consulta de activos');
    $fechaGeneracion = date('d/m/Y H:i');

    $contexto['fechaGeneracion'] = $fechaGeneracion;

    $logoRuta = __DIR__ . '/img/logo-mep 2025.png';
    $logoVariable = '';
    $logoDatos = '';

    if (is_readable($logoRuta)) {
        $contenidoLogo = file_get_contents($logoRuta);

        if ($contenidoLogo !== false) {
            $logoDatos = $contenidoLogo;
            $logoVariable = 'var:logoMep';
        }
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 9,
        'margin_right' => 9,
        'margin_top' => 27,
        'margin_bottom' => 15,
        'margin_header' => 7,
        'margin_footer' => 7,
        'default_font_size' => 8,
        'default_font' => 'dejavusans',
    ]);

    $mpdf->shrink_tables_to_fit = 1;
    $mpdf->keep_table_proportions = true;
    $mpdf->autoScriptToLang = true;
    $mpdf->autoLangToFont = true;

    $mpdf->SetTitle($tituloDoc);
    $mpdf->SetAuthor('Sistema TecnoPresta');
    $mpdf->SetCreator('Sistema TecnoPresta');
    $mpdf->SetSubject('Consulta de activos: ' . $etiqueta);

    if ($logoVariable !== '') {
        $mpdf->imageVars['logoMep'] = $logoDatos;
    }

    $logoHtml = $logoVariable !== ''
        ? '<img src="' . $logoVariable . '" style="height:22px;" alt="MEP">'
        : '<strong>MINISTERIO DE EDUCACIÓN PÚBLICA</strong>';

    $mpdf->SetHTMLHeader(
        '<table width="100%" style="border-bottom:1px solid #1F4E79;padding-bottom:3px;font-family:sans-serif;font-size:7pt;color:#555;">'
        . '<tr>'
        . '<td style="width:55%;vertical-align:middle;">' . $logoHtml . '</td>'
        . '<td style="width:45%;text-align:right;vertical-align:middle;">'
        . '<strong style="color:#1F4E79;">' . cag_h($tituloDoc) . '</strong><br>Sistema TecnoPresta'
        . '</td>'
        . '</tr>'
        . '</table>'
    );

    $mpdf->SetHTMLFooter(
        '<div style="border-top:1px solid #CCCCCC;padding-top:3px;font-family:sans-serif;font-size:7pt;color:#666;text-align:center;">'
        . 'Página {PAGENO} de {nbpg} | Generado: ' . cag_h($fechaGeneracion)
        . '</div>'
    );

    $mpdf->WriteHTML(cag_html_resumen($contexto, '7.5pt'));

    $mpdf->WriteHTML(
        '<table style="width:100%;border-collapse:collapse;font-size:6.5pt;">'
        . '<colgroup>'
        . '<col style="width:9%;"><col style="width:8%;"><col style="width:13%;"><col style="width:9%;">'
        . '<col style="width:12%;"><col style="width:11%;"><col style="width:11%;"><col style="width:5%;">'
        . '<col style="width:5%;"><col style="width:6%;"><col style="width:6%;"><col style="width:5%;">'
        . '</colgroup>'
        . '<thead><tr>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Tipo</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Marca</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Modelo</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Placa</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Serial</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Estado</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Ubicación</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Donación</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Robados</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Prestables</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">En uso</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Fuente</th>'
        . '</tr></thead><tbody>',
        \Mpdf\HTMLParserMode::HTML_BODY,
        false,
        false
    );

    $especificaciones = cag_especificacion_indicadores();
    $topeBloque = 300000;
    $bloqueFilas = '';

    foreach ($filas as $fila) {
        $estadoInfo = cag_badge_estado($fila);

        $celdaEstado = $estadoInfo['baja']
            ? cag_badge_estilo('Baja', 'dark') . ' ' . cag_badge_estilo($estadoInfo['estado']['texto'], $estadoInfo['estado']['clase'])
            : cag_badge_estilo($estadoInfo['estado']['texto'], $estadoInfo['estado']['clase']);

        $celda = '<tr>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;">' . cag_h($fila['tipo']) . '</td>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;">' . cag_h($fila['marca']) . '</td>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;word-wrap:break-word;">' . cag_h($fila['modelo']) . '</td>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;text-align:center;">' . cag_h($fila['placa']) . '</td>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;">' . cag_h($fila['serial']) . '</td>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;text-align:center;">' . $celdaEstado . '</td>'
            . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;word-wrap:break-word;">' . cag_h($fila['ubicacion']) . '</td>';

        foreach (['donacion', 'robados', 'prestables', 'en_uso'] as $claveIndicador) {
            $badge = cag_badge_indicador($fila, $especificaciones[$claveIndicador]);
            $celda .= '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;text-align:center;">'
                . cag_badge_estilo($badge['texto'], $badge['clase'], $badge['tenue']) . '</td>';
        }

        $celda .= '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;word-wrap:break-word;">'
            . cag_h($fila['fuente']) . '</td>'
            . '</tr>';

        $bloqueFilas .= $celda;

        if (strlen($bloqueFilas) >= $topeBloque) {
            $mpdf->WriteHTML($bloqueFilas, \Mpdf\HTMLParserMode::HTML_BODY, false, false);
            $bloqueFilas = '';
        }
    }

    if ($bloqueFilas !== '') {
        $mpdf->WriteHTML($bloqueFilas, \Mpdf\HTMLParserMode::HTML_BODY, false, false);
    }

    // Fila de totales de indicadores.
    $indicadores = $contexto['indicadores'];
    $mpdf->WriteHTML(
        '<tr style="background:#eef3f9;font-weight:bold;">'
        . '<td colspan="7" style="border:1px solid #CFD8E3;padding:2px;text-align:right;">TOTALES DE INDICADORES</td>'
        . '<td style="border:1px solid #CFD8E3;padding:2px;text-align:center;">' . cag_num((int)($indicadores['cnt_donar'] ?? 0)) . '</td>'
        . '<td style="border:1px solid #CFD8E3;padding:2px;text-align:center;">' . cag_num((int)($indicadores['cnt_robado'] ?? 0)) . '</td>'
        . '<td style="border:1px solid #CFD8E3;padding:2px;text-align:center;">' . cag_num((int)($indicadores['cnt_prestable'] ?? 0)) . '</td>'
        . '<td style="border:1px solid #CFD8E3;padding:2px;text-align:center;">' . cag_num((int)($indicadores['cnt_en_uso'] ?? 0)) . '</td>'
        . '<td style="border:1px solid #CFD8E3;padding:2px;text-align:center;">' . cag_num((int)($indicadores['cnt_baja'] ?? 0)) . ' bajas</td>'
        . '</tr></tbody></table>',
        \Mpdf\HTMLParserMode::HTML_BODY,
        false,
        true
    );

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $mpdf->Output($nombreArchivo, 'D');
    exit;
}

function cag_imprimir(
    mysqli $link,
    array $contexto,
    array $filtros
): void {
    @ini_set('display_errors', '0');

    $filas = cag_obtener_activos_todos($link, $filtros);

    if (empty($filas)) {
        http_response_code(500);
        exit('No fue posible obtener los datos para imprimir.');
    }

    $modo = $contexto['modo'];
    $tituloDoc = (string)($modo['titulo_doc'] ?? 'CONSULTA DE ACTIVOS');
    $fechaGeneracion = date('d/m/Y H:i');
    $contexto['fechaGeneracion'] = $fechaGeneracion;

    $resumen = cag_html_resumen($contexto, '8pt');
    $especificaciones = cag_especificacion_indicadores();
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= cag_h($tituloDoc) ?></title>
    <style>
        @page { size: A4 landscape; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #222; font-family: Arial, Helvetica, sans-serif; font-size: 8pt; }
        .toolbar { display: flex; gap: 8px; justify-content: flex-end; padding: 10px 14px; background: #f1f5f9; border-bottom: 1px solid #cfd8e3; }
        .toolbar button { border: 0; border-radius: 6px; padding: 8px 16px; font-weight: 600; cursor: pointer; }
        .toolbar .imprimir { background: #1F4E79; color: #fff; }
        .toolbar .cerrar { background: #e2e8f0; color: #1f2937; }
        .contenido { padding: 10px 14px 18px; }
        .doc-header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1F4E79; margin-bottom: 4mm; }
        .doc-header td { vertical-align: middle; padding: 0 0 3mm; }
        .doc-header img { height: 30px; }
        .doc-header .titulo { text-align: right; }
        .doc-header .titulo strong { display: block; color: #1F4E79; font-size: 13pt; }
        .doc-header .titulo span { color: #555; font-size: 8pt; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos thead { display: table-header-group; }
        table.datos tr { page-break-inside: avoid; }
        table.datos th { background: #1F4E79; color: #fff; border: 1px solid #9FB3C8; padding: 3px; font-size: 7pt; text-align: center; }
        table.datos td { border: 1px solid #CFD8E3; padding: 3px; font-size: 7pt; vertical-align: top; }
        table.datos td.centro { text-align: center; }
        table.datos tfoot td { background: #eef3f9; font-weight: bold; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 8pt; }
            .contenido { padding: 0; }
            table.datos th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table.datos tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" class="imprimir" onclick="window.print()">Imprimir</button>
        <button type="button" class="cerrar" onclick="window.close()">Cerrar</button>
    </div>

    <div class="contenido">
        <table class="doc-header">
            <tr>
                <td style="width:55%;">
                    <img src="img/logo-mep 2025.png" alt="Ministerio de Educación Pública">
                </td>
                <td class="titulo" style="width:45%;">
                    <strong><?= cag_h($tituloDoc) ?></strong>
                    <span>Sistema TecnoPresta · Generado: <?= cag_h($fechaGeneracion) ?></span>
                </td>
            </tr>
        </table>

        <?= $resumen ?>

        <table class="datos">
            <colgroup>
                <col style="width:9%;"><col style="width:8%;"><col style="width:13%;"><col style="width:9%;">
                <col style="width:12%;"><col style="width:11%;"><col style="width:11%;"><col style="width:5%;">
                <col style="width:5%;"><col style="width:6%;"><col style="width:6%;"><col style="width:5%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Placa</th>
                    <th>Serial</th>
                    <th>Estado</th>
                    <th>Ubicación</th>
                    <th>Donación</th>
                    <th>Robados</th>
                    <th>Prestables</th>
                    <th>En uso</th>
                    <th>Fuente</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <?php $estadoInfo = cag_badge_estado($fila); ?>
                    <tr>
                        <td><?= cag_h($fila['tipo']) ?></td>
                        <td><?= cag_h($fila['marca']) ?></td>
                        <td><?= cag_h($fila['modelo']) ?></td>
                        <td class="centro"><?= cag_h($fila['placa']) ?></td>
                        <td><?= cag_h($fila['serial']) ?></td>
                        <td class="centro">
                            <?php if ($estadoInfo['baja']): ?>
                                <?= cag_badge_estilo('Baja', 'dark') ?>
                                <?= cag_badge_estilo($estadoInfo['estado']['texto'], $estadoInfo['estado']['clase']) ?>
                            <?php else: ?>
                                <?= cag_badge_estilo($estadoInfo['estado']['texto'], $estadoInfo['estado']['clase']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= cag_h($fila['ubicacion']) ?></td>
                        <?php foreach (['donacion', 'robados', 'prestables', 'en_uso'] as $claveIndicador): ?>
                            <?php $badge = cag_badge_indicador($fila, $especificaciones[$claveIndicador]); ?>
                            <td class="centro"><?= cag_badge_estilo($badge['texto'], $badge['clase'], $badge['tenue']) ?></td>
                        <?php endforeach; ?>
                        <td><?= cag_h($fila['fuente']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" style="text-align:right;">TOTALES DE INDICADORES</td>
                    <td class="centro"><?= cag_num((int)($contexto['indicadores']['cnt_donar'] ?? 0)) ?></td>
                    <td class="centro"><?= cag_num((int)($contexto['indicadores']['cnt_robado'] ?? 0)) ?></td>
                    <td class="centro"><?= cag_num((int)($contexto['indicadores']['cnt_prestable'] ?? 0)) ?></td>
                    <td class="centro"><?= cag_num((int)($contexto['indicadores']['cnt_en_uso'] ?? 0)) ?></td>
                    <td class="centro"><?= cag_num((int)($contexto['indicadores']['cnt_baja'] ?? 0)) ?> bajas</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
    <?php
    exit;
}

// =====================================================================
// Procesamiento de la petición
// =====================================================================

$modos = cag_modos_disponibles();

$fuentes = cag_obtener_fuentes($link);
$mapaFuentes = cag_mapa_fuentes($fuentes);

$fuenteRaw = isset($_GET['fuente']) && is_string($_GET['fuente']) ? $_GET['fuente'] : '0';
$idFondos = preg_match('/^[0-9]+$/', $fuenteRaw) ? (int)$fuenteRaw : 0;
$errorFuente = '';

if ($idFondos > 0 && !isset($mapaFuentes[$idFondos])) {
    $errorFuente = 'La fuente presupuestaria seleccionada no es válida.';
    $idFondos = 0;
}

// Modo de consulta: se valida contra el catálogo. Una clave desconocida nunca
// provoca un error SQL, se aplica el modo predeterminado con un aviso.
$modoRaw = isset($_GET['modo']) && is_string($_GET['modo']) ? trim($_GET['modo']) : '';
$modo = cag_resolver_modo($modoRaw);
$errorModo = '';

if ($modo === null) {
    $modo = cag_modo_defecto();
    $errorModo = 'El tipo de consulta solicitado no es válido. Se aplicó «'
        . cag_modo_etiqueta($modo) . '».';
}

$codigoRaw = isset($_GET['codigo']) && is_string($_GET['codigo']) ? $_GET['codigo'] : '';
$codigoInput = trim($codigoRaw);
$codigo = '';
$errorCodigo = '';

if ($codigoInput !== '') {
    [$codigo, $errorCodigo] = cag_normalizar_codigo($codigoInput);

    if ($errorCodigo === '') {
        $codigoInput = $codigo;
    }
}

$incluirBaja = isset($_GET['baja']) && (string)$_GET['baja'] === '1';

// El código presupuestario es obligatorio: sin él no se ejecuta ninguna consulta.
$hayIntentoConsulta = isset($_GET['buscar']) || $codigoInput !== '';
$filtrosValidos = $errorCodigo === '' && $codigo !== '';
$consultaRealizada = $hayIntentoConsulta && $filtrosValidos;

$fuenteNombre = cag_nombre_fuente($mapaFuentes, $idFondos);

$filtros = $consultaRealizada
    ? cag_construir_filtros($modo, $idFondos, $codigo, $incluirBaja)
    : null;

$total = $filtros !== null ? cag_contar_activos($link, $filtros) : 0;
$indicadores = $filtros !== null ? cag_obtener_indicadores($link, $filtros) : [];

$porPagina = 100;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

// La acción solicitada se detecta antes de paginar: cuando se exporta o se
// imprime no hace falta traer la página en pantalla, el exportador obtiene
// el juego completo de filas con una sola consulta.
$accion = isset($_GET['exportar']) && is_string($_GET['exportar'])
    ? strtolower(trim($_GET['exportar']))
    : '';
$imprimir = isset($_GET['imprimir']) && (string)$_GET['imprimir'] === '1';
$esExportacion = $accion !== '' || $imprimir;

$instituciones = $consultaRealizada ? cag_obtener_instituciones_por_codigo($link, $codigo) : [];
$activos = ($consultaRealizada && $total > 0 && !$esExportacion)
    ? cag_obtener_activos($link, $filtros, $porPagina, $offset)
    : [];

$desde = $total > 0 ? $offset + 1 : 0;
$hasta = $total > 0 ? min($offset + $porPagina, $total) : 0;

// ------------------------------------------------------------------ Avisos
$mensajes = [];

if ($errorCodigo !== '') {
    $mensajes[] = ['tipo' => 'danger', 'texto' => $errorCodigo];
} elseif ($hayIntentoConsulta && !$filtrosValidos) {
    $mensajes[] = [
        'tipo'  => 'danger',
        'texto' => 'El código presupuestario es obligatorio para realizar la consulta. '
            . 'Debe contener solo dígitos, de 1 a 4 posiciones.',
    ];
}

if ($errorFuente !== '') {
    $mensajes[] = [
        'tipo'  => 'warning',
        'texto' => $errorFuente . ' Se consultaron todas las fuentes presupuestarias.',
    ];
}

if ($errorModo !== '') {
    $mensajes[] = ['tipo' => 'warning', 'texto' => $errorModo];
}

if (isset($_GET['msg']) && is_string($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'sin_datos':
            $mensajes[] = ['tipo' => 'warning', 'texto' => 'No hay activos para los filtros seleccionados.'];
            break;
        case 'filtros_invalidos':
            $mensajes[] = ['tipo' => 'warning', 'texto' => 'Los filtros no son válidos. Revísalos e intenta de nuevo.'];
            break;
        case 'accion_invalida':
            $mensajes[] = ['tipo' => 'warning', 'texto' => 'La acción solicitada no es válida.'];
            break;
    }
}

// ------------------------------------------------------------------ Parámetros de URL
$paramsBase = ['modo' => $modo['clave']];

if ($idFondos > 0) {
    $paramsBase['fuente'] = $idFondos;
}

if ($codigo !== '') {
    $paramsBase['codigo'] = $codigo;
}

if ($incluirBaja) {
    $paramsBase['baja'] = 1;
}

// ------------------------------------------------------------------ Exportaciones
if ($esExportacion) {
    if (!$consultaRealizada) {
        header('Location: ' . cag_url(['msg' => 'filtros_invalidos']));
        exit;
    }

    if ($total === 0) {
        header('Location: ' . cag_url(array_merge($paramsBase, ['msg' => 'sin_datos'])));
        exit;
    }

    $contexto = [
        'modo'          => $modo,
        'codigo'        => $codigo,
        'idFondos'      => $idFondos,
        'fuenteNombre'  => $fuenteNombre,
        'instituciones' => $instituciones,
        'total'         => $total,
        'indicadores'   => $indicadores,
        'incluirBaja'   => $incluirBaja,
        'fechaGeneracion' => date('d/m/Y H:i'),
    ];

    $nombreArchivo = $modo['archivo'] . '_' . $codigo . '_' . date('Ymd_His');

    if ($accion === 'excel') {
        cag_exportar_excel($link, $contexto, $filtros, $nombreArchivo . '.xlsx');
    }

    if ($accion === 'pdf') {
        cag_exportar_pdf($link, $contexto, $filtros, $nombreArchivo . '.pdf');
    }

    if ($imprimir) {
        cag_imprimir($link, $contexto, $filtros);
    }

    header('Location: ' . cag_url(array_merge($paramsBase, ['msg' => 'accion_invalida'])));
    exit;
}

$urlExcel = cag_url(array_merge($paramsBase, ['exportar' => 'excel']));
$urlPdf = cag_url(array_merge($paramsBase, ['exportar' => 'pdf']));
$urlImprimir = cag_url(array_merge($paramsBase, ['imprimir' => '1']));
$urlLimpiar = cag_url([]);
$rangoInicio = max(1, $pagina - 2);
$rangoFin = min($totalPaginas, $pagina + 2);

$especificaciones = cag_especificacion_indicadores();

// Tarjetas de resumen: valor principal y desglose de los estados restantes.
$tarjetas = [
    [
        'titulo'   => 'Donación',
        'icono'    => 'bi-gift',
        'color'    => 'success',
        'valor'    => (int)($indicadores['cnt_donar'] ?? 0),
        'detalles' => [
            ['conteo' => (int)($indicadores['cnt_donar_sin_marcar'] ?? 0), 'texto' => 'sin marcar'],
        ],
    ],
    [
        'titulo'   => 'Robados',
        'icono'    => 'bi-shield-exclamation',
        'color'    => 'dark',
        'valor'    => (int)($indicadores['cnt_robado'] ?? 0),
        'detalles' => [],
    ],
    [
        'titulo'   => 'Prestables',
        'icono'    => 'bi-arrow-left-right',
        'color'    => 'info',
        'valor'    => (int)($indicadores['cnt_prestable'] ?? 0),
        'detalles' => [
            ['conteo' => (int)($indicadores['cnt_no_prestable'] ?? 0), 'texto' => 'no prestables'],
        ],
    ],
    [
        'titulo'   => 'En uso',
        'icono'    => 'bi-person-check',
        'color'    => 'success',
        'valor'    => (int)($indicadores['cnt_en_uso'] ?? 0),
        'detalles' => [
            ['conteo' => (int)($indicadores['cnt_sin_uso'] ?? 0), 'texto' => 'sin uso'],
            ['conteo' => (int)($indicadores['cnt_sin_evaluar'] ?? 0), 'texto' => 'sin evaluar'],
        ],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="manifest" href="manifest.json">
    <title>Consulta de Activos</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=9">
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <style>
        .filtro-card { border: 1px solid #dbe4ef; border-radius: 14px; }
        .resumen-card { border: 0; border-left: 5px solid var(--mep-primary); border-radius: 12px; }
        .indicador-card { border: 1px solid #dbe4ef; border-radius: 12px; background: #fff; height: 100%; }
        .indicador-card .indicador-valor { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
        .indicador-card .indicador-icono { font-size: 1.5rem; opacity: .65; }
        .detail-label { color: #6c757d; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .detail-value { color: #213547; font-weight: 600; }
        .tabla-activos { min-width: 1420px; }
        .tabla-activos thead th { background: #1F4E79; color: #fff; border-color: #9FB3C8; font-size: .74rem; letter-spacing: .03em; text-transform: uppercase; vertical-align: middle; white-space: nowrap; }
        .tabla-activos tbody td { font-size: .86rem; vertical-align: middle; }
        .tabla-activos .badge { font-size: .72rem; font-weight: 600; padding: .38em .55em; }
        .tabla-activos .badge.bg-warning { color: #212529; }
        .tabla-activos tfoot td { background: #eef3f9; color: #1F4E79; font-weight: 700; font-size: .84rem; }
        .tabla-nota { font-size: .78rem; color: #6c757d; }
        .empty-state { border-radius: 14px; }
        .sedes-tabla th { background: #eef3f9; color: #1F4E79; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
        .sedes-tabla td { font-size: .84rem; }
        .pagination .page-link { color: #1F4E79; }
        .pagination .page-item.active .page-link { background-color: #1F4E79; border-color: #1F4E79; }
        @media (max-width: 767.98px) {
            .tabla-activos { min-width: 1100px; }
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>
    <main class="contenido-principal">
        <div class="container">
            <div class="hero-box mb-4 mt-2 fade-enter">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3">
                            <div class="hero-icon">
                                <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-1">Consulta de Activos</h2>
                                <p class="mb-0 opacity-75">Consulta por tipo de activo, fuente presupuestaria y código presupuestario</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================= Filtros -->
        <div class="container mb-4">
            <div class="card filtro-card shadow-sm d-print-none">
                <div class="card-body p-4">
                    <form method="get" action="navegar.php" id="form-consulta" class="row g-3 align-items-start">
                        <input type="hidden" name="ruta" value="consulta_activos_n.php">
                        <input type="hidden" name="buscar" value="1">
                        <?php if (isset($_GET['subsistema_id'], $_GET['modulo_id'])): ?>
                            <input type="hidden" name="subsistema_id" value="<?= (int)$_GET['subsistema_id'] ?>">
                            <input type="hidden" name="modulo_id" value="<?= (int)$_GET['modulo_id'] ?>">
                        <?php endif; ?>

                        <div class="col-lg-5">
                            <label for="modo" class="form-label fw-semibold">Tipo de consulta</label>
                            <div class="input-group">
                                <select name="modo" id="modo" class="form-select" onchange="cagCambioModo();">
                                    <?php foreach (cag_modos_agrupados() as $grupo): ?>
                                        <optgroup label="<?= cag_h($grupo['grupo']) ?>">
                                            <?php foreach ($grupo['modos'] as $opcion): ?>
                                                <option value="<?= cag_h($opcion['clave']) ?>"
                                                        data-descripcion="<?= cag_h($opcion['descripcion']) ?>"
                                                        data-icono="<?= cag_h($opcion['icono']) ?>"
                                                        data-color="<?= cag_h($opcion['color']) ?>"
                                                        <?= $modo['clave'] === $opcion['clave'] ? 'selected' : '' ?>>
                                                    <?= cag_h($opcion['etiqueta']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-group-text bg-<?= cag_h($modo['color']) ?> text-white" id="badge-modo" title="Tipo de consulta activo">
                                    <i class="bi <?= cag_h($modo['icono']) ?>" id="icono-modo"></i>
                                </span>
                            </div>
                            <div class="form-text" id="descripcion-modo"><?= cag_h($modo['descripcion']) ?></div>
                        </div>

                        <div class="col-lg-3">
                            <label for="fuente" class="form-label fw-semibold">Fuente presupuestaria</label>
                            <select name="fuente" id="fuente" class="form-select">
                                <option value="0">Todas las fuentes</option>
                                <?php foreach ($fuentes as $fuente): ?>
                                    <option value="<?= (int)$fuente['id_fondos'] ?>" <?= $idFondos === (int)$fuente['id_fondos'] ? 'selected' : '' ?>>
                                        <?= cag_h($fuente['fondos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label for="codigo" class="form-label fw-semibold">
                                Código presupuestario <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="codigo" id="codigo" class="form-control"
                                   inputmode="numeric" pattern="[0-9]{1,4}" maxlength="4"
                                   title="Solo dígitos, de 1 a 4 posiciones."
                                   autocomplete="off" value="<?= cag_h($codigoInput) ?>" placeholder="Ej. 0326">
                            <div class="form-text">Obligatorio · se completa a 4 dígitos (326 se busca como 0326).</div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="form-check mt-0">
                                    <input class="form-check-input" type="checkbox" name="baja" id="baja" value="1"
                                           <?= $incluirBaja ? 'checked' : '' ?> <?= $modo['ignora_activo'] ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="baja">
                                        Incluir activos dados de baja
                                        <?php if ($modo['ignora_activo']): ?>
                                            <span class="text-muted small">(el tipo de consulta «<?= cag_h($modo['etiqueta']) ?>» ya los incluye)</span>
                                        <?php endif; ?>
                                    </label>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-mep-primary flex-grow-1">
                                        <i class="bi bi-search me-2"></i>Buscar
                                    </button>
                                    <a href="<?= cag_h($urlLimpiar) ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-eraser me-1"></i>Limpiar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================================================= Avisos -->
        <?php if (!empty($mensajes)): ?>
            <div class="container mb-4">
                <?php foreach ($mensajes as $mensaje): ?>
                    <div class="alert alert-<?= cag_h($mensaje['tipo']) ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= cag_h($mensaje['texto']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ================================================= Tarjetas de indicadores -->
        <?php if ($total > 0): ?>
            <div class="container mb-4">
                <div class="row g-3">
                    <?php foreach ($tarjetas as $tarjeta): ?>
                        <div class="col-6 col-lg-3">
                            <div class="card indicador-card shadow-sm">
                                <div class="card-body py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="detail-label"><?= cag_h($tarjeta['titulo']) ?></div>
                                            <div class="indicador-valor text-<?= cag_h($tarjeta['color']) ?>"><?= cag_num($tarjeta['valor']) ?></div>
                                        </div>
                                        <i class="bi <?= cag_h($tarjeta['icono']) ?> indicador-icono text-<?= cag_h($tarjeta['color']) ?>"></i>
                                    </div>
                                    <?php
                                    $detalles = [];
                                    foreach ($tarjeta['detalles'] as $detalle) {
                                        if ((int)$detalle['conteo'] > 0) {
                                            $detalles[] = cag_num((int)$detalle['conteo']) . ' ' . cag_h($detalle['texto']);
                                        }
                                    }
                                    ?>
                                    <?php if ($detalles !== []): ?>
                                        <div class="small text-muted mt-1"><?= implode(' &middot; ', $detalles) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= Centro(s) -->
        <?php if ($consultaRealizada): ?>
            <div class="container mb-4">
                <div class="card resumen-card shadow-sm">
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <?php if (empty($instituciones)): ?>
                                    <h5 class="fw-bold mb-2" style="color: var(--mep-primary);">
                                        <i class="bi bi-exclamation-circle me-2"></i>Sin institución registrada
                                    </h5>
                                    <div class="alert alert-warning mb-0">
                                        No se encontró una institución para el código <?= cag_h($codigo) ?>. Los activos se muestran igualmente.
                                    </div>
                                <?php elseif (count($instituciones) === 1): ?>
                                    <?php $institucion = $instituciones[0]; ?>
                                    <h5 class="fw-bold mb-3" style="color: var(--mep-primary);">
                                        <i class="bi bi-building me-2"></i><?= cag_h($institucion['institucion']) ?>
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Código presupuestario</div>
                                            <div class="detail-value"><?= cag_h($institucion['codigo']) ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Código SABER</div>
                                            <div class="detail-value"><?= cag_h($institucion['cod_saber'] ?: 'Sin dato') ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Regional</div>
                                            <div class="detail-value"><?= cag_h($institucion['regional'] ?: 'Sin regional') ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Circuito</div>
                                            <div class="detail-value"><?= cag_h($institucion['circuito'] ?: 'Sin circuito') ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Estado</div>
                                            <div>
                                                <span class="badge bg-<?= (int)$institucion['activo'] === 1 ? 'success' : 'secondary' ?>">
                                                    <?= (int)$institucion['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <h5 class="fw-bold mb-2" style="color: var(--mep-primary);">
                                        <i class="bi bi-diagram-3 me-2"></i>Código presupuestario <?= cag_h($codigo) ?> · <?= count($instituciones) ?> sedes
                                    </h5>
                                    <p class="text-muted small mb-3">Los activos están registrados a nivel del código presupuestario; no se atribuyen a una sede específica.</p>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle sedes-tabla mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Institución</th>
                                                    <th>Código SABER</th>
                                                    <th>Regional</th>
                                                    <th>Circuito</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($instituciones as $institucion): ?>
                                                    <tr>
                                                        <td><?= cag_h($institucion['institucion']) ?></td>
                                                        <td><?= cag_h($institucion['cod_saber'] ?: 'Sin dato') ?></td>
                                                        <td><?= cag_h($institucion['regional'] ?: 'Sin regional') ?></td>
                                                        <td><?= cag_h($institucion['circuito'] ?: 'Sin circuito') ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= (int)$institucion['activo'] === 1 ? 'success' : 'secondary' ?>">
                                                                <?= (int)$institucion['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex flex-column gap-3">
                                    <div>
                                        <div class="detail-label">Tipo de consulta</div>
                                        <div class="detail-value"><?= cag_h($modo['etiqueta']) ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Fuente presupuestaria</div>
                                        <div class="detail-value"><?= cag_h($fuenteNombre) ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Código consultado</div>
                                        <div class="detail-value"><?= cag_h($codigo) ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Total de activos</div>
                                        <div class="fw-bold" style="color: var(--mep-primary); font-size: 2rem; line-height: 1;">
                                            <?= cag_num($total) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= Encabezado de resultados -->
        <div class="container mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h4 class="fw-bold mb-0" style="color: var(--mep-primary);">
                        <i class="bi bi-list-check me-2"></i>Activos encontrados
                    </h4>
                    <small class="text-muted">
                        <?php if ($total > 0): ?>
                            Mostrando <?= cag_num($desde) ?>&ndash;<?= cag_num($hasta) ?> de <?= cag_num($total) ?> activos
                            <?php if ((int)($indicadores['cnt_baja'] ?? 0) > 0): ?>
                                · <?= cag_num((int)$indicadores['cnt_baja']) ?> dados de baja
                            <?php endif; ?>
                        <?php elseif ($consultaRealizada): ?>
                            0 activos encontrados
                        <?php else: ?>
                            Sin consulta realizada
                        <?php endif; ?>
                    </small>
                </div>

                <?php if ($total > 0): ?>
                    <div class="d-flex flex-wrap gap-2 d-print-none">
                        <a class="btn btn-outline-success" href="<?= cag_h($urlExcel) ?>">
                            <i class="bi bi-file-earmark-excel me-1"></i>Excel
                        </a>
                        <a class="btn btn-outline-danger" href="<?= cag_h($urlPdf) ?>">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= cag_h($urlImprimir) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i>Imprimir
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================= Tabla -->
        <div class="container mb-4">
            <?php if ($total > 0): ?>
                <div class="card shadow-sm border-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle tabla-activos mb-0">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Placa</th>
                                    <th>Serial</th>
                                    <th>Estado</th>
                                    <th>Ubicación</th>
                                    <th>Donación</th>
                                    <th>Robados</th>
                                    <th>Prestables</th>
                                    <th>En uso</th>
                                    <th>Fuente presupuestaria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activos as $activo): ?>
                                    <?php $estadoInfo = cag_badge_estado($activo); ?>
                                    <tr>
                                        <td><?= cag_h($activo['tipo']) ?></td>
                                        <td><?= cag_h($activo['marca']) ?></td>
                                        <td><?= cag_h($activo['modelo']) ?></td>
                                        <td class="text-nowrap"><?= cag_h($activo['placa']) ?></td>
                                        <td class="text-nowrap"><?= cag_h($activo['serial']) ?></td>
                                        <td class="text-nowrap">
                                            <?php if ($estadoInfo['baja']): ?>
                                                <?= cag_badge_html('Baja', 'dark') ?>
                                                <?= cag_badge_html($estadoInfo['estado']['texto'], $estadoInfo['estado']['clase']) ?>
                                            <?php else: ?>
                                                <?= cag_badge_html($estadoInfo['estado']['texto'], $estadoInfo['estado']['clase']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= cag_h($activo['ubicacion']) ?></td>
                                        <?php foreach (['donacion', 'robados', 'prestables', 'en_uso'] as $claveIndicador): ?>
                                            <?php $badge = cag_badge_indicador($activo, $especificaciones[$claveIndicador]); ?>
                                            <td class="text-center">
                                                <?= cag_badge_html($badge['texto'], $badge['clase'], $badge['tenue']) ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><?= cag_h($activo['fuente']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="7" class="text-end">TOTALES DE INDICADORES</td>
                                    <td class="text-center"><?= cag_num((int)($indicadores['cnt_donar'] ?? 0)) ?></td>
                                    <td class="text-center"><?= cag_num((int)($indicadores['cnt_robado'] ?? 0)) ?></td>
                                    <td class="text-center"><?= cag_num((int)($indicadores['cnt_prestable'] ?? 0)) ?></td>
                                    <td class="text-center"><?= cag_num((int)($indicadores['cnt_en_uso'] ?? 0)) ?></td>
                                    <td class="text-center"><?= cag_num((int)($indicadores['cnt_baja'] ?? 0)) ?> bajas</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <p class="tabla-nota mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    «Sin marcar» indica que el activo no tiene registrado el indicador de donación;
                    «Sin evaluar» que el uso nunca fue evaluado.
                    Solo se incluyen activos con código presupuestario de 4 dígitos.
                </p>
            <?php elseif (!$hayIntentoConsulta): ?>
                <div class="card shadow-sm border-0 empty-state">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-search" style="font-size: 3rem; color: #9fb0c6;"></i>
                        <h5 class="fw-bold mt-3">Indique el código presupuestario para iniciar la consulta</h5>
                        <p class="text-muted mb-0">
                            El código presupuestario es obligatorio. Seleccione el tipo de consulta, indique la fuente
                            y escriba el código de 4 dígitos para ver los activos del centro.
                        </p>
                    </div>
                </div>
            <?php elseif ($consultaRealizada): ?>
                <div class="card shadow-sm border-0 empty-state">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #9fb0c6;"></i>
                        <h5 class="fw-bold mt-3">No hay activos para mostrar</h5>
                        <p class="text-muted mb-0">
                            No hay activos en «<?= cag_h($modo['etiqueta']) ?>» para el código <?= cag_h($codigo) ?>
                            <?= $idFondos > 0 ? 'y la fuente ' . cag_h($fuenteNombre) : '' ?>.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 empty-state">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #d9a441;"></i>
                        <h5 class="fw-bold mt-3">No fue posible realizar la consulta</h5>
                        <p class="text-muted mb-0">Corrija los filtros e intente de nuevo.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================================================= Paginación -->
        <?php if ($total > 0 && $totalPaginas > 1): ?>
            <div class="container mb-5 d-print-none">
                <nav aria-label="Paginación de activos">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <?php if ($pagina <= 1): ?>
                                <span class="page-link" aria-disabled="true">Anterior</span>
                            <?php else: ?>
                                <a class="page-link" href="<?= cag_h(cag_url(array_merge($paramsBase, ['pagina' => $pagina - 1]))) ?>" aria-label="Anterior">
                                    Anterior
                                </a>
                            <?php endif; ?>
                        </li>

                        <?php if ($rangoInicio > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= cag_h(cag_url(array_merge($paramsBase, ['pagina' => 1]))) ?>">1</a>
                            </li>
                            <?php if ($rangoInicio > 2): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($numeroPagina = $rangoInicio; $numeroPagina <= $rangoFin; $numeroPagina++): ?>
                            <li class="page-item <?= $numeroPagina === $pagina ? 'active' : '' ?>">
                                <?php if ($numeroPagina === $pagina): ?>
                                    <span class="page-link" aria-current="page"><?= $numeroPagina ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="<?= cag_h(cag_url(array_merge($paramsBase, ['pagina' => $numeroPagina]))) ?>">
                                        <?= $numeroPagina ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>

                        <?php if ($rangoFin < $totalPaginas): ?>
                            <?php if ($rangoFin < $totalPaginas - 1): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= cag_h(cag_url(array_merge($paramsBase, ['pagina' => $totalPaginas]))) ?>">
                                    <?= $totalPaginas ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                            <?php if ($pagina >= $totalPaginas): ?>
                                <span class="page-link" aria-disabled="true">Siguiente</span>
                            <?php else: ?>
                                <a class="page-link" href="<?= cag_h(cag_url(array_merge($paramsBase, ['pagina' => $pagina + 1]))) ?>" aria-label="Siguiente">
                                    Siguiente
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>

        <a href="<?= cag_h($ruta_regreso) ?>" class="btn-disponibilidad d-print-none" style="bottom: 100px;" data-tooltip="Regresar">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </main>
    <?php include 'partials/footer.php'; ?>

    <script>
        function cagCambioModo() {
            const select = document.getElementById('modo');
            const opcion = select.options[select.selectedIndex];

            document.getElementById('descripcion-modo').textContent = opcion.dataset.descripcion || '';

            const badge = document.getElementById('badge-modo');
            badge.className = 'input-group-text bg-' + (opcion.dataset.color || 'primary') + ' text-white';
            document.getElementById('icono-modo').className = 'bi ' + (opcion.dataset.icono || 'bi-box-seam');

            // Si ya hay un código consultando, se vuelve a consultar con el nuevo tipo.
            const codigo = document.getElementById('codigo').value.trim();
            if (codigo !== '') {
                document.getElementById('form-consulta').submit();
            }
        }
    </script>
</body>
</html>
