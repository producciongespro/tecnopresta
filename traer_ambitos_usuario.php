<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("conexion.php");
require_once __DIR__ . '/usuarioAzure.php';

$link = $mysqli;
$usuario_azure = obtenerUsuarioSesion();

header('Content-Type: application/json; charset=utf-8');

if (!$usuario_azure) {
    echo json_encode(['ambitos' => []]);
    exit;
}

$logcodigo = $usuario_azure['codigoPresu'] ?? '';

$ur_id = isset($_GET['usuarios_roles_id']) ? intval($_GET['usuarios_roles_id']) : 0;
if ($ur_id <= 0) {
    echo json_encode(['ambitos' => []]);
    exit;
}

// Solo se pueden ver ámbitos de prestadores del mismo centro
$check = $link->query("SELECT id FROM usuarios_roles WHERE id = $ur_id AND codigo_presu = '$logcodigo' AND eliminado = 0 LIMIT 1");
if (!$check || $check->num_rows != 1) {
    echo json_encode(['ambitos' => []]);
    exit;
}

$ambitos = [];
$res = $link->query("SELECT id_ambito, nombre FROM t_ambitos_prestador WHERE usuarios_roles_id = $ur_id AND eliminado = 0 ORDER BY id_ambito");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $lugares = [];
        $resL = $link->query("SELECT lugar_id FROM t_ambitos_prestador_lugar WHERE ambito_id = " . intval($row['id_ambito']));
        if ($resL) {
            while ($l = $resL->fetch_assoc()) {
                $lugares[] = intval($l['lugar_id']);
            }
        }
        $ambitos[] = [
            'id_ambito' => intval($row['id_ambito']),
            'nombre'    => $row['nombre'],
            'lugares'   => $lugares,
        ];
    }
}

echo json_encode(['ambitos' => $ambitos], JSON_UNESCAPED_UNICODE);
