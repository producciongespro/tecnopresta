<?php
// coordinacion_dashboard.php
session_start();

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
    exit;
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
    exit;
}

date_default_timezone_set('America/Costa_Rica');

// ============================================
// OBTENER DATOS DEL USUARIO LOGUEADO
// ============================================
$logusuario = $_SESSION['cedula'];
$logcorreo = $_SESSION['correomep'];

// ============================================
// CONSULTAR ROLES DEL USUARIO
// ============================================
$grupos_usuario = [];
$es_coordinador = false;
$id_soportista = null;
$nombre_usuario = '';
$sin_registro = false;
$sin_permisos = false;
$error_db = false;

try {
    // Primero obtenemos el id_soportista por correo (o cédula como respaldo)
    $sql = "SELECT id_soportista, nombre, correo, cedula 
            FROM soportistas 
            WHERE correo = ? OR cedula = ? 
            LIMIT 1";
            
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $logcorreo, $logusuario);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $soportista = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if($soportista) {
        $id_soportista = $soportista['id_soportista'];
        $nombre_usuario = $soportista['nombre'];
        
        // Obtenemos los grupos a los que pertenece (activos)
        $sql_grupos = "SELECT DISTINCT grupo 
                       FROM soportistas_clasificaciones 
                       WHERE id_soportista = ? 
                       AND activo = 1";
                       
        $stmt_grupos = mysqli_prepare($link, $sql_grupos);
        mysqli_stmt_bind_param($stmt_grupos, "i", $id_soportista);
        mysqli_stmt_execute($stmt_grupos);
        $result_grupos = mysqli_stmt_get_result($stmt_grupos);
        
        while($row = mysqli_fetch_assoc($result_grupos)) {
            $grupo = $row['grupo'];
            switch($grupo) {
                case 1:
                    $grupos_usuario[] = 'mesa';
                    break;
                case 2:
                    $grupos_usuario[] = 'virtual';
                    break;
                case 3:
                    $grupos_usuario[] = 'sitio';
                    break;
                case 4:
                    $es_coordinador = true;
                    break;
            }
        }
        mysqli_stmt_close($stmt_grupos);
        
        // Si es coordinador, tiene acceso a todos los módulos operativos
        if($es_coordinador) {
            $grupos_usuario = ['mesa', 'virtual', 'sitio'];
        }
        
    } else {
        // Usuario no registrado como soportista
        $sin_registro = true;
        $nombre_usuario = $logusuario;
    }
    
    // Si no hay grupos asignados y no es coordinador
    if(empty($grupos_usuario) && !$es_coordinador && !$sin_registro) {
        $sin_permisos = true;
    }
    
} catch(Exception $e) {
    $error_db = true;
    error_log("Error en coordinacion_dashboard: " . $e->getMessage());
}

// ============================================
// VERIFICAR ACCESO: SOLO COORDINADORES (GRUPO 4)
// ============================================
if(!$es_coordinador) {
    echo '<script language="javascript">
        alert("Acceso denegado. Solo los coordinadores pueden acceder al Centro de Coordinación.");
        self.location = "dashboard_soportistas.php";
    </script>';
    exit;
}

