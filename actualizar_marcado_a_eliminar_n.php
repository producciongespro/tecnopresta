<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    $msg = "Error de conexión a MySQL: " . mysqli_connect_error();
    echo json_encode(['success' => false, 'error' => $msg]); exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    $msg = "Error cargando el conjunto de caracteres utf8";
    echo json_encode(['success' => false, 'error' => $msg]); exit();
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida']); exit();
}

$logusuario = $usuario_azure['cedula'] ?? '';
$logcodigo = $usuario_azure['codigoPresu'] ?? '';
$motivo = isset($_POST['motivo']) ? trim((string) $_POST['motivo']) : '';

$params_ruta = '';
$sid = intval($_POST['subsistema_id'] ?? 0);
$mid = intval($_POST['modulo_id'] ?? 0);
if ($sid && $mid) {
    $params_ruta = '&subsistema_id=' . $sid . '&modulo_id=' . $mid;
}

if (empty($_POST['idsplacas']) || !is_array($_POST['idsplacas'])) {
    echo json_encode(['success' => false, 'error' => 'No hay ningún activo seleccionado']);
    exit();
}

if ($logcodigo === '') {
    echo json_encode(['success' => false, 'error' => 'No se pudo identificar el centro del usuario']);
    exit();
}

$ids = array_values(array_unique(array_filter(array_map('intval', $_POST['idsplacas']), function ($id) {
    return $id > 0;
})));

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'Los activos seleccionados no son válidos']);
    exit();
}

$idsList = implode(',', $ids);

// Verificar que todos los activos existan y pertenezcan al centro del usuario.
$activosPorId = [];
$sqlActivos = "SELECT id_placa, placa, serial, codigo, id_activo
               FROM t_placa
               WHERE id_placa IN ($idsList) AND codigo = ?";
$stmtActivos = $link->prepare($sqlActivos);
if (!$stmtActivos) {
    echo json_encode(['success' => false, 'error' => 'Error al validar los activos: ' . $link->error]);
    exit();
}
$stmtActivos->bind_param("s", $logcodigo);
$stmtActivos->execute();
$resActivos = $stmtActivos->get_result();
if (!$resActivos) {
    echo json_encode(['success' => false, 'error' => 'Error al obtener los activos seleccionados']);
    $stmtActivos->close();
    $link->close();
    exit();
}
while ($row = $resActivos->fetch_assoc()) {
    $activosPorId[(int) $row['id_placa']] = $row;
}
$stmtActivos->close();

if (count($activosPorId) !== count($ids)) {
    echo json_encode(['success' => false, 'error' => 'Uno o más activos no existen o no pertenecen a su centro']);
    exit();
}

// Verificar préstamos activos (prestamo_detalle_devuelto = 0).
$sqlPrestados = "SELECT p.id_placa, p.placa, p.serial,
                        pd.prestamo_Id,
                        pr.prestamo_fechaDevolucion,
                        pr.prestamo_nombre_solicitante
                 FROM t_placa p
                 INNER JOIN t_prestamo_detalle pd
                         ON pd.prestamo_detalle_id_placa = p.id_placa
                        AND pd.prestamo_detalle_devuelto = 0
                 LEFT JOIN t_prestamo pr ON pr.prestamo_Id = pd.prestamo_Id
                 WHERE p.id_placa IN ($idsList) AND p.codigo = ?
                 ORDER BY p.placa, pd.prestamo_Id";
$stmtPrestados = $link->prepare($sqlPrestados);
if (!$stmtPrestados) {
    echo json_encode(['success' => false, 'error' => 'Error al validar los préstamos: ' . $link->error]);
    exit();
}
$stmtPrestados->bind_param("s", $logcodigo);
$stmtPrestados->execute();
$resPrestados = $stmtPrestados->get_result();
if (!$resPrestados) {
    echo json_encode(['success' => false, 'error' => 'Error al validar los préstamos activos']);
    $stmtPrestados->close();
    $link->close();
    exit();
}

