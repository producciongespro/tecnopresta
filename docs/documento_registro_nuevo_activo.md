# Registro de Nuevo Activo — Documentación Técnica

**Sistema:** TecnoPresta v1.1  
**Módulo:** Gestión de Inventario → Gestión de Activos  
**Fecha:** Agosto 2026  
**Archivos involucrados:** 4 (1 nuevo, 2 modificados, 1 SQL)

---

## 1. Descripción Funcional

Formulario de dos fases (SPA-like) para registrar un nuevo activo con placa y serial:

1. **Fase de búsqueda:** Seleccione un fondo presupuestario (opcional) y busque el activo por clase, marca o modelo. Solo se muestran activos cuyo `modelo_id` está registrado en la tabla `t_modelos`.
2. **Fase de registro:** Al seleccionar un activo, se muestra su detalle (imagen, clase, marca, modelo, color). El usuario ingresa placa, serial y origen presupuestario para completar el registro.

---

## 2. Archivos

| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `formulario_agregar_nuevo_activo_n.php` | **Nuevo** | Formulario principal (HTML + CSS + JS) |
| `ajax/ajax_buscar_activos_nuevo_n.php` | **Nuevo** | Endpoint AJAX para búsqueda de activos |
| `guardar_placa_n.php` | **Modificado** | Fix: obtención del código de centro educativo |
| `sql/registro_formulario_registro_activos.sql` | **Nuevo** | Script SQL de registro en navegación |

---

## 3. Flujo de Base de Datos

### 3.1 Cadena de filtrado por fondo presupuestario

```
t_fondos (id_fondos, fondos)
    └── t_modelo_fondos (id_modelo, id_fondos)   ← tabla puente
        └── t_modelos (id_modelo, modelo)
            └── t_activo (modelo_id → t_modelos.id_modelo)
```

Las 3 ramas de query en el AJAX **exigen** `INNER JOIN t_modelos`:
- **Sin filtro, sin texto:** retorna activos con `modelo_id` válido
- **Con fondo:** filtra por `mf.id_fondos` + `modelo_id` válido
- **Sin fondo, con texto:** LIKE en clase/marca/modelo + `modelo_id` válido

### 3.2 Guardado en t_placa

```sql
INSERT INTO t_placa (placa, serial, id_activo, codigo, id_estado, prestar, activo, id_fondos, alias_id, id_lugar)
VALUES (?, ?, ?, ?, 1, 1, 1, ?, 0, 0)
```

| Campo | Fuente |
|-------|--------|
| `placa` | Input del usuario |
| `serial` | Input del usuario |
| `id_activo` | Seleccionado en fase de búsqueda |
| `codigo` | `$usuario_azure['codigoPresu']` (centro educativo del funcionario) |
| `id_fondos` | Seleccionado en fase de registro |
| `alias_id` | 0 (hardcoded) |
| `id_lugar` | 0 (hardcoded) |

---

## 4. Bugs Corregidos

### 4.1 Select2 no funcionaba (botón Buscar inactivo)

**Causa:** Las rutas locales `select2/select2.min.js` y `select2/select2.min.css` apuntaban a archivos inexistentes (estaban anidados en `select2/select2/`). Select2 no cargaba, `$().select2()` lanzaba error JS, y todo el bloque `$(document).ready` fallaba silenciosamente.

**Fix:** Cambiado a CDN:
```html
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
```

### 4.2 Select2 `allowClear` sin `<option value="">`

**Causa:** El `<option value="0">Todos los fondos</option>` no es compatible con `allowClear: true`. Select2 necesitaba un valor vacío.

**Fix:** Cambiado a `<option value="">Todos los fondos</option>` y ajustada toda la lógica JS de comparación (`== 0` → `== ''`, etc.).

### 4.3 Búsqueda sin filtro no restringía por t_modelos

**Causa:** Las ramas "sin filtro" y "texto sin fondo" no tenían `INNER JOIN t_modelos`, por lo que retornaban activos con `modelo_id = NULL`.

**Fix:** Agregado `INNER JOIN t_modelos tm ON a.modelo_id = tm.id_modelo` en las 3 ramas del query.

### 4.4 Código de centro educativo vacío al guardar

**Causa:** `guardar_placa_n.php` usaba `$_SESSION['codigo']` que solo se establece al pasar por un portal (`portal_inventario_activo.php`). Al navegar por `navegar.php`, la variable no existía → `codigo = ""`.

**Fix:** Cambiado a `$usuario_azure['codigoPresu']` (disponible siempre vía Azure session):
```php
// Antes:
$codigo = $_SESSION['codigo'] ?? '';
// Ahora:
$codigo = $usuario_azure['codigoPresu'] ?? '';
```

---

## 5. Funcionalidades del JS

| Funcionalidad | Descripción |
|---------------|-------------|
| Auto-buscar al cambiar fondo | Evento `change` en Select2 dispara `ejecutarBusqueda()` |
| Botón "Buscar" | Ejecuta `ejecutarBusqueda()` con fondo + texto |
| Enter en campo de búsqueda | Ejecuta `ejecutarBusqueda()` |
| Botón "Mostrar todos" | Aparece cuando fondo devuelve 0 resultados; limpia filtros y busca todo |
| Auto-scroll | Scroll a resultados o mensaje de "sin resultados" |
| Fallback visual | Mensaje explicativo cuando el fondo no tiene activos vinculados |
| Console.log de debugging | Logs etiquetados `[FONDO]`, `[BUSQUEDA]`, `[AJAX]`, `[FALLBACK]` |

---

## 6. Estructura de Navegación (SQL)

El script `sql/registro_formulario_registro_activos.sql` registra:

| Tabla | Datos |
|-------|-------|
| `formularios` | `nombre='Registro de Activos'`, `ruta='formulario_agregar_nuevo_activo_n.php'`, `modulo_id=1` |
| `permisos` | `ver(1)`, `crear(2)` |
| `roles_permisos` | Asignados al rol Root (`id_rol=1`) con validación idempotente |

**Ejecutar:** `source sql/registro_formulario_registro_activos.sql` en MySQL.

---

## 7. Notas

- El `numero_activo` en `t_placa` es un correlativo opcional por alias. No se usa en este formulario.
- El campo `codigo` en `t_placa` debe coincidir con el `CentrosEducativosDondeTrabaja` del funcionario Azure.
- La fecha mostrada en fase 2 era solo visual (no se guardaba). Fue eliminada por solicitud del usuario.
- La tabla `t_modelos` debe estar poblada para que los activos aparezcan en la búsqueda.
