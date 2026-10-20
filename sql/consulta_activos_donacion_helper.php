<?php

function cad_h($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cad_num(int $valor): string
{
    return number_format($valor, 0, ',', '.');
}

function cad_corregir_acentos(?string $texto): string
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

function cad_normalizar_codigo(string $codigo): array
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

function cad_estado_clase(string $estado): string
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

function cad_obtener_fuentes(mysqli $link): array
{
    $fuentes = [];
    $resultado = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY id_fondos");

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $fila['fondos'] = cad_corregir_acentos($fila['fondos']);
            $fuentes[] = $fila;
        }
        $resultado->free();
    } else {
        error_log('Consulta de fuentes para donación: ' . $link->error);
    }

    return $fuentes;
}

function cad_mapa_fuentes(array $fuentes): array
{
    $mapa = [];

    foreach ($fuentes as $fuente) {
        $mapa[(int)$fuente['id_fondos']] = (string)$fuente['fondos'];
    }

    return $mapa;
}

function cad_nombre_fuente(array $mapaFuentes, int $idFondos): string
{
    if ($idFondos <= 0) {
        return 'Todas las fuentes';
    }

    return $mapaFuentes[$idFondos] ?? 'Sin fuente';
}

function cad_contar_activos(mysqli $link, int $idFondos = 0, string $codigo = ''): int
{
    $sql = "SELECT COUNT(*) AS total FROM t_placa p WHERE p.donar = 1";
    $tipos = '';
    $parametros = [];

    if ($idFondos > 0) {
        $sql .= " AND p.id_fondos = ?";
        $tipos .= 'i';
        $parametros[] = $idFondos;
    }

    if ($codigo !== '') {
        $sql .= " AND p.codigo = ?";
        $tipos .= 's';
        $parametros[] = $codigo;
    }

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Conteo de activos para donación: ' . $link->error);
        return 0;
    }

    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$parametros);
    }

    if (!$stmt->execute()) {
        error_log('Ejecución de conteo de activos para donación: ' . $stmt->error);
        $stmt->close();
        return 0;
    }

    $total = 0;
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    return (int)$total;
}

function cad_obtener_activos(
    mysqli $link,
    int $idFondos = 0,
    string $codigo = '',
    int $limite = 100,
    int $offset = 0
): array {
    $limite = max(1, $limite);
    $offset = max(0, $offset);

    $sql = "
        SELECT
            p.id_placa,
            p.codigo,
            p.placa,
            p.serial,
            COALESCE(NULLIF(ag.clase, ''), 'Sin tipo') AS tipo,
            COALESCE(NULLIF(m.marca, ''), 'Sin marca') AS marca,
            COALESCE(NULLIF(a.modelo, ''), 'Sin modelo') AS modelo,
            COALESCE(NULLIF(e.estado, ''), 'Sin estado') AS estado,
            COALESCE(NULLIF(l.lugar, ''), 'Sin ubicación') AS ubicacion,
            COALESCE(NULLIF(f.fondos, ''), 'Sin fuente') AS fuente
        FROM t_placa p
        LEFT JOIN t_activo a ON a.id_activo = p.id_activo
        LEFT JOIN t_activo_general ag ON ag.id_ag = a.id_ag
        LEFT JOIN t_marca m ON m.id_marca = a.id_marca
        LEFT JOIN t_estado e ON e.id_estado = p.id_estado
        LEFT JOIN t_lugar l ON l.id_lugar = p.id_lugar
        LEFT JOIN t_fondos f ON f.id_fondos = p.id_fondos
        WHERE p.donar = 1
    ";

    $tipos = '';
    $parametros = [];

    if ($idFondos > 0) {
        $sql .= " AND p.id_fondos = ?";
        $tipos .= 'i';
        $parametros[] = $idFondos;
    }

    if ($codigo !== '') {
        $sql .= " AND p.codigo = ?";
        $tipos .= 's';
        $parametros[] = $codigo;
    }

    $sql .= " ORDER BY p.codigo ASC, p.placa ASC, p.id_placa ASC LIMIT ? OFFSET ?";
    $tipos .= 'ii';
    $parametros[] = $limite;
    $parametros[] = $offset;

    $stmt = $link->prepare($sql);

    if (!$stmt) {
        error_log('Consulta de activos para donación: ' . $link->error);
        return [];
    }

    $stmt->bind_param($tipos, ...$parametros);

    if (!$stmt->execute()) {
        error_log('Ejecución de consulta de activos para donación: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();

    if (!$resultado) {
        error_log('Resultado de consulta de activos para donación: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $activos = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    $stmt->close();

    foreach ($activos as &$activo) {
        $activo['tipo'] = cad_corregir_acentos($activo['tipo']);
        $activo['marca'] = cad_corregir_acentos($activo['marca']);
        $activo['modelo'] = cad_corregir_acentos($activo['modelo']);
        $activo['estado'] = cad_corregir_acentos($activo['estado']);
        $activo['ubicacion'] = cad_corregir_acentos($activo['ubicacion']);
        $activo['fuente'] = cad_corregir_acentos($activo['fuente']);
    }
    unset($activo);

    return $activos;
}

function cad_obtener_instituciones_por_codigo(mysqli $link, string $codigo): array
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
        error_log('Consulta de instituciones para donación: ' . $link->error);
        return [];
    }

    $stmt->bind_param('s', $codigo);

    if (!$stmt->execute()) {
        error_log('Ejecución de consulta de instituciones para donación: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $resultado = $stmt->get_result();

    if (!$resultado) {
        error_log('Resultado de consulta de instituciones para donación: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $instituciones = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    $stmt->close();

    foreach ($instituciones as &$institucion) {
        $institucion['institucion'] = cad_corregir_acentos($institucion['institucion']);
        $institucion['regional'] = cad_corregir_acentos($institucion['regional']);
    }
    unset($institucion);

    return $instituciones;
}

function cad_lista_centros(array $instituciones): string
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

function cad_resumen_centros(string $codigo, array $instituciones): string
{
    if ($codigo === '') {
        return 'Todos los centros educativos';
    }

    return cad_lista_centros($instituciones);
}
