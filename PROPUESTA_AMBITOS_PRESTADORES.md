# 📋 PROPUESTA DE SOLUCIÓN: ÁMBITOS DE PRESTADORES

## 1. ANÁLISIS DEL PROBLEMA

### Situación Actual
- Los **prestadores** (rol_id=3) se asignan a un centro educativo a través de `usuarios_roles`
- Pueden prestar **TODOS** los activos del centro sin restricción de ubicación
- No existe control por lugar/zona específica

### Situación Deseada
- Cada prestador debe tener **una o varias ubicaciones** (t_lugar) asignadas
- Podrá prestar **SOLO** los activos que estén en esos lugares
- El director/administrador asigna estas ubicaciones y puede nombrar esta agrupación (ej: "Equipo INCO")
- Esta asignación se realiza en `formulario_crear_roles_n.php`

---

## 2. PROPUESTA DE DISEÑO - NUEVA TABLA

### 📌 Nueva Tabla: `t_ambitos_prestador`

```sql
CREATE TABLE `t_ambitos_prestador` (
  `id_ambito` INT(11) NOT NULL AUTO_INCREMENT,
  `usuarios_roles_id` INT(11) NOT NULL,           -- FK a usuarios_roles
  `nombre_ambito` VARCHAR(255) NOT NULL,          -- Ej: "Equipo INCO", "Laboratorio Inglés"
  `descripcion` VARCHAR(500) NULL,                -- Descripción opcional
  `activo` TINYINT(1) DEFAULT 1,                  -- Control de estado
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id_ambito`),
  UNIQUE KEY `uq_usuarios_roles_id` (`usuarios_roles_id`),  -- UN ámbito por prestador-centro
  CONSTRAINT `fk_ambitos_usuarios_roles` 
    FOREIGN KEY (`usuarios_roles_id`) REFERENCES `usuarios_roles` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 📌 Nueva Tabla: `t_ambitos_prestador_lugares`

```sql
CREATE TABLE `t_ambitos_prestador_lugares` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ambito_id` INT(11) NOT NULL,                    -- FK a t_ambitos_prestador
  `lugar_id` INT(11) NOT NULL,                    -- FK a t_lugar
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ambito_lugar` (`ambito_id`, `lugar_id`),  -- No duplicar lugar en mismo ámbito
  CONSTRAINT `fk_ambitos_lugares_ambito` 
    FOREIGN KEY (`ambito_id`) REFERENCES `t_ambitos_prestador` (`id_ambito`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT `fk_ambitos_lugares_lugar` 
    FOREIGN KEY (`lugar_id`) REFERENCES `t_lugar` (`id_lugar`)
      ON DELETE RESTRICT
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. DIAGRAMA DE RELACIONES

```
usuarios (1) ─────┐
                  │
                  └─── (N) usuarios_roles
                          │
                          └─── (1) t_ambitos_prestador
                                  │
                                  └─── (N) t_ambitos_prestador_lugares
                                          │
                                          └─── (N) t_lugar
                                          
t_activo ────────── (1) t_lugar

RELACIÓN FUNCIONAL:
Un Prestador → Asignado a Centro (usuarios_roles)
            → Tiene UN Ámbito (t_ambitos_prestador)
            → Ámbito cubre N Lugares (t_ambitos_prestador_lugares)
            → Puede prestar Activos en esos Lugares
```

---

## 4. QUERIES SQL ÚTILES

### Crear el Ámbito (Al guardar en formulario)
```sql
-- 1. Crear el ámbito
INSERT INTO t_ambitos_prestador (usuarios_roles_id, nombre_ambito, descripcion)
VALUES (@usuarios_roles_id, 'Equipo INCO', 'Equipos del área INCO');

SET @ambito_id = LAST_INSERT_ID();

