<?php
/**
 * ============================================================
 * CAPA DE DATOS — "Consulta de Activos"
 * ============================================================
 *
 * Reemplaza a sql/consulta_activos_donacion_helper.php.
 *
 * Responsabilidades:
 *   - Utilidades de formato y escape (cag_h, cag_num, badges)
 *   - Catálogo de modos (delegado a sql/consulta_activos_modos.php)
 *   - Constructor único de filtros (cag_construir_filtros)
 *   - Conteo, listado paginado, listado completo e indicadores
 *   - Consulta de instituciones por código presupuestario
 *
 * REGLA CENTRAL
 * -------------
 * El WHERE se construye UNA sola vez en cag_construir_filtros() y lo
 * comparten el conteo, el listado y los indicadores. De ese modo el total
 * mostrado y las filas listadas no pueden divergir.
 */

require_once __DIR__ . '/consulta_activos_modos.php';

/**
 * Tope defensivo de filas para las exportaciones. El código presupuestario es
 * obligatorio, por lo que el máximo real observado es de 1.534 filas; este
 * tope solo protege al servidor si en el futuro se relaja esa condición.
 */
if (!defined('CAG_TOPE_EXPORTACION')) {
    define('CAG_TOPE_EXPORTACION', 50000);
}

// =====================================================================
// Utilidades de formato
// =====================================================================

