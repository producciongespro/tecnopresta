<?php
/**
 * ============================================================
 * ENDPOINT: Buscar Activos para Registro de Nuevo Activo
 * ============================================================
 * Proposito: Retorna combinaciones distintas de (id_ag, id_marca,
 * modelo_id) de la tabla t_activo, donde los 3 valores existan
 * y sean validos en sus tablas asociadas.
 *
 * Condiciones para mostrar un registro:
 *   1. t_activo.modelo_id IS NOT NULL → existe en t_modelos
 *   2. t_activo.id_ag IS NOT NULL → existe en t_activo_general
 *   3. t_activo.id_marca IS NOT NULL → existe en t_marca
 *   4. t_modelos esta asociado a un fondo (t_modelo_fondos)
 *
 * Cadena relacional:
 *   t_activo.modelo_id → t_modelos.id_modelo → t_modelo_fondos.id_modelo
 *   t_activo.id_ag → t_activo_general.id_ag
 *   t_activo.id_marca → t_marca.id_marca
 *
 * Flujo:
 *   1. Usuario escribe texto (opcional) y/o selecciona fondo (opcional)
 *   2. Endpoint busca con GROUP BY sobre la combinacion unica
 *   3. Retorna combinaciones distintas con tipo, marca, modelo
 *
 * Reglas:
 *   - Fondo es OPCIONAL (default: todos los fondos)
 *   - Solo se muestran combinaciones donde los 3 valores son validos
 *   - id_activo se obtiene como MIN() para uso interno del registro
 *   - id_color NO se usa en WHERE ni en filtrado
 *
 * Seguridad:
 *   - Valida sesion Azure
 *   - Prepared statements
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../conexion.php');
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo json_encode(['success' => false, 'error' => 'Error de conexion a MySQL: ' . mysqli_connect_error()]);
    exit();
}
if (!mysqli_set_charset($link, "utf8")) {
    echo json_encode(['success' => false, 'error' => 'Error cargando conjunto de caracteres utf8']);
    exit();
}

require_once __DIR__ . '/../usuarioAzure.php';
if (!obtenerUsuarioSesion()) {
    echo json_encode(['success' => false, 'error' => 'Sesion no valida']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit();
}

// ── Recepcion de parametros ──
$id_fondos = intval($_POST['id_fondos'] ?? 0);
$busqueda  = trim($_POST['busqueda'] ?? '');

// ─────────────────────────────────────────────────────────────
// Construir query dinamico con filtros opcionales
// ─────────────────────────────────────────────────────────────
$conditions = [
    'tm.mdl_elm = 0',
    'a.modelo_id IS NOT NULL',
    'a.id_ag IS NOT NULL',
    'a.id_marca IS NOT NULL'
];
$params = [];
$types  = '';

// Filtro opcional: fondo presupuestario
if ($id_fondos > 0) {
    $conditions[] = 'mf.id_fondos = ?';
    $params[]     = $id_fondos;
    $types       .= 'i';
}

// Filtro opcional: texto de busqueda (busca en modelo, tipo y marca)
if ($busqueda !== '') {
    $term = "%$busqueda%";
    $conditions[] = '(tm.modelo LIKE ? OR ag.clase LIKE ? OR m.marca LIKE ?)';
    $params[]     = $term;
    $params[]     = $term;
    $params[]     = $term;
    $types       .= 'sss';
}

$whereClause = implode(' AND ', $conditions);

$query = "SELECT
            MIN(a.id_activo) AS id_activo,
            ag.id_ag,
            ag.clase,
            ag.imagen,
            m.id_marca,
            m.marca,
            tm.id_modelo,
            tm.modelo
          FROM t_activo a
          INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
          INNER JOIN t_marca m ON a.id_marca = m.id_marca
          INNER JOIN t_modelos tm ON a.modelo_id = tm.id_modelo
          INNER JOIN t_modelo_fondos mf ON tm.id_modelo = mf.id_modelo
          WHERE $whereClause
          GROUP BY ag.id_ag, ag.clase, ag.imagen, m.id_marca, m.marca, tm.id_modelo, tm.modelo
          ORDER BY ag.clase ASC, m.marca ASC, tm.modelo ASC";

$stmt = $link->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error preparando consulta: ' . $link->error]);
    exit();
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$rows   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success'    => true,
    'resultados' => $rows,
    'total'      => count($rows)
]);