$prestados = [];
while ($row = $resPrestados->fetch_assoc()) {
    $idPlaca = (int) $row['id_placa'];
    if (!isset($prestados[$idPlaca])) {
        $prestados[$idPlaca] = [
            'id_placa' => $idPlaca,
            'placa' => $row['placa'],
            'serial' => $row['serial'],
            'prestamos' => [],
        ];
    }

    $prestamoId = (int) $row['prestamo_Id'];
    $fechaDevolucion = $row['prestamo_fechaDevolucion'];
    $clave = $prestamoId . '|' . $fechaDevolucion;

    $prestados[$idPlaca]['prestamos'][$clave] = [
        'prestamo_Id' => $prestamoId,
        'fechaDevolucion' => $fechaDevolucion,
        'solicitante' => $row['prestamo_nombre_solicitante'],
    ];
}
$stmtPrestados->close();

if (!empty($prestados)) {
    $detalles = [];
    foreach ($prestados as $prestado) {
        $placa = htmlspecialchars((string) $prestado['placa'], ENT_QUOTES, 'UTF-8');
        $partes = [];

        foreach ($prestado['prestamos'] as $prestamo) {
            $texto = $prestamo['prestamo_Id'] > 0
                ? 'préstamo #' . $prestamo['prestamo_Id']
                : 'préstamo activo';

            if (!empty($prestamo['fechaDevolucion'])) {
                $timestamp = strtotime((string) $prestamo['fechaDevolucion']);
                if ($timestamp !== false) {
                    $texto .= ', devolución ' . date('d/m/Y', $timestamp);
                }
            }

            if (!empty($prestamo['solicitante'])) {
                $texto .= ', solicitante ' . htmlspecialchars((string) $prestamo['solicitante'], ENT_QUOTES, 'UTF-8');
            }

            $partes[] = $texto;
        }

        $detalles[] = $placa . ' (' . implode('; ', $partes) . ')';
    }

    $mensaje = 'No se puede eliminar porque los siguientes activos están prestados: '
        . implode(', ', $detalles)
        . '. Debe registrar la devolución antes de marcarlos para eliminación.';

    $prestadosSalida = array_map(function ($prestado) {
        $prestado['prestamos'] = array_values($prestado['prestamos']);
        return $prestado;
    }, array_values($prestados));

    echo json_encode([
        'success' => false,
        'error' => $mensaje,
        'prestados' => $prestadosSalida,
    ]);
    $link->close();
    exit();
}

// Marcar y registrar bitácora dentro de una transacción.
$stmtUpdate = null;
$stmtBitacora = null;

try {
    if (!mysqli_begin_transaction($link)) {
        throw new Exception('No se pudo iniciar la transacción');
    }

    $stmtUpdate = $link->prepare("UPDATE t_placa SET marcado = 1 WHERE id_placa = ? AND codigo = ?");
    $stmtBitacora = $link->prepare("INSERT INTO bitacora_eliminados
        (id_placa, placa, serial, codigo, id_activo, usuario, motivo)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    if (!$stmtUpdate || !$stmtBitacora) {
        throw new Exception('No se pudieron preparar las consultas de marcado');
    }

    foreach ($ids as $idPlaca) {
        $activo = $activosPorId[$idPlaca];

        $stmtUpdate->bind_param("is", $idPlaca, $logcodigo);
        if (!$stmtUpdate->execute()) {
            throw new Exception('Error al marcar el activo con ID ' . $idPlaca);
        }

        // Si ya estaba marcado, no se duplica el registro de bitácora.
        if ($stmtUpdate->affected_rows === 0) {
            continue;
        }

        $idActivo = (int) $activo['id_activo'];
        $stmtBitacora->bind_param(
            "isssiss",
            $idPlaca,
            $activo['placa'],
            $activo['serial'],
            $activo['codigo'],
            $idActivo,
            $logusuario,
            $motivo
        );
        if (!$stmtBitacora->execute()) {
            throw new Exception('Error al registrar la bitácora del activo con ID ' . $idPlaca);
        }
    }

    mysqli_commit($link);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    mysqli_rollback($link);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo completar el marcado: ' . $e->getMessage(),
    ]);
} finally {
    if ($stmtUpdate) {
        $stmtUpdate->close();
    }
    if ($stmtBitacora) {
        $stmtBitacora->close();
    }
    $link->close();
}
?>
