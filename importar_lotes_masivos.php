<?php
/**
 * importar_lotes.php
 * 
 * Script para importación masiva de activos desde CSV
 * Versión 2.0 - Con validación de duplicados y transacciones
 */

// ============================================
// CONFIGURACIÓN INICIAL
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

require_once("conexion.php");
$link = $mysqli;

// Verificar conexión
if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}

// Configurar charset
if (!mysqli_set_charset($link, "utf8")) {
    die("Error cargando el conjunto de caracteres utf8");
}

function redirigirConResultado($tipo, $titulo, $html) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['import_result'] = [
        'tipo' => $tipo,
        'titulo' => $titulo,
        'html' => $html
    ];

    header("Location: navegar.php?ruta=registro_masivo_de_activos.php&subsistema_id=2&modulo_id=11&formulario_id=34");
    exit;
}

// ============================================
// FUNCIONES DE VALIDACIÓN
// ============================================

/**
 * Verifica si existen duplicados de placa o serial en la base de datos
 */
function verificarDuplicados($link, $datos) {
    $duplicados = [
        'placa' => [],
        'serial' => [],
        'ambos' => []
    ];
    
    $placas = array_column($datos, 0);
    $serials = array_column($datos, 1);
    
    // Escapar valores para consulta
    $placasEscapadas = array_map(function($p) use ($link) {
        return "'" . mysqli_real_escape_string($link, trim($p)) . "'";
    }, $placas);
    
    $serialsEscapados = array_map(function($s) use ($link) {
        return "'" . mysqli_real_escape_string($link, trim($s)) . "'";
    }, $serials);
    
    // Verificar placas duplicadas
    if (!empty($placasEscapadas)) {
        $queryPlaca = "SELECT placa FROM t_placa WHERE placa IN (" . implode(',', $placasEscapadas) . ")";
        $resultPlaca = mysqli_query($link, $queryPlaca);
        while ($row = mysqli_fetch_assoc($resultPlaca)) {
            $duplicados['placa'][] = $row['placa'];
        }
    }
    
    // Verificar seriales duplicados
    if (!empty($serialsEscapados)) {
        $querySerial = "SELECT serial FROM t_placa WHERE serial IN (" . implode(',', $serialsEscapados) . ")";
        $resultSerial = mysqli_query($link, $querySerial);
        while ($row = mysqli_fetch_assoc($resultSerial)) {
            $duplicados['serial'][] = $row['serial'];
        }
    }
    
    // Verificar duplicados dentro del mismo lote
    $duplicadosLote = [];
    $vistos = [];
    foreach ($datos as $index => $fila) {
        $placa = trim($fila[0]);
        $serial = trim($fila[1]);
        $key = $placa . '|' . $serial;
        
        if (isset($vistos[$key])) {
            $duplicadosLote[] = [
                'fila' => $index + 2, // +2 porque el índice 0 es el encabezado
                'placa' => $placa,
                'serial' => $serial
            ];
        }
        $vistos[$key] = true;
    }
    
    return [
        'en_bd' => $duplicados,
        'en_lote' => $duplicadosLote
    ];
}

/**
 * Valida la estructura de los datos
 */
