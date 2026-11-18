<?php
/**
 * Helper compartido para generación y extracción robusta del bloque JSON canónico
 * y metadatos de firma digital en PDFs de certificación de inventario.
 */

if (!function_exists('normalizarCadenaUtf8')) {
    /**
     * Garantiza que una cadena esté codificada en UTF-8 válido.
     */
    function normalizarCadenaUtf8(string $texto): string
    {
        if ($texto === '') {
            return '';
        }
        if (mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }
        // Reparar byte a byte preservando secuencias UTF-8 válidas (evita doble codificación si hay mezcla UTF-8 + Win-1252)
        $reparado = preg_replace_callback(
            '/(?:[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|([\x80-\xFF])/S',
            static function (array $m): string {
                return isset($m[1]) ? (@mb_convert_encoding($m[1], 'UTF-8', 'Windows-1252') ?: '') : $m[0];
            },
            $texto
        );
        if (is_string($reparado) && mb_check_encoding($reparado, 'UTF-8')) {
            return $reparado;
        }
        $conv = @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        if ($conv !== false && mb_check_encoding($conv, 'UTF-8')) {
            return $conv;
        }
        if (function_exists('iconv')) {
            $ic = @iconv('Windows-1252', 'UTF-8//IGNORE', $texto);
            if ($ic !== false) {
                return $ic;
            }
        }
        return @mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1') ?: $texto;
    }
}

if (!function_exists('normalizarDatosUtf8Recursivo')) {
    /**
     * Normaliza recursivamente cadenas y llaves de un arreglo a UTF-8 válido.
     *
     * @param mixed $valor
     * @return mixed
     */
    function normalizarDatosUtf8Recursivo($valor)
    {
        if (is_string($valor)) {
            return normalizarCadenaUtf8($valor);
        }
        if (is_array($valor)) {
            $out = [];
            foreach ($valor as $k => $v) {
                $nk = is_string($k) ? normalizarCadenaUtf8($k) : $k;
                $out[$nk] = normalizarDatosUtf8Recursivo($v);
            }
            return $out;
        }
        return $valor;
    }
}

