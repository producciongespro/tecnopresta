<?php
/**
 * ============================================================
 * HELPER: Web Service del MEP
 * ============================================================
 * Centraliza la consulta a ConsultaFuncionario y la
 * normalizacion de los centros educativos reportados por el
 * MEP.
 *
 * Se usa desde:
 *   - sql/buscar_usuario_cedula_n.php
 *   - actualizar_gestor_usuarios_n.php
 * ============================================================
 */

if (!defined('WS_CONSULTA_FUNCIONARIO')) {
    define('WS_CONSULTA_FUNCIONARIO', 'https://apps.mep.go.cr/wstecnopresta/servicio.asmx?WSDL');
}

/**
 * Consulta el WS del MEP y devuelve TODOS los registros del
 * funcionario. Cada registro puede representar un centro
 * educativo distinto.
 *
 * - Retorna [] si el servicio responde vacio.
 * - Lanza excepcion si el transporte/SOAP falla.
 *
 * @return array<int, array<string, mixed>>
 */
function consultarFuncionarioWS(string $cedula): array
{
    if (!class_exists('SoapClient')) {
        throw new RuntimeException('La extension SOAP de PHP no esta habilitada');
    }

    $client = new SoapClient(WS_CONSULTA_FUNCIONARIO, [
        'connection_timeout' => 15,
        'exceptions' => true,
    ]);

    $resWs = $client->ConsultaFuncionario(['str_identificacion' => $cedula]);
    $datosWs = json_decode((string) ($resWs->ConsultaFuncionarioResult ?? ''), true);

    if (!is_array($datosWs) || empty($datosWs)) {
        return [];
    }

    // El WS normalmente devuelve un arreglo de objetos. Si devuelve
    // un solo objeto asociativo, se envuelve para mantener el mismo
    // formato de salida.
    if (isset($datosWs[0]) && is_array($datosWs[0])) {
        return array_values(array_filter($datosWs, 'is_array'));
    }

    return [$datosWs];
}

/**
 * Normaliza los centros educativos devueltos por el WS.
 *
 * Devuelve una lista sin duplicados:
 * [
 *   ['codigo' => '6532', 'dependencia' => 'CTP ...'],
 * ]
 *
 * @param array<int, array<string, mixed>> $registrosWs
 * @return array<int, array{codigo: string, dependencia: string}>
 */
function normalizarCentrosMep(array $registrosWs): array
{
    $centros = [];

    foreach ($registrosWs as $registro) {
        if (!is_array($registro)) {
            continue;
        }

        $dependencia = trim((string) ($registro['Dependencia'] ?? ''));
        $rawCentros = trim((string) ($registro['CentrosEducativosDondeTrabaja'] ?? ''));
        $codigos = [];

        if ($rawCentros !== '') {
            $partes = preg_split('/[\s,;|]+/', $rawCentros) ?: [];
            foreach ($partes as $parte) {
                $parte = trim($parte, " \t\n\r\0\x0B\"'");
                if (preg_match('/^\d{3,4}$/', $parte)) {
                    $codigos[] = $parte;
                }
            }
        }

        // Respaldo: el codigo completo suele venir con formato
        // 210-573-03-53-6532. Se toma el ultimo segmento de 3 o 4
        // digitos, no el codigo completo con guiones.
        if (empty($codigos)) {
            $codigoLargo = trim((string) ($registro['Codigo_Presupuestario'] ?? ''));
            if ($codigoLargo !== '') {
                $partes = preg_split('/[^0-9]+/', $codigoLargo) ?: [];
                for ($i = count($partes) - 1; $i >= 0; $i--) {
                    if (preg_match('/^\d{3,4}$/', $partes[$i])) {
                        $codigos[] = $partes[$i];
                        break;
                    }
                }
            }
        }

        foreach ($codigos as $codigo) {
            if (!isset($centros[$codigo])) {
                $centros[$codigo] = [
                    'codigo' => $codigo,
                    'dependencia' => $dependencia,
                ];
            } elseif ($centros[$codigo]['dependencia'] === '' && $dependencia !== '') {
                $centros[$codigo]['dependencia'] = $dependencia;
            }
        }
    }

    return array_values($centros);
}
