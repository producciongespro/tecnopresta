<?php
// En producción (PHP 8.3) se desactiva display_errors para no romper el JSON de salida
error_reporting(0);
ini_set("display_errors", "0");

header('Content-Type: application/json; charset=utf-8');

$endpoint = "https://apps.mep.go.cr/wstecnopresta/servicio.asmx";
$wsdlUrl  = $endpoint . "?WSDL";

//$correo = trim((string)($_POST['correo'] ?? ''));
$correo = 'erick.cerdas.gonzalez@mep.go.cr';

$pass   = (string)($_POST['pass'] ?? '');
$cedula = "";

/**
 * Ejecuta una petición SOAP 1.1 usando cURL nativo (No requiere php-soap ni allow_url_fopen)
 */
function soapCallViaCurl(string $endpoint, string $metodo, array $parametros): string {
    if (!function_exists('curl_init')) {
        throw new \RuntimeException("La extensión cURL no está habilitada en PHP.");
    }

    // Construir los nodos XML de los parámetros de forma segura
    $xmlParams = '';
    foreach ($parametros as $clave => $valor) {
        $valorEscapado = htmlspecialchars((string)$valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xmlParams .= "<{$clave}>{$valorEscapado}</{$clave}>";
    }

    // Sobre SOAP 1.1 exacto requerido por el WS .NET (ASMX) del MEP
    $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
             . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
             . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
             . 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soap:Body>'
             . "<{$metodo} xmlns=\"http://tempuri.org/\">{$xmlParams}</{$metodo}>"
             . '</soap:Body>'
             . '</soap:Envelope>';

    $headers = [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://tempuri.org/' . $metodo . '"',
        'Content-Length: ' . strlen($xmlBody), // Requerido por IIS del MEP (evita HTTP 411)
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xmlBody,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new \RuntimeException("Error cURL al conectar con WS MEP: " . $curlErr);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new \RuntimeException("El WS MEP respondió con código HTTP " . $httpCode);
    }

    // Extraer el contenido de <MetodoResult>...</MetodoResult>
    $tagResultado = $metodo . 'Result';
    $patron = '/<' . preg_quote($tagResultado, '/') . '[^>]*>(.*?)<\/' . preg_quote($tagResultado, '/') . '>/s';

    if (preg_match($patron, $response, $coincidencias)) {
        return html_entity_decode($coincidencias[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    return "";
}

try {
    $resultadoJson = "";
    $usoSoapNativo = false;

    // 1. Intenta usar SoapClient si está instalado y allow_url_fopen está activo
    if (class_exists('SoapClient') && filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        try {
            $client = new \SoapClient($wsdlUrl, [
                'exceptions'         => true,
                'connection_timeout' => 10,
                'cache_wsdl'         => WSDL_CACHE_BOTH,
            ]);

            $resValidaCredenciales = $client->ValidaCredenciales([
                'str_correo' => $correo,
                'str_pass'   => $pass
            ]);

            $cedula = (string)($resValidaCredenciales->ValidaCredencialesResult ?? '');
            $new_strcedula = str_replace('"', '', $cedula);

            $resConsultaFuncionario = $client->ConsultaFuncionario([
                'str_identificacion' => $new_strcedula
            ]);

            $resultadoJson = (string)($resConsultaFuncionario->ConsultaFuncionarioResult ?? '');
            $usoSoapNativo = true;
        } catch (\Throwable $soapError) {
            // Si SoapClient falla por WSDL/red/permisos, cae silenciosamente al respaldo cURL
            $usoSoapNativo = false;
        }
    }

    // 2. Respaldo automático con cURL (o motor principal si SoapClient no está activo)
    if (!$usoSoapNativo) {
        $cedula = soapCallViaCurl($endpoint, 'ValidaCredenciales', [
            'str_correo' => $correo,
            'str_pass'   => $pass
        ]);

        $new_strcedula = str_replace('"', '', $cedula);

        $resultadoJson = soapCallViaCurl($endpoint, 'ConsultaFuncionario', [
            'str_identificacion' => $new_strcedula
        ]);
    }

    // Si el WS devuelve vacío (ej. usuario no existe en Integra), devolver arreglo JSON vacío []
    // para que el frontend (Object.keys(data).length === 0) muestre la alerta institucional correcta
    echo ($resultadoJson !== '') ? $resultadoJson : '[]';

} catch (\Throwable $th) {
    http_response_code(500);
    echo json_encode([
        "error" => "Error al conectar con el servicio de validación: " . $th->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}