// Obtener estadísticas generales para mostrar en tarjetas
// Estos valores se actualizarán vía AJAX, pero podemos inicializar variables aquí si queremos
$total_mesa = 0;
$total_regionales = 0;
$dias_pendientes = 0;
$tickets_virtuales = 0;
$soportistas_virtual = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoPresta - Centro de Coordinación</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/nueva-identidad.css">
    
    <style>
        .coordinacion-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--mep-primary);
            margin-bottom: 0.5rem;
        }
        
        .coordinacion-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .coordinacion-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--mep-border);
            text-decoration: none;
            display: block;
            color: inherit;
        }
        
        .coordinacion-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .card-header-icon {
            background: var(--mep-primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .card-header-icon i {
            font-size: 3rem;
        }
        
        .card-body-content {
            padding: 25px;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--mep-primary);
            margin-bottom: 10px;
        }
        
        .card-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .card-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--mep-light);
            padding-top: 15px;
            margin-top: 10px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--mep-accent);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .badge-pending {
            background: #FFF3E0;
            color: #F57C00;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .coordinacion-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-card {
            background: var(--mep-light);
            border-left: 4px solid var(--mep-accent);
            padding: 15px 20px;
            border-radius: 8px;
        }
        
        .btn-mep-secondary {
            background: var(--mep-accent);
            color: var(--mep-primary);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-mep-secondary:hover {
            background: #e0c088;
            transform: translateY(-1px);
            color: var(--mep-primary);
        }
    </style>
</head>
<body>

<?php include 'partials/header.php'; ?>

<div class="coordinacion-container">
    <div class="container-fluid px-0">
        <!-- Encabezado -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="section-title">
                        <i class="bi bi-speedometer2 me-2"></i> Centro de Coordinación
                    </h1>
                    <p class="text-muted">
                        <i class="bi bi-person-circle"></i> Bienvenido, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>
                    </p>
                </div>
                <a href="dashboard_soportistas.php" class="btn-mep-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
        
        <!-- Grid de tarjetas del coordinador -->
        <div class="coordinacion-grid">
            
            <!-- Card 1: Asignación de Regionales - Mesa de Soporte -->
            <a href="coordinacion_regionales.php" class="coordinacion-card">
                <div class="card-header-icon" style="background: #192952;">
                    <i class="bi bi-pin-map-fill"></i>
                </div>
                <div class="card-body-content">
                    <h3 class="card-title">
                        <i class="bi bi-headset me-2" style="color: var(--mep-accent);"></i>
                        Regionales - Mesa de Soporte
                    </h3>
                    <p class="card-description">
                        Asigne las regionales que cada soportista de Mesa de Soporte tendrá a cargo. 
                        Gestione coberturas territoriales y responsabilidades.
                    </p>
                    <div class="card-stats">
                        <div>
                            <span class="stat-number" id="totalMesa">0</span>
                            <div class="stat-label">Soportistas Mesa</div>
                        </div>
                        <div>
                            <span class="stat-number" id="totalRegionales">0</span>
                            <div class="stat-label">Regionales</div>
                        </div>
                        <i class="bi bi-chevron-right" style="color: var(--mep-accent); font-size: 1.5rem;"></i>
                    </div>
                </div>
            </a>
            
            <!-- Card 2: Aprobación de Días No Disponibles -->
            <a href="coordinacion_aprobar_dias.php" class="coordinacion-card">
                <div class="card-header-icon" style="background: #CFAC65;">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="card-body-content">
                    <h3 class="card-title">
                        <i class="bi bi-clock-history me-2" style="color: var(--mep-accent);"></i>
                        Aprobar Ausencias
                    </h3>
                    <p class="card-description">
                        Revise y apruebe o rechace las solicitudes de días no disponibles 
                        registradas por los soportistas.
                    </p>
                    <div class="card-stats">
                        <div>
                            <span class="stat-number" id="diasPendientes">0</span>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div>
                            <span class="badge-pending">
                                <i class="bi bi-exclamation-triangle"></i> Requiere atención
                            </span>
                        </div>
                    </div>
                </div>
            </a>

             <!-- Card 5: Crear Visitas (sin ticket) -->
            <a href="coordinacion_visitas.php" class="coordinacion-card">
                <div class="card-header-icon" style="background: #6A1B9A;">
                    <i class="bi bi-clipboard-plus-fill"></i>
                </div>
                <div class="card-body-content">
                    <div class="text-center mb-2">
                        <i class="bi bi-journal-plus me-2" style="color: var(--mep-accent);"></i>
                    </div>
                    <h3 class="card-title">                        
                        Enviar Profesionales en Soporte
                    </h3>
                    <p class="card-description">
                        Permite asignar y enviar soportistas a un centro
                    </p>
                </div>
            </a>
            
            <!-- Card 3: Adjudicar Tickets Virtual → Sitio -->
            <a href="coordinacion_adjudicar_tickets.php" class="coordinacion-card">
                <div class="card-header-icon" style="background: #2E7D32;">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div class="card-body-content">
                    <div class="text-center mb-2">
                        <i class="bi bi-laptop fs-5" style="color: var(--mep-accent);"></i>
                    </div>
                    <h3 class="card-title">                        
                        Asignar Ticket Virtual a <i class="bi bi-arrow-right ms-1 me-1"></i> Visita de Soporte en Sitio
                    </h3>                   
                    <p class="card-description fw-bold">
                        Crear Visita Técnica o Cerrar Ticket
                    </p>
                </div>
            </a>
            
            <!-- Card 4: Panel Soporte en Sitio -->
            <a href="coordinacion-soporte-en-sitio.php" class="coordinacion-card">
                <div class="card-header-icon" style="background: #E65100;">
                    <i class="bi bi-sliders2"></i>
                </div>
                <div class="card-body-content">
                    <div class="text-center mb-2">
                        <i class="bi bi-graph-up" style="color: var(--mep-accent);"></i>
                    </div>
                    <h3 class="card-title">                        
                        Gestión de Visitas de Soporte en Sitio con Ticket asignado
                    </h3>
                    <p class="card-description">
                        Editar Visita Técnica de Sitio con Tickets asignado
                    </p>
                </div>
            </a>
           
        </div>

        <!-- Sección informativa adicional -->
        <div class="info-card mt-4">
            <i class="bi bi-info-circle-fill me-2" style="color: var(--mep-accent);"></i>
            <strong>Panel de Control del Coordinador</strong>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

<script>
// Cargar estadísticas vía AJAX para mostrar números en las tarjetas
async function cargarEstadisticas() {
    try {
        const response = await fetch('ajax/ajax_coordinacion_stats.php');
        const data = await response.json();
        
        if(data.success) {
            document.getElementById('totalMesa').innerText = data.total_mesa || 0;
            document.getElementById('totalRegionales').innerText = data.total_regionales || 0;
            document.getElementById('diasPendientes').innerText = data.dias_pendientes || 0;
            document.getElementById('ticketsVirtuales').innerText = data.tickets_virtuales || 0;
            document.getElementById('soportistasVirtual').innerText = data.soportistas_virtual || 0;
        }
    } catch(error) {
        console.error('Error cargando estadísticas:', error);
        // Valores por defecto silenciosos
        document.getElementById('totalMesa').innerText = '0';
        document.getElementById('totalRegionales').innerText = '0';
        document.getElementById('diasPendientes').innerText = '0';
        document.getElementById('ticketsVirtuales').innerText = '0';
        document.getElementById('soportistasVirtual').innerText = '0';
    }
}

// Cargar estadísticas al iniciar
cargarEstadisticas();

// Opcional: Recargar estadísticas cada 30 segundos
setInterval(cargarEstadisticas, 30000);
</script>

</body>
</html>