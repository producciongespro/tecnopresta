<?php
/**
 * ============================================================
 * CATÁLOGO DE MODOS DE CONSULTA — "Consulta de Activos"
 * ============================================================
 *
 * Fuente única de verdad de los tipos de consulta disponibles.
 *
 * Cada modo declara:
 *   - clave       : identificador estable usado en la URL (?modo=)
 *   - etiqueta    : texto visible en el desplegable
 *   - descripcion : texto informativo mostrado bajo el selector
 *   - grupo       : agrupa las opciones en <optgroup> del desplegable
 *   - icono       : clase de Bootstrap Icons
 *   - color       : clase contextual de Bootstrap para el badge del modo
 *   - titulo_doc  : título del documento (pantalla, Excel, PDF, impresión)
 *   - archivo     : prefijo del nombre de archivo exportado
 *   - where       : predicado SQL adicional (literal del repositorio)
 *   - params      : parámetros del predicado, en el mismo orden
 *   - ignora_activo: true si el modo no debe aplicar el filtro "en servicio"
 *
 * SEGURIDAD
 * ---------
 * "where" y "params" son literales controlados por este archivo. Nunca se
 * concatena entrada del usuario dentro del SQL: la clave que llega por URL se
 * valida contra las claves de este catálogo y, si no existe, se aplica el modo
 * por defecto con un aviso en pantalla (nunca un error SQL).
 *
 * El filtro base común a todos los modos se construye en
 * cag_construir_filtros() (sql/consulta_activos_helper.php).
 */

if (!defined('ACCESO_SEGURO') && !defined('CAG_CATALOGO_CARGADO')) {
    // Este archivo es una biblioteca: no produce salida por sí mismo.
    define('CAG_CATALOGO_CARGADO', true);
}

/**
 * Devuelve el catálogo completo de modos de consulta.
 *
 * @return array<int, array<string, mixed>>
 */
function cag_modos_disponibles(): array
{
    static $modos = null;

    if ($modos !== null) {
        return $modos;
    }

    $modos = [
        // ---------------------------------------------------------- General
        [
            'clave'         => 'inventario',
            'etiqueta'      => 'Todos los activos del centro',
            'descripcion'   => 'Inventario completo de los activos en servicio registrados bajo el código presupuestario consultado.',
            'grupo'         => 'General',
            'icono'         => 'bi-box-seam',
            'color'         => 'primary',
            'titulo_doc'    => 'INVENTARIO DE ACTIVOS',
            'archivo'       => 'Inventario_Activos',
            'where'         => '',
            'params'        => [],
            'ignora_activo' => false,
        ],

        // ------------------------------------------------ Condición del activo
        [
            'clave'         => 'excelente',
            'etiqueta'      => 'Activos en Condición Excelente',
            'descripcion'   => 'Unidades físicas en condición excelente, sinefa de mantenimiento correctivo.',
            'grupo'         => 'Condición del activo',
            'icono'         => 'bi-stars',
            'color'         => 'success',
            'titulo_doc'    => 'ACTIVOS EN CONDICIÓN EXCELENTE',
            'archivo'       => 'Activos_Excelente',
            'where'         => 'p.id_estado = ?',
            'params'        => [1],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'bueno',
            'etiqueta'      => 'Activos en Condición Buena',
            'descripcion'   => 'Unidades físicas en condición buena, con desgaste normal de uso.',
            'grupo'         => 'Condición del activo',
            'icono'         => 'bi-check-circle',
            'color'         => 'info',
            'titulo_doc'    => 'ACTIVOS EN CONDICIÓN BUENA',
            'archivo'       => 'Activos_Bueno',
            'where'         => 'p.id_estado = ?',
            'params'        => [2],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'regular',
            'etiqueta'      => 'Activos en Condición Regular',
            'descripcion'   => 'Unidades físicas en condición regular, con desgaste visible que amerita seguimiento.',
            'grupo'         => 'Condición del activo',
            'icono'         => 'bi-exclamation-circle',
            'color'         => 'warning',
            'titulo_doc'    => 'ACTIVOS EN CONDICIÓN REGULAR',
            'archivo'       => 'Activos_Regular',
            'where'         => 'p.id_estado = ?',
            'params'        => [3],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'malos',
            'etiqueta'      => 'Activos en Mal Estado',
            'descripcion'   => 'Unidades físicas en mal estado, que requieren reparación o reposición.',
            'grupo'         => 'Condición del activo',
            'icono'         => 'bi-exclamation-triangle',
            'color'         => 'danger',
            'titulo_doc'    => 'ACTIVOS EN MAL ESTADO',
            'archivo'       => 'Activos_Mal_Estado',
            'where'         => 'p.id_estado = ?',
            'params'        => [4],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'robados',
            'etiqueta'      => 'Activos Robados o Hurtados',
            'descripcion'   => 'Unidades físicas cuya condición fue reportada como robada o hurtada.',
            'grupo'         => 'Condición del activo',
            'icono'         => 'bi-shield-exclamation',
            'color'         => 'dark',
            'titulo_doc'    => 'ACTIVOS ROBADOS O HURTADOS',
            'archivo'       => 'Activos_Robados',
            'where'         => 'p.id_estado = ?',
            'params'        => [5],
            'ignora_activo' => false,
        ],

        // ------------------------------------------- Disponibilidad y uso
        [
            'clave'         => 'donacion',
            'etiqueta'      => 'Activos para Donación',
            'descripcion'   => 'Activos marcados para donación, disponibles para resignación.',
            'grupo'         => 'Disponibilidad y uso',
            'icono'         => 'bi-gift',
            'color'         => 'success',
            'titulo_doc'    => 'ACTIVOS MARCADOS PARA DONACIÓN',
            'archivo'       => 'Activos_Donacion',
            'where'         => 'p.donar = ?',
            'params'        => [1],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'prestables',
            'etiqueta'      => 'Activos Prestables',
            'descripcion'   => 'Activos habilitados para ser prestados a un usuario o a otro centro.',
            'grupo'         => 'Disponibilidad y uso',
            'icono'         => 'bi-arrow-left-right',
            'color'         => 'info',
            'titulo_doc'    => 'ACTIVOS PRESTABLES',
            'archivo'       => 'Activos_Prestables',
            'where'         => 'p.prestar = ?',
            'params'        => [1],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'no_prestables',
            'etiqueta'      => 'Activos No Prestables',
            'descripcion'   => 'Activos no habilitados para préstamo, de uso exclusivo del centro.',
            'grupo'         => 'Disponibilidad y uso',
            'icono'         => 'bi-lock',
            'color'         => 'secondary',
            'titulo_doc'    => 'ACTIVOS NO PRESTABLES',
            'archivo'       => 'Activos_No_Prestables',
            'where'         => 'p.prestar = ?',
            'params'        => [0],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'en_uso',
            'etiqueta'      => 'Activos en Uso',
            'descripcion'   => 'Activos que se encuentran asignados y en uso.',
            'grupo'         => 'Disponibilidad y uso',
            'icono'         => 'bi-person-check',
            'color'         => 'success',
            'titulo_doc'    => 'ACTIVOS EN USO',
            'archivo'       => 'Activos_En_Uso',
            'where'         => 'p.enuso = ?',
            'params'        => [1],
            'ignora_activo' => false,
        ],
        [
            'clave'         => 'no_uso',
            'etiqueta'      => 'No Marcado en Uso (sin uso)',
            'descripcion'   => 'Activos no marcados como en uso, incluidos los que nunca han sido evaluados.',
            'grupo'         => 'Disponibilidad y uso',
            'icono'         => 'bi-person-dash',
            'color'         => 'secondary',
            'titulo_doc'    => 'ACTIVOS NO MARCADOS EN USO',
            'archivo'       => 'Activos_No_Marcados_En_Uso',
            'where'         => '(p.enuso = ? OR p.enuso IS NULL)',
            'params'        => [0],
            'ignora_activo' => false,
        ],

        // ------------------------------------------------------- Ubicación
        [
            'clave'         => 'sin_ubicacion',
            'etiqueta'      => 'Activos sin Ubicación registrada',
            'descripcion'   => 'Activos sin lugar de ubicación registrado en el inventario.',
            'grupo'         => 'Ubicación',
            'icono'         => 'bi-geo-alt',
            'color'         => 'secondary',
            'titulo_doc'    => 'ACTIVOS SIN UBICACIÓN REGISTRADA',
            'archivo'       => 'Activos_Sin_Ubicacion',
            'where'         => '(p.id_lugar IS NULL OR p.id_lugar = ?)',
            'params'        => [0],
            'ignora_activo' => false,
        ],

        // ------------------------------------------- Situación administrativa
        [
            'clave'         => 'baja',
            'etiqueta'      => 'Activos Dados de Baja',
            'descripcion'   => 'Activos dados de baja en el inventario (activo = 0).',
            'grupo'         => 'Situación administrativa',
            'icono'         => 'bi-archive',
            'color'         => 'dark',
            'titulo_doc'    => 'ACTIVOS DADOS DE BAJA',
            'archivo'       => 'Activos_Dados_de_Baja',
            'where'         => 'p.activo = ?',
            'params'        => [0],
            'ignora_activo' => true,
        ],
    ];

    return $modos;
}

