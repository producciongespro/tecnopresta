<?php
/**
 * crear_certificacion_para_firmar.php  — VERSIÓN NUEVA
 *
 * FLUJO:
 *  1. Funcionario revisa activos físicamente y marca checkboxes
 *  2. Clic "Generar PDF" → POST a generar_pdf_certificacion.php
 *     → descarga certificacion_XXXX.pdf con JSON incrustado
 *  3. Funcionario firma el PDF con GAUDI (desktop o móvil)
 *  4. Regresa al sistema y sube el PDF firmado via subir_certificacion.php
 *
 * NO hay integración directa con el middleware de tarjeta.
 * No requiere GAUDI instalado en el servidor.
 */

/**session_start();
$tienellave = in_array($_SESSION['tipo'], [1, 2]);

if ($tienellave == false) {
    echo '<script>alert("No tienes permisos"); self.location = "index.html";</script>';
    exit;
} */

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

if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
    exit;
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

date_default_timezone_set('America/Costa_Rica');
/** $logusuario     = $_SESSION['cedula'];
$lognombre      = $_SESSION['nombre'];
$logtipo        = $_SESSION['tipo'];
$logcodigo      = $_SESSION['codigo'];
$loginstitucion = $_SESSION['dependencia']; */
$lognombre = trim(($usuario_azure['nombre'] ?? '') . ' ' . ($usuario_azure['apellidos'] ?? ''));
// Recibir parámetros del formulario anterior
$id_fondos    = isset($_POST['fondos'])       ? (int)$_POST['fondos']       : 0;
$codigo_centro = isset($_POST['codigo_centro']) ? $_POST['codigo_centro']   : '';

if ($id_fondos == 0 || empty($codigo_centro)) {
    header("Location: centro_de_certificacion.php?error=faltan_datos");
    exit;
}

// Información de la institución
$stmt_inst = $link->prepare("SELECT * FROM t_instituciones WHERE codigo = ?");
$stmt_inst->bind_param("s", $codigo_centro);
$stmt_inst->execute();
$institucion = $stmt_inst->get_result()->fetch_assoc();
$stmt_inst->close();

// Información del fondo
$stmt_fondo = $link->prepare("SELECT * FROM t_fondos WHERE id_fondos = ?");
$stmt_fondo->bind_param("i", $id_fondos);
$stmt_fondo->execute();
$fondo = $stmt_fondo->get_result()->fetch_assoc();
$stmt_fondo->close();

// Activos
$consultaSQL = "SELECT 
                    t_placa.id_placa,
                    t_placa.placa, 
                    t_placa.serial, 
                    t_placa.id_estado,
                    t_estado.estado AS estado_texto,
                    t_marca.marca, 
                    t_activo_general.clase,
                    t_activo.modelo,
                    t_lugar.lugar
                FROM t_activo 
                INNER JOIN t_activo_general ON t_activo_general.id_ag = t_activo.id_ag 
                INNER JOIN t_marca         ON t_activo.id_marca = t_marca.id_marca 
                INNER JOIN t_placa         ON t_placa.id_activo = t_activo.id_activo 
                INNER JOIN t_lugar         ON t_placa.id_lugar = t_lugar.id_lugar 
                INNER JOIN t_estado        ON t_placa.id_estado = t_estado.id_estado 
                INNER JOIN t_fondos        ON t_placa.id_fondos = t_fondos.id_fondos 
                INNER JOIN t_instituciones ON t_instituciones.codigo = t_placa.codigo 
                WHERE t_placa.id_fondos = ? AND t_placa.codigo = ? 
                ORDER BY t_placa.placa ASC";

$stmt = $link->prepare($consultaSQL);
$stmt->bind_param("is", $id_fondos, $codigo_centro);
$stmt->execute();
$result = $stmt->get_result();
$activos = [];
while ($row = $result->fetch_assoc()) { $activos[] = $row; }
$stmt->close();