-- 2. Asociar lugares (valores del array seleccionado)
INSERT INTO t_ambitos_prestador_lugares (ambito_id, lugar_id)
SELECT @ambito_id, lugar_id 
FROM (
  VALUES (1), (3), (5)  -- IDs de lugares seleccionados
) AS lugaresSel(lugar_id);
```

### Obtener Activos que Puede Prestar un Prestador
```sql
SELECT DISTINCT a.id_activo, a.nombre, a.modelo, l.lugar
FROM t_activo a
INNER JOIN t_lugar l ON a.id_lugar = l.id_lugar
INNER JOIN t_ambitos_prestador_lugares apl ON l.id_lugar = apl.lugar_id
INNER JOIN t_ambitos_prestador ap ON apl.ambito_id = ap.id_ambito
INNER JOIN usuarios_roles ur ON ap.usuarios_roles_id = ur.id
WHERE ur.id = @usuario_rol_id
  AND ur.rol_id = 3          -- Rol prestador
  AND ur.eliminado = 0
  AND ap.activo = 1
  AND l.activo = 1;
```

### Validar Límite de Activos por Lugar
```sql
SELECT l.lugar, COUNT(a.id_activo) as cantidad_activos
FROM t_lugar l
LEFT JOIN t_activo a ON l.id_lugar = a.id_lugar
WHERE l.id_lugar IN (
  SELECT lugar_id FROM t_ambitos_prestador_lugares 
  WHERE ambito_id = @ambito_id
)
GROUP BY l.id_lugar, l.lugar;
```

---

## 5. IMPACTO EN OTRAS TABLAS

### 5.1 Tabla `t_activo` - MODIFICACIÓN NECESARIA

**Problema Actual:** La tabla `t_activo` no tiene relación con `t_lugar`

**Solución:**
```sql
-- Agregar columna de relación
ALTER TABLE `t_activo` 
ADD COLUMN `id_lugar` INT(11) NULL AFTER `numero_activo`,
ADD CONSTRAINT `fk_activo_lugar` 
  FOREIGN KEY (`id_lugar`) REFERENCES `t_lugar` (`id_lugar`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
```

### 5.2 Tabla `usuarios_roles` - SIN CAMBIOS
- Se mantiene igual
- Solo se agrega una FK en la nueva tabla hacia esta

### 5.3 Tabla `t_lugar` - SIN CAMBIOS
- Solo recibe FKs de la nueva tabla

### 5.4 Tabla `usuarios` - SIN CAMBIOS
- Solo recibe FKs indirectas

---

## 6. LÓGICA DE IMPLEMENTACIÓN EN PHP

### A. Función para Guardar Ámbito
```php
function crearAmbito($usuarios_roles_id, $nombre_ambito, $lugares_ids, $mysqli) {
    // Validar que no exista ámbito previo
    $check = $mysqli->query(
        "SELECT id_ambito FROM t_ambitos_prestador 
         WHERE usuarios_roles_id = $usuarios_roles_id"
    );
    
    if ($check->num_rows > 0) {
        // Actualizar existente
        $ambito_id = $check->fetch_assoc()['id_ambito'];
        $mysqli->query(
            "UPDATE t_ambitos_prestador 
             SET nombre_ambito = '$nombre_ambito' 
             WHERE id_ambito = $ambito_id"
        );
        
        // Eliminar lugares previos
        $mysqli->query(
            "DELETE FROM t_ambitos_prestador_lugares 
             WHERE ambito_id = $ambito_id"
        );
    } else {
        // Crear nuevo
        $mysqli->query(
            "INSERT INTO t_ambitos_prestador (usuarios_roles_id, nombre_ambito)
             VALUES ($usuarios_roles_id, '$nombre_ambito')"
        );
        $ambito_id = $mysqli->insert_id;
    }
    
    // Agregar lugares seleccionados
    $lugares_list = implode(',', $lugares_ids);
    $mysqli->query(
        "INSERT INTO t_ambitos_prestador_lugares (ambito_id, lugar_id)
         SELECT $ambito_id, id_lugar FROM t_lugar 
         WHERE id_lugar IN ($lugares_list)"
    );
    
    return $ambito_id;
}
```

### B. Función para Obtener Ámbito del Prestador
```php
function obtenerAmbito($usuarios_roles_id, $mysqli) {
    $result = $mysqli->query(
        "SELECT ap.id_ambito, ap.nombre_ambito, ap.descripcion,
                GROUP_CONCAT(apl.lugar_id) as lugares_ids,
                GROUP_CONCAT(l.lugar) as lugares_nombres
         FROM t_ambitos_prestador ap
         LEFT JOIN t_ambitos_prestador_lugares apl 
           ON ap.id_ambito = apl.ambito_id
         LEFT JOIN t_lugar l ON apl.lugar_id = l.id_lugar
         WHERE ap.usuarios_roles_id = $usuarios_roles_id 
           AND ap.activo = 1
         GROUP BY ap.id_ambito"
    );
    
    return $result->fetch_assoc();
}
```

---

## 7. MODIFICACIONES EN `formulario_crear_roles_n.php`

### 7.1 Estructura HTML Nueva

Se agregaría después de la sección "Código Presupuestario":

```html
<!-- SECCIÓN ÁMBITO (Solo visible para PRESTADORES) -->
<div id="seccionAmbito" class="mb-4" style="display:none;">
  <div class="form-section-title">Ámbito de Prestador</div>
  
  <div class="mb-3">
    <label class="form-label fw-semibold">Nombre del Ámbito</label>
    <input type="text" name="nombre_ambito" id="nombre_ambito" 
           class="form-control" 
           placeholder="Ej: Equipo INCO, Laboratorio de Informática"
           maxlength="255">
    <small class="text-muted">Nombre descriptivo de esta categoría de lugares</small>
  </div>
  
  <div class="mb-3">
    <label class="form-label fw-semibold">Seleccionar Lugares</label>
    <div id="contenedorLugares" class="border rounded p-3" style="max-height: 250px; overflow-y: auto; background: #f8f9fa;">
      <div class="text-muted text-center py-3">
        <small>Cargando lugares...</small>
      </div>
    </div>
    <div class="invalid-feedback-custom" id="lugaresError">
      Debe seleccionar al menos un lugar.
    </div>
  </div>
  
  <div class="mb-3">
    <label class="form-label fw-semibold">Descripción (Opcional)</label>
    <textarea name="descripcion_ambito" id="descripcion_ambito" 
              class="form-control" rows="2"
              placeholder="Detalles adicionales sobre este ámbito..."
              maxlength="500"></textarea>
  </div>
</div>
```

### 7.2 JavaScript para Mostrar/Ocultar

```javascript
// Cuando cambie la selección de rol
document.querySelectorAll('input[name="rol_radio"]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    const rolId = this.value;
    const seccionAmbito = document.getElementById('seccionAmbito');
    
    if (rolId == 3) {  // 3 = Prestador
      seccionAmbito.style.display = 'block';
      cargarLugares();
    } else {
      seccionAmbito.style.display = 'none';
    }
    
    rolInput.value = rolId;
    limpiarError(null, rolError, roleOptions);
  });
});

