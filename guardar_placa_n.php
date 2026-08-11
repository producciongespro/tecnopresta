<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once("conexion.php");
$link = $mysqli;

header('Content-Type: application/json');

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener y validar datos
$placa = trim($_POST['placa'] ?? '');
$serial = trim($_POST['serial'] ?? '');
$id_activo = intval($_POST['id_activo'] ?? 0);
$id_fondos = intval($_POST['id_fondos'] ?? 0);
$codigo = $_SESSION['codigo'] ?? '';

// Validaciones básicas
if (empty($placa) || empty($serial) || $id_activo <= 0 || $id_fondos <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Todos los campos son requeridos',
        'details' => [
            'placa' => empty($placa) ? 'Falta la placa' : 'OK',
            'serial' => empty($serial) ? 'Falta el serial' : 'OK',
            'id_activo' => $id_activo <= 0 ? 'ID de activo inválido' : 'OK',
            'id_fondos' => $id_fondos <= 0 ? 'ID de fondos inválido' : 'OK'
        ]
    ]);
    exit();
}

// Verificar si la placa ya existe y en qué departamento
$stmt_check = $link->prepare("SELECT id_placa, codigo FROM t_placa WHERE placa = ?");
$stmt_check->bind_param("s", $placa);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $mismo_departamento = ($row['codigo'] == $codigo);
    
    echo json_encode([
        'success' => false, 
        'message' => 'La placa ya existe en el sistema',
        'details' => [
            'placa_existente' => true,
            'en_este_departamento' => $mismo_departamento,
            'mensaje_departamento' => $mismo_departamento 
                ? 'La placa ya existe en este centro' 
                : 'La placa existe en otro centro'
        ]
    ]);
    exit();
}
$stmt_check->close();

// Verificar si el serial ya existe y en qué departamento
$stmt_check = $link->prepare("SELECT id_placa, codigo FROM t_placa WHERE serial = ?");
$stmt_check->bind_param("s", $serial);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $mismo_departamento = ($row['codigo'] == $codigo);
    
    echo json_encode([
        'success' => false, 
        'message' => 'El serial ya existe en el sistema',
        'details' => [
            'serial_existente' => true,
            'en_este_departamento' => $mismo_departamento,
            'mensaje_departamento' => $mismo_departamento 
                ? 'El serial ya existe en este centro' 
                : 'El serial existe en otro centro'
        ]
    ]);
    exit();
}
$stmt_check->close();

// Insertar en la base de datos
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
) VALUES (?, ?, ?, ?, 1, 1, 1, ?, 0, 0)";

$stmt = $link->prepare($query);
$stmt->bind_param("ssisi", $placa, $serial, $id_activo, $codigo, $id_fondos);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Activo registrado correctamente',
        'details' => [
            'placa' => $placa,
            'serial' => $serial,
            'departamento' => $codigo
        ]
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al guardar el activo',
        'details' => [
            'error' => $stmt->error,
            'codigo_error' => $stmt->errno
        ]
    ]);
}

$stmt->close();
$link->close();
?>