// Certificación anterior
$stmt_ant = $link->prepare(
    "SELECT * FROM certificaciones 
     WHERE centro_id = ? AND fuente_presupuestaria_id = ? 
     ORDER BY fecha_certificacion DESC LIMIT 1"
);
$stmt_ant->bind_param("si", $codigo_centro, $id_fondos);
$stmt_ant->execute();
$certificacion_anterior = $stmt_ant->get_result()->fetch_assoc();
$stmt_ant->close();

// Activos de la certificación anterior
$activos_anteriores = [];
$fecha_cert_anterior = null;
if ($certificacion_anterior) {
    $fecha_cert_anterior = $certificacion_anterior['fecha_certificacion'];
    $stmt_det = $link->prepare(
        "SELECT placa, serial, estado_actual FROM certificaciones_detalle 
         WHERE certificacion_id = ?"
    );
    $stmt_det->bind_param("i", $certificacion_anterior['id']);
    $stmt_det->execute();
    $res_det = $stmt_det->get_result();
    while ($row = $res_det->fetch_assoc()) {
        $activos_anteriores[$row['placa']] = $row;
    }
    $stmt_det->close();
}

function getDiferencia($placa, $activoActual, $activosAnteriores) {
    if (!isset($activosAnteriores[$placa])) return 'nuevo';
    if ($activoActual['estado_texto'] != $activosAnteriores[$placa]['estado_actual']) return 'cambio';
    return 'sin_cambio';
}

$activos_desaparecidos = [];
foreach ($activos_anteriores as $placa => $anterior) {
    $existe = false;
    foreach ($activos as $actual) {
        if ($actual['placa'] == $placa) { $existe = true; break; }
    }
    if (!$existe) $activos_desaparecidos[] = $anterior;
}

