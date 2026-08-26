-- ============================================================
-- CAMBIO DE ESQUEMA: t_modelos
-- TecnoPresta - v1.0
-- Fecha: Agosto 2026
-- ============================================================
-- Agrega columnas id_ag e id_marca a t_modelos para
-- deduplicación correcta: (modelo, id_ag, id_marca).
--
-- Riesgo: MÍNIMO — tabla de ~12 filas actuales.
-- Tiempo estimado: < 1 segundo en producción.
-- Rollback: Ver sección final.
-- ============================================================

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────
-- 1. Agregar columnas id_ag e id_marca (nullable, seguro)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE t_modelos
    ADD COLUMN id_ag INT NULL DEFAULT NULL AFTER modelo,
    ADD COLUMN id_marca INT NULL DEFAULT NULL AFTER id_ag;

-- ─────────────────────────────────────────────────────────────
-- 2. Actualizar unique constraint
--    Antes: uq_modelo (solo modelo)
--    Ahora: uq_modelo_tipo_marca (modelo + id_ag + id_marca)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE t_modelos
    DROP INDEX uq_modelo,
    ADD UNIQUE KEY uq_modelo_tipo_marca (modelo, id_ag, id_marca);

-- ─────────────────────────────────────────────────────────────
-- 3. Agregar índices para búsquedas por tipo y marca
-- ─────────────────────────────────────────────────────────────
ALTER TABLE t_modelos
    ADD KEY idx_modelos_ag (id_ag),
    ADD KEY idx_modelos_marca (id_marca);

COMMIT;

-- ============================================================
-- ROLLBACK (si algo sale mal)
-- ============================================================
-- ALTER TABLE t_modelos DROP KEY idx_modelos_marca;
-- ALTER TABLE t_modelos DROP KEY idx_modelos_ag;
-- ALTER TABLE t_modelos DROP INDEX uq_modelo_tipo_marca;
-- ALTER TABLE t_modelos ADD UNIQUE KEY uq_modelo (modelo);
-- ALTER TABLE t_modelos DROP COLUMN id_marca;
-- ALTER TABLE t_modelos DROP COLUMN id_ag;

-- ============================================================
-- Verificación post-ejecución
-- ============================================================
-- DESCRIBE t_modelos;
-- SHOW KEYS FROM t_modelos WHERE Key_name != 'PRIMARY';
