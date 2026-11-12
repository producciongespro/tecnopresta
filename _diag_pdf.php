<?php
// TEMPORAL - diagnostico TCPDF
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo 'PHP: ', PHP_VERSION, PHP_EOL;
echo 'cwd: ', getcwd(), PHP_EOL;
echo 'SCRIPT_FILENAME: ', $_SERVER['SCRIPT_FILENAME'] ?? 'n/a', PHP_EOL;
echo '__DIR__: ', __DIR__, PHP_EOL;

require __DIR__ . '/vendor/autoload.php';
echo 'TCPDF class: ', var_export(class_exists('TCPDF'), true), PHP_EOL;
echo 'K_PATH_FONTS: ', (defined('K_PATH_FONTS') ? K_PATH_FONTS : 'UNDEFINED'), PHP_EOL;
if (defined('K_PATH_FONTS')) {
    $f = rtrim(K_PATH_FONTS, '/\\') . '/core/helvetica.json';
    echo 'helvetica.json: ', $f, ' exists=', var_export(is_file($f), true), ' readable=', var_export(is_readable($f), true), PHP_EOL;
}
echo 'open_basedir: ', var_export(ini_get('open_basedir'), true), PHP_EOL;

try {
    $pdf = new TCPDF('P', 'mm', 'Letter', true, 'UTF-8', false);
    echo 'TCPDF constructed OK', PHP_EOL;
    $pdf->SetCreator('test');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 11);
    echo 'SetFont OK', PHP_EOL;
    $pdf->writeHTML('<h1>CERTIFICACION</h1><p>Prueba</p>', true, false, true, false, '');
    echo 'writeHTML OK', PHP_EOL;
    $pdf->Output('test.pdf', 'D');
    exit;
} catch (Throwable $e) {
    echo 'ERROR: ', get_class($e), ': ', $e->getMessage(), PHP_EOL;
    echo $e->getTraceAsString(), PHP_EOL;
}
