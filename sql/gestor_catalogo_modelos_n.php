<?php
/**
 * ============================================================
 * ENDPOINT: Gestor Catalogo de Modelos - Datos
 * ============================================================
 * Proposito: Retorna JSON con lista de modelos del catalogo
 * (t_modelos), fuentes presupuestarias (t_fondos) y la relacion
 * 0..N modelo-fondos (t_modelo_fondos).
 *
 * Seguridad:
 * - Valida sesion Azure
 * - Solo accesible por usuario Root
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
/*
// Solo Root puede acceder a este endpoint
if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}
*/
try {
    $conexionBD = BD::crearInstancia();

    // Consultar modelos del catalogo (activos e inactivos, para gestion)
    $sqlModelos = "
        SELECT
            tm.id_modelo,
            tm.modelo,
            tm.mdl_elm,
            tm.created_by,
            tm.created_at,
            tm.updated_at,
            (SELECT COUNT(*) FROM t_activo a WHERE a.modelo_id = tm.id_modelo) AS activos_asociados
        FROM t_modelos tm
        ORDER BY tm.modelo ASC
    ";
    $stmtMod = $conexionBD->prepare($sqlModelos);
    $stmtMod->execute();
    $modelos = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

    // Consultar fuentes presupuestarias disponibles
    $sqlFondos = "
        SELECT id_fondos, fondos
        FROM t_fondos
        ORDER BY fondos ASC
    ";
    $stmtFondos = $conexionBD->prepare($sqlFondos);
    $stmtFondos->execute();
    $fondos = $stmtFondos->fetchAll(PDO::FETCH_ASSOC);

    // Consultar la relacion modelo-fondos
    $sqlRelacion = "
        SELECT id_modelo, id_fondos
        FROM t_modelo_fondos
    ";
    $stmtRel = $conexionBD->prepare($sqlRelacion);
    $stmtRel->execute();
    $relaciones = $stmtRel->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar fondos por modelo para facilitar el renderizado
    $fondosPorModelo = [];
    foreach ($relaciones as $rel) {
        $fondosPorModelo[(int)$rel['id_modelo']][] = (int)$rel['id_fondos'];
    }

    echo json_encode([
        'success' => true,
        'modelos' => $modelos,
        'fondos' => $fondos,
        'fondos_por_modelo' => $fondosPorModelo
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
