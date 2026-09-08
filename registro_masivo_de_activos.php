<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$importResult = $_SESSION['import_result'] ?? null;
unset($_SESSION['import_result']);

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

require_once("conexion.php");
$link = $mysqli;
mysqli_set_charset($link, "utf8");
date_default_timezone_set('America/Costa_Rica');

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="librerias_mapa_calor/leaflet.css" />
    
    <title>Mapa de Calor - Activos Hurtados en Instituciones Educativas</title>

    <style>
        /* Estilos existentes */
        .btn-flotante-regresar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #1e2851ff;
            color: #fff;
            border: 2px solid #c8ae64ff;
            box-shadow: 0 4px 15px rgba(30, 40, 81, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .btn-flotante-regresar:hover {
            transform: scale(1.1);
            background: #c8ae64ff;
            border-color: #1e2851ff;
            color: #fff;
            box-shadow: 0 6px 25px rgba(30, 40, 81, 0.5);
        }
        
        .btn-flotante-regresar:active {
            transform: scale(0.95);
        }
        
        .btn-flotante-regresar i { 
            font-size: 30px;
            color: #fff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        .checkbox-comparar {
            transform: scale(1.3);
            cursor: pointer;
        }

        /* Nuevos estilos para validación */
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .upload-area:hover {
            border-color: #1e2851ff;
            background-color: #e9ecef;
        }

        .upload-area.dragover {
            border-color: #c8ae64ff;
            background-color: #f8f0e0;
            transform: scale(1.02);
        }

        .upload-area.has-file {
            border-color: #28a745;
            background-color: #f0f8f0;
        }

        .upload-area.error {
            border-color: #dc3545;
            background-color: #f8f0f0;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* Barra de progreso de análisis */
        .analysis-progress {
            display: none;
            margin-top: 20px;
        }

        .analysis-progress.active {
            display: block;
        }

        .progress-bar-animated {
            transition: width 0.5s ease;
        }

        /* Tabla de resultados */
        .analysis-results {
            display: none;
            margin-top: 20px;
            max-height: 500px;
            overflow-y: auto;
        }

        .analysis-results.active {
            display: block;
        }

        .result-item {
            padding: 10px;
            border-left: 4px solid #28a745;
            margin-bottom: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .result-item.warning {
            border-left-color: #ffc107;
            background-color: #fff8e6;
        }

        .result-item.danger {
            border-left-color: #dc3545;
            background-color: #ffe6e6;
        }

        .result-item.success {
            border-left-color: #28a745;
            background-color: #e6ffe6;
        }

        .result-item .badge {
            margin-right: 8px;
        }

        /* Detalles de columnas */
        .column-detail {
            font-size: 0.9rem;
            padding: 4px 8px;
            background-color: #fff;
            border-radius: 4px;
            margin: 2px 0;
        }

        .column-detail .col-name {
            font-weight: bold;
            color: #1e2851ff;
        }

        .column-detail .col-issues {
            color: #dc3545;
        }

        .column-detail .col-issues.warning {
            color: #ffc107;
        }

        /* Spinner de carga */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1e2851ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Indicador de archivo válido */
        .file-valid-icon {
            display: none;
            color: #28a745;
            font-size: 1.2rem;
        }

        .file-valid-icon.show {
            display: inline;
        }

        .btn-import:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Estilos para el resumen de columnas */
        .column-summary {
            font-size: 0.85rem;
        }
        
        .column-summary .col-header {
            font-weight: 600;
            color: #1e2851ff;
        }
        
        .issue-tag {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin: 2px;
            display: inline-block;
        }
        
        .issue-tag.warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .issue-tag.danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .issue-tag.success {
            background-color: #d4edda;
            color: #155724;
        }

        /* Tabla de muestra de datos */
        .sample-data-table {
            font-size: 0.85rem;
        }
        
        .sample-data-table td {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .highlight-zero {
            background-color: #fff3cd !important;
        }
        
        .field-type-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .field-type-badge.varchar {
            background-color: #d4edda;
            color: #155724;
        }
        
        .field-type-badge.numeric {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .field-type-badge.text {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <?php if ($importResult): ?>
        <div class="container mt-4">
            <div class="alert alert-<?= $importResult['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <h5 class="alert-heading mb-2"><?= htmlspecialchars($importResult['titulo']) ?></h5>
                <?= $importResult['html'] ?? '' ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- CUERPO PRINCIPAL -->
    <!-- ============================================ -->
    <div class="container py-4">
        
        <a href="navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=2&modulo_id=11" class="btn-flotante-regresar" title="Volver al panel general">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
        <!-- Formulario de importación -->
        <div class="col-lg-10 mx-auto">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> Cargar archivo CSV - Activos</h4>
                    <small class="text-light">Plantilla oficial de importación de activos</small>
                </div>
                <div class="card-body">
                    <form action="importar_lotes_masivos.php" method="post" enctype="multipart/form-data" id="importForm">
                        <div class="mb-4">
                            <label for="pcsv" class="form-label fw-bold">Seleccionar archivo CSV</label>
                            
                            <!-- Área de carga mejorada -->
                            <div class="upload-area" id="uploadArea" onclick="document.getElementById('pcsv').click()">
                                <i class="bi bi-file-earmark-spreadsheet fs-1 text-primary" id="uploadIcon"></i>
                                <p class="mt-2 mb-1" id="uploadText">Arrastra tu archivo aquí o haz clic para seleccionar</p>
                                <p class="small text-muted" id="uploadSubtext">Formato CSV (Máx. 10MB)</p>
                                <input type="file" class="d-none" name="pcsv" id="pcsv" accept=".csv" required>
                                <div id="fileName" class="fw-bold text-success mt-2"></div>
                                <div id="fileSize" class="small text-muted"></div>
                                <i class="bi bi-check-circle-fill file-valid-icon" id="fileValidIcon"></i>
                            </div>

                            <!-- Estructura esperada con tipos de campo -->
                            <div class="alert alert-secondary mt-2">
                                <i class="bi bi-info-circle"></i>
                                <strong>Estructura esperada:</strong>
                                <div class="small mt-1">
                                    <span class="badge bg-primary">Numero de Placa</span>
                                    <span class="badge bg-primary">Numero de Serial</span>
                                    <span class="badge bg-primary">id_activo</span>
                                    <span class="badge bg-primary">Codigo Presupuestario</span>
                                    <span class="badge bg-primary">ID_Estado</span>
                                    <span class="badge bg-primary">Para_Prestar</span>
                                    <span class="badge bg-primary">Estado_Activo</span>
                                    <span class="badge bg-primary">Identificar de fondos</span>
                                    <span class="badge bg-primary">Alias</span>
                                </div>
                                <div class="small mt-1">
                                    <span class="field-type-badge varchar">id_activo: VARCHAR (permite ceros a la izquierda)</span>
                                    <span class="field-type-badge numeric">ID_Estado, Para_Prestar, Estado_Activo, Identificar de fondos: NUMÉRICO</span>
                                    <span class="field-type-badge text">Numero de Placa, Numero de Serial, Alias: TEXTO</span>
                                </div>
                            </div>

                            <!-- Barra de progreso de análisis -->
                            <div class="analysis-progress" id="analysisProgress">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold" id="analysisStatus">Analizando estructura del archivo...</span>
                                    <span id="analysisPercentage" class="fw-bold">0%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" 
                                        id="analysisBar" 
                                        role="progressbar" 
                                        style="width: 0%"
                                        aria-valuenow="0" 
                                        aria-valuemin="0" 
                                        aria-valuemax="100">
                                        <span id="progressLabel">0%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Resultados del análisis -->
                            <div class="analysis-results" id="analysisResults">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="bi bi-clipboard-data"></i> Resultados del análisis</h6>
                                        <span class="badge bg-secondary" id="resultBadge">Pendiente</span>
                                    </div>
                                    <div class="card-body" id="resultContent">
                                        <!-- Resultados dinámicos -->
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i> Utilice la plantilla oficial proporcionada por el Grupo Desarrollador de TecnoPresta.
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div id="validationStatus">
                                <!-- Espacio para mensajes de validación -->
                            </div>
                            <button type="submit" class="btn btn-primary btn-import" id="importButton" disabled>
                                <i class="bi bi-upload"></i> Importar Lote
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <?php include 'partials/footer.php'; ?>

    <!-- ============================================ -->
    <!-- SCRIPTS -->
    <!-- ============================================ -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.7.1.min.js"></script>

    <script>
    $(document).ready(function() {
        const $fileInput = $('#pcsv');
        const $uploadArea = $('#uploadArea');
        const $fileName = $('#fileName');
        const $fileSize = $('#fileSize');
        const $importButton = $('#importButton');
        const $analysisProgress = $('#analysisProgress');
        const $analysisResults = $('#analysisResults');
        const $resultContent = $('#resultContent');
        const $resultBadge = $('#resultBadge');
        const $analysisBar = $('#analysisBar');
        const $analysisStatus = $('#analysisStatus');
        const $analysisPercentage = $('#analysisPercentage');

        // Variables para almacenar datos del análisis
        let fileAnalysis = null;
        let isFileValid = false;

        // Encabezados esperados de la plantilla
        const EXPECTED_HEADERS = [
            'Numero de Placa',
            'Numero de Serial',
            'id_activo',
            'Codigo Presupuestario',
            'ID_Estado',
            'Para_Prestar',
            'Estado_Activo',
            'Identificar de fondos',
            'Alias'
        ];

        // Definición de tipos de campos
        const FIELD_TYPES = {
            'id_activo': 'VARCHAR',
            'Codigo Presupuestario': 'VARCHAR',
            'ID_Estado': 'NUMERIC',
            'Para_Prestar': 'NUMERIC',
            'Estado_Activo': 'NUMERIC',
            'Identificar de fondos': 'NUMERIC',
            'Numero de Placa': 'TEXT',
            'Numero de Serial': 'TEXT',
            'Alias': 'TEXT'
        };

        // Evento de arrastrar y soltar
        $uploadArea.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        $uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        $uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $fileInput[0].files = files;
                $fileInput.trigger('change');
            }
        });

        // Evento de cambio de archivo
        $fileInput.on('change', function() {
            const file = this.files[0];
            if (!file) {
                resetUploadArea();
                return;
            }

            // Validar tipo de archivo
            const validTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!validTypes.includes(file.type) && fileExtension !== 'csv') {
                showError('El archivo debe ser de tipo CSV');
                resetUploadArea();
                return;
            }

            // Validar tamaño (10MB)
            if (file.size > 10 * 1024 * 1024) {
                showError('El archivo excede el tamaño máximo permitido (10MB)');
                resetUploadArea();
                return;
            }

            // Mostrar información del archivo
            $uploadArea.addClass('has-file').removeClass('error');
            $fileName.text(`📄 ${file.name}`);
            $fileSize.text(`Tamaño: ${(file.size / 1024).toFixed(2)} KB`);
            $('#uploadText').text('Archivo cargado correctamente');
            $('#uploadSubtext').text('Analizando estructura...');
            $('#uploadIcon').removeClass('text-primary').addClass('text-success');
            $('#fileValidIcon').addClass('show');

            // Iniciar análisis del archivo
            analyzeFile(file);
        });

        // Función para resetear el área de carga
        function resetUploadArea() {
            $uploadArea.removeClass('has-file error');
            $fileName.text('');
            $fileSize.text('');
            $('#uploadText').text('Arrastra tu archivo aquí o haz clic para seleccionar');
            $('#uploadSubtext').text('Formato CSV (Máx. 10MB)');
            $('#uploadIcon').removeClass('text-success').addClass('text-primary');
            $('#fileValidIcon').removeClass('show');
            $fileInput.val('');
            $importButton.prop('disabled', true);
            $analysisProgress.removeClass('active');
            $analysisResults.removeClass('active');
            fileAnalysis = null;
            isFileValid = false;
        }

        // Función para mostrar errores
        function showError(message) {
            $uploadArea.addClass('error');
            $('#uploadText').text('⚠️ ' + message);
            $('#uploadSubtext').text('Por favor, selecciona un archivo válido');
            $('#uploadIcon').removeClass('text-primary text-success').addClass('text-danger');
            $('#fileValidIcon').removeClass('show');
            $fileName.text('');
            $fileSize.text('');
            $fileInput.val('');
            $importButton.prop('disabled', true);
            $analysisProgress.removeClass('active');
            $analysisResults.removeClass('active');
            
            // Remover clase error después de 3 segundos
            setTimeout(() => {
                $uploadArea.removeClass('error');
                $('#uploadIcon').removeClass('text-danger').addClass('text-primary');
            }, 3000);
        }

        // ============================================
        // FUNCIONES DE ANÁLISIS - VERSIÓN FINAL
        // ============================================

        // Función principal de análisis
        function analyzeFile(file) {
            $analysisProgress.addClass('active');
            $analysisResults.removeClass('active');
            updateProgress(0, 'Iniciando análisis...');
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const content = e.target.result;
                    const lines = content.split('\n').filter(line => line.trim() !== '');
                    
                    if (lines.length < 2) {
                        showError('El archivo está vacío o no contiene datos');
                        $analysisProgress.removeClass('active');
                        return;
                    }

                    // Paso 1: DETECTAR SEPARADOR
                    updateProgress(15, 'Detectando separador...');
                    const separator = detectBestSeparator(lines);
                    
                    // Paso 2: PARSEAR ENCABEZADOS
                    updateProgress(30, 'Analizando encabezados...');
                    const headers = parseCSVLineRobust(lines[0], separator);
                    const headerIssues = validateHeaders(headers);
                    
                    // Paso 3: Analizar datos
                    updateProgress(50, 'Analizando datos de activos...');
                    const dataRows = lines.slice(1);
                    const analysis = analyzeAssetDataFinal(dataRows, headers, separator);
                    
                    // Paso 4: Generar resultados
                    updateProgress(80, 'Generando informe detallado...');
                    const result = generateAssetReportFinal(headers, analysis, dataRows.length, headerIssues);
                    
                    // Guardar para referencia
                    fileAnalysis = result;
                    
                    // Mostrar resultados
                    setTimeout(() => {
                        displayAssetResultsFinal(result);
                        updateProgress(100, 'Análisis completado');
                        
                        // Habilitar botón si el archivo es válido
                        if (result.isValid) {
                            isFileValid = true;
                            $importButton.prop('disabled', false);
                            $resultBadge.text('✅ Válido').removeClass('bg-secondary').addClass('bg-success');
                            $('#uploadSubtext').text('✅ Archivo listo para importar');
                        } else {
                            $resultBadge.text('❌ Problemas detectados').removeClass('bg-secondary').addClass('bg-danger');
                            $('#uploadSubtext').text('⚠️ Corrige los problemas antes de importar');
                        }
                        
                        setTimeout(() => {
                            $analysisProgress.removeClass('active');
                        }, 2000);
                        
                    }, 500);
                    
                } catch (error) {
                    console.error('Error analizando archivo:', error);
                    showError('Error al analizar el archivo: ' + error.message);
                    $analysisProgress.removeClass('active');
                }
            };
            
            reader.onerror = function() {
                showError('Error al leer el archivo');
                $analysisProgress.removeClass('active');
            };
            
            reader.readAsText(file, 'UTF-8');
        }

        // Función mejorada para detectar el separador
        function detectBestSeparator(lines) {
            const sampleLines = lines.slice(0, Math.min(5, lines.length));
            const separators = [',', ';', '\t', '|'];
            
            let bestSeparator = ',';
            let bestScore = 0;
            
            separators.forEach(sep => {
                let totalScore = 0;
                let consistentColumns = true;
                let columnCount = 0;
                let linesProcessed = 0;
                
                sampleLines.forEach((line, index) => {
                    if (line.trim() === '') return;
                    
                    const count = (line.match(new RegExp(sep, 'g')) || []).length;
                    
                    if (count > 0) {
                        const parts = line.split(sep);
                        
                        if (index === 0) {
                            columnCount = parts.length;
                        } else if (parts.length !== columnCount) {
                            consistentColumns = false;
                        }
                        
                        totalScore += count + (parts.length >= 9 ? 20 : 0);
                        
                        if (parts.length === 9) {
                            totalScore += 50;
                        }
                        
                        linesProcessed++;
                    }
                });
                
                if (consistentColumns && linesProcessed > 1) {
                    totalScore += 30;
                }
                
                if (totalScore > bestScore) {
                    bestScore = totalScore;
                    bestSeparator = sep;
                }
            });
            
            if (bestScore === 0) {
                const firstLine = lines[0];
                separators.forEach(sep => {
                    const count = (firstLine.match(new RegExp(sep, 'g')) || []).length;
                    if (count > 0 && count > bestScore) {
                        bestScore = count;
                        bestSeparator = sep;
                    }
                });
            }
            
            return bestSeparator;
        }

        // Función robusta para parsear CSV
        function parseCSVLineRobust(line, separator) {
            if (!line || line.trim() === '') {
                return [];
            }
            
            if (separator === ',') {
                return parseCSVWithComma(line);
            }
            
            return line.split(separator).map(p => p.trim());
        }

        // Parser específico para CSV con coma
        function parseCSVWithComma(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            let i = 0;
            
            while (i < line.length) {
                const char = line[i];
                
                if (char === '"') {
                    inQuotes = !inQuotes;
                    current += char;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
                i++;
            }
            
            result.push(current.trim());
            
            return result.map(field => field.replace(/^"|"$/g, '').trim());
        }

        // Función para validar encabezados
        function validateHeaders(headers) {
            const issues = [];
            const expectedHeaders = EXPECTED_HEADERS;
            
            if (!headers || headers.length < 2) {
                issues.push('No se detectaron encabezados válidos en el archivo');
                return issues;
            }
            
            if (headers.length !== expectedHeaders.length) {
                issues.push(`Número incorrecto de columnas: ${headers.length} (se esperaban ${expectedHeaders.length})`);
            }
            
            expectedHeaders.forEach((expected, index) => {
                if (index < headers.length) {
                    const actual = headers[index];
                    const normalizedExpected = expected.toLowerCase().replace(/\s+/g, ' ');
                    const normalizedActual = actual.toLowerCase().replace(/\s+/g, ' ');
                    
                    if (normalizedActual !== normalizedExpected) {
                        issues.push(`Columna ${index + 1}: Se esperaba "${expected}" pero se encontró "${actual}"`);
                    }
                }
            });
            
            return issues;
        }

        // ============================================
        // ANÁLISIS FINAL - id_activo como VARCHAR
        // ============================================

        function analyzeAssetDataFinal(rows, headers, separator) {
            const analysis = {
                totalRows: rows.length,
                processedRows: 0,
                columns: headers.map(h => ({
                    name: h || 'Columna',
                    fieldType: FIELD_TYPES[h] || 'TEXT',
                    issues: [],
                    sampleValues: [],
                    hasLeadingZeros: false,
                    hasSpecialChars: false,
                    hasEmptyValues: false,
                    numericCount: 0,
                    totalCount: 0,
                    problematicRows: []
                })),
                issues: {
                    separatorWarning: [],
                    emptyRows: 0,
                    malformedRows: 0,
                    invalidData: []
                },
                rows: []
            };

            if (!headers || headers.length < 2) {
                return analysis;
            }

            // Definir tipos de campos
            const NUMERIC_FIELDS = ['ID_Estado', 'Para_Prestar', 'Estado_Activo', 'Identificar de fondos'];
            const VARCHAR_FIELDS = ['id_activo', 'Codigo Presupuestario'];
            const TEXT_FIELDS = ['Numero de Placa', 'Numero de Serial', 'Alias'];

            rows.forEach((row, rowIndex) => {
                if (row.trim() === '') {
                    analysis.issues.emptyRows++;
                    return;
                }
                
                let columns = parseCSVLineRobust(row, separator);
                
                if (columns.length < headers.length && separator !== ',') {
                    const testColumns = parseCSVWithComma(row);
                    if (testColumns.length > columns.length) {
                        columns = testColumns;
                    }
                }
                
                if (columns.length !== headers.length) {
                    analysis.issues.malformedRows++;
                    analysis.issues.invalidData.push(
                        `Fila ${rowIndex + 2}: ${columns.length} columnas (se esperaban ${headers.length})`
                    );
                    return;
                }
                
                analysis.processedRows++;
                analysis.rows.push(row);
                
                headers.forEach((header, colIndex) => {
                    const value = columns[colIndex] || '';
                    const colInfo = analysis.columns[colIndex];
                    if (!colInfo) return;
                    
                    colInfo.totalCount++;
                    
                    if (colInfo.sampleValues.length < 5 && value.trim() !== '') {
                        colInfo.sampleValues.push(value);
                    }
                    
                    const headerClean = (header || '').trim();
                    
                    // 1. VARCHAR - id_activo y Codigo Presupuestario
                    if (VARCHAR_FIELDS.some(f => headerClean.includes(f))) {
                        // Solo verificar ceros a la izquierda como advertencia
                        if (/^\d+$/.test(value) && value.length > 1 && value[0] === '0') {
                            colInfo.hasLeadingZeros = true;
                            colInfo.problematicRows.push(rowIndex + 2);
                        }
                        if (value.trim() === '') {
                            colInfo.hasEmptyValues = true;
                        }
                        if (/[^0-9-]/.test(value) && value.trim() !== '') {
                            colInfo.hasSpecialChars = true;
                        }
                    }
                    // 2. NUMERIC
                    else if (NUMERIC_FIELDS.includes(headerClean)) {
                        if (/^\d+$/.test(value)) {
                            colInfo.numericCount++;
                        }
                        if (value.trim() === '') {
                            colInfo.hasEmptyValues = true;
                        }
                    }
                    // 3. TEXT
                    else if (TEXT_FIELDS.includes(headerClean)) {
                        if (/[^a-zA-Z0-9\s\-_]/.test(value) && value.trim() !== '') {
                            colInfo.hasSpecialChars = true;
                        }
                        if (value.trim() === '') {
                            colInfo.hasEmptyValues = true;
                        }
                    }
                });
            });

            // Generar issues
            analysis.columns.forEach((col, index) => {
                if (!col) return;
                
                const headerClean = (col.name || '').trim();
                
                // Ceros a la izquierda - SOLO ADVERTENCIA
                if (col.hasLeadingZeros && col.problematicRows.length > 0) {
                    col.issues.push(
                        `⚠️ Ceros a la izquierda en ${col.problematicRows.length} filas (ej: filas ${col.problematicRows.slice(0, 3).join(', ')}${col.problematicRows.length > 3 ? '...' : ''})`
                    );
                }
                
                if (col.hasSpecialChars) {
                    col.issues.push('⚠️ Contiene caracteres especiales no esperados');
                }
                
                if (col.hasEmptyValues) {
                    const criticalFields = ['id_activo', 'Numero de Placa', 'Numero de Serial'];
                    if (criticalFields.some(f => headerClean.toLowerCase().includes(f.toLowerCase()))) {
                        col.issues.push('❌ Contiene valores vacíos (campo obligatorio)');
                    } else {
                        col.issues.push('⚠️ Contiene valores vacíos');
                    }
                }
                
                // Validar campos numéricos
                const isNumericField = NUMERIC_FIELDS.some(f => headerClean === f);
                if (isNumericField && col.totalCount > 0) {
                    const numericPercentage = (col.numericCount / col.totalCount) * 100;
                    if (numericPercentage < 100) {
                        col.issues.push(`❌ ${Math.round(100 - numericPercentage)}% de valores NO son numéricos`);
                    }
                }
            });

            if (separator !== ',') {
                analysis.issues.separatorWarning.push(
                    `Separador detectado: "${separator}" (se recomienda coma ",")`
                );
            }

            return analysis;
        }

        // Función para generar reporte final
        function generateAssetReportFinal(headers, analysis, totalRows, headerIssues) {
            const result = {
                isValid: true,
                headers: headers || [],
                totalRows: totalRows || 0,
                processedRows: (analysis && analysis.processedRows) || 0,
                analysis: analysis || { columns: [], issues: {}, rows: [] },
                summary: {
                    totalIssues: 0,
                    warnings: [],
                    errors: [],
                    criticalErrors: []
                }
            };

            if (!headers || headers.length < 2) {
                result.isValid = false;
                result.summary.criticalErrors.push('El archivo no tiene una estructura de columnas válida');
                return result;
            }

            if (headerIssues && headerIssues.length > 0) {
                headerIssues.forEach(issue => {
                    result.summary.errors.push(`ENCABEZADO: ${issue}`);
                    result.isValid = false;
                });
            }

            if (!result.processedRows || result.processedRows === 0) {
                result.isValid = false;
                result.summary.criticalErrors.push('No se pudieron procesar datos válidos en el archivo');
                return result;
            }

            if (analysis && analysis.issues && analysis.issues.malformedRows > 0) {
                result.summary.errors.push(
                    `${analysis.issues.malformedRows} filas tienen número incorrecto de columnas`
                );
                result.isValid = false;
            }

            if (analysis && analysis.issues && analysis.issues.emptyRows > 0) {
                result.summary.warnings.push(
                    `${analysis.issues.emptyRows} filas vacías encontradas (serán ignoradas)`
                );
            }

            if (analysis && analysis.columns && analysis.columns.length > 0) {
                analysis.columns.forEach((col, index) => {
                    if (!col || !col.issues) return;
                    
                    if (col.issues.length > 0) {
                        const headerClean = (col.name || '').trim();
                        
                        // Campos críticos
                        const criticalFields = ['id_activo', 'Numero de Placa', 'Numero de Serial'];
                        const isCritical = criticalFields.some(f => 
                            headerClean.toLowerCase().includes(f.toLowerCase())
                        );
                        
                        // Campos numéricos estrictos
                        const numericFields = ['ID_Estado', 'Para_Prestar', 'Estado_Activo', 'Identificar de fondos'];
                        const isNumeric = numericFields.some(f => headerClean === f);
                        
                        col.issues.forEach(issue => {
                            const fullIssue = `Columna "${col.name || index}": ${issue}`;
                            
                            // Valores vacíos en campos críticos -> ERROR
                            if (isCritical && issue.includes('valores vacíos')) {
                                result.summary.errors.push(fullIssue);
                                result.isValid = false;
                            } 
                            // Valores no numéricos en campos numéricos -> ERROR
                            else if (isNumeric && issue.includes('NO son numéricos')) {
                                result.summary.errors.push(fullIssue);
                                result.isValid = false;
                            }
                            // Ceros a la izquierda -> SOLO ADVERTENCIA
                            else if (isCritical && issue.includes('Ceros a la izquierda')) {
                                result.summary.warnings.push(fullIssue);
                            }
                            // Otros problemas en campos críticos -> ERROR
                            else if (isCritical) {
                                result.summary.errors.push(fullIssue);
                                result.isValid = false;
                            } 
                            // Campos no críticos -> ADVERTENCIA
                            else {
                                result.summary.warnings.push(fullIssue);
                            }
                            result.summary.totalIssues++;
                        });
                    }
                });
            }

            if (analysis && analysis.issues && analysis.issues.separatorWarning && analysis.issues.separatorWarning.length > 0) {
                result.summary.warnings.push(analysis.issues.separatorWarning[0]);
            }

            if (analysis && analysis.issues && analysis.issues.invalidData && analysis.issues.invalidData.length > 0) {
                analysis.issues.invalidData.forEach(issue => {
                    result.summary.errors.push(issue);
                    result.isValid = false;
                });
            }

            if (result.summary.criticalErrors.length > 0) {
                result.isValid = false;
            }

            // Si solo hay advertencias, el archivo es válido
            if (result.summary.errors.length === 0 && result.summary.criticalErrors.length === 0) {
                result.isValid = true;
            }

            return result;
        }

        // Función para mostrar resultados final
        function displayAssetResultsFinal(result) {
            $analysisResults.addClass('active');
            $resultContent.empty();

            if (!result || !result.headers || result.headers.length < 2) {
                $resultContent.html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Error crítico:</strong> No se pudo analizar la estructura del archivo.
                        <div class="mt-2">Asegúrate de que el archivo sea un CSV válido con el formato correcto.</div>
                    </div>
                `);
                return;
            }

            // Resumen general
            const summaryHtml = `
                <div class="alert ${result.isValid ? 'alert-success' : 'alert-danger'}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi ${result.isValid ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i>
                            <strong>${result.isValid ? 'Archivo válido' : 'Problemas detectados'}</strong>
                        </div>
                        <div>
                            <span class="badge bg-info">${result.processedRows || 0} registros válidos</span>
                            <span class="badge bg-secondary">${result.headers.length} columnas</span>
                            ${result.totalRows - result.processedRows > 0 ? 
                                `<span class="badge bg-warning">${result.totalRows - result.processedRows} filas con problemas</span>` : 
                                ''}
                        </div>
                    </div>
                    ${!result.isValid ? '<div class="mt-2"><strong>⚠️ El archivo tiene problemas que deben corregirse antes de importar.</strong></div>' : 
                    result.summary.warnings.length > 0 ? '<div class="mt-2 text-warning">⚠️ Hay advertencias que revisar, pero el archivo puede importarse.</div>' : 
                    '<div class="mt-2 text-success">✅ El archivo está listo para importar.</div>'}
                </div>
            `;
            $resultContent.append(summaryHtml);

            // Resumen de problemas
            const totalIssues = (result.summary.criticalErrors?.length || 0) + 
                            (result.summary.errors?.length || 0) + 
                            (result.summary.warnings?.length || 0);
            
            if (totalIssues > 0) {
                const issuesHtml = `
                    <div class="mt-3">
                        <h6><i class="bi bi-list-check"></i> Detalle de problemas (${totalIssues})</h6>
                        <div class="small">
                            ${result.summary.criticalErrors && result.summary.criticalErrors.length > 0 ? `
                                <div class="text-danger mb-2">
                                    <strong><i class="bi bi-x-circle"></i> Errores críticos:</strong><br>
                                    ${result.summary.criticalErrors.map(e => `• ${e}`).join('<br>')}
                                </div>
                            ` : ''}
                            ${result.summary.errors && result.summary.errors.length > 0 ? `
                                <div class="text-danger mb-2">
                                    <strong><i class="bi bi-exclamation-circle"></i> Errores:</strong><br>
                                    ${result.summary.errors.map(e => `• ${e}`).join('<br>')}
                                </div>
                            ` : ''}
                            ${result.summary.warnings && result.summary.warnings.length > 0 ? `
                                <div class="text-warning mb-2">
                                    <strong><i class="bi bi-exclamation-triangle"></i> Advertencias:</strong><br>
                                    ${result.summary.warnings.map(w => `• ${w}`).join('<br>')}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                $resultContent.append(issuesHtml);
            }

            // Detalle por columna con tipo de campo
            if (result.headers && result.headers.length > 0 && result.analysis && result.analysis.columns) {
                const columnsHtml = `
                    <div class="mt-3">
                        <h6><i class="bi bi-table"></i> Análisis por columna</h6>
                        <div class="row small column-summary">
                            ${result.analysis.columns.map((col, index) => {
                                if (!col) return '';
                                
                                const colName = col.name || `Columna ${index + 1}`;
                                const fieldType = col.fieldType || 'TEXT';
                                const isCritical = ['id_activo', 'Numero de Placa', 'Numero de Serial'].some(f => 
                                    colName.toLowerCase().includes(f.toLowerCase())
                                );
                                const hasIssues = col.issues && col.issues.length > 0;
                                const status = !hasIssues ? 'success' : 
                                            (isCritical ? 'danger' : 'warning');
                                const icon = !hasIssues ? '✅' : 
                                            (isCritical ? '❌' : '⚠️');
                                
                                const typeColor = fieldType === 'VARCHAR' ? 'varchar' : 
                                                (fieldType === 'NUMERIC' ? 'numeric' : 'text');
                                
                                return `
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="column-detail border p-2 rounded border-${status}">
                                            <div class="col-name">
                                                ${icon} ${colName}
                                                <span class="field-type-badge ${typeColor}">${fieldType}</span>
                                                ${isCritical ? '<span class="badge bg-danger ms-1">Crítico</span>' : ''}
                                            </div>
                                            <div class="text-muted">
                                                ${col.totalCount || 0} valores • ${col.numericCount || 0} numéricos
                                            </div>
                                            ${hasIssues ? `
                                                <div class="col-issues small text-${status} mt-1">
                                                    ${col.issues.map(issue => `• ${issue}`).join('<br>')}
                                                </div>
                                            ` : `
                                                <div class="text-success small mt-1">✅ Sin problemas detectados</div>
                                            `}
                                            ${col.sampleValues && col.sampleValues.length > 0 ? `
                                                <div class="small text-muted mt-1">
                                                    <strong>Muestra:</strong> ${col.sampleValues.join(', ')}
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
                $resultContent.append(columnsHtml);
            }

            // Muestra de datos
            if (result.analysis && result.analysis.rows && result.analysis.rows.length > 0) {
                const sampleRows = result.analysis.rows.slice(0, 5);
                const separator = detectBestSeparator(sampleRows);
                const parsedRows = sampleRows.map(row => parseCSVLineRobust(row, separator));
                
                if (parsedRows.length > 0 && parsedRows[0].length === result.headers.length) {
                    const sampleHtml = `
                        <div class="mt-3">
                            <h6><i class="bi bi-eye"></i> Muestra de datos (${parsedRows.length} primeros registros válidos)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped sample-data-table">
                                    <thead>
                                        <tr>
                                            ${result.headers.map(h => `<th>${h || 'Columna'}</th>`).join('')}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${parsedRows.map((row, idx) => `
                                            <tr>
                                                ${row.map((cell, colIdx) => {
                                                    const header = result.headers[colIdx] || '';
                                                    const isIdActivo = header && header.toLowerCase().includes('id_activo');
                                                    const hasLeadingZero = isIdActivo && /^\d+$/.test(cell) && cell.length > 1 && cell[0] === '0';
                                                    return `<td class="${hasLeadingZero ? 'highlight-zero' : ''}" title="${cell}">${cell || '—'}</td>`;
                                                }).join('')}
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            ${parsedRows.some(row => row.some(cell => /^\d+$/.test(cell) && cell.length > 1 && cell[0] === '0')) ? 
                                '<div class="small text-warning">⚠️ Los valores resaltados en amarillo tienen ceros a la izquierda. Esto es normal para campos VARCHAR como id_activo.</div>' : 
                                ''}
                        </div>
                    `;
                    $resultContent.append(sampleHtml);
                }
            }
        }

        // Función para actualizar barra de progreso
        function updateProgress(percent, status) {
            $analysisBar.css('width', percent + '%');
            $analysisBar.attr('aria-valuenow', percent);
            $analysisPercentage.text(percent + '%');
            $('#progressLabel').text(percent + '%');
            $analysisStatus.text(status);
            
            if (percent < 30) {
                $analysisBar.removeClass('bg-success bg-warning').addClass('bg-info');
            } else if (percent < 70) {
                $analysisBar.removeClass('bg-info bg-success').addClass('bg-warning');
            } else {
                $analysisBar.removeClass('bg-info bg-warning').addClass('bg-success');
            }
        }

        // Prevenir envío del formulario si el archivo no es válido
        $('#importForm').on('submit', function(e) {
            if (!isFileValid) {
                e.preventDefault();
                showError('El archivo no es válido. Por favor, corrige los problemas detectados.');
                return false;
            }
            
            if (!confirm('¿Estás seguro de que deseas importar este lote?\nSe importarán ' + 
                (fileAnalysis ? fileAnalysis.processedRows : 0) + ' registros válidos.')) {
                e.preventDefault();
                return false;
            }
        });

        // Prevenir acción por defecto en drag and drop
        $(document).on('dragover drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    </script>
</body>
</html>