<?php
/**
 * ============================================================
 * FUNCIONES MODELOS (NUEVA VERSION)
 * ============================================================
 * Proposito: Funciones reutilizables para el catalogo maestro de
 * modelos de activos (t_modelos) ligado a t_activo.modelo_id.
 *
 * Uso: require_once 'funciones_modelos_n.php';
 *
 * Depende de: conexion.php (variable global $mysqli)
 * ============================================================
 */

require_once __DIR__ . '/conexion.php';

/**
 * Normaliza el texto de un modelo para su uso como clave canonica.
 * - Trim de espacios al inicio/fin
 * - Colapsa espacios consecutivos en uno solo
 * - Aplica utf8mb4 para uniformidad
 *
 * @param string|null $texto
 * @return string
 */
function normalizarModelo($texto)
{
    $texto = trim((string)$texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);
    return $texto;
}

/**
 * Obtiene el id_modelo existente para un texto canonico, o null si no existe.
 *
 * @param mysqli $link
 * @param string $modelo Texto canonico del modelo
 * @return int|null
 */
function obtenerIdModelo($link, $modelo)
{
    $modelo = normalizarModelo($modelo);
    if ($modelo === '') {
        return null;
    }

    $stmt = $link->prepare("SELECT id_modelo FROM t_modelos WHERE modelo = ? LIMIT 1");
    if (!$stmt) {
        error_log('[funciones_modelos_n] Error preparando SELECT: ' . $link->error);
        return null;
    }
    $stmt->bind_param('s', $modelo);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id_modelo);
    $stmt->fetch();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    return $existe ? (int)$id_modelo : null;
}

/**
 * Obtiene o crea el modelo en t_modelos (upsert por texto canonico).
 * Si el modelo ya existe (aunque este marcado como eliminado) lo reactiva.
 *
 * @param mysqli      $link
 * @param string      $modelo    Texto del modelo (se normaliza internamente)
 * @param string|null $created_by Identificador del usuario que lo crea
 * @return int|null id_modelo o null en caso de error
 */
function obtenerOCrearModelo($link, $modelo, $created_by = null)
{
    $modelo = normalizarModelo($modelo);
    if ($modelo === '') {
        return null;
    }

    // 1. Buscar el modelo existente
    $stmt = $link->prepare("SELECT id_modelo, mdl_elm FROM t_modelos WHERE modelo = ? LIMIT 1");
    if (!$stmt) {
        error_log('[funciones_modelos_n] Error preparando SELECT: ' . $link->error);
        return null;
    }
    $stmt->bind_param('s', $modelo);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id_modelo, $mdl_elm);
    $stmt->fetch();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    if ($existe) {
        // 2a. Si estaba eliminado, reactivarlo
        if ((int)$mdl_elm === 1) {
            $stmtU = $link->prepare("UPDATE t_modelos SET mdl_elm = 0, updated_at = NOW(), created_by = COALESCE(?, created_by) WHERE id_modelo = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $created_by, $id_modelo);
                $stmtU->execute();
                $stmtU->close();
            }
        }
        return (int)$id_modelo;
    }

    // 2b. Insertar nuevo modelo
    $stmtI = $link->prepare("INSERT INTO t_modelos (modelo, mdl_elm, created_by) VALUES (?, 0, ?)");
    if (!$stmtI) {
        error_log('[funciones_modelos_n] Error preparando INSERT: ' . $link->error);
        return null;
    }
    $stmtI->bind_param('ss', $modelo, $created_by);
    if ($stmtI->execute()) {
        $nuevoId = (int)$stmtI->insert_id;
        $stmtI->close();
        return $nuevoId;
    }

    // 2c. Condicion de carrera: otro proceso inserto el mismo modelo justo ahora
    if ($stmtI->errno === 1062) { // Duplicate entry
        $stmtI->close();
        $stmt = $link->prepare("SELECT id_modelo FROM t_modelos WHERE modelo = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $modelo);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id_modelo);
        $stmt->fetch();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe ? (int)$id_modelo : null;
    }

    $error = $stmtI->error;
    $stmtI->close();
    error_log('[funciones_modelos_n] Error insertando modelo: ' . $error);
    return null;
}

/**
 * Obtiene el texto canonico de un modelo a partir de su id_modelo.
 *
 * @param mysqli $link
 * @param int    $id_modelo
 * @return string|null
 */
function obtenerModeloPorId($link, $id_modelo)
{
    $id_modelo = (int)$id_modelo;
    if ($id_modelo <= 0) {
        return null;
    }

    $stmt = $link->prepare("SELECT modelo FROM t_modelos WHERE id_modelo = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id_modelo);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($modelo);
    $stmt->fetch();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    return $existe ? $modelo : null;
}
