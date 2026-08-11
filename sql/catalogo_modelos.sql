-- ============================================================
-- Fase 1: Catalogo de Modelos de Activos Tecnologicos
-- Sistema: TecnoPresta
-- Fecha: Agosto 2026
-- Descripcion:
--   1. Respaldo de t_activo y t_modelos_sugeridos
--   2. Tabla maestra t_modelos
--   3. Tabla puente t_modelo_fondos (0..N fuentes por modelo)
--   4. Columna t_activo.modelo_id (sin FK por legado)
--   5. Vista v_activo_modelo
--   6. Siembra de los modelos sugeridos existentes
-- ============================================================

START TRANSACTION;

-- ============================================================
-- 1. RESPALDOS
-- ============================================================
SET @backup_activo = CONCAT('t_activo_backup_', DATE_FORMAT(NOW(), '%Y%m%d'));
SET @sql1 = CONCAT('CREATE TABLE ', @backup_activo, ' AS SELECT * FROM t_activo');
PREPARE st1 FROM @sql1;
EXECUTE st1;
DEALLOCATE PREPARE st1;

SET @backup_sug = CONCAT('t_modelos_sugeridos_backup_', DATE_FORMAT(NOW(), '%Y%m%d'));
SET @sql2 = CONCAT('CREATE TABLE ', @backup_sug, ' AS SELECT * FROM t_modelos_sugeridos');
PREPARE st2 FROM @sql2;
EXECUTE st2;
DEALLOCATE PREPARE st2;

-- ============================================================
-- 2. TABLA MAESTRA t_modelos
-- ============================================================
CREATE TABLE IF NOT EXISTS t_modelos (
    id_modelo   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    modelo      varchar(250)  NOT NULL,
    mdl_elm     tinyint       NOT NULL DEFAULT 0,          -- 0 activo / 1 eliminado (soft delete)
    created_at  datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by  varchar(50)   NULL,
    PRIMARY KEY (id_modelo),
    UNIQUE KEY uq_modelo (modelo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. TABLA PUENTE t_modelo_fondos (0..N)
-- ============================================================
CREATE TABLE IF NOT EXISTS t_modelo_fondos (
    id_modelo  INT UNSIGNED NOT NULL,
    id_fondos  int          NOT NULL,
    PRIMARY KEY (id_modelo, id_fondos),
    CONSTRAINT fk_mf_modelo FOREIGN KEY (id_modelo) REFERENCES t_modelos(id_modelo) ON DELETE CASCADE,
    CONSTRAINT fk_mf_fondos FOREIGN KEY (id_fondos) REFERENCES t_fondos(id_fondos) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. COLUMNA modelo_id EN t_activo
-- ============================================================
ALTER TABLE t_activo
    ADD COLUMN modelo_id INT UNSIGNED NULL AFTER modelo,
    ADD KEY idx_modelo_id (modelo_id);

-- ============================================================
-- 5. VISTA v_activo_modelo
-- ============================================================
CREATE OR REPLACE VIEW v_activo_modelo AS
SELECT a.*, COALESCE(tm.modelo, a.modelo) AS modelo_canonico
FROM t_activo a
LEFT JOIN t_modelos tm ON a.modelo_id = tm.id_modelo;

-- ============================================================
-- 6. SIEMBRA DE MODELOS SUGERIDOS
-- ============================================================
INSERT IGNORE INTO t_modelos (modelo, created_by)
SELECT DISTINCT TRIM(modelo), 'sistema'
FROM t_modelos_sugeridos
WHERE modelo IS NOT NULL AND TRIM(modelo) <> '';

-- ============================================================
-- 7. CONFIRMAR
-- ============================================================
COMMIT;

SELECT 'Script ejecutado exitosamente' AS resultado,
       @backup_activo AS respaldo_activo,
       @backup_sug    AS respaldo_sugeridos,
       (SELECT COUNT(*) FROM t_modelos) AS total_modelos;
