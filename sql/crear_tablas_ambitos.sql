-- ============================================================
-- Script de Creación: Tablas para Ámbitos de Préstamo
-- Sistema: TecnoPresta
-- Versión: 2.0
-- Fecha: 2026-08-12
-- Motor: MariaDB 10.4
-- ============================================================
-- Los ámbitos (categorías) agrupan uno o más lugares de t_lugar
-- y se asignan a un prestador (usuarios_roles con rol_id = 3).
-- Cada ámbito pertenece a una institución (t_instituciones).
-- Los ámbitos de una institución NO son visibles en otras.
-- ============================================================

-- ============================================================
-- 1. CREAR TABLA: t_ambitos_prestador
-- ============================================================
-- Un ámbito pertenece a:
--   - usuarios_roles (prestador en un centro)  -> usuarios_roles.id
--   - t_instituciones (centro educativo)       -> t_instituciones.id_ins
-- Un prestador puede tener VARIOS ámbitos en el mismo centro.

CREATE TABLE IF NOT EXISTS `t_ambitos_prestador` (
  `id_ambito` INT(11) NOT NULL AUTO_INCREMENT,
  `usuarios_roles_id` INT(10) UNSIGNED NOT NULL COMMENT 'FK a usuarios_roles.id (prestador en un centro)',
  `id_instituciones` INT(11) NOT NULL COMMENT 'FK a t_instituciones.id_ins (institución del ámbito)',
  `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre del ámbito (ej: Equipo INCO)',
  `eliminado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=activo, 1=eliminado',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id_ambito`),
  KEY `idx_ur` (`usuarios_roles_id`),
  KEY `idx_instituciones` (`id_instituciones`),

  CONSTRAINT `fk_ambitos_ur`
    FOREIGN KEY (`usuarios_roles_id`) REFERENCES `usuarios_roles` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT `fk_ambitos_ins`
    FOREIGN KEY (`id_instituciones`) REFERENCES `t_instituciones` (`id_ins`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ámbitos de préstamo (categorías) de prestadores por centro';

-- ============================================================
-- 2. CREAR TABLA: t_ambitos_prestador_lugar
-- ============================================================
-- Relación N:N entre ámbitos y lugares de t_lugar.
-- Un ámbito puede tener múltiples lugares.
-- Un lugar puede pertenecer a múltiples ámbitos.

CREATE TABLE IF NOT EXISTS `t_ambitos_prestador_lugar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ambito_id` INT(11) NOT NULL COMMENT 'FK a t_ambitos_prestador.id_ambito',
  `lugar_id` INT(11) NOT NULL COMMENT 'FK a t_lugar.id_lugar',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de asignación',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ambito_lugar` (`ambito_id`, `lugar_id`) COMMENT 'Un lugar no se repite en el mismo ámbito',
  KEY `idx_lugar` (`lugar_id`) COMMENT 'Índice para búsquedas por lugar',

  CONSTRAINT `fk_ambitos_lugar_ambito`
    FOREIGN KEY (`ambito_id`) REFERENCES `t_ambitos_prestador` (`id_ambito`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT `fk_ambitos_lugar_lugar`
    FOREIGN KEY (`lugar_id`) REFERENCES `t_lugar` (`id_lugar`)
      ON DELETE RESTRICT
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lugares pertenecientes a cada ámbito de préstamo';

-- ============================================================
-- 3. VISTA: v_activos_por_prestador
-- ============================================================
-- Activos (t_placa) prestables para cada prestador según sus ámbitos.
-- Se vincula por t_placa (codigo = institución, id_lugar = lugar).
-- Un activo se considera prestable si: prestar = 1 y activo = 1.

CREATE OR REPLACE VIEW `v_activos_por_prestador` AS
SELECT
  ur.id AS usuarios_roles_id,
  ur.usuario_id,
  ur.rol_id,
  ur.codigo_presu,
  u.cedula,
  u.nombre AS usuario_nombre,
  ap.id_ambito,
  ap.nombre AS nombre_ambito,
  p.id_placa,
  p.placa,
  p.serial,
  p.codigo,
  p.id_lugar,
  l.lugar,
  a.id_activo,
  a.modelo
FROM `usuarios_roles` ur
INNER JOIN `usuarios` u ON u.id = ur.usuario_id
INNER JOIN `t_ambitos_prestador` ap ON ap.usuarios_roles_id = ur.id AND ap.eliminado = 0
INNER JOIN `t_ambitos_prestador_lugar` apl ON apl.ambito_id = ap.id_ambito
INNER JOIN `t_lugar` l ON l.id_lugar = apl.lugar_id
INNER JOIN `t_placa` p ON p.id_lugar = l.id_lugar
  AND p.codigo = ur.codigo_presu
  AND p.prestar = 1
  AND p.activo = 1
LEFT JOIN `t_activo` a ON a.id_activo = p.id_activo
WHERE ur.rol_id = 3
  AND ur.eliminado = 0;

-- ============================================================
-- 4. VALIDACIONES POSTERIORES
-- ============================================================

-- Verificar que las tablas existen
-- SHOW TABLES LIKE 't_ambitos_prestador%';

-- Verificar índices y FKs
-- SHOW INDEX FROM t_ambitos_prestador;
-- SHOW CREATE TABLE t_ambitos_prestador_lugar;

-- Verificar que no haya prestadores sin ámbito asignado
-- SELECT ur.id, u.nombre, ur.codigo_presu
-- FROM usuarios_roles ur
-- INNER JOIN usuarios u ON u.id = ur.usuario_id
-- WHERE ur.rol_id = 3
--   AND ur.eliminado = 0
--   AND NOT EXISTS (
--     SELECT 1 FROM t_ambitos_prestador ap
--     WHERE ap.usuarios_roles_id = ur.id AND ap.eliminado = 0
--   );

-- Consulta de ejemplo: ámbitos con sus lugares y cantidad
-- SELECT ap.id_ambito, ap.nombre, ap.id_instituciones, i.codigo, i.institucion,
--        GROUP_CONCAT(l.lugar SEPARATOR ', ') AS lugares,
--        COUNT(apl.lugar_id) AS cantidad_lugares
-- FROM t_ambitos_prestador ap
-- INNER JOIN t_instituciones i ON i.id_ins = ap.id_instituciones
-- LEFT JOIN t_ambitos_prestador_lugar apl ON apl.ambito_id = ap.id_ambito
-- LEFT JOIN t_lugar l ON l.id_lugar = apl.lugar_id
-- WHERE ap.eliminado = 0
-- GROUP BY ap.id_ambito, ap.nombre, ap.id_instituciones, i.codigo, i.institucion;