function cag_h($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cag_num(int $valor): string
{
    return number_format($valor, 0, ',', '.');
}

function cag_corregir_acentos(?string $texto): string
{
    $texto = (string)($texto ?? '');

    if ($texto === '' || !preg_match('/[\x80-\xFF]/', $texto)) {
        return $texto;
    }

    if (!mb_check_encoding($texto, 'UTF-8')) {
        return $texto;
    }

    if (!preg_match('/\x{00C3}/u', $texto)) {
        return $texto;
    }

    $corregido = @mb_convert_encoding($texto, 'WINDOWS-1252', 'UTF-8');

    if ($corregido === false || $corregido === $texto || !mb_check_encoding($corregido, 'UTF-8')) {
        return $texto;
    }

    return $corregido;
}

/**
 * Normaliza el código presupuestario a 4 dígitos (comportamiento vigente).
 *
 * @param string $codigo
 * @return array{0: string, 1: string}  [código normalizado, mensaje de error]
 */
function cag_normalizar_codigo(string $codigo): array
{
    $codigo = trim($codigo);

    if ($codigo === '') {
        return ['', ''];
    }

    if (!preg_match('/^[0-9]{1,4}$/', $codigo)) {
        return [$codigo, 'El código presupuestario debe contener solo dígitos, de 1 a 4 posiciones.'];
    }

    return [str_pad($codigo, 4, '0', STR_PAD_LEFT), ''];
}

/**
 * Clase contextual de Bootstrap según la condición del activo.
 */
function cag_estado_clase(string $estado): string
{
    switch (strtolower(trim($estado))) {
        case 'excelente':
            return 'success';
        case 'bueno':
            return 'info';
        case 'regular':
            return 'warning';
        case 'malo':
            return 'danger';
        case 'robado o hurtado':
            return 'dark';
        default:
            return 'secondary';
    }
}

/**
 * Color hexadecimal de las clases contextuales de Bootstrap 5.
 */
function cag_clase_color(string $clase): string
{
    $colores = [
        'primary'   => '#0d6efd',
        'secondary' => '#6c757d',
        'success'   => '#198754',
        'info'      => '#0dcaf0',
        'warning'   => '#ffc107',
        'danger'    => '#dc3545',
        'dark'      => '#212529',
        'light'     => '#f8f9fa',
    ];

    return $colores[$clase] ?? '#6c757d';
}

/**
 * Tinte claro (fondo suave) de las clases contextuales de Bootstrap 5.
 *
 * Devuelve el color en formato hexadecimal de 6 posiciones SIN '#', porque
 * PhpSpreadsheet rechaza los valores con almohadilla y deja la celda sin
 * relleno (lo que terminó generando texto blanco sobre fondo blanco).
 *
 * Se usa en la exportación a Excel donde el texto se imprime siempre en
 * negro: un tinte claro garantiza contraste legible en todas las variantes,
 * a diferencia de los colores saturados (por ejemplo, el azul #0dcaf0 con
 * letra blanca es prácticamente ilegible).
 */
function cag_color_tinte(string $clase): string
{
    $tintes = [
        'primary'   => 'CFE2FF',
        'secondary' => 'D8DDE1',
        'success'   => 'D1E7DD',
        'info'      => 'CFF4FC',
        'warning'   => 'FFF3CD',
        'danger'    => 'F8D7DA',
        'dark'      => 'CED4DA',
        'light'     => 'F8F9FA',
    ];

    return $tintes[$clase] ?? 'E9ECEF';
}

/**
 * Color de fuente de las celdas con badge en la exportación a Excel.
 *
 * Negro sobre los tintes claros de cag_color_tinte().
 */
function cag_color_fuente_excel(): string
{
    return '000000';
}

/**
 * Especificación de las cuatro columnas indicadoras del reporte.
 */
function cag_especificacion_indicadores(): array
{
    return [
        'donacion' => [
            'columna'  => 'Donación',
            'campo'    => 'flag_donar',
            'nulo'     => 'Sin marcar',
            'clase_si' => 'success',
        ],
        'robados' => [
            'columna'  => 'Robados',
            'campo'    => 'flag_robado',
            'nulo'     => 'No',
            'clase_si' => 'dark',
        ],
        'prestables' => [
            'columna'  => 'Prestables',
            'campo'    => 'flag_prestable',
            'nulo'     => 'No',
            'clase_si' => 'info',
        ],
        'en_uso' => [
            'columna'  => 'En uso',
            'campo'    => 'flag_en_uso',
            'nulo'     => 'Sin evaluar',
            'clase_si' => 'success',
        ],
    ];
}

/**
 * Estado de un indicador para una fila: texto, clase de Bootstrap y si es atenuado.
 *
 * @param mixed $valor      1 = sí, 0 = no, null = sin marcar / sin evaluar
 * @return array{texto: string, clase: string, tenue: bool, valor: int|null}
 */
function cag_indicador($valor, string $claseSi = 'success', string $textoNulo = 'Sin marcar'): array
{
    if ($valor === null) {
        return [
            'texto'  => $textoNulo,
            'clase'  => 'secondary',
            'tenue'  => true,
            'valor'  => null,
        ];
    }

    $activo = ((int)$valor === 1);

    return [
        'texto' => $activo ? 'Sí' : 'No',
        'clase' => $activo ? $claseSi : 'secondary',
        'tenue' => false,
        'valor' => $activo ? 1 : 0,
    ];
}

/**
 * Badge de un indicador para una fila concreta.
 *
 * @param array<string, mixed> $activo
 * @param array<string, mixed> $spec
 * @return array{texto: string, clase: string, tenue: bool, valor: int|null}
 */
function cag_badge_indicador(array $activo, array $spec): array
{
    $campo = (string)($spec['campo'] ?? '');

    return cag_indicador(
        array_key_exists($campo, $activo) ? $activo[$campo] : null,
        (string)($spec['clase_si'] ?? 'success'),
        (string)($spec['nulo'] ?? 'Sin marcar')
    );
}

/**
 * Badge HTML para pantalla (usa las clases de Bootstrap del proyecto).
 */
function cag_badge_html(string $texto, string $clase, bool $tenue = false): string
{
    $extra = $tenue ? ' opacity-75' : '';

    return '<span class="badge bg-' . $clase . $extra . '">' . cag_h($texto) . '</span>';
}

/**
 * Badge HTML para PDF e impresión (estilos en línea, sin Bootstrap).
 */
function cag_badge_estilo(string $texto, string $clase, bool $tenue = false): string
{
    if ($tenue) {
        $fondo = '#e2e8f0';
        $color = '#6c757d';
    } else {
        $fondo = cag_clase_color($clase);
        $color = '#ffffff';

        if ($clase === 'warning') {
            $color = '#212529';
        }
    }

    return '<span style="background:' . $fondo . ';color:' . $color
        . ';padding:1px 4px;border-radius:3px;font-size:6.5pt;white-space:nowrap;">'
        . cag_h($texto) . '</span>';
}

/**
 * Badge de la columna Estado: condición del activo y, si aplica, la marca de baja.
 *
 * @param array<string, mixed> $activo
 * @return array{estado: array, baja: bool}
 */
function cag_badge_estado(array $activo): array
{
    $estado = trim((string)($activo['estado'] ?? ''));
    $baja = ((int)($activo['activo'] ?? 1) === 0);

    return [
        'estado' => [
            'texto' => $estado !== '' ? $estado : 'Sin estado',
            'clase' => cag_estado_clase($estado),
            'tenue' => false,
        ],
        'baja' => $baja,
    ];
}

// =====================================================================
// Fuentes presupuestarias
// =====================================================================

function cag_obtener_fuentes(mysqli $link): array
{
    $fuentes = [];
    $resultado = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY id_fondos");

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $fila['fondos'] = cag_corregir_acentos($fila['fondos']);
            $fuentes[] = $fila;
        }
        $resultado->free();
    } else {
        error_log('Consulta de fuentes de activos: ' . $link->error);
    }

    return $fuentes;
}