if (!function_exists('prepararPayloadsJsonCertificacion')) {
    /**
     * Genera tanto la versión UTF-8 legible (para BD y FileAttachment) como la
     * versión ASCII pura (\uXXXX, inmune a fuentes Win-1252 de TCPDF en MultiCell/Keywords).
     *
     * @return array{compacto: string, ascii: string}|null
     */
    function prepararPayloadsJsonCertificacion(array $json_data): ?array
    {
        $normalizado = normalizarDatosUtf8Recursivo($json_data);

        $flags_utf8  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $flags_ascii = JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags_utf8  |= JSON_INVALID_UTF8_SUBSTITUTE;
            $flags_ascii |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $compacto = json_encode($normalizado, $flags_utf8);
        if ($compacto === false || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $ascii = json_encode($normalizado, $flags_ascii);
        if ($ascii === false || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return [
            'compacto' => $compacto,
            'ascii'    => $ascii,
        ];
    }
}

if (!function_exists('decodificarLiteralPdf')) {
    /**
     * Decodifica secuencias de escape de cadenas literales PDF (ISO 32000-1 §7.3.4.2).
     */
    function decodificarLiteralPdf(string $texto): string
    {
        $len = strlen($texto);
        $out = '';

        for ($i = 0; $i < $len; $i++) {
            $c = $texto[$i];
            if ($c !== '\\' || $i + 1 >= $len) {
                $out .= $c;
                continue;
            }

            $sig = $texto[++$i];
            switch ($sig) {
                case 'n':
                    $out .= "\n";
                    break;
                case 'r':
                    $out .= "\r";
                    break;
                case 't':
                    $out .= "\t";
                    break;
                case 'b':
                    $out .= "\x08";
                    break;
                case 'f':
                    $out .= "\x0c";
                    break;
                case '(':
                case ')':
                case '\\':
                    $out .= $sig;
                    break;
                case "\r":
                    if ($i + 1 < $len && $texto[$i + 1] === "\n") {
                        $i++;
                    }
                    break;
                case "\n":
                    break;
                default:
                    if ($sig >= '0' && $sig <= '7') {
                        $oct = $sig;
                        for ($j = 0; $j < 2 && $i + 1 < $len; $j++) {
                            $d = $texto[$i + 1];
                            if ($d >= '0' && $d <= '7') {
                                $oct .= $d;
                                $i++;
                            } else {
                                break;
                            }
                        }
                        $out .= chr(octdec($oct) & 0xFF);
                    } else {
                        $out .= $sig;
                    }
                    break;
            }
        }

        return $out;
    }
}

if (!function_exists('leerLiteralPdfEnOffset')) {
    /**
     * Lee una cadena literal PDF balanceada (...) a partir del índice de '('.
     */
    function leerLiteralPdfEnOffset(string $binario, int $inicio_paren, ?int &$fin_pos = null): ?string
    {
        $len = strlen($binario);
        if ($inicio_paren < 0 || $inicio_paren >= $len || $binario[$inicio_paren] !== '(') {
            return null;
        }

        $profundidad = 1;
        $i = $inicio_paren + 1;
        $contenido = '';

        while ($i < $len && $profundidad > 0) {
            $c = $binario[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $contenido .= $c . $binario[$i + 1];
                $i += 2;
                continue;
            }
            if ($c === '(') {
                $profundidad++;
                $contenido .= $c;
            } elseif ($c === ')') {
                $profundidad--;
                if ($profundidad > 0) {
                    $contenido .= $c;
                }
            } else {
                $contenido .= $c;
            }
            $i++;
        }

        if ($profundidad !== 0) {
            return null;
        }

        $fin_pos = $i;
        return decodificarLiteralPdf($contenido);
    }
}

if (!function_exists('extraerObjetoJsonBalanceado')) {
    /**
     * Extrae el primer objeto JSON {...} balanceado respetando cadenas y escapes.
     */
    function extraerObjetoJsonBalanceado(string $texto, int $inicio = 0): ?string
    {
        $pos = strpos($texto, '{', $inicio);
        if ($pos === false) {
            return null;
        }

        $len = strlen($texto);
        $prof = 0;
        $en_str = false;

        for ($i = $pos; $i < $len; $i++) {
            $c = $texto[$i];
            if ($en_str) {
                if ($c === '\\') {
                    $i++;
                    continue;
                }
                if ($c === '"') {
                    $en_str = false;
                }
                continue;
            }

            if ($c === '"') {
                $en_str = true;
            } elseif ($c === '{') {
                $prof++;
            } elseif ($c === '}') {
                $prof--;
                if ($prof === 0) {
                    return substr($texto, $pos, $i - $pos + 1);
                }
            }
        }

        return null;
    }
}

if (!function_exists('convertirWin1252AUtf8')) {
    /**
     * Convierte BOM UTF-16 o secuencias Windows-1252/Latin1 a UTF-8 válido.
     */
    function convertirWin1252AUtf8(string $texto): string
    {
        if (strncmp($texto, "\xFE\xFF", 2) === 0) {
            $utf16 = @mb_convert_encoding(substr($texto, 2), 'UTF-8', 'UTF-16BE');
            if ($utf16 !== false) {
                return $utf16;
            }
        }
        if (strncmp($texto, "\xFF\xFE", 2) === 0) {
            $utf16 = @mb_convert_encoding(substr($texto, 2), 'UTF-8', 'UTF-16LE');
            if ($utf16 !== false) {
                return $utf16;
            }
        }
        return normalizarCadenaUtf8($texto);
    }
}

if (!function_exists('intentarDecodificarJsonCert')) {
    /**
     * Valida que el candidato decodifique a JSON válido de certificación de inventario.
     */
    function intentarDecodificarJsonCert(string $candidato): ?string
    {
        $limpio = preg_replace('/^\xEF\xBB\xBF/', '', trim($candidato));
        if ($limpio === '' || $limpio[0] !== '{') {
            return null;
        }

        $datos = json_decode($limpio, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
            return null;
        }

        $es_cert = (isset($datos['uuid'], $datos['version']) && (!isset($datos['tipo']) || $datos['tipo'] === 'certificacion_inventario'))
            || (($datos['tipo'] ?? '') === 'certificacion_inventario');
        if (!$es_cert) {
            return null;
        }

        return mb_check_encoding($limpio, 'UTF-8')
            ? $limpio
            : json_encode(normalizarDatosUtf8Recursivo($datos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('limpiarSaltosEnStrings')) {
    /**
     * Sustituye saltos de línea dentro de strings JSON por un espacio (solo fallback final).
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
}

if (!function_exists('decodificarJsonPdfSucio')) {
    /**
     * Intenta decodificar JSON de certificación probando primero variantes exactas
     * sin inyectar espacios (preservando seriales y \uXXXX partidos por MultiCell),
     * luego conversión Windows-1252 -> UTF-8 y escapes PDF, y finalmente espacios.
     */
    function decodificarJsonPdfSucio(string $crudo): ?string
    {
        $bases = [$crudo];

        // Prioridad: eliminar \r/\n sin insertar espacios para no dañar seriales ni \uXXXX partidos
        $sin_saltos = str_replace(["\r\n", "\r", "\n"], '', $crudo);
        if ($sin_saltos !== $crudo) {
            $bases[] = $sin_saltos;
        }

        // Desescapar escapes PDF solo si aparecen indicadores (evita alterar '\' legítimos antes de tiempo)
        if (strpos($crudo, '\\(') !== false || strpos($crudo, '\\)') !== false || strpos($crudo, '\\\\') !== false) {
            $pdf_dec = decodificarLiteralPdf($crudo);
            $bases[] = $pdf_dec;
            $bases[] = str_replace(["\r\n", "\r", "\n"], '', $pdf_dec);

            // Fallback cuando un extractor previo mezcló escapes PDF parciales con JSON escapes
            $bases[] = strtr($sin_saltos, ['\\(' => '(', '\\)' => ')', '\\\\' => '\\']);
        }

        $candidatos = [];
        foreach (array_unique($bases) as $b) {
            $candidatos[] = $b;
            $bal = extraerObjetoJsonBalanceado($b);
            if ($bal !== null && $bal !== $b) {
                $candidatos[] = $bal;
            }
        }

        // Pasada 1: intentar candidatos tal cual (sin conversión ni espacios)
        foreach ($candidatos as $c) {
            $ok = intentarDecodificarJsonCert($c);
            if ($ok !== null) {
                return $ok;
            }
        }

        // Pasada 2: convertir Windows-1252 / UTF-16 -> UTF-8 (tildes/ñ emitidas en Helvetica TCPDF)
        foreach ($candidatos as $c) {
            $utf8 = convertirWin1252AUtf8($c);
            if ($utf8 !== $c) {
                $ok = intentarDecodificarJsonCert($utf8);
                if ($ok !== null) {
                    return $ok;
                }
                $bal = extraerObjetoJsonBalanceado($utf8);
                if ($bal !== null) {
                    $ok = intentarDecodificarJsonCert($bal);
                    if ($ok !== null) {
                        return $ok;
                    }
                }
            }
        }

        // Pasada 3 (último recurso): limpieza de caracteres de control o saltos con espacio
        foreach ($bases as $b) {
            $sin_ctrl = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $b);
            $con_esp  = limpiarSaltosEnStrings($sin_ctrl);
            $variantes = [
                str_replace(["\r\n", "\r", "\n"], '', $sin_ctrl),
                convertirWin1252AUtf8(str_replace(["\r\n", "\r", "\n"], '', $sin_ctrl)),
                $con_esp,
                convertirWin1252AUtf8($con_esp),
            ];
            foreach (array_unique($variantes) as $variante) {
                $ok = intentarDecodificarJsonCert($variante);
                if ($ok !== null) {
                    return $ok;
                }
                $bal = extraerObjetoJsonBalanceado($variante);
                if ($bal !== null) {
                    $ok = intentarDecodificarJsonCert($bal);
                    if ($ok !== null) {
                        return $ok;
                    }
                }
            }
        }

        return null;
    }
}

if (!function_exists('descomprimirDatosStreamPdf')) {
    /**
     * Genera variantes (cruda y FlateDecode) de un stream PDF.
     *
     * @return array<int, string>
     */
    function descomprimirDatosStreamPdf(string $stream_raw): array
    {
        $salidas = [];
        if ($stream_raw === '') {
            return $salidas;
        }

        $salidas[] = $stream_raw;

        $dec = @gzuncompress($stream_raw);
        if ($dec !== false && $dec !== '') {
            $salidas[] = $dec;
            return $salidas;
        }

        $dec = @gzinflate($stream_raw);
        if ($dec !== false && $dec !== '') {
            $salidas[] = $dec;
            return $salidas;
        }

        if (strlen($stream_raw) > 2) {
            $dec = @gzinflate(substr($stream_raw, 2));
            if ($dec !== false && $dec !== '') {
                $salidas[] = $dec;
            }
        }

        return $salidas;
    }
}

if (!function_exists('iterarStreamsPdf')) {
    /**
     * Recorre los bloques stream...endstream del binario PDF invocando un callback por cada variante.
     * Si el callback retorna un valor distinto de null, detiene el recorrido y lo devuelve.
     *
     * @return mixed
     */
    function iterarStreamsPdf(string $binario, callable $callback)
    {
        $cursor = 0;
        while (($pos_stream = strpos($binario, 'stream', $cursor)) !== false) {
            $anterior = $pos_stream > 0 ? $binario[$pos_stream - 1] : "\n";
            $inicio_datos = $pos_stream + 6;
            $salto_valido = false;

            if (substr($binario, $inicio_datos, 2) === "\r\n") {
                $inicio_datos += 2;
                $salto_valido = true;
            } elseif (isset($binario[$inicio_datos]) && $binario[$inicio_datos] === "\n") {
                $inicio_datos += 1;
                $salto_valido = true;
            }

            if (!$salto_valido || (ctype_alnum($anterior) && $anterior !== '>')) {
                $cursor = $pos_stream + 6;
                continue;
            }

            $pos_end = strpos($binario, 'endstream', $inicio_datos);
            if ($pos_end === false) {
                break;
            }
            $cursor = $pos_end + 9;

            $header_ctx = substr($binario, max(0, $pos_stream - 260), min(260, $pos_stream));
            if (stripos($header_ctx, '/Image') !== false) {
                continue;
            }

            $stream_raw = preg_replace('/(?:\r\n|\r|\n)\z/', '', substr($binario, $inicio_datos, $pos_end - $inicio_datos));
            foreach (descomprimirDatosStreamPdf($stream_raw) as $variante) {
                $res = $callback($variante, $header_ctx);
                if ($res !== null) {
                    return $res;
                }
            }
        }

        return null;
    }
}

if (!function_exists('extraerTextoDeStreamPdf')) {
    /**
     * Extrae texto de operadores Tj / TJ / ' / " en un content stream PDF en O(n).
     */
    function extraerTextoDeStreamPdf(string $stream): string
    {
        $len = strlen($stream);
        $out = '';
        $en_array_tj = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $stream[$i];

            if ($c === '%') {
                $eol = strcspn($stream, "\r\n", $i);
                $i += $eol;
                continue;
            }

            if ($c === '(') {
                $fin = null;
                $lit = leerLiteralPdfEnOffset($stream, $i, $fin);
                if ($lit !== null && $fin !== null) {
                    $out .= $lit;
                    $i = $fin - 1;
                    $pos_ws = $fin + strspn($stream, " \t\r\n", $fin);
                    $op = substr($stream, $pos_ws, 2);
                    if (!$en_array_tj && ($op === 'Tj' || substr($stream, $pos_ws, 1) === "'")) {
                        $out .= "\n";
                    }
                    continue;
                }
            }

            if ($c === '[') {
                $en_array_tj = true;
                continue;
            }

            if ($c === ']' && $en_array_tj) {
                $en_array_tj = false;
                $pos_ws = $i + 1 + strspn($stream, " \t\r\n", $i + 1);
                if (substr($stream, $pos_ws, 2) === 'TJ') {
                    $out .= "\n";
                }
                continue;
            }

            // Cadenas hexadecimales solo dentro de arrays TJ o justo antes de Tj/'
            if ($c === '<' && ($i + 1 < $len) && $stream[$i + 1] !== '<') {
                $cierre = strpos($stream, '>', $i + 1);
                if ($cierre !== false && ($cierre - $i) < 65536) {
                    $pos_ws = $cierre + 1 + strspn($stream, " \t\r\n", $cierre + 1);
                    $op = substr($stream, $pos_ws, 2);
                    if ($en_array_tj || $op === 'Tj' || substr($stream, $pos_ws, 1) === "'") {
                        $hex = preg_replace('/\s+/', '', substr($stream, $i + 1, $cierre - $i - 1));
                        if ($hex !== '' && preg_match('/^[0-9A-Fa-f]+$/', $hex)) {
                            if (strlen($hex) % 2 === 1) {
                                $hex .= '0';
                            }
                            $bin = @hex2bin($hex);
                            if ($bin !== false) {
                                $out .= convertirWin1252AUtf8($bin);
                            }
                        }
                        if ($op === 'Tj') {
                            $out .= "\n";
                        }
                    }
                    $i = $cierre;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('cargarAutoloadPdfParser')) {
    /**
     * Carga vendor/autoload.php solo si existe en el servidor y aún no se cargó PdfParser.
     */
    function cargarAutoloadPdfParser(): void
    {
        if (!class_exists('\Smalot\PdfParser\Parser') && file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
    }
}

if (!function_exists('extraerJsonDesdeAttachment')) {
    /**
     * Método principal (Prioridad 1): Extrae JSON de /EmbeddedFile adjunto en el PDF.
     */
    function extraerJsonDesdeAttachment(string $ruta): ?string
    {
        cargarAutoloadPdfParser();

        // Capa A: Smalot PdfParser sobre objetos decodificados (sin depender de texto del diccionario)
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($ruta);
                foreach ($pdf->getObjects() as $obj) {
                    $raw_content = $obj->getContent();
                    if (!is_string($raw_content) || trim($raw_content) === '') {
                        continue;
                    }

                    $trim_raw = ltrim($raw_content, "\xEF\xBB\xBF \t\r\n");
                    $es_candidato = strpos($raw_content, 'certificacion_inventario') !== false
                        || substr($trim_raw, 0, 1) === '{';

                    if (!$es_candidato && method_exists($obj, 'getHeader')) {
                        $header = $obj->getHeader();
                        if ($header && $header->has('Type') && stripos((string) $header->get('Type'), 'EmbeddedFile') !== false) {
                            $es_candidato = true;
                        }
                    }

                    if ($es_candidato) {
                        $decodificado = decodificarJsonPdfSucio($raw_content);
                        if ($decodificado !== null) {
                            return $decodificado;
                        }
                    }
                }
            } catch (Exception $e) {
                // Continuar con capas binarias
            }
        }

        $binario = @file_get_contents($ruta);
        if ($binario === false) {
            return null;
        }

        // Capa B: Buscar /Type /EmbeddedFile ignorando diccionarios anidados como /Params << /Size ... >>
        if (preg_match_all('/\/Type\s*\/EmbeddedFile\b/i', $binario, $ef_matches, PREG_OFFSET_CAPTURE)) {
            foreach ($ef_matches[0] as $m) {
                $offset = $m[1];
                $pos_stream = strpos($binario, 'stream', $offset);
                if ($pos_stream === false || ($pos_stream - $offset) > 2048) {
                    continue;
                }

                $inicio_datos = $pos_stream + 6;
                if (substr($binario, $inicio_datos, 2) === "\r\n") {
                    $inicio_datos += 2;
                } elseif (isset($binario[$inicio_datos]) && ($binario[$inicio_datos] === "\n" || $binario[$inicio_datos] === "\r")) {
                    $inicio_datos += 1;
                } else {
                    continue;
                }

                $pos_end = strpos($binario, 'endstream', $inicio_datos);
                if ($pos_end === false) {
                    continue;
                }

                $stream_raw = preg_replace('/(?:\r\n|\r|\n)\z/', '', substr($binario, $inicio_datos, $pos_end - $inicio_datos));
                foreach (descomprimirDatosStreamPdf($stream_raw) as $variante) {
                    $trim_var = ltrim($variante, "\xEF\xBB\xBF \t\r\n");
                    if (strpos($variante, 'certificacion_inventario') !== false || substr($trim_var, 0, 1) === '{') {
                        $ok = decodificarJsonPdfSucio($variante);
                        if ($ok !== null) {
                            return $ok;
                        }
                    }
                }
            }
        }

        // Capa C: Escaneo secuencial O(n) de todos los streams del PDF
        return iterarStreamsPdf($binario, function (string $variante) {
            $trim = ltrim($variante, "\xEF\xBB\xBF \t\r\n");
            if (substr($trim, 0, 1) === '{'
                && (strpos($variante, 'certificacion_inventario') !== false || strpos($variante, '"uuid"') !== false)
            ) {
                return decodificarJsonPdfSucio($variante);
            }
            return null;
        });
    }
}

if (!function_exists('extraerJsonDesdeKeywords')) {
    /**
     * Extrae JSON canónico desde el metadato /Keywords (literal PDF o hex UTF-16BE).
     */
    function extraerJsonDesdeKeywords(string $ruta): ?string
    {
        $binario = @file_get_contents($ruta);
        if ($binario === false) {
            return null;
        }

        if (!preg_match_all('/\/Keywords\s*/', $binario, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Probar del último al primero (las actualizaciones incrementales añaden nuevos /Info al final)
        foreach (array_reverse($matches[0]) as $m) {
            $pos = $m[1] + strlen($m[0]);
            if (!isset($binario[$pos])) {
                continue;
            }

            $candidato = null;
            if ($binario[$pos] === '(') {
                $candidato = leerLiteralPdfEnOffset($binario, $pos);
            } elseif ($binario[$pos] === '<' && (!isset($binario[$pos + 1]) || $binario[$pos + 1] !== '<')) {
                $cierre = strpos($binario, '>', $pos + 1);
                if ($cierre !== false) {
                    $hex = preg_replace('/\s+/', '', substr($binario, $pos + 1, $cierre - $pos - 1));
                    if ($hex !== '' && ctype_xdigit($hex)) {
                        if (strlen($hex) % 2 === 1) {
                            $hex .= '0';
                        }
                        $bin = @hex2bin($hex);
                        if ($bin !== false) {
                            $candidato = convertirWin1252AUtf8($bin);
                        }
                    }
                }
            }

            if ($candidato !== null
                && (strpos($candidato, 'certificacion_inventario') !== false || strpos($candidato, '"uuid"') !== false)
            ) {
                $ok = decodificarJsonPdfSucio($candidato);
                if ($ok !== null) {
                    return $ok;
                }
            }
        }

        return null;
    }
}

if (!function_exists('reconstruirJsonDesdeTJ')) {
    /**
     * Reconstruye texto de operadores TJ/Tj sobre streams descomprimidos y binario PDF
     * para recuperar bloques BEGIN_JSON...END_JSON fragmentados.
     */
    function reconstruirJsonDesdeTJ(string $binario): ?string
    {
        $fragmentos = [];

        iterarStreamsPdf($binario, function (string $variante) use (&$fragmentos) {
            if (strpos($variante, 'BEGIN_JSON') !== false
                || strpos($variante, 'END_JSON') !== false
                || strpos($variante, 'certificacion_inventario') !== false
                || strpos($variante, 'TJ') !== false
                || strpos($variante, 'Tj') !== false
            ) {
                $txt = extraerTextoDeStreamPdf($variante);
                if ($txt !== '') {
                    $fragmentos[] = $txt;
                }
            }
            return null;
        });

        if (!empty($fragmentos)) {
            $ok = extraerJsonDeTexto(implode("\n", $fragmentos));
            if ($ok !== null) {
                return $ok;
            }
        }

        // Fallback sobre el binario crudo (ej. si hubiera operadores o texto fuera de streams)
        $txt_crudo = extraerTextoDeStreamPdf($binario);
        if ($txt_crudo !== '') {
            $ok = extraerJsonDeTexto($txt_crudo);
            if ($ok !== null) {
                return $ok;
            }
        }

        return extraerJsonDeTexto($binario);
    }
}

if (!function_exists('extraerTextoPdf')) {
    /**
     * Extrae texto legible del PDF combinando Smalot PdfParser, operadores PDF y pdftotext.
     */
    function extraerTextoPdf(string $ruta): string
    {
        cargarAutoloadPdfParser();
        $partes = [];

        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($ruta);
                $txt    = $pdf->getText();
                if (is_string($txt) && trim($txt) !== '') {
                    $partes[] = $txt;
                }
            } catch (Exception $e) {
                // Continuar con lectores nativos
            }
        }

        $binario = @file_get_contents($ruta);
        if ($binario !== false) {
            $stream_texts = [];
            iterarStreamsPdf($binario, function (string $variante) use (&$stream_texts) {
                if (strpos($variante, 'TJ') !== false
                    || strpos($variante, 'Tj') !== false
                    || strpos($variante, 'BEGIN_JSON') !== false
                    || strpos($variante, 'END_JSON') !== false
                    || strpos($variante, 'certificacion_inventario') !== false
                ) {
                    $txt = extraerTextoDeStreamPdf($variante);
                    if ($txt !== '') {
                        $stream_texts[] = $txt;
                    }
                }
                return null;
            });

            if (!empty($stream_texts)) {
                $partes[] = implode("\n", $stream_texts);
            }
        }

        if (empty($partes)) {
            $pdftotext = trim(@shell_exec('which pdftotext 2>/dev/null') ?? '');
            if ($pdftotext !== '' && @is_executable($pdftotext)) {
                $out = @shell_exec($pdftotext . ' -raw -nopgbrk ' . escapeshellarg($ruta) . ' - 2>/dev/null');
                if (is_string($out) && trim($out) !== '') {
                    $partes[] = $out;
                }
            }
        }

        return implode("\n", $partes);
    }
}

if (!function_exists('extraerJsonDeTexto')) {
    /**
     * Busca BEGIN_JSON...END_JSON en texto (tolerando saltos dentro de marcadores o líneas)
     * y lo decodifica preservando exactamente el contenido de strings.
     */
    function extraerJsonDeTexto(string $texto): ?string
    {
        $normalizado = preg_replace('/BEGIN_\s*JSON/', 'BEGIN_JSON', $texto);
        $normalizado = preg_replace('/END_\s*JSON/', 'END_JSON', $normalizado);

        $offset = 0;
        while (($ini = strpos($normalizado, 'BEGIN_JSON', $offset)) !== false) {
            $pos_contenido = $ini + strlen('BEGIN_JSON');
            $fin = strpos($normalizado, 'END_JSON', $pos_contenido);
            if ($fin === false) {
                break;
            }

            $bloque = substr($normalizado, $pos_contenido, $fin - $pos_contenido);
            $ok = decodificarJsonPdfSucio($bloque);
            if ($ok !== null) {
                return $ok;
            }

            $offset = $fin + strlen('END_JSON');
        }

        // Último fallback si los marcadores faltaran: objeto balanceado alrededor de certificacion_inventario / uuid
        $pos_clave = strpos($normalizado, '"certificacion_inventario"');
        if ($pos_clave === false) {
            $pos_clave = strpos($normalizado, '"uuid"');
        }
        if ($pos_clave !== false) {
            $prefijo = substr($normalizado, 0, $pos_clave);
            $pos_llave = strrpos($prefijo, '{');
            while ($pos_llave !== false) {
                $bal = extraerObjetoJsonBalanceado($normalizado, $pos_llave);
                if ($bal !== null) {
                    $ok = decodificarJsonPdfSucio($bal);
                    if ($ok !== null) {
                        return $ok;
                    }
                }
                if ($pos_llave === 0) {
                    break;
                }
                $pos_llave = strrpos(substr($prefijo, 0, $pos_llave), '{');
            }
        }

        return null;
    }
}

if (!function_exists('extraerJsonCanonico')) {
    /**
     * Método de respaldo (Prioridades 2 y 3): texto plano, /Keywords y streams TJ/Tj.
     */
    function extraerJsonCanonico(string $texto, string $ruta_pdf = ''): ?string
    {
        if ($texto !== '') {
            $res = extraerJsonDeTexto($texto);
            if ($res !== null) {
                return $res;
            }
        }

        if ($ruta_pdf !== '') {
            $res = extraerJsonDesdeKeywords($ruta_pdf);
            if ($res !== null) {
                return $res;
            }

            $binario = @file_get_contents($ruta_pdf);
            if ($binario !== false) {
                $res = reconstruirJsonDesdeTJ($binario);
                if ($res !== null) {
                    return $res;
                }
            }
        }

        return null;
    }
}

if (!function_exists('extraerInfoCertificado')) {
    /**
     * Extrae metadatos del certificado digital (pdfsig u OpenSSL PKCS#7).
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
                if ($dt) {
                    $info['valid_desde'] = $dt->format('Y-m-d H:i:s');
                }
            }
            if (preg_match('/Certificate Validity - Not After:\s*(.+)/i', $pdfsig_output, $m)) {
                $dt = DateTime::createFromFormat('D M j H:i:s T Y', trim($m[1]));
                if ($dt) {
                    $info['valid_hasta'] = $dt->format('Y-m-d H:i:s');
                }
            }

            if (!empty($info)) {
                $info['metodo'] = 'pdfsig';
                return $info;
            }
        }

        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            return $info;
        }

        if (!preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $contenido, $br)) {
            return $info;
        }

        if (!preg_match('/\/Contents\s*<([0-9A-Fa-f]+)>/', $contenido, $mc)) {
            return $info;
        }

        $pkcs7_bin = @hex2bin($mc[1]);
        if ($pkcs7_bin === false || strlen($pkcs7_bin) < 16) {
            return $info;
        }

        $tmp_p7 = sys_get_temp_dir() . '/cert_' . bin2hex(random_bytes(6)) . '.p7b';
        if (@file_put_contents($tmp_p7, $pkcs7_bin) === false) {
            return $info;
        }

        $openssl = trim(@shell_exec('which openssl 2>/dev/null') ?? '');
        if (empty($openssl)) {
            $openssl = '/usr/bin/openssl';
        }

        if (@is_executable($openssl)) {
            $cmd_x509 = $openssl . ' pkcs7 -inform DER -in ' . escapeshellarg($tmp_p7) .
                        ' -print_certs 2>/dev/null | ' .
                        $openssl . ' x509 -noout -subject -issuer -serial -dates 2>/dev/null';
            $x509_out = @shell_exec($cmd_x509) ?? '';

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
                    if ($dt) {
                        $info['valid_desde'] = $dt->format('Y-m-d H:i:s');
                    }
                }
                if (preg_match('/notAfter=(.+)/i', $x509_out, $m)) {
                    $dt = DateTime::createFromFormat('M j H:i:s Y T', trim($m[1]));
                    if ($dt) {
                        $info['valid_hasta'] = $dt->format('Y-m-d H:i:s');
                    }
                }
            }
        }

        @unlink($tmp_p7);

        if (!empty($info)) {
            $info['metodo'] = 'openssl_pkcs7';
        }

        return $info;
    }
}
