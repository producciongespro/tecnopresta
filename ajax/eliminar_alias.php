<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$logcodigo = $usuario_azure['codigoPresu'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$alias_id = isset($_POST['alias_id']) ? intval($_POST['alias_id']) : 0;

if ($alias_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identificador de alias inválido']);
    exit();
}

require_once dirname(__DIR__) . '/conexion.php';
$link = $mysqli;
mysqli_set_charset($link, "utf8");

// Iniciar transacción para consistencia de datos
mysqli_begin_transaction($link);

try {
    // 1. Eliminar el alias de t_alias
    $sql_delete = "DELETE FROM t_alias WHERE alias_id = ? AND codigo = ?";
    $stmt_del = mysqli_prepare($link, $sql_delete);
    if (!$stmt_del) {
        throw new Exception("Error al preparar eliminación de alias: " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt_del, "is", $alias_id, $logcodigo);
    if (!mysqli_stmt_execute($stmt_del)) {
        throw new Exception("Error al eliminar alias: " . mysqli_stmt_error($stmt_del));
    }
    mysqli_stmt_close($stmt_del);

    // 2. Poner Alias en cero en t_placa (liberar los elementos vinculados)
    $sql_update = "UPDATE t_placa SET alias_id = 0 WHERE codigo = ? AND alias_id = ?";
    $stmt_upd = mysqli_prepare($link, $sql_update);
    if (!$stmt_upd) {
        throw new Exception("Error al preparar actualización de placas: " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt_upd, "si", $logcodigo, $alias_id);
    if (!mysqli_stmt_execute($stmt_upd)) {
        throw new Exception("Error al actualizar placas: " . mysqli_stmt_error($stmt_upd));
    }
    mysqli_stmt_close($stmt_upd);

    mysqli_commit($link);
    mysqli_close($link);

    echo json_encode([
        'success' => true, 
        'message' => 'Alias eliminado correctamente y placas asociadas liberadas'
    ]);
} catch (Exception $e) {
    mysqli_rollback($link);
    mysqli_close($link);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
