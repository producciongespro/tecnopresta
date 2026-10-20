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

require_once __DIR__ . '/sql/consulta_activos_donacion_helper.php';

$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';

if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
        . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
        . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}

function cad_cargar_autoload(): bool
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

function cad_url(array $parametros = []): string
{
    $base = ['ruta' => 'consulta_activos_donacion_n.php'];

    if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
        $base['subsistema_id'] = intval($_GET['subsistema_id']);
        $base['modulo_id'] = intval($_GET['modulo_id']);
    }

    return 'navegar.php?' . http_build_query(array_merge($base, $parametros));
}

function cad_html_detalle_sedes(array $instituciones, string $tamanoFuente = '7.5pt'): string
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
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cad_h($institucion['institucion'] ?? '') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cad_h($codigoSaber !== '' ? $codigoSaber : 'Sin dato') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cad_h($regional !== '' ? $regional : 'Sin regional') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . cad_h($circuito !== '' ? $circuito : 'Sin circuito') . '</td>'
            . '<td style="border:1px solid #cfd8e3;padding:2px;">' . ($activo === 1 ? 'Activo' : 'Inactivo') . '</td>'
            . '</tr>';
    }

    return $html . '</tbody></table>';
}

function cad_html_resumen(
    string $fuenteNombre,
    string $codigo,
    array $instituciones,
    int $total,
    string $fechaGeneracion,
    string $tamanoFuente = '8pt'
): string {
    $codigoTexto = $codigo !== '' ? $codigo : 'Todos';
    $centrosTexto = cad_resumen_centros($codigo, $instituciones);

    $html = '<table style="width:100%;border-collapse:collapse;font-size:' . $tamanoFuente . ';margin-bottom:3mm;">'
        . '<tr>'
        . '<td style="width:18%;background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Fuente presupuestaria</td>'
        . '<td style="width:32%;border:1px solid #cfd8e3;padding:2mm;">' . cad_h($fuenteNombre) . '</td>'
        . '<td style="width:18%;background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Código presupuestario</td>'
        . '<td style="width:32%;border:1px solid #cfd8e3;padding:2mm;">' . cad_h($codigoTexto) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Centro(s)</td>'
        . '<td colspan="3" style="border:1px solid #cfd8e3;padding:2mm;">' . cad_h($centrosTexto) . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Total de activos</td>'
        . '<td style="border:1px solid #cfd8e3;padding:2mm;">' . cad_num($total) . '</td>'
        . '<td style="background:#eef3f9;border:1px solid #cfd8e3;padding:2mm;font-weight:bold;">Fecha de generación</td>'
        . '<td style="border:1px solid #cfd8e3;padding:2mm;">' . cad_h($fechaGeneracion) . '</td>'
        . '</tr>'
        . '</table>';

    if (count($instituciones) === 1) {
        $institucion = $instituciones[0];
        $regional = trim((string)($institucion['regional'] ?? ''));
        $circuito = trim((string)($institucion['circuito'] ?? ''));
        $codigoSaber = trim((string)($institucion['cod_saber'] ?? ''));
        $activo = (int)($institucion['activo'] ?? 0);

        $html .= '<div style="font-size:' . $tamanoFuente . ';margin-bottom:2mm;">'
            . '<strong>Código SABER:</strong> ' . cad_h($codigoSaber !== '' ? $codigoSaber : 'Sin dato')
            . ' &nbsp;|&nbsp; <strong>Regional:</strong> ' . cad_h($regional !== '' ? $regional : 'Sin regional')
            . ' &nbsp;|&nbsp; <strong>Circuito:</strong> ' . cad_h($circuito !== '' ? $circuito : 'Sin circuito')
            . ' &nbsp;|&nbsp; <strong>Estado:</strong> ' . ($activo === 1 ? 'Activo' : 'Inactivo')
            . '</div>';
    }

    if ($codigo !== '' && empty($instituciones)) {
        $html .= '<div style="font-size:' . $tamanoFuente . ';color:#b02a37;font-weight:bold;margin-bottom:2mm;">'
            . 'No se encontró una institución registrada para el código ' . cad_h($codigo) . '.'
            . '</div>';
    }

    return $html . cad_html_detalle_sedes($instituciones, $tamanoFuente);
}

