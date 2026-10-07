<?php
/**
 * ============================================================
 * ENDPOINT: Gestor Catalogo de Modelos - Datos
 * ============================================================
 * Proposito: Retorna tipos, marcas, modelos y combinaciones
 * creadas en t_activo (agrupadas por id_ag,id_marca,modelo_id).
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

// Solo Root puede acceder a este endpoint
if (!esUsuarioRoot()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $conexionBD = BD::crearInstancia();

    // 1. Tipos de activo (t_activo_general)
    $sqlTipos = "SELECT id_ag, clase FROM t_activo_general ORDER BY clase ASC";
    $stmtTipos = $conexionBD->prepare($sqlTipos);
    $stmtTipos->execute();
    $tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

    // 2. Marcas (t_marca)
    $sqlMarcas = "SELECT id_marca, marca FROM t_marca ORDER BY marca ASC";
    $stmtMarcas = $conexionBD->prepare($sqlMarcas);
    $stmtMarcas->execute();
    $marcas = $stmtMarcas->fetchAll(PDO::FETCH_ASSOC);

    // 3. Modelos (t_modelos)
    $sqlModelos = "SELECT id_modelo, modelo, mdl_elm FROM t_modelos ORDER BY modelo ASC";
    $stmtMod = $conexionBD->prepare($sqlModelos);
    $stmtMod->execute();
    $modelos = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

    // 4. Combinaciones creadas en t_activo (agrupadas por id_ag,id_marca,modelo_id)
    $sqlCombos = "
        SELECT 
            a.id_ag,
            ag.clase,
            a.id_marca,
            m.marca,
            a.modelo_id,
            COALESCE(tm.modelo, '') AS modelo_nombre,
            COUNT(DISTINCT a.id_activo) AS filas_t_activo,
            COUNT(DISTINCT p.id_placa) AS unidades_fisicas
        FROM t_activo a
        LEFT JOIN t_activo_general ag ON ag.id_ag = a.id_ag
        LEFT JOIN t_marca m ON m.id_marca = a.id_marca
        INNER JOIN t_modelos tm ON tm.id_modelo = a.modelo_id
        LEFT JOIN t_placa p ON p.id_activo = a.id_activo
        WHERE (a.modelo_id IS NOT NULL AND a.modelo_id <> 0)
        GROUP BY a.id_ag, ag.clase, a.id_marca, m.marca, a.modelo_id, tm.modelo
        ORDER BY ag.clase ASC, m.marca ASC, tm.modelo ASC
    ";
    $stmtCombos = $conexionBD->prepare($sqlCombos);
    $stmtCombos->execute();
    $combos = $stmtCombos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tipos' => $tipos,
        'marcas' => $marcas,
        'modelos' => $modelos,
        'combos' => $combos
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