function validarEstructura($datos, $headers) {
    $errores = [];
    $camposEsperados = 9; // Número de campos esperados
    
    foreach ($datos as $index => $fila) {
        $numFila = $index + 2; // +2 por el encabezado y el índice 0
        
        // Verificar número de columnas
        if (count($fila) !== $camposEsperados) {
            $errores[] = "Fila {$numFila}: Número incorrecto de columnas (" . count($fila) . " de {$camposEsperados} esperados)";
            continue;
        }
        
        // Validar campos obligatorios
        if (empty(trim($fila[0]))) {
            $errores[] = "Fila {$numFila}: Campo 'Numero de Placa' vacío";
        }
        if (empty(trim($fila[1]))) {
            $errores[] = "Fila {$numFila}: Campo 'Numero de Serial' vacío";
        }
        if (empty(trim($fila[2]))) {
            $errores[] = "Fila {$numFila}: Campo 'id_activo' vacío";
        }
        
        // Validar campos numéricos (ID_Estado, Para_Prestar, Estado_Activo, Identificar de fondos)
        $camposNumericos = [4, 5, 6, 7]; // Índices de ID_Estado, Para_Prestar, Estado_Activo, Identificar de fondos
        $nombresNumericos = ['ID_Estado', 'Para_Prestar', 'Estado_Activo', 'Identificar de fondos'];
        
        foreach ($camposNumericos as $idx => $campoIdx) {
            $valor = trim($fila[$campoIdx]);
            if ($valor !== '' && !is_numeric($valor)) {
                $errores[] = "Fila {$numFila}: Campo '{$nombresNumericos[$idx]}' debe ser numérico (valor: '{$valor}')";
            }
        }
        
        // Validar id_activo (varchar, solo números y guiones)
        $idActivo = trim($fila[2]);
        if ($idActivo !== '' && !preg_match('/^[0-9-]+$/', $idActivo)) {
            $errores[] = "Fila {$numFila}: Campo 'id_activo' solo debe contener números y guiones (valor: '{$idActivo}')";
        }
    }
    
    return $errores;
}

/**
 * Genera un reporte HTML de los errores
 */
