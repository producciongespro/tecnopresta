-- ============================================================
-- SCRIPT: Registro del formulario Administracion de Usuarios
-- ============================================================
-- Proposito: Actualizar la ruta del formulario "Usuarios" (id=53)
-- para apuntar a gestor_usuarios_n.php
--
-- Modulo: 16 (Gestion de Usuarios y Acceso)
-- Subsistema: 4 (Administracion del Sistema)
-- ============================================================

-- Actualizar ruta del formulario 53
UPDATE formularios
SET ruta = 'gestor_usuarios_n.php',
    updated_at = NOW()
WHERE id = 53 AND ruta IS NULL;

-- Verificar
SELECT id, modulo_id, nombre, ruta, updated_at
FROM formularios
WHERE id = 53;