function cad_exportar_excel(
    mysqli $link,
    int $idFondos,
    string $codigo,
    array $instituciones,
    string $fuenteNombre,
    int $total,
    string $nombreArchivo
): void {
    if (!cad_cargar_autoload()) {
        http_response_code(500);
        exit('La librería PhpSpreadsheet no está disponible.');
    }

    @ini_set('display_errors', '0');

    $filas = cad_obtener_activos($link, $idFondos, $codigo, $total, 0);

    if (empty($filas)) {
        http_response_code(500);
        exit('No fue posible obtener los datos para exportar.');
    }

    $codigoTexto = $codigo !== '' ? $codigo : 'Todos';
    $centrosTexto = cad_resumen_centros($codigo, $instituciones);
    $fechaGeneracion = date('d/m/Y H:i');

    $documento = new Spreadsheet();
    $hoja = $documento->getActiveSheet();
    $hoja->setTitle('Activos Donación');

    $documento->getProperties()
        ->setCreator('Sistema TecnoPresta')
        ->setTitle('Activos marcados para donación')
        ->setSubject('Consulta de activos para donación')
        ->setDescription('Activos para donación por fuente presupuestaria y centro educativo');

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

    $hoja->mergeCells('A1:H1');
    $hoja->setCellValue('A1', 'MINISTERIO DE EDUCACIÓN PÚBLICA');
    $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Arial');
    $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->mergeCells('A2:H2');
    $hoja->setCellValue('A2', 'Dirección de Recursos Tecnológicos en Educación');
    $hoja->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->setName('Arial');
    $hoja->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->mergeCells('A3:H3');
    $hoja->setCellValue('A3', 'Sistema TecnoPresta');
    $hoja->getStyle('A3')->getFont()->setSize(9)->setName('Arial');
    $hoja->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hoja->mergeCells('A4:H4');
    $hoja->setCellValue('A4', 'ACTIVOS MARCADOS PARA DONACIÓN');
    $hoja->getStyle('A4')->getFont()->setBold(true)->setSize(12)->setName('Arial');
    $hoja->getStyle('A4')->getFont()->getColor()->setRGB('FFFFFF');
    $hoja->getStyle('A4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
    $hoja->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('A4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    $hoja->setCellValue('A5', 'Fuente presupuestaria:');
    $hoja->mergeCells('B5:H5');
    $hoja->setCellValue('B5', $fuenteNombre);

    $hoja->setCellValue('A6', 'Código presupuestario:');
    $hoja->mergeCells('B6:H6');
    $hoja->setCellValue('B6', $codigoTexto);

    $hoja->setCellValue('A7', 'Centro(s):');
    $hoja->mergeCells('B7:H7');
    $hoja->setCellValue('B7', $centrosTexto);

    $hoja->setCellValue('A8', 'Total de activos:');
    $hoja->setCellValue('B8', $total);
    $hoja->getStyle('B8')->getNumberFormat()->setFormatCode('#,##0');
    $hoja->setCellValue('D8', 'Fecha de generación:');
    $hoja->mergeCells('E8:H8');
    $hoja->setCellValue('E8', $fechaGeneracion);

    $hoja->getStyle('A5:A8')->getFont()->setBold(true)->setName('Arial')->setSize(9);
    $hoja->getStyle('B5:H7')->getAlignment()->setWrapText(true);
    $hoja->getStyle('B5:H7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $hoja->getStyle('E8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $hoja->getRowDimension(1)->setRowHeight(22);
    $hoja->getRowDimension(2)->setRowHeight(18);
    $hoja->getRowDimension(3)->setRowHeight(18);
    $hoja->getRowDimension(4)->setRowHeight(24);
    $hoja->getRowDimension(7)->setRowHeight(count($instituciones) > 1 ? 45 : 20);

    $encabezados = [
        'Tipo',
        'Marca',
        'Modelo',
        'Placa',
        'Serial',
        'Estado',
        'Ubicación',
        'Fuente Presupuestaria',
    ];

    $columna = 'A';

    foreach ($encabezados as $encabezado) {
        $hoja->setCellValue($columna . '10', $encabezado);
        $columna++;
    }

    $hoja->getStyle('A10:H10')->applyFromArray($estiloEncabezado);

    $filaExcel = 11;

    foreach ($filas as $fila) {
        $hoja->setCellValueExplicit('A' . $filaExcel, (string)$fila['tipo'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('B' . $filaExcel, (string)$fila['marca'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('C' . $filaExcel, (string)$fila['modelo'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('D' . $filaExcel, (string)$fila['placa'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('E' . $filaExcel, (string)$fila['serial'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('F' . $filaExcel, (string)$fila['estado'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('G' . $filaExcel, (string)$fila['ubicacion'], DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('H' . $filaExcel, (string)$fila['fuente'], DataType::TYPE_STRING);
        $filaExcel++;
    }

    $ultimaFila = $filaExcel - 1;

    $hoja->getStyle('A11:H' . $ultimaFila)->applyFromArray($estiloDatos);
    $hoja->getStyle('C11:C' . $ultimaFila)->getAlignment()->setWrapText(true);
    $hoja->getStyle('G11:H' . $ultimaFila)->getAlignment()->setWrapText(true);
    $hoja->getStyle('D11:F' . $ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('H11:H' . $ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $hoja->getColumnDimension('A')->setWidth(16);
    $hoja->getColumnDimension('B')->setWidth(16);
    $hoja->getColumnDimension('C')->setWidth(28);
    $hoja->getColumnDimension('D')->setWidth(14);
    $hoja->getColumnDimension('E')->setWidth(20);
    $hoja->getColumnDimension('F')->setWidth(14);
    $hoja->getColumnDimension('G')->setWidth(22);
    $hoja->getColumnDimension('H')->setWidth(24);

    $hoja->setAutoFilter('A10:H' . $ultimaFila);
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

function cad_exportar_pdf(
    mysqli $link,
    int $idFondos,
    string $codigo,
    array $instituciones,
    string $fuenteNombre,
    int $total,
    string $nombreArchivo
): void {
    if (!cad_cargar_autoload()) {
        http_response_code(500);
        exit('La librería mPDF no está disponible.');
    }

    @ini_set('display_errors', '0');
    @ini_set('memory_limit', '2048M');
    @set_time_limit(0);

    $fechaGeneracion = date('d/m/Y H:i');
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

    $mpdf->SetTitle('Activos marcados para donación');
    $mpdf->SetAuthor('Sistema TecnoPresta');
    $mpdf->SetCreator('Sistema TecnoPresta');
    $mpdf->SetSubject('Consulta de activos para donación');

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
        . '<strong style="color:#1F4E79;">ACTIVOS MARCADOS PARA DONACIÓN</strong><br>Sistema TecnoPresta'
        . '</td>'
        . '</tr>'
        . '</table>'
    );

    $mpdf->SetHTMLFooter(
        '<div style="border-top:1px solid #CCCCCC;padding-top:3px;font-family:sans-serif;font-size:7pt;color:#666;text-align:center;">'
        . 'Página {PAGENO} de {nbpg} | Generado: ' . cad_h($fechaGeneracion)
        . '</div>'
    );

    $resumen = cad_html_resumen($fuenteNombre, $codigo, $instituciones, $total, $fechaGeneracion, '7.5pt');

    $mpdf->WriteHTML($resumen);

    $mpdf->WriteHTML(
        '<table style="width:100%;border-collapse:collapse;font-size:7pt;">'
        . '<colgroup>'
        . '<col style="width:13%;"><col style="width:10%;"><col style="width:18%;"><col style="width:10%;">'
        . '<col style="width:13%;"><col style="width:10%;"><col style="width:15%;"><col style="width:11%;">'
        . '</colgroup>'
        . '<thead><tr>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Tipo</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Marca</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Modelo</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Placa</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Serial</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Estado</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Ubicación</th>'
        . '<th style="background:#1F4E79;color:#FFFFFF;border:1px solid #9FB3C8;padding:2px;text-align:center;">Fuente Presupuestaria</th>'
        . '</tr></thead><tbody>',
        \Mpdf\HTMLParserMode::HTML_BODY,
        false,
        false
    );

    $topeBloque = 300000;
    $bloqueFilas = '';

    for ($offset = 0; $offset < $total; $offset += 500) {
        $filas = cad_obtener_activos($link, $idFondos, $codigo, 500, $offset);

        if (empty($filas)) {
            break;
        }

        foreach ($filas as $fila) {
            $bloqueFilas .= '<tr>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;">' . cad_h($fila['tipo']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;">' . cad_h($fila['marca']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;word-wrap:break-word;">' . cad_h($fila['modelo']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;text-align:center;">' . cad_h($fila['placa']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;">' . cad_h($fila['serial']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;text-align:center;">' . cad_h($fila['estado']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;word-wrap:break-word;">' . cad_h($fila['ubicacion']) . '</td>'
                . '<td style="border:1px solid #CFD8E3;padding:2px;vertical-align:top;word-wrap:break-word;">' . cad_h($fila['fuente']) . '</td>'
                . '</tr>';

            if (strlen($bloqueFilas) >= $topeBloque) {
                $mpdf->WriteHTML($bloqueFilas, \Mpdf\HTMLParserMode::HTML_BODY, false, false);
                $bloqueFilas = '';
            }
        }
    }

    if ($bloqueFilas !== '') {
        $mpdf->WriteHTML($bloqueFilas, \Mpdf\HTMLParserMode::HTML_BODY, false, false);
    }

    $mpdf->WriteHTML('</tbody></table>', \Mpdf\HTMLParserMode::HTML_BODY, false, true);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $mpdf->Output($nombreArchivo, 'D');
    exit;
}

function cad_imprimir(
    mysqli $link,
    int $idFondos,
    string $codigo,
    array $instituciones,
    string $fuenteNombre,
    int $total
): void {
    @ini_set('display_errors', '0');

    $fechaGeneracion = date('d/m/Y H:i');
    $resumen = cad_html_resumen($fuenteNombre, $codigo, $instituciones, $total, $fechaGeneracion, '8pt');
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activos para donación</title>
    <style>
        @page { size: A4 landscape; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #222; font-family: Arial, Helvetica, sans-serif; font-size: 8.5pt; }
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
        table.datos th { background: #1F4E79; color: #fff; border: 1px solid #9FB3C8; padding: 3px; font-size: 7.5pt; text-align: center; }
        table.datos td { border: 1px solid #CFD8E3; padding: 3px; font-size: 7.5pt; vertical-align: top; }
        table.datos td.centro { text-align: center; }
        .no-print { }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 8pt; }
            .contenido { padding: 0; }
            table.datos th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
                    <strong>ACTIVOS MARCADOS PARA DONACIÓN</strong>
                    <span>Sistema TecnoPresta · Generado: <?= cad_h($fechaGeneracion) ?></span>
                </td>
            </tr>
        </table>

        <?= $resumen ?>

        <table class="datos">
            <colgroup>
                <col style="width:13%;"><col style="width:10%;"><col style="width:18%;"><col style="width:10%;">
                <col style="width:13%;"><col style="width:10%;"><col style="width:15%;"><col style="width:11%;">
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
                    <th>Fuente Presupuestaria</th>
                </tr>
            </thead>
            <tbody>
                <?php
                for ($offset = 0; $offset < $total; $offset += 1000) {
                    $filas = cad_obtener_activos($link, $idFondos, $codigo, 1000, $offset);

                    if (empty($filas)) {
                        break;
                    }

                    foreach ($filas as $fila) {
                        ?>
                        <tr>
                            <td><?= cad_h($fila['tipo']) ?></td>
                            <td><?= cad_h($fila['marca']) ?></td>
                            <td><?= cad_h($fila['modelo']) ?></td>
                            <td class="centro"><?= cad_h($fila['placa']) ?></td>
                            <td><?= cad_h($fila['serial']) ?></td>
                            <td class="centro"><?= cad_h($fila['estado']) ?></td>
                            <td><?= cad_h($fila['ubicacion']) ?></td>
                            <td><?= cad_h($fila['fuente']) ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
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

$fuentes = cad_obtener_fuentes($link);
$mapaFuentes = cad_mapa_fuentes($fuentes);

$fuenteRaw = isset($_GET['fuente']) && is_string($_GET['fuente']) ? $_GET['fuente'] : '0';
$idFondos = preg_match('/^[0-9]+$/', $fuenteRaw) ? (int)$fuenteRaw : 0;
$errorFuente = '';

if ($idFondos > 0 && !isset($mapaFuentes[$idFondos])) {
    $errorFuente = 'La fuente presupuestaria seleccionada no es válida.';
    $idFondos = 0;
}

$codigoRaw = isset($_GET['codigo']) && is_string($_GET['codigo']) ? $_GET['codigo'] : '';
$codigoInput = trim($codigoRaw);
$codigo = '';
$errorCodigo = '';

if ($codigoInput !== '') {
    [$codigo, $errorCodigo] = cad_normalizar_codigo($codigoInput);

    if ($errorCodigo === '') {
        $codigoInput = $codigo;
    }
}

$filtrosValidos = $errorFuente === '' && $errorCodigo === '';
$fuenteNombre = cad_nombre_fuente($mapaFuentes, $idFondos);

$total = $filtrosValidos ? cad_contar_activos($link, $idFondos, $codigo) : 0;
$porPagina = 100;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;
$instituciones = $filtrosValidos && $codigo !== '' ? cad_obtener_instituciones_por_codigo($link, $codigo) : [];
$activos = $filtrosValidos && $total > 0 ? cad_obtener_activos($link, $idFondos, $codigo, $porPagina, $offset) : [];
$desde = $total > 0 ? $offset + 1 : 0;
$hasta = $total > 0 ? min($offset + $porPagina, $total) : 0;

$mensaje = '';
$tipoMensaje = '';

if ($errorCodigo !== '') {
    $mensaje = $errorCodigo;
    $tipoMensaje = 'danger';
} elseif ($errorFuente !== '') {
    $mensaje = $errorFuente;
    $tipoMensaje = 'danger';
}

if (isset($_GET['msg']) && is_string($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'sin_datos':
            $mensaje = 'No hay activos para los filtros seleccionados.';
            $tipoMensaje = 'warning';
            break;
        case 'filtros_invalidos':
            $mensaje = 'Los filtros no son válidos. Revísalos e intenta de nuevo.';
            $tipoMensaje = 'warning';
            break;
        case 'accion_invalida':
            $mensaje = 'La acción solicitada no es válida.';
            $tipoMensaje = 'warning';
            break;
    }
}

$paramsBase = [];

if ($idFondos > 0) {
    $paramsBase['fuente'] = $idFondos;
}

if ($codigo !== '') {
    $paramsBase['codigo'] = $codigo;
}

$paramsEntrada = [];

if ($idFondos > 0) {
    $paramsEntrada['fuente'] = $idFondos;
}

if ($codigoInput !== '') {
    $paramsEntrada['codigo'] = $codigoInput;
}

$accion = isset($_GET['exportar']) && is_string($_GET['exportar'])
    ? strtolower(trim($_GET['exportar']))
    : '';
$imprimir = isset($_GET['imprimir']) && (string)$_GET['imprimir'] === '1';

if ($accion !== '' || $imprimir) {
    if (!$filtrosValidos) {
        header('Location: ' . cad_url(array_merge($paramsEntrada, ['msg' => 'filtros_invalidos'])));
        exit;
    }

    if ($total === 0) {
        header('Location: ' . cad_url(array_merge($paramsBase, ['msg' => 'sin_datos'])));
        exit;
    }

    $nombreArchivo = 'Activos_Donacion_'
        . ($codigo !== '' ? $codigo : 'Todos')
        . '_' . date('Ymd_His');

    if ($accion === 'excel') {
        cad_exportar_excel(
            $link,
            $idFondos,
            $codigo,
            $instituciones,
            $fuenteNombre,
            $total,
            $nombreArchivo . '.xlsx'
        );
    }

    if ($accion === 'pdf') {
        cad_exportar_pdf(
            $link,
            $idFondos,
            $codigo,
            $instituciones,
            $fuenteNombre,
            $total,
            $nombreArchivo . '.pdf'
        );
    }

    if ($imprimir) {
        cad_imprimir($link, $idFondos, $codigo, $instituciones, $fuenteNombre, $total);
    }

    header('Location: ' . cad_url(array_merge($paramsBase, ['msg' => 'accion_invalida'])));
    exit;
}

$urlExcel = cad_url(array_merge($paramsBase, ['exportar' => 'excel']));
$urlPdf = cad_url(array_merge($paramsBase, ['exportar' => 'pdf']));
$urlImprimir = cad_url(array_merge($paramsBase, ['imprimir' => '1']));
$urlLimpiar = cad_url([]);
$rangoInicio = max(1, $pagina - 2);
$rangoFin = min($totalPaginas, $pagina + 2);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="manifest" href="manifest.json">
    <title>Activos para Donación</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=9">
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <style>
        .filtro-card { border: 1px solid #dbe4ef; border-radius: 14px; }
        .resumen-card { border: 0; border-left: 5px solid var(--mep-primary); border-radius: 12px; }
        .detail-label { color: #6c757d; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
        .detail-value { color: #213547; font-weight: 600; }
        .tabla-activos { min-width: 980px; }
        .tabla-activos thead th { background: #1F4E79; color: #fff; border-color: #9FB3C8; font-size: .74rem; letter-spacing: .03em; text-transform: uppercase; vertical-align: middle; white-space: nowrap; }
        .tabla-activos tbody td { font-size: .86rem; vertical-align: middle; }
        .tabla-activos .badge { font-size: .72rem; font-weight: 600; padding: .38em .55em; }
        .tabla-activos .badge.bg-warning { color: #212529; }
        .empty-state { border-radius: 14px; }
        .sedes-tabla th { background: #eef3f9; color: #1F4E79; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
        .sedes-tabla td { font-size: .84rem; }
        .pagination .page-link { color: #1F4E79; }
        .pagination .page-item.active .page-link { background-color: #1F4E79; border-color: #1F4E79; }
        @media (max-width: 767.98px) {
            .tabla-activos { min-width: 860px; }
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
                                <i class="bi bi-gift" style="font-size: 2rem;"></i>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-1">Activos para Donación</h2>
                                <p class="mb-0 opacity-75">Consulta por fuente presupuestaria y centro educativo</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mb-4">
            <div class="card filtro-card shadow-sm d-print-none">
                <div class="card-body p-4">
                    <form method="get" action="navegar.php" class="row g-3 align-items-end">
                        <input type="hidden" name="ruta" value="consulta_activos_donacion_n.php">
                        <?php if (isset($_GET['subsistema_id'], $_GET['modulo_id'])): ?>
                            <input type="hidden" name="subsistema_id" value="<?= (int)$_GET['subsistema_id'] ?>">
                            <input type="hidden" name="modulo_id" value="<?= (int)$_GET['modulo_id'] ?>">
                        <?php endif; ?>

                        <div class="col-lg-4">
                            <label for="fuente" class="form-label fw-semibold">Fuente presupuestaria</label>
                            <select name="fuente" id="fuente" class="form-select">
                                <option value="0">Todas las fuentes</option>
                                <?php foreach ($fuentes as $fuente): ?>
                                    <option value="<?= (int)$fuente['id_fondos'] ?>" <?= $idFondos === (int)$fuente['id_fondos'] ? 'selected' : '' ?>>
                                        <?= cad_h($fuente['fondos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label for="codigo" class="form-label fw-semibold">Código presupuestario</label>
                            <input type="text" name="codigo" id="codigo" class="form-control"
                                   inputmode="numeric" pattern="[0-9]{1,4}" maxlength="4"
                                   autocomplete="off" value="<?= cad_h($codigoInput) ?>" placeholder="Ej. 0326">
                            <div class="form-text">Se completa a 4 dígitos. Ejemplo: 326 se busca como 0326.</div>
                        </div>

                        <div class="col-lg-4 d-flex gap-2">
                            <button type="submit" class="btn btn-mep-primary flex-grow-1">
                                <i class="bi bi-search me-2"></i>Buscar
                            </button>
                            <a href="<?= cad_h($urlLimpiar) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="container mb-4">
                <div class="alert alert-<?= cad_h($tipoMensaje) ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= cad_h($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($filtrosValidos): ?>
            <div class="container mb-4">
                <div class="card resumen-card shadow-sm">
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <?php if ($codigo === ''): ?>
                                    <h5 class="fw-bold mb-2" style="color: var(--mep-primary);">
                                        <i class="bi bi-buildings me-2"></i>Todos los centros educativos
                                    </h5>
                                    <p class="text-muted mb-0">Activos marcados para donación en todos los centros registrados.</p>
                                <?php elseif (empty($instituciones)): ?>
                                    <h5 class="fw-bold mb-2" style="color: var(--mep-primary);">
                                        <i class="bi bi-exclamation-circle me-2"></i>Sin institución registrada
                                    </h5>
                                    <div class="alert alert-warning mb-0">
                                        No se encontró una institución para el código <?= cad_h($codigo) ?>. Los activos se muestran igualmente.
                                    </div>
                                <?php elseif (count($instituciones) === 1): ?>
                                    <?php $institucion = $instituciones[0]; ?>
                                    <h5 class="fw-bold mb-3" style="color: var(--mep-primary);">
                                        <i class="bi bi-building me-2"></i><?= cad_h($institucion['institucion']) ?>
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Código presupuestario</div>
                                            <div class="detail-value"><?= cad_h($institucion['codigo']) ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Código SABER</div>
                                            <div class="detail-value"><?= cad_h($institucion['cod_saber'] ?: 'Sin dato') ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Regional</div>
                                            <div class="detail-value"><?= cad_h($institucion['regional'] ?: 'Sin regional') ?></div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="detail-label">Circuito</div>
                                            <div class="detail-value"><?= cad_h($institucion['circuito'] ?: 'Sin circuito') ?></div>
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
                                        <i class="bi bi-diagram-3 me-2"></i>Código presupuestario <?= cad_h($codigo) ?> · <?= count($instituciones) ?> sedes
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
                                                        <td><?= cad_h($institucion['institucion']) ?></td>
                                                        <td><?= cad_h($institucion['cod_saber'] ?: 'Sin dato') ?></td>
                                                        <td><?= cad_h($institucion['regional'] ?: 'Sin regional') ?></td>
                                                        <td><?= cad_h($institucion['circuito'] ?: 'Sin circuito') ?></td>
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
                                        <div class="detail-label">Fuente presupuestaria</div>
                                        <div class="detail-value"><?= cad_h($fuenteNombre) ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Código consultado</div>
                                        <div class="detail-value"><?= cad_h($codigo !== '' ? $codigo : 'Todos') ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Total de activos</div>
                                        <div class="fw-bold" style="color: var(--mep-primary); font-size: 2rem; line-height: 1;">
                                            <?= cad_num($total) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="container mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h4 class="fw-bold mb-0" style="color: var(--mep-primary);">
                        <i class="bi bi-list-check me-2"></i>Activos encontrados
                    </h4>
                    <small class="text-muted">
                        <?php if ($total > 0): ?>
                            Mostrando <?= cad_num($desde) ?>&ndash;<?= cad_num($hasta) ?> de <?= cad_num($total) ?> activos
                        <?php else: ?>
                            0 activos encontrados
                        <?php endif; ?>
                    </small>
                </div>

                <?php if ($total > 0): ?>
                    <div class="d-flex flex-wrap gap-2 d-print-none">
                        <a class="btn btn-outline-success" href="<?= cad_h($urlExcel) ?>">
                            <i class="bi bi-file-earmark-excel me-1"></i>Excel
                        </a>
                        <a class="btn btn-outline-danger" href="<?= cad_h($urlPdf) ?>">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= cad_h($urlImprimir) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i>Imprimir
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
                                    <th>Fuente Presupuestaria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activos as $activo): ?>
                                    <tr>
                                        <td><?= cad_h($activo['tipo']) ?></td>
                                        <td><?= cad_h($activo['marca']) ?></td>
                                        <td><?= cad_h($activo['modelo']) ?></td>
                                        <td class="text-nowrap"><?= cad_h($activo['placa']) ?></td>
                                        <td class="text-nowrap"><?= cad_h($activo['serial']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= cad_estado_clase($activo['estado']) ?>">
                                                <?= cad_h($activo['estado']) ?>
                                            </span>
                                        </td>
                                        <td><?= cad_h($activo['ubicacion']) ?></td>
                                        <td><?= cad_h($activo['fuente']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 empty-state">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #9fb0c6;"></i>
                        <h5 class="fw-bold mt-3">No hay activos para mostrar</h5>
                        <p class="text-muted mb-0">
                            <?php if (!$filtrosValidos): ?>
                                Corrige los filtros para realizar la consulta.
                            <?php elseif ($codigo !== '' && empty($instituciones)): ?>
                                No se encontró el código presupuestario <?= cad_h($codigo) ?>.
                            <?php else: ?>
                                No hay activos marcados para donación con los filtros seleccionados.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total > 0 && $totalPaginas > 1): ?>
            <div class="container mb-5 d-print-none">
                <nav aria-label="Paginación de activos">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <?php if ($pagina <= 1): ?>
                                <span class="page-link" aria-disabled="true">Anterior</span>
                            <?php else: ?>
                                <a class="page-link" href="<?= cad_h(cad_url(array_merge($paramsBase, ['pagina' => $pagina - 1]))) ?>" aria-label="Anterior">
                                    Anterior
                                </a>
                            <?php endif; ?>
                        </li>

                        <?php if ($rangoInicio > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= cad_h(cad_url(array_merge($paramsBase, ['pagina' => 1]))) ?>">1</a>
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
                                    <a class="page-link" href="<?= cad_h(cad_url(array_merge($paramsBase, ['pagina' => $numeroPagina]))) ?>">
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
                                <a class="page-link" href="<?= cad_h(cad_url(array_merge($paramsBase, ['pagina' => $totalPaginas]))) ?>">
                                    <?= $totalPaginas ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                            <?php if ($pagina >= $totalPaginas): ?>
                                <span class="page-link" aria-disabled="true">Siguiente</span>
                            <?php else: ?>
                                <a class="page-link" href="<?= cad_h(cad_url(array_merge($paramsBase, ['pagina' => $pagina + 1]))) ?>" aria-label="Siguiente">
                                    Siguiente
                                </a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>

        <a href="<?= cad_h($ruta_regreso) ?>" class="btn-disponibilidad d-print-none" style="bottom: 100px;" data-tooltip="Regresar">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </main>
    <?php include 'partials/footer.php'; ?>
</body>
</html>
