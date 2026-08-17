-- ============================================================
-- ESTRUCTURA DE TABLAS DE LA BASE DE DATOS: ltecnopre
-- Sistema: TecnoPresta
-- Recopilado: Agosto 2026
-- ============================================================
-- Nota: Estas estructuras han sido extraídas del análisis de:
-- 1. Archivos SQL: pntm.sql (backup antiguo 2020)
-- 2. Código PHP: insertes, updates y selects en los formularios
-- 3. Configuración de modelos y migraciones
-- ============================================================

-- ============================================================
-- TABLA 1: t_activo
-- Descripción: Activos (equipos tecnológicos) del sistema
-- ============================================================
CREATE TABLE `t_activo` (
  `id_activo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `id_marca` int(4) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `id_color` int(4) NOT NULL,
  `imagen` varchar(50) NOT NULL,
  `alias_id` int(11) NOT NULL,
  `numero_activo` int(11) NOT NULL,
  `id_ag` int(11) NULL,                           -- Referencia a t_activo_general
  `modelo_id` INT UNSIGNED NULL,                  -- Referencia a t_modelos (FK sin aplicar por compatibilidad)
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_activo`),
  KEY `alias_id` (`alias_id`),
  KEY `id_marca` (`id_marca`),
  KEY `id_color` (`id_color`),
  KEY `id_ag` (`id_ag`),
  KEY `idx_modelo_id` (`modelo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA 2: t_lugar
-- Descripción: Lugares/ubicaciones donde se localizan los activos
-- Estructura extraída de referencias en el código:
--   - form_para_ubicar_n.php: SELECT id_lugar, lugar FROM t_lugar
--   - herramienta_editar_ubicacion_activo_n.php: 
--       - SELECT id_lugar, lugar FROM t_lugar ORDER BY id_lugar
--       - Validación de rango: if ($nuevo_id_lugar < 1 || $nuevo_id_lugar > 6)
--   - guardarsp_n.php: INSERT INTO t_placa (..., id_lugar) VALUES (...)
--   - Joins: INNER JOIN t_lugar ON t_placa.id_lugar = t_lugar.id_lugar
-- ============================================================
CREATE TABLE `t_lugar` (
  `id_lugar` int(11) NOT NULL AUTO_INCREMENT,
  `lugar` varchar(255) NOT NULL,
  `descripcion` varchar(500) NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_lugar`),
  UNIQUE KEY `uq_lugar` (`lugar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA 3: usuarios
-- Descripción: Usuarios del sistema (funcionarios)
-- Estructura extraída de:
--   - sql/formulario_principal.php INSERT statement (líneas ~134-151)
--   - sql/guardar_rol_del_usuario_n.php (líneas ~177-182)
--   - UPDATE statement: UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?
--   - Consultas SELECT: SELECT id, cedula, nombre, correo FROM usuarios WHERE cedula = ? LIMIT 1
-- ============================================================
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NULL,
  `azure_id` varchar(255) NULL,                   -- ID de Azure AD
  `sexo` tinyint(1) NULL,                         -- 1=Masculino, 2=Femenino
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` datetime NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cedula` (`cedula`),
  UNIQUE KEY `uq_correo` (`correo`),
  UNIQUE KEY `uq_azure_id` (`azure_id`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA 4: usuarios_roles
-- Descripción: Asignación de roles a usuarios por código presupuestario
-- Estructura extraída de:
--   - sql/formulario_principal.php INSERT statement (líneas ~370-382):
--       INSERT INTO usuarios_roles (usuario_id, rol_id, subsistema_id, codigo_presu, created_at)
--   - sql/guardar_rol_del_usuario_n.php (línea ~159-160)
--   - SELECT statements múltiples que usan:
--       - SELECT DISTINCT u.id, u.rol_id, u.subsistema_id, u.codigo_presu, r.rol
--       FROM usuarios_roles u INNER JOIN t_roles r WHERE eliminado = 0
--       - SELECT ur.id, u.correo, u.cedula, ur.codigo_presu, ur.rol_id 
--       FROM usuarios_roles ur INNER JOIN usuarios u WHERE eliminado = 0
--   - UPDATE: UPDATE usuarios_roles SET rol_id = ?, updated_at = NOW() WHERE ...
-- ============================================================
CREATE TABLE `usuarios_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `subsistema_id` int(11) DEFAULT 1,              -- Subsistema por defecto
  `codigo_presu` varchar(10) NOT NULL,            -- Código presupuestario del centro
  `eliminado` tinyint(1) DEFAULT 0,               -- Soft delete (0=activo, 1=eliminado)
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_rol_centro` (`usuario_id`, `rol_id`, `codigo_presu`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_rol_id` (`rol_id`),
  KEY `idx_codigo_presu` (`codigo_presu`),
  KEY `idx_eliminado` (`eliminado`),
  CONSTRAINT `fk_usuarios_roles_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_roles_rol` FOREIGN KEY (`rol_id`) REFERENCES `t_roles` (`id_rol`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA 5: t_roles
-- Descripción: Roles del sistema con sus permisos y descripciones
-- Estructura extraída de:
--   - Consultas SELECT: SELECT id_rol, rol, descripcion FROM t_roles ORDER BY id_rol
--   - Consultas SELECT: SELECT id_rol, rol FROM t_roles ORDER BY id_rol
--   - sql/select.php: SELECT * FROM t_roles
--   - sql/gestor_roles.php: SELECT id_rol, rol, descripcion FROM t_roles ORDER BY id_rol ASC
--   - Restricción: En eliminar_rol.php, validación de rol_root (1) y rol_adm (2)
--   - Roles válidos para insertar (guardar_rol_del_usuario_n.php): [2, 3, 4, 7]
--     - 2 = Administrador
--     - 3 = Prestador
--     - 4 = Inventariador
--     - 7 = Consultor
--     - 5 = Solicitante (por defecto)
--   - 1 = Root (super administrador)
-- ============================================================
CREATE TABLE `t_roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `rol` varchar(100) NOT NULL,
  `descripcion` varchar(500) NULL,
  `imagen` varchar(255) NULL,
  `color` varchar(7) NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `uq_rol` (`rol`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATOS INICIALES PARA t_roles
-- Basado en validaciones y referencias en el código
-- ============================================================
INSERT INTO `t_roles` (`id_rol`, `rol`, `descripcion`, `activo`) VALUES
(1, 'Root', 'Administrador del Sistema - Acceso Total', 1),
(2, 'Administrador', 'Administrador del Centro Educativo', 1),
(3, 'Prestador', 'Responsable de Préstamos de Activos', 1),
(4, 'Inventariador', 'Responsable del Inventario', 1),
(5, 'Solicitante', 'Usuario que Solicita Activos', 1),
(7, 'Consultor', 'Usuario de Consulta y Reporte', 1);

-- ============================================================
-- INFORMACIÓN ADICIONAL
-- ============================================================
-- Tabla t_placa (referenciada, estructura del pntm.sql):
--   - Contiene: id_placa, placa, serial, id_activo, codigo, id_estado, prestar, id_lugar, etc.
--
-- Relaciones de Llaves Foráneas principales:
--   - usuarios_roles.usuario_id -> usuarios.id
--   - usuarios_roles.rol_id -> t_roles.id_rol
--   - t_activo.id_marca -> t_marca.id_marca
--   - t_activo.id_color -> t_color.id_color
--   - t_activo.alias_id -> t_alias.alias_id
--   - t_placa.id_lugar -> t_lugar.id_lugar
--   - t_placa.id_activo -> t_activo.id_activo
--   - t_activo.modelo_id -> t_modelos.id_modelo (sin FK por legado)
--
-- Códigos Presupuestarios comunes encontrados: 4071, 4040
-- ============================================================