function cag_mapa_fuentes(array $fuentes): array
{
    $mapa = [];

    foreach ($fuentes as $fuente) {
        $mapa[(int)$fuente['id_fondos']] = (string)$fuente['fondos'];
    }

    return $mapa;
}

function cag_nombre_fuente(array $mapaFuentes, int $idFondos): string
{
    if ($idFondos <= 0) {
        return 'Todas las fuentes';
    }

    return $mapaFuentes[$idFondos] ?? 'Sin fuente';
}

// =====================================================================
// Construcción de filtros
// =====================================================================

/**
 * Construye el WHERE compartido por el conteo, el listado y los indicadores.
 *
 * Filtros base (todos los modos salvo `baja`):
 *   p.activo = 1
 *   COALESCE(p.marcado,0) <> 1
 *   p.codigo = ?        (OBLIGATORIO)
 *   p.id_fondos = ?     (opcional: 0 = todas las fuentes)
 *
 * @param array<string, mixed> $modo
 * @param int    $idFondos      0 = todas las fuentes
 * @param string $codigo        código presupuestario ya normalizado
 * @param bool   $incluirBaja   incluir también los activos dados de baja
 * @return array{where: string, params: array, tipos: string, ignora_activo: bool}
 */
function cag_construir_filtros(array $modo, int $idFondos, string $codigo, bool $incluirBaja): array
{
    $condiciones = [];
    $parametros = [];
    $tipos = '';

    // El modo `baja` lista precisamente los dados de baja, así que nunca debe
    // heredar el filtro "en servicio". Tampoco lo hereda el resto cuando el
    // usuario marca "Incluir activos dados de baja".
    $ignoraActivo = !empty($modo['ignora_activo']) || $incluirBaja;

    if (!$ignoraActivo) {
        $condiciones[] = 'p.activo = ?';
        $parametros[] = 1;
        $tipos .= 'i';
    }

    $condiciones[] = 'COALESCE(p.marcado, 0) <> ?';
    $parametros[] = 1;
    $tipos .= 'i';

    if ($codigo !== '') {
        $condiciones[] = 'p.codigo = ?';
        $parametros[] = $codigo;
        $tipos .= 's';
    }

    if ($idFondos > 0) {
        $condiciones[] = 'p.id_fondos = ?';
        $parametros[] = $idFondos;
        $tipos .= 'i';
    }

    $whereModo = trim((string)($modo['where'] ?? ''));

    if ($whereModo !== '') {
        $condiciones[] = '(' . $whereModo . ')';

        foreach (($modo['params'] ?? []) as $parametro) {
            $parametros[] = $parametro;
            $tipos .= is_int($parametro) ? 'i' : 's';
        }
    }

    return [
        'where'         => implode(' AND ', $condiciones),
        'params'        => $parametros,
        'tipos'         => $tipos,
        'ignora_activo' => $ignoraActivo,
    ];
}

// =====================================================================
// Consultas de activos
// =====================================================================

/**
 * SELECT base con los JOIN de catálogo y los cuatro indicadores.
 */
