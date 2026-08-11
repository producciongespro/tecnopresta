-- ============================================================
-- Registro: Catalogo de Modelos en el modulo "Catalogos" (id=19)
-- Sistema: TecnoPresta
-- Version: 1.1
-- Fecha: Agosto 2026
-- ============================================================
-- Agrega al modulo "Catalogos" (subsistema Administracion id=4)
-- el formulario del catalogo maestro de modelos:
--   1. Gestion de Modelos                 (gestor_catalogo_modelos_n.php)
-- con sus permisos asignados al rol Root (id_rol=1).
-- ============================================================

START TRANSACTION;

SET @modulo_id = 19;

-- ============================================================
-- 1. FORMULARIO "GESTION DE MODELOS"
-- ============================================================
INSERT INTO formularios (modulo_id, nombre, descripcion, ruta, imagen, orden, color, eliminado)
VALUES (@modulo_id, 'Gestion de Modelos', 'CRUD del catalogo maestro de modelos de activos', 'gestor_catalogo_modelos_n.php', NULL, 1, '#003876', 0);
SET @form_modelos_id = LAST_INSERT_ID();

-- ============================================================
-- 2. PERMISOS (formulario_id x accion_id)
--    ver(1), crear(2), editar(3), eliminar(4), auditar(11)
-- ============================================================
INSERT INTO permisos (formulario_id, accion_id) VALUES
(@form_modelos_id, 1),
(@form_modelos_id, 2),
(@form_modelos_id, 3),
(@form_modelos_id, 4),
(@form_modelos_id, 11);

-- ============================================================
-- 3. ASIGNAR PERMISOS AL ROL ROOT (id_rol = 1)
-- ============================================================
INSERT INTO roles_permisos (rol_id, permiso_id)
SELECT 1, p.id
FROM permisos p
WHERE p.formulario_id = @form_modelos_id
  AND NOT EXISTS (
      SELECT 1 FROM roles_permisos rp
      WHERE rp.rol_id = 1 AND rp.permiso_id = p.id
  );

-- ============================================================
-- 4. CONFIRMAR TRANSACCION
-- ============================================================
COMMIT;

-- ============================================================
-- Mensaje de confirmacion (visible si se ejecuta desde CLI)
-- ============================================================
SELECT 'Script ejecutado exitosamente' AS resultado,
       CONCAT('Formulario creado en modulo Catalogos (ID: ', @modulo_id,
              '): Gestion de Modelos (ID: ', @form_modelos_id, ')') AS formularios_info,
       'Permisos creados y asignados al rol Root (ID: 1)' AS permisos_info;
