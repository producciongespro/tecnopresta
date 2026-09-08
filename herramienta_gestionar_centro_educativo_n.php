<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once("conexion.php");
$link = $mysqli;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Configurar conexión y charset
if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    die("Error cargando el conjunto de caracteres UTF-8: " . mysqli_error($link));
}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// ==== CONSTRUIR RUTA DE REGRESO =====
$ruta_regreso = 'navegar.php?ruta=formulario_menu_principal.php';

if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $_SESSION['subsistema_id'] = intval($_GET['subsistema_id']);
    $_SESSION['modulo_id']     = intval($_GET['modulo_id']);
}

if (isset($_SESSION['subsistema_id'], $_SESSION['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . $_SESSION['subsistema_id']
    . '&modulo_id=' . $_SESSION['modulo_id'];
}

// === Bloquear acceso directo ===
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

// ==== URL BASE: todas las acciones internas pasan por el navegador central ====
$urlBase = 'navegar.php?ruta=herramienta_gestionar_centro_educativo_n.php';

// ==== PHPSPREADSHEET: cargar solo si está instalado (evita fatal si no existe vendor) ====
$excelDisponible = false;
$rutasAutoload = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($rutasAutoload as $rutaAutoload) {
    if (file_exists($rutaAutoload)) {
        require_once $rutaAutoload;
        $excelDisponible = true;
        break;
    }
}

// Inicializar variables de mensaje
$mensaje = '';
$tipo = ''; // success | error | warning

// PROCESAR EXPORTACIÓN A EXCEL
if (isset($_GET['exportar_excel'])) {
    if (!$excelDisponible) {
        $mensaje = 'La exportación a Excel no está disponible: la librería PhpSpreadsheet no se encuentra instalada en el servidor.';
        $tipo = 'warning';
    } else {
        // Consulta para obtener los datos
        $sql = "SELECT * FROM t_instituciones ORDER BY codigo";
        $result = $link->query($sql);

        if (!$result) {
            $mensaje = 'No fue posible obtener los datos para exportar: ' . $link->error;
            $tipo = 'error';
        } else {
            // Crear nuevo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Establecer propiedades del documento
            $spreadsheet->getProperties()
                ->setCreator("Sistema Tecnopresta")
                ->setTitle("Listado de Instituciones")
                ->setSubject("Instituciones Educativas");

            // Estilos para el encabezado
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '337AB7']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];

            // Estilos para las celdas
            $cellStyle = [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];

            // Títulos de columnas
            $sheet->setCellValue('A1', 'ID');
            $sheet->setCellValue('B1', 'CÓDIGO');
            $sheet->setCellValue('C1', 'INSTITUCIÓN');
            $sheet->setCellValue('D1', 'CÓDIGO SABER');
            $sheet->setCellValue('E1', 'ESTADO');
            $sheet->setCellValue('F1', 'FECHA REGISTRO');

            // Aplicar estilo al encabezado
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            // Autoajustar ancho de columnas
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Llenar datos
            $row = 2;
            while ($data = $result->fetch_assoc()) {
                $sheet->setCellValue('A' . $row, $data['id_ins']);
                $sheet->setCellValue('B' . $row, $data['codigo']);
                $sheet->setCellValue('C' . $row, $data['institucion']);
                $sheet->setCellValue('D' . $row, $data['cod_saber']);
                $sheet->setCellValue('E' . $row, $data['activo'] == 1 ? 'ACTIVO' : 'INACTIVO');
                $sheet->setCellValue('F' . $row, date('Y-m-d H:i:s'));

                // Aplicar estilo a la fila
                $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($cellStyle);

                $row++;
            }

            // Centrar algunas columnas
            $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B:B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E:E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Congelar la primera fila (encabezados)
            $sheet->freezePane('A2');

            // Configurar la respuesta para descargar el archivo
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="instituciones_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        }
    }
}

// Procesar operaciones
$accion = $_POST['accion'] ?? '';
$edicion = false;
$datos_edicion = null;

if ($accion === 'insertar' || $accion === 'editar') {
    $codigo = trim((string)($_POST['codigo'] ?? ''));
    $institucion = trim((string)($_POST['institucion'] ?? ''));
    $cod_saber = trim((string)($_POST['cod_saber'] ?? ''));
    $activo = isset($_POST['activo']) ? 1 : 0;
    $id_ins = intval($_POST['id_ins'] ?? 0);
    $exito = false;

    // Validar campos:
    // - código: exactamente 4 dígitos numéricos (puede iniciar con cero)
    // - institución: alfanumérica, máximo 250 caracteres
    // - código saber: estructura 000000-00, o los valores "Cerrado" / "NR";
    //   si se deja en blanco se guarda "NR"
    $cod_saber_ok = '';
    if ($cod_saber === '') {
        $cod_saber_ok = 'NR';
    } elseif (preg_match('/^[0-9]{6}-[0-9]{2}$/', $cod_saber)) {
        $cod_saber_ok = $cod_saber;
    } elseif (strcasecmp($cod_saber, 'Cerrado') === 0) {
        $cod_saber_ok = 'Cerrado';
    } elseif (strcasecmp($cod_saber, 'NR') === 0) {
        $cod_saber_ok = 'NR';
    }

    if ($codigo === '' || $institucion === '') {
        $mensaje = 'El código y la institución son obligatorios.';
        $tipo = 'error';
    } elseif (!preg_match('/^[0-9]{4}$/', $codigo)) {
        $mensaje = 'El código debe contener exactamente 4 dígitos numéricos (puede iniciar con cero).';
        $tipo = 'error';
    } elseif (mb_strlen($institucion) > 250) {
        $mensaje = 'La institución no puede superar los 250 caracteres.';
        $tipo = 'error';
    } elseif ($cod_saber_ok === '') {
        $mensaje = 'El código saber debe tener el formato 100863-00 (6 dígitos, guion y 2 dígitos), o ser "Cerrado" o "NR".';
        $tipo = 'error';
    } else {
        // Código saber normalizado (formato válido, "Cerrado" o "NR")
        $cod_saber = $cod_saber_ok;

        // ==== VALIDACIONES DE UNICIDAD (implementadas sin cambios en la BD) ====
        // 1) Pueden existir 2 o más centros con el mismo código (4 dígitos),
        //    siempre que su código saber sea distinto.
        // 2) El código saber es único a nivel global (no pueden existir 2 o más
        //    instituciones con el mismo código saber). Excepción: los valores
        //    "NR" y "Cerrado" SÍ pueden repetirse.
        // 3) Pueden existir 2 o más instituciones con el mismo nombre, siempre
        //    que su código saber sea distinto (se cumple de forma implícita
        //    gracias a la regla 2).
        // 4) Esta lógica aplica tanto al crear como al editar (el bloque se
        //    comparte para las acciones 'insertar' y 'editar').
        $duplicado = false;
        $institucion_duplicada = '';
        if ($cod_saber !== 'NR' && $cod_saber !== 'Cerrado') {
            $check = $link->prepare("SELECT institucion FROM t_instituciones WHERE cod_saber = ? AND id_ins != ? LIMIT 1");
            $check->bind_param("si", $cod_saber, $id_ins);
            $check->execute();
            $resultadoCheck = $check->get_result();
            if ($filaCheck = $resultadoCheck->fetch_assoc()) {
                $duplicado = true;
                $institucion_duplicada = $filaCheck['institucion'];
            }
            $check->close();
        }

        if ($duplicado) {
            $mensaje = "El código saber '$cod_saber' ya está registrado en la institución '" . htmlspecialchars($institucion_duplicada, ENT_QUOTES) . "'.";
            $tipo = 'error';
        } else {
            if ($accion === 'insertar') {
                $sql = "INSERT INTO t_instituciones (codigo, institucion, cod_saber, activo) VALUES (?, ?, ?, ?)";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("sssi", $codigo, $institucion, $cod_saber, $activo);
            } else {
                $sql = "UPDATE t_instituciones SET codigo=?, institucion=?, cod_saber=?, activo=? WHERE id_ins=?";
                $stmt = $link->prepare($sql);
                $stmt->bind_param("sssii", $codigo, $institucion, $cod_saber, $activo, $id_ins);
            }

            if ($stmt->execute()) {
                $exito = true;
                $mensaje = 'Institución ' . ($accion === 'insertar' ? 'creada' : 'actualizada') . ' correctamente.';
                $tipo = 'success';
            } else {
                $mensaje = 'Error al guardar la institución: ' . $stmt->error;
                $tipo = 'error';
            }
            $stmt->close();
        }
    }

    // Si hubo error, conservar los valores enviados para que el usuario los corrija
    if (!$exito) {
        $datos_edicion = [
            'id_ins'      => $id_ins,
            'codigo'      => $codigo,
            'institucion' => $institucion,
            'cod_saber'   => $cod_saber,
            'activo'      => $activo,
        ];
        $edicion = ($accion === 'editar');
    }
}

if ($accion === 'eliminar') {
    $id_ins = intval($_POST['id_ins'] ?? 0);

    if ($id_ins <= 0) {
        $mensaje = 'Identificador inválido para eliminar la institución.';
        $tipo = 'error';
    } else {
        $stmt = $link->prepare("DELETE FROM t_instituciones WHERE id_ins=?");
        $stmt->bind_param("i", $id_ins);

        if ($stmt->execute()) {
            $mensaje = 'Institución eliminada correctamente.';
            $tipo = 'success';
        } else {
            $mensaje = 'No se pudo eliminar la institución: ' . $stmt->error;
            $tipo = 'error';
        }
        $stmt->close();
    }
}

// Obtener datos para editar (solo si no quedó en modo edición por un error de POST)
if (!$edicion && isset($_GET['editar'])) {
    $id_ins = intval($_GET['editar']);

    if ($id_ins > 0) {
        $sql = "SELECT * FROM t_instituciones WHERE id_ins = ?";
        $stmt = $link->prepare($sql);
        $stmt->bind_param("i", $id_ins);
        $stmt->execute();
        $result = $stmt->get_result();
        $datos_edicion = $result->fetch_assoc();

        if ($datos_edicion) {
            $edicion = true;
        }
        $stmt->close();
    }
}

// Datos del listado
$result = $link->query("SELECT * FROM t_instituciones ORDER BY codigo");
$totalRegistros = $result ? $result->num_rows : 0;

// Configuración del modal de resultado
$modalIcono = 'check-circle-fill';
$modalClase = 'success';
$modalTitulo = 'Operación exitosa';
if ($tipo === 'error') {
    $modalIcono = 'x-circle-fill';
    $modalClase = 'error';
    $modalTitulo = 'Error';
} elseif ($tipo === 'warning') {
    $modalIcono = 'exclamation-triangle-fill';
    $modalClase = 'warning';
    $modalTitulo = 'Atención';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Centros Educativos</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css?v=11" />

    <style>
        .table-mep tbody tr {
            transition: background 0.2s ease;
        }

        .table-mep tbody tr:hover {
            background: rgba(25, 41, 82, 0.04);
        }

        .filter-box {
            background: #fff;
            border-radius: 12px;
            padding: 8px 14px;
            border: 1px solid var(--mep-border, #D9D9D9);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .action-btn {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .action-btn[data-tooltip],
        .btn-exportar-excel[data-tooltip],
        .btn-mep-primary[data-tooltip] {
            position: relative;
        }

        .action-btn[data-tooltip]::before,
        .btn-exportar-excel[data-tooltip]::before,
        .btn-mep-primary[data-tooltip]::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 100;
        }

        .action-btn[data-tooltip]:hover::before,
        .btn-exportar-excel[data-tooltip]:hover::before,
        .btn-mep-primary[data-tooltip]:hover::before {
            opacity: 1;
        }

        .celda-acciones .btn {
            transition: transform 0.15s ease;
        }

        .celda-acciones .btn:active {
            transform: scale(0.92);
        }

        .empty-state {
            display: none;
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
            color: var(--mep-accent, #CFAC65);
        }

        /* Botón Exportar a Excel compacto: encaja con el contador en la misma fila
           (la fuente en producción separa más los caracteres) */
        .btn-exportar-excel {
            padding: 6px 16px;
            font-size: 0.82rem;
            letter-spacing: 0;
            gap: 6px;
            border-radius: 8px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-exportar-excel i {
            font-size: 1.05rem;
            color: #9FE870;
        }

        .contador-registros {
            white-space: nowrap;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <main class="container py-4 contenido-principal">
        <!-- Hero / Encabezado -->
        <div class="hero-box mb-4 fade-enter">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="hero-icon">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1">Gestión de Centros Educativos</h2>
                            <p class="mb-0 opacity-75">Agregue, edite o elimine las instituciones del sistema.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- FORMULARIO -->
            <div class="col-lg-5">
                <div class="prestamo-form-card h-100">
                    <div class="prestamo-form-header">
                        <i class="bi bi-<?php echo $edicion ? 'pencil-square' : 'building-add'; ?> me-2"></i>
                        <?php echo $edicion ? 'Editar Institución' : 'Nueva Institución'; ?>
                    </div>
                    <div class="prestamo-form-body">
                        <?php if ($edicion): ?>
                        <div class="alert alert-info py-2 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle"></i>
                            <span>
                                Editando: <strong><?php echo htmlspecialchars($datos_edicion['codigo'] ?? '', ENT_QUOTES); ?></strong>
                                &mdash; <?php echo htmlspecialchars($datos_edicion['institucion'] ?? '', ENT_QUOTES); ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($urlBase, ENT_QUOTES); ?>" autocomplete="off">
                            <input type="hidden" name="id_ins" value="<?php echo htmlspecialchars($datos_edicion['id_ins'] ?? 0, ENT_QUOTES); ?>">

                            <div class="mb-3">
                                <label for="codigo" class="form-label fw-semibold required-field">Código</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" id="codigo" name="codigo" required
                                           maxlength="4" pattern="[0-9]{4}" placeholder="Ej: 0123"
                                           title="El código debe contener exactamente 4 dígitos numéricos (puede iniciar con cero)"
                                           value="<?php echo htmlspecialchars($datos_edicion['codigo'] ?? '', ENT_QUOTES); ?>">
                                </div>
                                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Debe contener exactamente 4 dígitos numéricos.</small>
                            </div>

                            <div class="mb-3">
                                <label for="institucion" class="form-label fw-semibold required-field">Institución</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" class="form-control" id="institucion" name="institucion" required
                                           maxlength="250" placeholder="Nombre completo del centro educativo"
                                           value="<?php echo htmlspecialchars($datos_edicion['institucion'] ?? '', ENT_QUOTES); ?>">
                                </div>
                                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Máximo 250 caracteres.</small>
                            </div>

                            <div class="mb-3">
                                <label for="cod_saber" class="form-label fw-semibold">Código Saber</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-journal-code"></i></span>
                                    <input type="text" class="form-control" id="cod_saber" name="cod_saber"
                                           maxlength="9" list="listaSaber" placeholder="Ej: 100863-00"
                                           pattern="[0-9]{6}-[0-9]{2}|[Cc]errado|[Nn][Rr]"
                                           title="El código saber debe tener el formato 100863-00 (6 dígitos, guion y 2 dígitos), o ser 'Cerrado' o 'NR'"
                                           value="<?php echo htmlspecialchars($datos_edicion['cod_saber'] ?? '', ENT_QUOTES); ?>">
                                    <datalist id="listaSaber">
                                        <option value="NR"></option>
                                        <option value="Cerrado"></option>
                                    </datalist>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Formato: 6 dígitos, guion y 2 dígitos (Ej: 100863-00), o las opciones "Cerrado" y "NR". Si lo deja en blanco, se guardará "NR".
                                </small>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo"
                                           <?php echo (($datos_edicion['activo'] ?? 1) ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="activo">
                                        <!-- <i class="bi bi-toggle-on me-1"></i>  -->
                                         Institución activa
                                    </label>
                                </div>
                            </div>

                            <?php if ($edicion): ?>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" name="accion" value="editar" class="btn btn-mep-primary" data-tooltip="Actualizar institución">
                                    <i class="bi bi-save me-1"></i> Actualizar Institución
                                </button>
                                <a href="<?php echo htmlspecialchars($urlBase, ENT_QUOTES); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Cancelar
                                </a>
                            </div>
                            <?php else: ?>
                            <button type="submit" name="accion" value="insertar" class="btn btn-mep-primary w-100" data-tooltip="Crear nueva institución">
                                <i class="bi bi-plus-circle me-1"></i> Crear Institución
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- LISTADO -->
            <div class="col-lg-7">
                <div class="prestamo-form-card">
                    <div class="prestamo-form-header">
                        <i class="bi bi-list-ul me-2"></i> Lista de Instituciones
                    </div>
                    <div class="prestamo-form-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="contador-container mb-0">
                                <div class="contador-registros">
                                    <i class="bi bi-database me-2"></i>
                                    Registros encontrados:
                                    <span id="contador"><?php echo $totalRegistros; ?></span>
                                    registros
                                </div>
                            </div>
                            <a href="<?php echo htmlspecialchars($urlBase, ENT_QUOTES); ?>&exportar_excel=1" class="btn btn-mep-primary btn-exportar-excel" data-tooltip="Exportar a Excel">
                                <i class="bi bi-file-earmark-excel"></i> Exportar
                            </a>
                        </div>

                        <div class="filter-box mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-search text-muted"></i>
                            <input type="text" id="FiltrarContenido" class="form-control form-control-sm"
                                   placeholder="Buscar por código, institución o código saber...">
                        </div>

                        <div class="edicion-tabla-scroll">
                            <table class="table table-hover table-mep align-middle mb-0" id="tablaInstituciones">
                                <thead class="header-fixed">
                                    <tr>
                                        <th>Código</th>
                                        <th>Institución</th>
                                        <th>Código Saber</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($row['codigo'], ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($row['institucion'], ENT_QUOTES); ?></td>
                                        <td><?php echo htmlspecialchars($row['cod_saber'] ?? '', ENT_QUOTES); ?></td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill estado-badge-<?php echo $row['activo'] ? 'activo' : 'inactivo'; ?>">
                                                <i class="bi bi-<?php echo $row['activo'] ? 'check-circle-fill' : 'x-circle-fill'; ?> me-1"></i>
                                                <?php echo $row['activo'] ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center celda-acciones">
                                                <a href="<?php echo htmlspecialchars($urlBase, ENT_QUOTES); ?>&editar=<?php echo (int)$row['id_ins']; ?>"
                                                   class="btn btn-sm btn-outline-primary action-btn" data-tooltip="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger action-btn btn-eliminar"
                                                        data-tooltip="Eliminar"
                                                        data-id="<?php echo (int)$row['id_ins']; ?>"
                                                        data-nombre="<?php echo htmlspecialchars($row['institucion'], ENT_QUOTES); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox me-2"></i>No hay instituciones registradas.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="empty-state" id="sinResultados">
                            <i class="bi bi-search"></i>
                            Sin resultados para la búsqueda actual.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if ($mensaje !== ''): ?>
    <!-- Modal de resultado -->
    <div class="modal fade gestor-modal" id="modalResultado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-<?php echo $modalIcono; ?> me-2"></i> <?php echo $modalTitulo; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="gestor-modal-icon <?php echo $modalClase; ?>">
                        <i class="bi bi-<?php echo $modalIcono; ?>"></i>
                    </div>
                    <p class="mb-0"><?php echo htmlspecialchars($mensaje, ENT_QUOTES); ?></p>
                </div>
                <div class="modal-footer centered">
                    <button type="button" class="gestor-btn-primary" data-bs-dismiss="modal">Aceptar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('modalResultado')).show();
        });
    </script>
    <?php endif; ?>

    <!-- Modal confirmación de eliminación -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="prestamo-modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i> Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="prestamo-modal-body">
                    <p id="confirmacionMensaje"></p>
                </div>
                <div class="prestamo-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarSi">Sí, eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para eliminar (enviado tras confirmación) -->
    <form id="form-eliminar" method="POST" action="<?php echo htmlspecialchars($urlBase, ENT_QUOTES); ?>" style="display:none;">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id_ins" id="id-eliminar" value="">
    </form>

    <!-- Botón flotante Volver -->
    <a href="<?php echo htmlspecialchars($ruta_regreso, ENT_QUOTES); ?>" class="btn-disponibilidad"
        style="bottom: 100px;" data-tooltip="Regresar">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <?php include 'partials/footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Filtro en vivo del listado
            $('#FiltrarContenido').on('keyup input', function() {
                var busqueda = $.trim(this.value).toLowerCase();
                var visibles = 0;

                $('#tablaInstituciones tbody tr').each(function() {
                    var coincide = $(this).text().toLowerCase().indexOf(busqueda) > -1;
                    $(this).toggle(coincide);
                    if (coincide) {
                        visibles++;
                    }
                });

                $('#contador').text(visibles);
                $('#sinResultados').toggle(visibles === 0);
            });

            // Eliminación con modal de confirmación
            $('.btn-eliminar').on('click', function() {
                var id = this.getAttribute('data-id');
                var nombre = this.getAttribute('data-nombre');

                document.getElementById('id-eliminar').value = id;
                mostrarConfirmacion(
                    '¿Está seguro de eliminar la institución "' + nombre + '"? Esta acción no se puede deshacer.',
                    function() {
                        document.getElementById('form-eliminar').submit();
                    }
                );
            });
        });

        // ==== Confirmación mediante modal (patrón herramienta_editar_masiva_n.php) ====
        var confirmCallback = null;

        function mostrarConfirmacion(mensaje, callback) {
            document.getElementById('confirmacionMensaje').textContent = mensaje;
            confirmCallback = callback;
            var modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
            modal.show();
        }

        document.getElementById('btnConfirmarSi').addEventListener('click', function() {
            if (confirmCallback) {
                confirmCallback();
                confirmCallback = null;
            }
            var modalEl = document.getElementById('modalConfirmacion');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });
    </script>
</body>
</html>
