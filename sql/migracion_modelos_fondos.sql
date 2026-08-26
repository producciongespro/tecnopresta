-- ============================================================
-- MIGRACIÓN: Poblar catálogo maestro de modelos
-- TecnoPresta - v1.0
-- Fecha: Agosto 2026
-- ============================================================
-- PREREQUISITO: Ejecutar primero sql/esquema_t_modelos.sql
--
-- Descripción:
--   1. Limpia t_modelos (elimina registros de prueba)
--   2. Pobla t_modelos desde combinaciones únicas de t_activo
--   3. Actualiza modelo_id en t_activo (FK → t_modelos)
--   4. Pobla t_modelo_fondos desde datos reales de t_placa
--
-- Seguridad:
--   - Transaccional (rollback si falla)
--   - No altera estructura de ninguna tabla
--   - No elimina datos existentes en t_placa o t_activo
--
-- Producción: Ejecutar en ventana de mantenimiento.
-- Tiempo estimado: < 30 segundos (328K registros en t_placa).
-- ============================================================

-- ============================================================
-- VERIFICACIÓN PREVIA (ejecutar primero, revisar resultados)
-- ============================================================
-- SELECT COUNT(*) AS total_modelos_ant FROM t_modelos;
-- SELECT COUNT(*) AS total_activo_ant FROM t_activo;
-- SELECT COUNT(*) AS total_placa_ant FROM t_placa;
-- SELECT COUNT(*) AS total_modelo_fondos_ant FROM t_modelo_fondos;

START TRANSACTION;

-- ============================================================
-- PASO 1: Limpiar t_modelos (eliminar registros de prueba)
-- ============================================================
DELETE FROM t_modelos;

-- ============================================================
-- PASO 2: Poblar t_modelos desde combinaciones únicas de t_activo
-- Cada fila = (modelo normalizado + id_ag + id_marca) único
-- ============================================================
INSERT INTO t_modelos (modelo, id_ag, id_marca, mdl_elm, created_by)
SELECT
    TRIM(a.modelo)        AS modelo,
    a.id_ag               AS id_ag,
    a.id_marca            AS id_marca,
    0                     AS mdl_elm,
    'MIGRACION'           AS created_by
FROM t_activo a
WHERE TRIM(a.modelo) IS NOT NULL
  AND TRIM(a.modelo) != ''
GROUP BY TRIM(a.modelo), a.id_ag, a.id_marca;

-- ============================================================
-- PASO 3: Actualizar modelo_id en t_activo
-- Solo actualiza registros que encontraron match en t_modelos
-- Los que no encuentran match quedan NULL (sin cambio)
-- ============================================================
UPDATE t_activo a
INNER JOIN t_modelos tm
    ON  TRIM(a.modelo) = tm.modelo
    AND a.id_ag        = tm.id_ag
    AND a.id_marca     = tm.id_marca
SET a.modelo_id = tm.id_modelo;

-- ============================================================
-- PASO 4: Poblar t_modelo_fondos desde datos reales de t_placa
-- Cada (modelo, fondo) que exista en placas se registra
-- ============================================================
INSERT INTO t_modelo_fondos (id_modelo, id_fondos)
SELECT DISTINCT
    a.modelo_id   AS id_modelo,
    p.id_fondos   AS id_fondos
FROM t_placa p
INNER JOIN t_activo a  ON p.id_activo = a.id_activo
WHERE a.modelo_id IS NOT NULL
  AND a.modelo_id > 0
ON DUPLICATE KEY UPDATE id_modelo = VALUES(id_modelo);

COMMIT;

-- ============================================================
-- VERIFICACIÓN POST-MIGRACIÓN
-- ============================================================
-- Modelos creados:
SELECT COUNT(*) AS modelos_creados FROM t_modelos;

-- Activos vinculados vs sin vinculo:
SELECT
    SUM(CASE WHEN modelo_id IS NOT NULL THEN 1 ELSE 0 END) AS activos_vinculados,
    SUM(CASE WHEN modelo_id IS NULL    THEN 1 ELSE 0 END) AS activos_sin_vinculo
FROM t_activo;

-- Relaciones modelo-fondo:
SELECT COUNT(*) AS relaciones_modelo_fondo FROM t_modelo_fondos;

-- Top 10 fondos con más modelos:
SELECT f.fondos, COUNT(mf.id_modelo) AS cantidad_modelos
FROM t_modelo_fondos mf
INNER JOIN t_fondos f ON mf.id_fondos = f.id_fondos
GROUP BY f.fondos
ORDER BY cantidad_modelos DESC
LIMIT 10;
