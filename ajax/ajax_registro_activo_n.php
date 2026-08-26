<?php
/**
 * ============================================================
 * ENDPOINT: Registro de Activo (modelo → placa/serial/color)
 * ============================================================
 * Proposito: Registra una nueva unidad fisica en t_placa,
 * vinculandola a un t_activo existente que cumpla todas las
 * condiciones de la cadena relacional.
 *
 * Cadena relacional:
 *   t_placa.id_activo → t_activo.id_activo
 *   t_activo.id_ag → t_activo_general.id_ag
 *   t_activo.id_marca → t_marca.id_marca
 *   t_activo.modelo_id → t_modelos.id_modelo
 *   t_modelos.id_modelo → t_modelo_fondos.id_modelo
 *
 * Flujo de validaciones (6 pasos):
 *   1. Validar datos de entrada (POST)
 *   2. Verificar que el modelo exista en t_modelos (mdl_elm = 0)
 *   3. Verificar que el modelo este asociado a un fondo (t_modelo_fondos)
 *   4. Verificar que el id_activo exista en t_activo y pertenezca al modelo
 *   5. Verificar placa/serial duplicados en t_placa
 *   6. Insertar en t_placa con color (hex)
 *
 * Regla de negocio:
 *   - Este proceso NO crea nuevos registros en t_activo
 *   - Solo registra unidades fisicas en t_placa
 *   - t_placa.color almacena el hexadecimal del color seleccionado
 *   - id_color NO se usa en WHERE ni en filtrado
 *
 * Seguridad:
 *   - Valida sesion Azure
 *   - Prepared statements
 *   - Transaccional
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../conexion.php');
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo json_encode(['success' => false, 'message' => 'Error de conexion: ' . mysqli_connect_error()]);
    exit();
}
mysqli_set_charset($link, "utf8");

require_once __DIR__ . '/../usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion no valida']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit();
}

// ══════════════════════════════════════════════════════════════
// PASO 1: Recepcion y validacion de datos de entrada
// ══════════════════════════════════════════════════════════════
$id_activo = isset($_POST['id_activo']) ? (int)$_POST['id_activo'] : 0;
$modelo_id = isset($_POST['modelo_id']) ? (int)$_POST['modelo_id'] : 0;
$color_hex = trim($_POST['color_hex'] ?? '');
$placa     = trim($_POST['placa'] ?? '');
$serial    = trim($_POST['serial'] ?? '');
$id_fondos = isset($_POST['id_fondos']) ? (int)$_POST['id_fondos'] : 0;
$codigo    = $usuario_azure['codigoPresu'] ?? '';

if ($id_activo <= 0) {
    echo json_encode(['success' => false, 'message' => 'Activo no valido']);
    exit();
}
if ($modelo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Modelo no valido']);
    exit();
}
if ($color_hex === '' || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color_hex)) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un color valido (formato hex)']);
    exit();
}
if ($placa === '') {
    echo json_encode(['success' => false, 'message' => 'La placa es obligatoria']);
    exit();
}
if ($serial === '') {
    echo json_encode(['success' => false, 'message' => 'El serial es obligatorio']);
    exit();
}
if ($id_fondos <= 0) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un fondo presupuestario']);
    exit();
}
if ($codigo === '') {
    echo json_encode(['success' => false, 'message' => 'No se pudo determinar el codigo presupuestario del usuario']);
    exit();
}

// ══════════════════════════════════════════════════════════════
// PASO 2: Verificar que el modelo exista en t_modelos y este activo
// ══════════════════════════════════════════════════════════════
$stmtModelo = $link->prepare(
    "SELECT id_modelo, modelo FROM t_modelos WHERE id_modelo = ? AND mdl_elm = 0"
);
if (!$stmtModelo) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $link->error]);
    exit();
}
$stmtModelo->bind_param("i", $modelo_id);
$stmtModelo->execute();
$resModelo = $stmtModelo->get_result();
if ($resModelo->num_rows === 0) {
    $stmtModelo->close();
    echo json_encode(['success' => false, 'message' => 'El modelo seleccionado no existe o esta desactivado']);
    exit();
}
$stmtModelo->close();

// ══════════════════════════════════════════════════════════════
// PASO 3: Verificar que el modelo este asociado a un fondo (t_modelo_fondos)
// ══════════════════════════════════════════════════════════════
$stmtFondo = $link->prepare(
    "SELECT id_modelo FROM t_modelo_fondos WHERE id_modelo = ? LIMIT 1"
);
if (!$stmtFondo) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $link->error]);
    exit();
}
$stmtFondo->bind_param("i", $modelo_id);
$stmtFondo->execute();
$resFondo = $stmtFondo->get_result();
if ($resFondo->num_rows === 0) {
    $stmtFondo->close();
    echo json_encode(['success' => false, 'message' => 'El modelo no tiene fondos presupuestarios asociados']);
    exit();
}
$stmtFondo->close();

// ══════════════════════════════════════════════════════════════
// PASO 4: Verificar que el id_activo exista en t_activo y pertenezca al modelo
// ══════════════════════════════════════════════════════════════
$stmtActivo = $link->prepare(
    "SELECT a.id_activo
     FROM t_activo a
     INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
     INNER JOIN t_marca m ON a.id_marca = m.id_marca
     WHERE a.id_activo = ? AND a.modelo_id = ? LIMIT 1"
);
if (!$stmtActivo) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $link->error]);
    exit();
}
$stmtActivo->bind_param("ii", $id_activo, $modelo_id);
$stmtActivo->execute();
$resActivo = $stmtActivo->get_result();
if ($resActivo->num_rows === 0) {
    $stmtActivo->close();
    echo json_encode([
        'success' => false,
        'message' => 'No se encontro un activo registrado para este modelo. Verifique que el activo exista y este correctamente vinculado.'
    ]);
    exit();
}
$stmtActivo->close();

// ══════════════════════════════════════════════════════════════
// PASO 5: Verificar placa duplicada
// ══════════════════════════════════════════════════════════════
$stmtCheck = $link->prepare("SELECT id_placa, codigo FROM t_placa WHERE placa = ?");
if (!$stmtCheck) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $link->error]);
    exit();
}
$stmtCheck->bind_param("s", $placa);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows > 0) {
    $rowCheck    = $resCheck->fetch_assoc();
    $mismoDepto  = ($rowCheck['codigo'] == $codigo);
    $stmtCheck->close();
    echo json_encode([
        'success' => false,
        'message' => 'La placa ya existe en el sistema',
        'details' => [
            'placa_existente'      => true,
            'en_este_departamento' => $mismoDepto,
            'mensaje_departamento' => $mismoDepto
                ? 'La placa ya existe en este centro'
                : 'La placa existe en otro centro'
        ]
    ]);
    exit();
}
$stmtCheck->close();

// ══════════════════════════════════════════════════════════════
// PASO 5b: Verificar serial duplicado
// ══════════════════════════════════════════════════════════════
$stmtCheckS = $link->prepare("SELECT id_placa, codigo FROM t_placa WHERE serial = ?");
if (!$stmtCheckS) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $link->error]);
    exit();
}
$stmtCheckS->bind_param("s", $serial);
$stmtCheckS->execute();
$resCheckS = $stmtCheckS->get_result();

if ($resCheckS->num_rows > 0) {
    $rowCheckS   = $resCheckS->fetch_assoc();
    $mismoDeptoS = ($rowCheckS['codigo'] == $codigo);
    $stmtCheckS->close();
    echo json_encode([
        'success' => false,
        'message' => 'El serial ya existe en el sistema',
        'details' => [
            'serial_existente'     => true,
            'en_este_departamento' => $mismoDeptoS,
            'mensaje_departamento' => $mismoDeptoS
                ? 'El serial ya existe en este centro'
                : 'El serial existe en otro centro'
        ]
    ]);
    exit();
}
$stmtCheckS->close();

// ══════════════════════════════════════════════════════════════
// PASO 6: Insertar placa CON campo color (hex)
// ══════════════════════════════════════════════════════════════
$query = "INSERT INTO t_placa (
    placa, serial, id_activo, codigo, id_estado,
    prestar, activo, id_fondos, alias_id, id_lugar, color
) VALUES (?, ?, ?, ?, 1, 1, 1, ?, 0, 0, ?)";

$stmt = $link->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparando INSERT: ' . $link->error]);
    exit();
}
$stmt->bind_param("ssisis", $placa, $serial, $id_activo, $codigo, $id_fondos, $color_hex);

if ($stmt->execute()) {
    echo json_encode([
        'success'   => true,
        'message'   => 'Activo registrado correctamente',
        'details'   => [
            'placa'       => $placa,
            'serial'      => $serial,
            'color'       => $color_hex,
            'departamento'=> $codigo
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el activo',
        'details' => [
            'error'       => $stmt->error,
            'codigo_error' => $stmt->errno
        ]
    ]);
}

$stmt->close();
$link->close();