function cag_sql_activos(): string
{
    return "
        SELECT
            p.id_placa,
            p.codigo,
            p.placa,
            p.serial,
            p.activo,
            COALESCE(NULLIF(ag.clase, ''), 'Sin tipo') AS tipo,
            COALESCE(NULLIF(m.marca, ''), 'Sin marca') AS marca,
            COALESCE(NULLIF(a.modelo, ''), 'Sin modelo') AS modelo,
            COALESCE(NULLIF(e.estado, ''), 'Sin estado') AS estado,
            COALESCE(NULLIF(l.lugar, ''), 'Sin ubicación') AS ubicacion,
            COALESCE(NULLIF(f.fondos, ''), 'Sin fuente') AS fuente,
            p.donar AS flag_donar,
            CASE WHEN p.id_estado = 5 THEN 1 ELSE 0 END AS flag_robado,
            p.prestar AS flag_prestable,
            p.enuso AS flag_en_uso
        FROM t_placa p
        LEFT JOIN t_activo a ON a.id_activo = p.id_activo
        LEFT JOIN t_activo_general ag ON ag.id_ag = a.id_ag
        LEFT JOIN t_marca m ON m.id_marca = a.id_marca
        LEFT JOIN t_estado e ON e.id_estado = p.id_estado
        LEFT JOIN t_lugar l ON l.id_lugar = p.id_lugar
        LEFT JOIN t_fondos f ON f.id_fondos = p.id_fondos
    ";
}

/**
 * Normaliza los campos de texto de una fila de activo.
 */
function cag_normalizar_fila(array $fila): array
{
    foreach (['tipo', 'marca', 'modelo', 'estado', 'ubicacion', 'fuente'] as $campo) {
        if (isset($fila[$campo])) {
            $fila[$campo] = cag_corregir_acentos((string)$fila[$campo]);
        }
    }

    return $fila;
}

/**
 * Cuenta los activos que cumplen los filtros.
 */
function cag_contar_activos(mysqli $link, array $filtros): int
{
    $sql = "SELECT COUNT(*) AS total FROM t_placa p WHERE " . $filtros['where'];

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Conteo de activos: ' . $link->error);
        return 0;
    }

    if (($filtros['tipos'] ?? '') !== '') {
        $stmt->bind_param($filtros['tipos'], ...$filtros['params']);
    }

    if (!$stmt->execute()) {
        error_log('Ejecución de conteo de activos: ' . $stmt->error);
        $stmt->close();
        return 0;
    }

    $total = 0;
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    return (int)$total;
}

/**
 * Lista una página de activos.
 */