// Función para cargar lugares
function cargarLugares() {
  fetch('api/obtener_lugares.php?codigo=' + codigoInput.value)
    .then(response => response.json())
    .then(data => {
      const contenedor = document.getElementById('contenedorLugares');
      contenedor.innerHTML = '';
      
      if (data.success && data.lugares.length > 0) {
        data.lugares.forEach(lugar => {
          const div = document.createElement('div');
          div.className = 'form-check';
          div.innerHTML = `
            <input class="form-check-input lugar-checkbox" type="checkbox" 
                   value="${lugar.id_lugar}" id="lugar_${lugar.id_lugar}">
            <label class="form-check-label" for="lugar_${lugar.id_lugar}">
              ${lugar.lugar}
            </label>
          `;
          contenedor.appendChild(div);
        });
      } else {
        contenedor.innerHTML = '<small class="text-danger">No hay lugares disponibles</small>';
      }
    });
}
```

### 7.3 Validación en `validarFormulario(event)`

```javascript
// Agregar validación para prestador
if (rolInput.value == 3) {  // Si es prestador
  const lugaresSeleccionados = document.querySelectorAll('input.lugar-checkbox:checked');
  const nombreAmbito = document.getElementById('nombre_ambito').value.trim();
  
  if (lugaresSeleccionados.length === 0) {
    mostrarError(null, lugaresError, null);
    event.preventDefault();
    return false;
  }
  
  if (!nombreAmbito) {
    mostrarError(document.getElementById('nombre_ambito'), 
                document.getElementById('nombreAmbitoError'), null);
    event.preventDefault();
    return false;
  }
  
  limpiarError(null, lugaresError);
}
```

---

## 8. ARCHIVOS A CREAR/MODIFICAR

| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `sql/crear_tablas_ambitos.sql` | **CREAR** | Script para crear t_ambitos_prestador y t_ambitos_prestador_lugares |
| `formulario_crear_roles_n.php` | **MODIFICAR** | Agregar sección de ámbito y lógica de visualización |
| `guardar_rol_del_usuario_n.php` | **MODIFICAR** | Guardar ámbito junto con el usuario-rol |
| `api/obtener_lugares.php` | **CREAR** | Endpoint para cargar lugares por código presupuestario |
| `funciones_ambitos.php` | **CREAR** | Funciones PHP para gestionar ámbitos |
| `actualizar_rol_del_usuario_n.php` | **CREAR** | Actualizar ámbito en edición de prestador |

---

## 9. FLUJO DE FUNCIONAMIENTO

```
┌─────────────────────────────────────────────────────────────┐
│ DIRECTOR/ADMINISTRADOR EN formulario_crear_roles_n.php      │
└─────────────────────────────────────────────────────────────┘
                           ↓
                    Selecciona ROL
                           ↓
                  ¿Es PRESTADOR (3)?
                    /            \
                  NO              SÍ
                  │               │
              Formulario       ┌───┴──────────────────┐
              normal           │ Mostrar:             │
                               │ - Nombre del Ámbito  │
                               │ - Selector Lugares   │
                               │ - Descripción        │
                               └──────────────────────┘
                                       ↓
                               Director elige lugares
                               e ingresa nombre
                                       ↓
                              Click "Guardar"
                                       ↓
                    ┌────────────────────────────────┐
                    │ guardar_rol_del_usuario_n.php  │
                    │                                │
                    │ 1. Guardar usuario_rol         │
                    │ 2. Crear ámbito                │
                    │ 3. Asignar lugares al ámbito   │
                    └────────────────────────────────┘
                                       ↓
                    ┌────────────────────────────────┐
                    │ Prestador asignado con su      │
                    │ ÁMBITO y LUGARES asociados     │
                    └────────────────────────────────┘
```

---

## 10. VENTAJAS DE ESTE DISEÑO

✅ **Escalabilidad:** Múltiples lugares por prestador  
✅ **Flexibilidad:** Nombre de ámbito personalizable  
✅ **Integridad:** FKs garantizan consistencia  
✅ **Auditoría:** Timestamps para seguimiento  
✅ **Control:** Directores controlan todo  
✅ **Seguridad:** Prestadores solo ven sus lugares  
✅ **Mantenimiento:** Fácil actualizar ubicaciones  

---

## 11. PRÓXIMOS PASOS DE IMPLEMENTACIÓN

1. ✅ Ejecutar script SQL de creación de tablas
2. ✅ Agregar columna `id_lugar` a `t_activo`
3. ✅ Crear funciones PHP en `funciones_ambitos.php`
4. ✅ Crear endpoint `api/obtener_lugares.php`
5. ✅ Modificar `formulario_crear_roles_n.php`
6. ✅ Modificar `guardar_rol_del_usuario_n.php`
7. ✅ Actualizar búsqueda de activos disponibles (filtrar por ámbito)
8. ✅ Pruebas integrales

---

**Documento preparado:** 2026-08-12  
**Versión:** 1.0  
**Estado:** Listo para implementación