function generarReporteErrores($errores, $duplicados, $totalFilas) {
    $html = '<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    $html .= '<h2 style="color: #dc3545;">❌ Error en la importación</h2>';
    $html .= '<p>No se pudo completar la importación. Se encontraron los siguientes problemas:</p>';
    
    // Resumen
    $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    $html .= '<strong>Resumen:</strong><br>';
    $html .= '• Total de registros: ' . $totalFilas . '<br>';
    $html .= '• Errores encontrados: ' . count($errores) . '<br>';
    if (!empty($duplicados['en_bd']['placa']) || !empty($duplicados['en_bd']['serial'])) {
        $html .= '• Registros duplicados en BD: ' . (count($duplicados['en_bd']['placa']) + count($duplicados['en_bd']['serial'])) . '<br>';
    }
    if (!empty($duplicados['en_lote'])) {
        $html .= '• Registros duplicados dentro del lote: ' . count($duplicados['en_lote']) . '<br>';
    }
    $html .= '</div>';
    
    // Errores de estructura
    if (!empty($errores)) {
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h4>📋 Errores de estructura:</h4>';
        $html .= '<ul style="list-style: none; padding: 0;">';
        foreach ($errores as $error) {
            $html .= '<li style="padding: 5px 10px; margin: 3px 0; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 3px;">❌ ' . htmlspecialchars($error) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    
    // Duplicados en BD
    if (!empty($duplicados['en_bd']['placa']) || !empty($duplicados['en_bd']['serial'])) {
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h4>⚠️ Registros duplicados en la base de datos:</h4>';
        $html .= '<ul style="list-style: none; padding: 0;">';
        foreach ($duplicados['en_bd']['placa'] as $placa) {
            $html .= '<li style="padding: 5px 10px; margin: 3px 0; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 3px;">⚠️ Placa duplicada: <strong>' . htmlspecialchars($placa) . '</strong></li>';
        }
        foreach ($duplicados['en_bd']['serial'] as $serial) {
            $html .= '<li style="padding: 5px 10px; margin: 3px 0; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 3px;">⚠️ Serial duplicado: <strong>' . htmlspecialchars($serial) . '</strong></li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    
    // Duplicados en lote
    if (!empty($duplicados['en_lote'])) {
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h4>⚠️ Registros duplicados dentro del mismo lote:</h4>';
        $html .= '<ul style="list-style: none; padding: 0;">';
        foreach ($duplicados['en_lote'] as $dup) {
            $html .= '<li style="padding: 5px 10px; margin: 3px 0; background: #f8d7da; border-left: 3px solid #dc3545; border-radius: 3px;">❌ Fila ' . $dup['fila'] . ': Placa <strong>' . htmlspecialchars($dup['placa']) . '</strong> y Serial <strong>' . htmlspecialchars($dup['serial']) . '</strong> duplicados</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    
    // Sugerencias
    $html .= '<div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin-top: 20px;">';
    $html .= '<strong>💡 Sugerencias:</strong><br>';
    $html .= '1. Corrija los errores en el archivo CSV y vuelva a intentarlo.<br>';
    $html .= '2. Asegúrese de que no haya placas o seriales duplicados en la base de datos.<br>';
    $html .= '3. Revise que el archivo tenga exactamente 9 columnas.<br>';
    $html .= '4. Verifique que los campos numéricos no tengan texto.<br>';
    $html .= '</div>';
    
    $html .= '<div style="margin-top: 20px; text-align: center;">';
    $html .= '<button type="button" onclick="window.location.reload();" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cerrar Reporte</button>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Genera un reporte de éxito
 */
function generarReporteExito($totalInsertados) {
    $html = '<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    $html .= '<h2 style="color: #28a745;">✅ Importación exitosa</h2>';
    $html .= '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    $html .= '<p style="font-size: 18px;">Se han importado <strong>' . $totalInsertados . '</strong> registros correctamente.</p>';
    $html .= '</div>';
    $html .= '<div style="margin-top: 20px; text-align: center;">';
    $html .= '<button type="button" onclick="window.location.reload();" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cerrar Reporte</button>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// ============================================
// PROCESO PRINCIPAL DE IMPORTACIÓN
// ============================================

// Iniciar transacción
mysqli_begin_transaction($link);

try {
    // Validar que se haya subido un archivo
    if (!isset($_FILES['pcsv']) || $_FILES['pcsv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No se seleccionó ningún archivo o hubo un error al subirlo.");
    }
    
    $archivo = $_FILES['pcsv'];
    $nombreArchivo = $archivo['name'];
    $rutaTemporal = $archivo['tmp_name'];
    
    // Validar extensión
    $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
    if (strtolower($extension) !== 'csv') {
        throw new Exception("El archivo debe ser de tipo CSV.");
    }
    
    // Leer el archivo CSV
    $handle = fopen($rutaTemporal, "r");
    if (!$handle) {
        throw new Exception("No se pudo abrir el archivo.");
    }
    
    // Leer encabezados
    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false || count($headers) < 9) {
        fclose($handle);
        throw new Exception("El archivo no tiene una estructura válida. Se esperaban 9 columnas.");
    }
    
    // Leer datos
    $datos = [];
    $numFila = 2;
    while (($fila = fgetcsv($handle, 0, ',')) !== false) {
        // Verificar que la fila no esté vacía
        if (count($fila) === 1 && empty($fila[0])) {
            continue;
        }
        $datos[] = $fila;
        $numFila++;
    }
    fclose($handle);
    
    // Verificar que haya datos
    if (empty($datos)) {
        throw new Exception("El archivo no contiene datos para importar.");
    }
    
    // VALIDACIÓN 1: Estructura de los datos
    $erroresEstructura = validarEstructura($datos, $headers);
    if (!empty($erroresEstructura)) {
        $reporte = generarReporteErrores($erroresEstructura, ['en_bd' => ['placa' => [], 'serial' => []], 'en_lote' => []], count($datos));
        mysqli_rollback($link);
        redirigirConResultado('danger', 'Error en la importación', $reporte);
    }
    
    // VALIDACIÓN 2: Duplicados
    $duplicados = verificarDuplicados($link, $datos);
    $hayDuplicados = !empty($duplicados['en_bd']['placa']) || 
                     !empty($duplicados['en_bd']['serial']) || 
                     !empty($duplicados['en_lote']);
    
    if ($hayDuplicados) {
        $reporte = generarReporteErrores([], $duplicados, count($datos));
        mysqli_rollback($link);
        redirigirConResultado('danger', 'Duplicados detectados', $reporte);
    }
    
    // VALIDACIÓN 3: Verificar que los id_activo existan en la tabla t_activo
    $idActivos = array_column($datos, 2);
    $idActivosEscapados = array_map(function($id) use ($link) {
        return "'" . mysqli_real_escape_string($link, trim($id)) . "'";
    }, $idActivos);
    
    if (!empty($idActivosEscapados)) {
        $queryActivos = "SELECT id_activo FROM t_activo WHERE id_activo IN (" . implode(',', $idActivosEscapados) . ")";
        $resultActivos = mysqli_query($link, $queryActivos);
        $activosExistentes = [];
        while ($row = mysqli_fetch_assoc($resultActivos)) {
            $activosExistentes[] = $row['id_activo'];
        }
        
        $activosFaltantes = array_diff($idActivos, $activosExistentes);
        if (!empty($activosFaltantes)) {
            $erroresActivos = [];
            foreach ($activosFaltantes as $id) {
                // Encontrar la fila donde está el id_activo faltante
                foreach ($datos as $index => $fila) {
                    if ($fila[2] == $id) {
                        $erroresActivos[] = "Fila " . ($index + 2) . ": id_activo '" . $id . "' no existe en la tabla t_activo";
                        break;
                    }
                }
            }
            $reporte = generarReporteErrores($erroresActivos, ['en_bd' => ['placa' => [], 'serial' => []], 'en_lote' => []], count($datos));
            mysqli_rollback($link);
            redirigirConResultado('danger', 'Activos no encontrados', $reporte);
        }
    }
    
    // ============================================
    // PREPARAR Y EJECUTAR LA INSERCIÓN
    // ============================================
    
    $query = "INSERT INTO t_placa (
        placa, 
        serial, 
        id_activo, 
        codigo, 
        id_estado, 
        prestar, 
        activo, 
        id_fondos, 
        alias_id, 
        id_lugar
    ) VALUES ";
    
    $valores = [];
    foreach ($datos as $fila) {
        // Escapar todos los valores para evitar inyección SQL
        $placa = mysqli_real_escape_string($link, trim($fila[0]));
        $serial = mysqli_real_escape_string($link, trim($fila[1]));
        $idActivo = mysqli_real_escape_string($link, trim($fila[2]));
        $codigo = mysqli_real_escape_string($link, trim($fila[3]));
        $idEstado = mysqli_real_escape_string($link, trim($fila[4]));
        $prestar = mysqli_real_escape_string($link, trim($fila[5]));
        $activo = mysqli_real_escape_string($link, trim($fila[6]));
        $idFondos = mysqli_real_escape_string($link, trim($fila[7]));
        $aliasId = mysqli_real_escape_string($link, trim($fila[8]));
        
        $valores[] = "(
            '{$placa}',
            '{$serial}',
            '{$idActivo}',
            '{$codigo}',
            {$idEstado},
            {$prestar},
            {$activo},
            {$idFondos},
            '{$aliasId}',
            0
        )";
    }
    
    $query .= implode(", ", $valores);
    
    // Ejecutar la consulta
    if (!mysqli_query($link, $query)) {
        throw new Exception("Error al insertar los datos: " . mysqli_error($link));
    }
    
    $totalInsertados = count($datos);
    
    // Confirmar transacción
    mysqli_commit($link);

    $reporteExito = generarReporteExito($totalInsertados);
    redirigirConResultado('success', 'Importación exitosa', $reporteExito);
    
} catch (Exception $e) {
    // Rollback en caso de error
    mysqli_rollback($link);
    $mensaje = '<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
    $mensaje .= '<h2 style="color: #dc3545;">❌ Error en la importación</h2>';
    $mensaje .= '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    $mensaje .= '<p><strong>Mensaje:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    $mensaje .= '</div>';
    $mensaje .= '<div style="margin-top: 20px; text-align: center;">';
    $mensaje .= '<button type="button" onclick="window.location.reload();" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cerrar Reporte</button>';
    $mensaje .= '</div>';
    $mensaje .= '</div>';

    redirigirConResultado('danger', 'Error en la importación', $mensaje);
}

// Cerrar conexión
mysqli_close($link);
?>