/**
 * Clave del modo que se aplica cuando no se indica ninguno o el indicado no existe.
 *
 * El modo por defecto es `inventario`: la vista base del formulario es el
 * inventario completo de los activos del código presupuestario consultado.
 * "Activos para Donación" es uno de los modos disponibles, no el estado inicial.
 *
 * @return string
 */
function cag_modo_por_defecto(): string
{
    return 'inventario';
}

/**
 * Resuelve una clave de modo contra el catálogo.
 *
 * @param string $clave
 * @return array<string, mixed>|null  null si la clave no existe en el catálogo.
 */
function cag_resolver_modo(string $clave): ?array
{
    $clave = trim($clave);

    if ($clave === '') {
        return null;
    }

    foreach (cag_modos_disponibles() as $modo) {
        if ($modo['clave'] === $clave) {
            return $modo;
        }
    }

    return null;
}

/**
 * Devuelve el modo por defecto ya resuelto como arreglo.
 *
 * @return array<string, mixed>
 */
function cag_modo_defecto(): array
{
    $modo = cag_resolver_modo(cag_modo_por_defecto());

    return $modo ?? cag_modos_disponibles()[0];
}

/**
 * Agrupa los modos por grupo conservando el orden de aparición del catálogo.
 *
 * @return array<int, array{grupo: string, modos: array<int, array<string, mixed>>}>
 */
function cag_modos_agrupados(): array
{
    $agrupados = [];

    foreach (cag_modos_disponibles() as $modo) {
        $grupo = (string)$modo['grupo'];

        if (!isset($agrupados[$grupo])) {
            $agrupados[$grupo] = [
                'grupo' => $grupo,
                'modos' => [],
            ];
        }

        $agrupados[$grupo]['modos'][] = $modo;
    }

    return array_values($agrupados);
}

/**
 * Título corto del modo, para encabezados de tarjeta y nombres de archivo.
 *
 * @param array<string, mixed> $modo
 * @return string
 */
function cag_modo_etiqueta(array $modo): string
{
    return (string)($modo['etiqueta'] ?? 'Consulta de activos');
}