$total_activos     = count($activos);
$total_desaparecidos = count($activos_desaparecidos);
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
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <title>Certificación de Inventario — <?php echo htmlspecialchars($institucion['institucion']); ?></title>
    <style>
        body { font-family: 'Henderson Sans', sans-serif; background-color: #f5f5f5; }
        .btn-flotante-regresar {
            position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px;
            border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            cursor: pointer; z-index: 1000; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; text-decoration: none;
        }
        .btn-flotante-regresar:hover { transform: scale(1.1); color: white; }
        .btn-flotante-regresar i { font-size: 32px; }
        .table-activo-nuevo    { background-color: #d4edda !important; border-left: 4px solid #28a745; }
        .table-activo-cambio   { background-color: #fff3cd !important; border-left: 4px solid #ffc107; }
        .checkbox-revisado     { transform: scale(1.2); cursor: pointer; }
        .observacion-input     { font-size: 0.85rem; min-width: 150px; }
        .table-responsive-custom { max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; }
        .table thead th        { position: sticky; top: 0; background-color: #fff; z-index: 10; }
        .resumen-card          { transition: all 0.2s ease; }
        .resumen-card:hover    { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .paso-activo           { border: 2px solid #0d6efd !important; }
        .paso-completado       { border: 2px solid #198754 !important; opacity: 0.7; }
        .numero-paso           { width: 32px; height: 32px; border-radius: 50%; display: inline-flex;
                                 align-items: center; justify-content: center; font-weight: bold;
                                 font-size: 14px; flex-shrink: 0; }
    </style>
</head>
<body>
<?php include 'partials/header.php'; ?>

<div class="container mb-5">
    <a href="navegar.php?ruta=centro_de_certificacion.php" class="btn-flotante-regresar" title="Volver al menú principal">
        <i class="bi bi-arrow-left-circle-fill"></i>
    </a>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h2 class="mb-0">
            <i class="bi bi-clipboard-check text-primary"></i> Certificación de Inventario
        </h2>
        <div>
            <span class="badge bg-primary p-2">
                <i class="bi bi-building"></i> <?php echo htmlspecialchars($institucion['institucion']); ?>
            </span>
            <span class="badge bg-success p-2 ms-1">
                <i class="bi bi-cash-stack"></i> <?php echo htmlspecialchars($fondo['fondos']); ?>
            </span>
        </div>
    </div>

    <!-- Alerta de compromiso -->
    <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-4" role="alert" style="background-color: #fff9e6;">
        <div class="d-flex align-items-start">
            <i class="bi bi-shield-check flex-shrink-0" style="font-size:1.8rem; color:#856404;"></i>
            <div class="ms-3">
                <h6 class="alert-heading mb-1">Compromiso Profesional</h6>
                <p class="mb-0 small">
                    Estimado(a) <strong><?php echo htmlspecialchars($lognombre); ?></strong>, 
                    certifica <strong><?php echo $total_activos; ?> activos</strong> de 
                    <strong><?php echo htmlspecialchars($institucion['institucion']); ?></strong>.
                    Al marcar cada casilla declara bajo fe de juramento haber verificado físicamente 
                    cada activo. La firma con GAUDI tiene valor legal (Ley 8454 C.R.).
                </p>
            </div>
        </div>
    </div>

    <!-- GUÍA DE PASOS -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card h-100 border-primary paso-activo" id="tarjetaPaso1">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="numero-paso bg-primary text-white me-2">1</span>
                        <strong>Revisar activos</strong>
                    </div>
                    <p class="small text-muted mb-0">
                        Marque cada activo como revisado físicamente. Todos deben estar marcados para continuar.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-secondary" id="tarjetaPaso2">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="numero-paso bg-secondary text-white me-2">2</span>
                        <strong>Descargar y firmar PDF</strong>
                    </div>
                    <p class="small text-muted mb-0">
                        Genere el PDF, fírmelo con GAUDI (desktop o móvil) y guarde el archivo firmado.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-secondary" id="tarjetaPaso3">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="numero-paso bg-secondary text-white me-2">3</span>
                        <strong>Subir PDF firmado</strong>
                    </div>
                    <p class="small text-muted mb-0">
                        Suba el PDF firmado. El sistema lo procesa y registra la certificación automáticamente.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($total_desaparecidos > 0): ?>
    <div class="alert alert-danger mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        
        <strong>Atención:</strong>
        <?php echo $total_desaparecidos; ?> activo(s) de la certificación anterior
        no aparecen en el inventario actual.
        
        <hr>
        
        <p class="mb-2">
            Revise los siguientes activos:
        </p>
        
        <ul class="mb-3">
            <?php foreach ($activos_desaparecidos as $activo): ?>
                <li>
                    <strong><?php echo htmlspecialchars($activo['placa']); ?></strong>
                    <?php if (!empty($activo['serial'])): ?>
                        - <?php echo htmlspecialchars($activo['serial']); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                id="confirmarDesaparecidos">
            
            <label
                class="form-check-label"
                for="confirmarDesaparecidos">
                
                Confirmo que conozco las diferencias detectadas y deseo continuar
                con la certificación de los activos actualmente asignados.
                
            </label>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($certificacion_anterior): ?>
    <div class="alert alert-secondary small mb-3">
        <i class="bi bi-info-circle"></i> 
        Última certificación: <strong><?php echo date('d/m/Y H:i', strtotime($fecha_cert_anterior)); ?></strong>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary small mb-3">
        <i class="bi bi-info-circle"></i> Esta será la <strong>primera certificación</strong> para esta fuente.
    </div>
    <?php endif; ?>

    <!-- PASO 1: TABLA DE ACTIVOS -->
    <div class="card shadow-sm mb-4" id="seccionPaso1">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table"></i> Activos a Certificar</h6>
            <span class="badge bg-primary">
                <span id="contadorRevisados">0</span> / <?php echo $total_activos; ?> revisados
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-custom">
                <table class="table table-hover mb-0" id="tablaActivos">
                    <thead class="table-light">
                        <tr>
                            <th style="width:5%">#</th>
                            <th style="width:12%">Placa</th>
                            <th style="width:15%">Serial</th>
                            <th style="width:20%">Descripción</th>
                            <th style="width:10%">Estado</th>
                            <th style="width:12%">Ubicación</th>
                            <th style="width:8%">Diferencia</th>
                            <th style="width:8%" class="text-center">Revisado</th>
                            <th style="width:15%">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_activos === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No hay activos registrados para esta fuente.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($activos as $i => $activo):
                            $diferencia = getDiferencia($activo['placa'], $activo, $activos_anteriores);
                            $rowClass   = $diferencia === 'nuevo' ? 'table-activo-nuevo' : ($diferencia === 'cambio' ? 'table-activo-cambio' : '');
                            $badge      = '';
                            if ($diferencia === 'nuevo')  $badge = '<span class="badge bg-success" style="font-size:.7rem">NUEVO</span>';
                            if ($diferencia === 'cambio') $badge = '<span class="badge bg-warning text-dark" style="font-size:.7rem">CAMBIO</span>';
                        ?>
                        <tr class="<?php echo $rowClass; ?>" data-placa="<?php echo htmlspecialchars($activo['placa']); ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($activo['placa']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($activo['serial']); ?></code></td>
                            <td>
                                <?php echo htmlspecialchars($activo['marca']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($activo['clase'] . ' ' . $activo['modelo']); ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($activo['estado_texto']); ?></span></td>
                            <td><small><?php echo htmlspecialchars($activo['lugar']); ?></small></td>
                            <td class="text-center"><?php echo $badge; ?></td>
                            <td class="text-center">
                                <input type="checkbox" class="checkbox-revisado"
                                    data-placa="<?php echo htmlspecialchars($activo['placa']); ?>"
                                    data-serial="<?php echo htmlspecialchars($activo['serial']); ?>"
                                    data-estado="<?php echo htmlspecialchars($activo['estado_texto']); ?>"
                                    data-ubicacion="<?php echo htmlspecialchars($activo['lugar']); ?>">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm observacion-input"
                                    placeholder="Opcional" maxlength="500"
                                    data-placa="<?php echo htmlspecialchars($activo['placa']); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PASO 2: GENERAR PDF -->
    <div class="card shadow-sm mb-4" id="seccionPaso2">
        <div class="card-header bg-white">
            <h6 class="mb-0">
                <span class="numero-paso bg-secondary text-white me-2" id="numeroPaso2">2</span>
                Generar PDF de Certificación
            </h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Una vez revisados todos los activos, genere el PDF. Se descargará automáticamente a su equipo.
                Luego fírmelo con <strong>GAUDI Desktop</strong> o <strong>GAUDI Móvil</strong>.
            </p>
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <button type="button" id="btnGenerarPdf" class="btn btn-primary btn-lg" disabled>
                    <i class="bi bi-file-earmark-pdf"></i> Generar PDF para Firmar
                </button>
                <div id="estadoGeneracion" class="text-muted small" style="display:none;">
                    <span class="spinner-border spinner-border-sm me-1"></span> Generando PDF...
                </div>
                <div id="pdfGenerado" style="display:none;" class="alert alert-success mb-0 py-2 px-3">
                    <i class="bi bi-check-circle-fill"></i>
                    PDF descargado. <strong>Fírmelo con GAUDI y luego suba el archivo firmado abajo.</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- PASO 3: SUBIR PDF FIRMADO -->
    <div class="card shadow-sm mb-4" id="seccionPaso3">
        <div class="card-header bg-white">
            <h6 class="mb-0">
                <span class="numero-paso bg-secondary text-white me-2" id="numeroPaso3">3</span>
                Subir PDF Firmado con GAUDI
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle-fill"></i>
                Suba únicamente el PDF que generó y firmó con GAUDI. El sistema validará automáticamente 
                que la firma es auténtica y corresponde a este documento.
                <br><strong>El PDF tiene vigencia de 24 horas desde su generación.</strong>
            </div>

            <div id="zonaSubida">
                <label for="inputPdfFirmado" class="form-label fw-bold">Seleccionar PDF firmado:</label>
                <input type="file" class="form-control mb-3" id="inputPdfFirmado"
                       accept=".pdf" <?php echo ($total_activos > 0) ? '' : 'disabled'; ?>>
                <button type="button" id="btnSubirPdf" class="btn btn-success btn-lg" disabled>
                    <i class="bi bi-cloud-upload"></i> Subir y Procesar Certificación
                </button>
                <div id="estadoSubida" class="mt-2" style="display:none;"></div>
            </div>
        </div>
    </div>

</div>

<!-- Modal de resultado -->
<div class="modal fade" id="modalResultado" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="resultadoHeader">
                <h5 class="modal-title" id="resultadoTitulo"></h5>
            </div>
            <div class="modal-body" id="resultadoMensaje"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btnCerrarResultado">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
// ── Estado de revisión ───────────────────────────────────────────
let revisados    = {};
let observaciones = {};

function desaparecidosConfirmados() {
    const chk = document.getElementById('confirmarDesaparecidos');
    if (!chk) {
        return true;
    }
    return chk.checked;
}

document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.checkbox-revisado');
    const inputsObs  = document.querySelectorAll('.observacion-input');

    checkboxes.forEach(cb => {
        const placa = cb.dataset.placa;
        revisados[placa] = false;
        cb.addEventListener('change', function () {
            revisados[placa] = this.checked;
            actualizarContador();
        });
    });

    inputsObs.forEach(inp => {
        const placa = inp.dataset.placa;
        observaciones[placa] = '';
        inp.addEventListener('input', function () {
            observaciones[placa] = this.value;
        });
    });

    const chkDesaparecidos = document.getElementById('confirmarDesaparecidos');
    if (chkDesaparecidos) {
        chkDesaparecidos.addEventListener('change', function () {
            actualizarContador();
        });
    }

    actualizarContador();
});

function actualizarContador() {
    const total   = Object.keys(revisados).length;
    const marcados = Object.values(revisados).filter(v => v).length;

    const todosOk = (total > 0 && marcados === total);

    document.getElementById('contadorRevisados').textContent = marcados;

    const btn2 = document.getElementById('btnGenerarPdf');

    btn2.disabled =
        !todosOk ||
        !desaparecidosConfirmados();

    // Actualizar aspecto de tarjeta de paso
    if (
        todosOk &&
        total > 0 &&
        desaparecidosConfirmados()
    ) {
        document.getElementById('tarjetaPaso1').className = 'card h-100 paso-completado border-success';
        document.getElementById('tarjetaPaso2').className = 'card h-100 paso-activo border-primary';
        document.getElementById('numeroPaso2').className  = 'numero-paso bg-primary text-white me-2';
    }
}

// ── Recopilar datos de activos ───────────────────────────────────
function obtenerDatosActivos() {
    return Array.from(document.querySelectorAll('#tablaActivos tbody tr'))
        .filter(tr => tr.querySelector('.checkbox-revisado'))
        .map(tr => {
            const cb = tr.querySelector('.checkbox-revisado');
            const placa = cb.dataset.placa;
            return {
                placa:       placa,
                serial:      cb.dataset.serial,
                estado:      cb.dataset.estado,
                ubicacion:   cb.dataset.ubicacion,
                revisado:    cb.checked ? 'TRUE' : 'FALSE',
                observacion: observaciones[placa] || '',
            };
        });
}

// ── PASO 2: Generar y descargar PDF ─────────────────────────────
document.getElementById('btnGenerarPdf').addEventListener('click', async function () {
    const btn   = this;
    const spin  = document.getElementById('estadoGeneracion');
    const ok    = document.getElementById('pdfGenerado');

    btn.disabled = true;
    spin.style.display = 'inline-flex';
    ok.style.display   = 'none';

    const activos = obtenerDatosActivos();

    // Construir FormData para POST
    const fd = new FormData();
    fd.append('id_fondos',    '<?php echo $id_fondos; ?>');
    fd.append('codigo_centro','<?php echo addslashes($codigo_centro); ?>');
    fd.append('activos_json', JSON.stringify(activos));

    try {
        const resp = await fetch('navegar.php?ruta=generar_pdf_certificacion.php', {
            method: 'POST',
            body:   fd,
        });

        if (!resp.ok) {
            // El servidor devolvió error JSON
            const err = await resp.json().catch(() => ({ message: `HTTP ${resp.status}` }));
            throw new Error(err.message || `Error ${resp.status}`);
        }

        // Es un PDF — descargarlo
        const blob     = await resp.blob();
        const url      = URL.createObjectURL(blob);
        const anchor   = document.createElement('a');
        const cd       = resp.headers.get('Content-Disposition') || '';
        const match    = cd.match(/filename="?([^"]+)"?/);
        anchor.href     = url;
        anchor.download = match ? match[1] : 'certificacion.pdf';
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);

        spin.style.display = 'none';
        ok.style.display   = 'block';

        // Avanzar a paso 3
        document.getElementById('tarjetaPaso2').className = 'card h-100 paso-completado border-success';
        document.getElementById('tarjetaPaso3').className = 'card h-100 paso-activo border-primary';
        document.getElementById('numeroPaso3').className  = 'numero-paso bg-primary text-white me-2';
        document.getElementById('inputPdfFirmado').disabled = false;

    } catch (e) {
        spin.style.display = 'none';
        btn.disabled = false;
        alert('Error al generar el PDF: ' + e.message);
    }
});

// ── PASO 3: Habilitar botón subir cuando hay archivo ────────────
document.getElementById('inputPdfFirmado').addEventListener('change', function () {
    document.getElementById('btnSubirPdf').disabled = !this.files.length;
});

// ── PASO 3: Subir PDF firmado ────────────────────────────────────
document.getElementById('btnSubirPdf').addEventListener('click', async function () {
    const btn      = this;
    const inputFile = document.getElementById('inputPdfFirmado');
    const estado   = document.getElementById('estadoSubida');

    if (!inputFile.files.length) return;

    btn.disabled = true;
    estado.style.display = 'block';
    estado.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <strong>Procesando PDF firmado...</strong>';

    const fd = new FormData();
    fd.append('pdf_firmado', inputFile.files[0]);

    try {
        const resp   = await fetch('navegar.php?ruta=subir_certificacion.php', { method: 'POST', body: fd });
        const result = await resp.json();

        estado.style.display = 'none';

        const modalResultado = new bootstrap.Modal(document.getElementById('modalResultado'));

        if (result.success) {
            document.getElementById('resultadoHeader').className  = 'modal-header bg-success text-white';
            document.getElementById('resultadoTitulo').innerHTML  = '<i class="bi bi-check-circle"></i> Certificación Exitosa';
            document.getElementById('resultadoMensaje').innerHTML = `
                <div class="text-center">
                    <i class="bi bi-file-check fs-1 text-success mb-3 d-block"></i>
                    <p><strong>${result.message}</strong></p>
                    <p class="small text-muted">${result.total_activos} activo(s) registrados.</p>
                    <p class="small text-muted">Fecha: ${result.fecha}</p>
                    <p class="small text-muted">UUID: ${result.uuid}</p>
                    <hr>
                    <p class="small text-success">
                        <i class="bi bi-shield-check"></i>
                        Firma digital GAUDI verificada y registrada correctamente.
                    </p>
                </div>`;
            document.getElementById('btnCerrarResultado').onclick = () => {
                modalResultado.hide();
                window.location.href = 'navegar.php?ruta=centro_de_certificacion.php';
            };
        } else {
            document.getElementById('resultadoHeader').className  = 'modal-header bg-danger text-white';
            document.getElementById('resultadoTitulo').innerHTML  = '<i class="bi bi-exclamation-triangle"></i> Error';
            document.getElementById('resultadoMensaje').innerHTML = `
                <div class="text-center">
                    <i class="bi bi-x-circle fs-1 text-danger mb-3 d-block"></i>
                    <p><strong>${result.message}</strong></p>
                    <p class="small text-muted">Verifique que el PDF es el correcto y fue firmado con GAUDI.</p>
                </div>`;
            btn.disabled = false;
        }

        modalResultado.show();

    } catch (e) {
        estado.style.display = 'none';
        btn.disabled = false;
        alert('Error de red al subir el PDF: ' + e.message);
    }
});
</script>
</body>
</html>
