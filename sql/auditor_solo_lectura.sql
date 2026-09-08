-- ============================================================
-- SCRIPT: Rol Auditor (id_rol=12) - Solo lectura global
-- ============================================================
-- Proposito: Otorgar al rol "Auditor" acceso de SOLO LECTURA
-- sobre TODOS los formularios activos del sistema.
--
-- Acciones otorgadas: ver (1), exportar (5), auditar (11)
-- NO otorga: crear (2), editar (3), eliminar (4), importar (6),
--            aprobar (7), asignar (8), cerrar (9), escalar (10)
--
-- Naturaleza: SOLO AGREGA REGISTROS (INSERT IGNORE).
-- No modifica estructura de tablas ni filas existentes.
-- Idempotente: el UNIQUE (formulario_id, accion_id) en permisos
-- y (rol_id, permiso_id) en roles_permisos evita duplicados.
-- ============================================================

-- PASO A: Asegurar permisos de lectura para todos los
-- formularios activos. Agrega las combinaciones faltantes
-- (preview previo: 39 exportar + 2 auditar).
INSERT IGNORE INTO permisos (formulario_id, accion_id)
SELECT f.id, a.id
FROM formularios f
CROSS JOIN acciones a
WHERE a.id IN (1, 5, 11)
  AND f.eliminado = 0;

-- PASO B: Asignar al rol Auditor (12) todos los permisos de
-- lectura existentes sobre formularios activos.
-- Preview previo: 219 filas (73 ver + 73 exportar + 73 auditar).
INSERT IGNORE INTO roles_permisos (rol_id, permiso_id)
SELECT 12, p.id
FROM permisos p
INNER JOIN formularios f ON f.id = p.formulario_id
WHERE p.accion_id IN (1, 5, 11)
  AND f.eliminado = 0;

-- VERIFICACION (solo lectura): conteo esperado = 219
SELECT COUNT(*) AS total_permisos_rol_12
FROM roles_permisos
WHERE rol_id = 12;

-- Detalle por accion: ver=73, exportar=73, auditar=73
SELECT a.nombre AS accion, COUNT(*) AS total
FROM roles_permisos rp
INNER JOIN permisos p ON p.id = rp.permiso_id
INNER JOIN acciones a ON a.id = p.accion_id
WHERE rp.rol_id = 12
GROUP BY a.nombre
ORDER BY MIN(a.id);