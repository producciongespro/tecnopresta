-- ============================================================
-- Registro: Formulario "Registro de Activos" en modulo "Gestion de Activos"
-- Sistema: TecnoPresta
-- Version: 1.0
-- Fecha: Agosto 2026
-- ============================================================
-- Agrega al modulo "Gestion de Activos" (subsistema Inventario id=1)
-- el formulario de registro nuevo de activos:
--   1. Registro de Activos   (formulario_agregar_nuevo_activo_n.php)
-- con sus permisos asignados al rol Root (id_rol=1).
-- ============================================================

START TRANSACTION;

SET @modulo_id = 1;

-- ============================================================
-- 1. FORMULARIO "REGISTRO DE ACTIVOS"
-- ============================================================
INSERT INTO formularios (modulo_id, nombre, descripcion, ruta, imagen, orden, color, eliminado)
VALUES (@modulo_id, 'Registro de Activos', 'Busqueda y registro de nuevos activos con placa y serial por fondo presupuestario', 'formulario_agregar_nuevo_activo_n.php', NULL, 1, '#003876', 0);
SET @form_registro_id = LAST_INSERT_ID();

-- ============================================================
-- 2. PERMISOS (formulario_id x accion_id)
--    ver(1), crear(2)
-- ============================================================
INSERT INTO permisos (formulario_id, accion_id) VALUES
(@form_registro_id, 1),
(@form_registro_id, 2);

-- ============================================================
-- 3. ASIGNAR PERMISOS AL ROL ROOT (id_rol = 1)
-- ============================================================
INSERT INTO roles_permisos (rol_id, permiso_id)
SELECT 1, p.id
FROM permisos p
WHERE p.formulario_id = @form_registro_id
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
       CONCAT('Formulario creado en modulo Gestion de Activos (ID: ', @modulo_id,
              '): Registro de Activos (ID: ', @form_registro_id, ')') AS formularios_info,
       'Permisos creados y asignados al rol Root (ID: 1)' AS permisos_info;
