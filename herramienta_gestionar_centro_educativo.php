<?php
session_start();
require_once("conexion.php");

// Añade esta línea para incluir PHPSpreadsheet (ajusta la ruta según tu estructura)
require_once __DIR__ . '/../vendor/autoload.php'; // O la ruta correcta a autoload.php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$link = $mysqli;

// Verificar permisos
$tienellave = in_array($_SESSION['tipo'], [1,7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos para realizar esta acción");
    window.history.back();
    </script>';
    exit();
}

// Configurar conexión y charset
if (mysqli_connect_errno()) {
    die("Error de conexión a MySQL: " . mysqli_connect_error());
}

if (!mysqli_set_charset($link, "utf8")) {
    die("Error cargando el conjunto de caracteres UTF-8: " . mysqli_error($link));
}

// PROCESAR EXPORTACIÓN A EXCEL
if (isset($_GET['exportar_excel'])) {
    // Consulta para obtener los datos
    $sql = "SELECT * FROM t_instituciones ORDER BY codigo";
    $result = $link->query($sql);
    
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
        $sheet->setCellValue('F' . $row, $data['fecha_registro'] ?? date('Y-m-d H:i:s'));
        
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

// Procesar operaciones (el resto del código se mantiene igual)
$accion = $_POST['accion'] ?? '';
$mensaje = '';

if ($accion === 'insertar' || $accion === 'editar') {
    $codigo = trim($_POST['codigo']);
    $institucion = trim($_POST['institucion']);
    $cod_saber = trim($_POST['cod_saber']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $id_ins = $_POST['id_ins'] ?? 0;

    // Validar código único
    $check_sql = "SELECT id_ins FROM t_instituciones WHERE codigo = ? AND id_ins != ?";
    $stmt = $link->prepare($check_sql);
    $stmt->bind_param("si", $codigo, $id_ins);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $mensaje = '<div class="alert alert-danger">El código ya existe en otra institución</div>';
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
            $mensaje = '<div class="alert alert-success">Registro ' . ($accion === 'insertar' ? 'creado' : 'actualizado') . ' correctamente</div>';
        } else {
            $mensaje = '<div class="alert alert-danger">Error al guardar: ' . $stmt->error . '</div>';
        }
    }
    $stmt->close();
}

if ($accion === 'eliminar') {
    $id_ins = $_POST['id_ins'];
    $sql = "DELETE FROM t_instituciones WHERE id_ins=?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("i", $id_ins);
    
    if ($stmt->execute()) {
        $mensaje = '<div class="alert alert-success">Registro eliminado correctamente</div>';
    } else {
        $mensaje = '<div class="alert alert-danger">Error al eliminar: ' . $stmt->error . '</div>';
    }
    $stmt->close();
}

// Obtener datos para editar
$edicion = false;
$datos_edicion = null;
if (isset($_GET['editar'])) {
    $id_ins = $_GET['editar'];
    $sql = "SELECT * FROM t_instituciones WHERE id_ins = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param("i", $id_ins);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos_edicion = $result->fetch_assoc();
    $edicion = true;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Instituciones</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-container { max-height: 400px; overflow-y: auto; }
        .required:after { content: " *"; color: red; }
        .export-btn { margin-bottom: 15px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md bg-dark navbar-dark">
        <img src="img/logodelgobierno.png" width="35" height="30" alt="" loading="lazy">
        <a class="navbar-brand" href="formulario_menu_principal.html">Tecnopresta</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="collapsibleNavbar">
            <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="herramientas.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply-all" viewBox="0 0 16 16">
                <path d="M8.098 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.7 8.7 0 0 0-1.921-.306 7 7 0 0 0-.798.008h-.013l-.005.001h-.001L8.8 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L4.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028zM9.3 10.386q.102 0 .223.006c.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96z"/>
                <path d="M5.232 4.293a.5.5 0 0 0-.7-.106L.54 7.127a1.147 1.147 0 0 0 0 1.946l3.994 2.94a.5.5 0 1 0 .593-.805L1.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028 4.012-2.954a.5.5 0 0 0 .106-.699"/>
                </svg> Regresar</a>
            </li> 
            <li class="nav-item">
                <a class="nav-link" href="gameover.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-door-open" viewBox="0 0 16 16">
                <path d="M8.5 10c-.276 0-.5-.448-.5-1s.224-1 .5-1 .5.448.5 1-.224 1-.5 1"/>
                <path d="M10.828.122A.5.5 0 0 1 11 .5V1h.5A1.5 1.5 0 0 1 13 2.5V15h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V1.5a.5.5 0 0 1 .43-.495l7-1a.5.5 0 0 1 .398.117M11.5 2H11v13h1V2.5a.5.5 0 0 0-.5-.5M4 1.934V15h6V1.077z"/>
                </svg> Cerrar Sesión</a>
            </li>  
            </ul>
        </div>  
    </nav>
    <div class="container mt-4">
        <h2 class="mb-4"><?= $edicion ? 'Editar' : 'Nueva' ?> Institución</h2>
        
        <?= $mensaje ?>
        
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="codigo" class="form-label required">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" 
                           value="<?= $datos_edicion['codigo'] ?? '' ?>" required maxlength="10">
                </div>
                
                <div class="col-md-6">
                    <label for="institucion" class="form-label required">Institución</label>
                    <input type="text" class="form-control" id="institucion" name="institucion"
                           value="<?= $datos_edicion['institucion'] ?? '' ?>" required maxlength="255">
                </div>
                
                <div class="col-md-6">
                    <label for="cod_saber" class="form-label">Código Saber</label>
                    <input type="text" class="form-control" id="cod_saber" name="cod_saber"
                           value="<?= $datos_edicion['cod_saber'] ?? '' ?>" maxlength="50">
                </div>
                
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                               <?= isset($datos_edicion['activo']) ? ($datos_edicion['activo'] ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label" for="activo">Institución Activa</label>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <?php if ($edicion): ?>
                    <input type="hidden" name="id_ins" value="<?= $datos_edicion['id_ins'] ?>">
                    <button type="submit" name="accion" value="editar" class="btn btn-primary">Actualizar</button>
                    <a href="herramienta_gestionar_centro_educativo.php" class="btn btn-secondary">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="accion" value="insertar" class="btn btn-success">Crear Institución</button>
                <?php endif; ?>
            </div>
        </form>

        <hr class="my-5">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Lista de Instituciones</h3>
            <!-- BOTÓN PARA EXPORTAR A EXCEL -->
            <a href="?exportar_excel=1" class="btn btn-success export-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel" viewBox="0 0 16 16">
                    <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/>
                    <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
                </svg>
                Exportar a Excel
            </a>
        </div>
        
        <div class="table-container">
            <table class="table table-striped table-hover">
                <thead class="sticky-top bg-light">
                    <tr>
                        <th>Código</th>
                        <th>Institución</th>
                        <th>Código Saber</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // CORRECCIÓN: Ordenar por código en lugar de id_ins
                    $sql = "SELECT * FROM t_instituciones ORDER BY codigo";
                    $result = $link->query($sql);
                    
                    while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['codigo']) ?></td>
                            <td><?= htmlspecialchars($row['institucion']) ?></td>
                            <td><?= htmlspecialchars($row['cod_saber']) ?></td>
                            <td>
                                <span class="badge bg-<?= $row['activo'] ? 'success' : 'danger' ?>">
                                    <?= $row['activo'] ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td>
                                <a href="?editar=<?= $row['id_ins'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar esta institución?')">
                                    <input type="hidden" name="id_ins" value="<?= $row['id_ins'] ?>">
                                    <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
</body>
</html>