function cag_obtener_activos(mysqli $link, array $filtros, int $limite = 100, int $offset = 0): array
{
    $limite = max(1, $limite);
    $offset = max(0, $offset);

    $sql = cag_sql_activos()
        . ' WHERE ' . $filtros['where']
        . ' ORDER BY p.placa ASC, p.id_placa ASC LIMIT ? OFFSET ?';

    $tipos = ($filtros['tipos'] ?? '') . 'ii';
    $parametros = $filtros['params'];
    $parametros[] = $limite;
    $parametros[] = $offset;

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Consulta de activos: ' . $link->error);
        return [];
    }

    $stmt->bind_param($tipos, ...$parametros);

    if (!$stmt->execute()) {
        error_log('Ejecución de consulta de activos: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();

    if (!$resultado) {
        error_log('Resultado de consulta de activos: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $activos = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    $stmt->close();

    foreach ($activos as &$activo) {
        $activo = cag_normalizar_fila($activo);
    }
    unset($activo);

    return $activos;
}

/**
 * Lista todos los activos que cumplen los filtros en una sola consulta.
 *
 * Se usa en las exportaciones: con el código presupuestario obligatorio el
 * volumen es acotado (máximo real observado: 1.534 filas), por lo que una
 * sola consulta evita el patrón de múltiples viajes a la base de datos.
 */
function cag_obtener_activos_todos(mysqli $link, array $filtros, int $tope = CAG_TOPE_EXPORTACION): array
{
    $tope = max(1, $tope);

    $sql = cag_sql_activos()
        . ' WHERE ' . $filtros['where']
        . ' ORDER BY p.placa ASC, p.id_placa ASC LIMIT ?';

    $tipos = ($filtros['tipos'] ?? '') . 'i';
    $parametros = $filtros['params'];
    $parametros[] = $tope;

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Consulta completa de activos: ' . $link->error);
        return [];
    }

    $stmt->bind_param($tipos, ...$parametros);

    if (!$stmt->execute()) {
        error_log('Ejecución de consulta completa de activos: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();

    if (!$resultado) {
        error_log('Resultado de consulta completa de activos: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $activos = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    $stmt->close();

    foreach ($activos as &$activo) {
        $activo = cag_normalizar_fila($activo);
    }
    unset($activo);

    return $activos;
}

/**
 * Indicadores del conjunto filtrado en una sola consulta.
 *
 * Alimenta las cuatro tarjetas de resumen y la fila de totales de la tabla.
 */
function cag_obtener_indicadores(mysqli $link, array $filtros): array
{
    $sql = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN p.donar = 1 THEN 1 ELSE 0 END), 0) AS cnt_donar,
            COALESCE(SUM(CASE WHEN p.donar = 0 THEN 1 ELSE 0 END), 0) AS cnt_no_donar,
            COALESCE(SUM(CASE WHEN p.donar IS NULL THEN 1 ELSE 0 END), 0) AS cnt_donar_sin_marcar,
            COALESCE(SUM(CASE WHEN p.id_estado = 5 THEN 1 ELSE 0 END), 0) AS cnt_robado,
            COALESCE(SUM(CASE WHEN p.prestar = 1 THEN 1 ELSE 0 END), 0) AS cnt_prestable,
            COALESCE(SUM(CASE WHEN p.prestar = 0 THEN 1 ELSE 0 END), 0) AS cnt_no_prestable,
            COALESCE(SUM(CASE WHEN p.enuso = 1 THEN 1 ELSE 0 END), 0) AS cnt_en_uso,
            COALESCE(SUM(CASE WHEN p.enuso = 0 THEN 1 ELSE 0 END), 0) AS cnt_sin_uso,
            COALESCE(SUM(CASE WHEN p.enuso IS NULL THEN 1 ELSE 0 END), 0) AS cnt_sin_evaluar,
            COALESCE(SUM(CASE WHEN p.activo = 0 THEN 1 ELSE 0 END), 0) AS cnt_baja
        FROM t_placa p
        WHERE " . $filtros['where'];

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Consulta de indicadores de activos: ' . $link->error);
        return [];
    }

    if (($filtros['tipos'] ?? '') !== '') {
        $stmt->bind_param($filtros['tipos'], ...$filtros['params']);
    }

    if (!$stmt->execute()) {
        error_log('Ejecución de consulta de indicadores: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();

    if (!$resultado) {
        error_log('Resultado de consulta de indicadores: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $fila = $resultado->fetch_assoc();
    $resultado->free();
    $stmt->close();

    if (!$fila) {
        return [];
    }

    foreach ($fila as $campo => $valor) {
        $fila[$campo] = (int)$valor;
    }

    return $fila;
}

// =====================================================================
// Instituciones
// =====================================================================

function cag_obtener_instituciones_por_codigo(mysqli $link, string $codigo): array
{
    if ($codigo === '') {
        return [];
    }

    $sql = "
        SELECT
            i.id_ins,
            i.codigo,
            i.institucion,
            i.cod_saber,
            i.circuito,
            i.activo,
            rg.nombre AS regional
        FROM t_instituciones i
        LEFT JOIN regionales rg ON rg.codigo = i.id_regional
        WHERE CHAR_LENGTH(TRIM(i.codigo)) <= 4
          AND LPAD(TRIM(i.codigo), 4, '0') = ?
        ORDER BY i.id_ins
    ";

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Consulta de instituciones: ' . $link->error);
        return [];
    }

    $stmt->bind_param('s', $codigo);

    if (!$stmt->execute()) {
        error_log('Ejecución de consulta de instituciones: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();

    if (!$resultado) {
        error_log('Resultado de consulta de instituciones: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $instituciones = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    $stmt->close();

    foreach ($instituciones as &$institucion) {
        $institucion['institucion'] = cag_corregir_acentos($institucion['institucion']);
        $institucion['regional'] = cag_corregir_acentos($institucion['regional']);
    }
    unset($institucion);

    return $instituciones;
}

function cag_lista_centros(array $instituciones): string
{
    if (empty($instituciones)) {
        return 'Sin institución registrada';
    }

    $centros = [];

    foreach ($instituciones as $institucion) {
        $nombre = trim((string)($institucion['institucion'] ?? ''));

        if ($nombre === '') {
            $nombre = 'Institución sin nombre';
        }

        $codigoSaber = trim((string)($institucion['cod_saber'] ?? ''));

        if ($codigoSaber !== '') {
            $nombre .= ' (' . $codigoSaber . ')';
        }

        $centros[] = $nombre;
    }

    return implode('; ', $centros);
}

function cag_resumen_centros(string $codigo, array $instituciones): string
{
    if ($codigo === '') {
        return 'Todos los centros educativos';
    }

    return cag_lista_centros($instituciones);
}
