<?php
/**
 * ============================================================
 * ENDPOINT: Gestor Catalogo de Lugares - Datos
 * ============================================================
 * Proposito: Retorna JSON con la lista de lugares del catalogo
 * (t_lugar) y la cantidad de activos (placas) asociados a cada
 * lugar.
 *
 * Seguridad:
 * - Valida sesion Azure
 * - Valida permiso de ruta (misma tarjeta "Gestion de Ubicacion")
 * - Prepared statements en consultas SQL
 * ============================================================
 */

// Configurar respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

// Validar sesion y acceso
require_once __DIR__ . '/../usuarioAzure.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/bd.php';

$usuario_azure = obtenerUsuarioSesion();
if (!$usuario_azure) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion invalida']);
    exit;
}

// Aplicar el mismo criterio de acceso que la tarjeta "Gestion de Ubicacion"
if (!usuarioTieneRuta('gestor_catalogo_lugares_n.php')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $conexionBD = BD::crearInstancia();

    // Consultar lugares del catalogo (activos e inactivos, para gestion)
    // y la cantidad de placas (activos fisicos) asociadas a cada lugar.
    $sql = "
        SELECT
            tl.id_lugar,
            tl.lugar,
            tl.activo,
            (SELECT COUNT(*) FROM t_placa p WHERE p.id_lugar = tl.id_lugar) AS activos_asociados
        FROM t_lugar tl
        ORDER BY tl.activo DESC, tl.lugar ASC
    ";
    $stmt = $conexionBD->prepare($sql);
    $stmt->execute();
    $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar tipos numericos
    foreach ($lugares as &$lugar) {
        $lugar['id_lugar'] = (int)$lugar['id_lugar'];
        $lugar['activo'] = (int)$lugar['activo'];
        $lugar['activos_asociados'] = (int)$lugar['activos_asociados'];
    }
    unset($lugar);

    echo json_encode([
        'success' => true,
        'lugares' => $lugares
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}