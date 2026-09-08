# Registro de Nuevo Activo y Gestión de Modelos — Documentación Técnica

**Sistema:** TecnoPresta v1.1  
**Módulo:** Gestión de Inventario → Gestión de Activos / Catálogos  
**Fecha:** Agosto 2026  
**Archivos involucrados:** 7 archivos PHP

---

## 1. Descripción Funcional

Formulario de dos fases (SPA-like) para registrar un nuevo activo con placa, serial y color:

1. **Fase de búsqueda:** El usuario escribe un texto de búsqueda (tipo, marca o modelo) y opcionalmente selecciona un fondo presupuestario. El sistema retorna **únicamente** los activos que cumplan todas las condiciones de la cadena relacional.
2. **Fase de registro:** Al seleccionar un activo, se muestra su detalle (imagen, tipo, marca, modelo). El usuario selecciona un color de la paleta, ingresa placa, serial y origen presupuestario para completar el registro.

---

## 2. Archivos

### Proceso de Registro de Activo

| Archivo | Descripción |
|---------|-------------|
| `formulario_agregar_nuevo_activo_n.php` | Formulario principal (HTML + CSS + JS) |
| `ajax/ajax_buscar_activos_nuevo_n.php` | Endpoint AJAX para búsqueda de activos registrables |
| `ajax/ajax_registro_activo_n.php` | Endpoint AJAX para registro de nueva placa |

### Proceso de Gestión de Modelos

| Archivo | Descripción |
|---------|-------------|
| `gestor_catalogo_modelos_n.php` | UI del catálogo maestro de modelos (tabla + modales) |
| `sql/gestor_catalogo_modelos_n.php` | Endpoint AJAX de datos (modelos, fondos, relación modelo-fondos) |
| `actualizar_gestor_catalogo_modelos_n.php` | Endpoint AJAX de acciones CRUD (crear/editar/toggle/guardar_fondos) |
| `funciones_modelos_n.php` | Funciones reutilizables: `normalizarModelo()`, `obtenerIdModelo()`, `obtenerOCrearModelo()`, `obtenerModeloPorId()` |

---

## 3. Cadena Relacional

```
t_placa
  └── id_activo → t_activo.id_activo
        ├── id_ag → t_activo_general.id_ag  (tipo/clase + imagen)
        ├── id_marca → t_marca.id_marca     (marca)
        └── modelo_id → t_modelos.id_modelo  (modelo)
              └── id_modelo → t_modelo_fondos.id_modelo  (fondo presupuestario)
```

### Condiciones para registrar un activo

Para que un activo aparezca en la búsqueda y pueda ser registrado, debe cumplir **todas** las siguientes condiciones:

| # | Condición | Tabla verificada |
|---|-----------|------------------|
| 1 | El modelo existe y está activo (`mdl_elm = 0`) | `t_modelos` |
| 2 | El modelo está asociado a al menos un fondo presupuestario | `t_modelo_fondos` |
| 3 | Existe al menos un registro en `t_activo` con `modelo_id` que apunte al modelo | `t_activo` |
| 4 | El `id_ag` del `t_activo` existe en `t_activo_general` | `t_activo_general` |
| 5 | El `id_marca` del `t_activo` existe en `t_marca` | `t_marca` |

---

## 4. Flujo de Búsqueda (`ajax_buscar_activos_nuevo_n.php`)

### SQL Base

Retorna **combinaciones distintas** de (id_ag, id_marca, modelo_id) que cumplan toda la cadena relacional. `id_activo` se obtiene como `MIN()` de los registros agrupados y se usa internamente para el registro.

```sql
SELECT
    MIN(a.id_activo) AS id_activo,
    ag.id_ag,
    ag.clase,
    ag.imagen,
    m.id_marca,
    m.marca,
    tm.id_modelo,
    tm.modelo
FROM t_activo a
INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
INNER JOIN t_marca m ON a.id_marca = m.id_marca
INNER JOIN t_modelos tm ON a.modelo_id = tm.id_modelo
INNER JOIN t_modelo_fondos mf ON tm.id_modelo = mf.id_modelo
WHERE tm.mdl_elm = 0
  AND a.modelo_id IS NOT NULL
  AND a.id_ag IS NOT NULL
  AND a.id_marca IS NOT NULL
  [AND mf.id_fondos = ?]
  [AND (tm.modelo LIKE ? OR ag.clase LIKE ? OR m.marca LIKE ?)]
GROUP BY ag.id_ag, ag.clase, ag.imagen, m.id_marca, m.marca, tm.id_modelo, tm.modelo
ORDER BY ag.clase ASC, m.marca ASC, tm.modelo ASC
```

### Filtros

| Filtro | Parámetro POST | Requerido |
|--------|----------------|-----------|
| Fondo presupuestario | `id_fondos` | Opcional (default: todos) |
| Texto de búsqueda | `busqueda` | Opcional (busca en modelo, tipo y marca) |

### Respuesta JSON

```json
{
    "success": true,
    "resultados": [
        {
            "id_activo": 405,
            "id_ag": 2,
            "clase": "Portátil",
            "imagen": "latitude.png",
            "id_marca": 4,
            "marca": "DELL",
            "id_modelo": 1,
            "modelo": "Latitude 3420"
        }
    ],
    "total": 1
}
```

> **Nota:** Cada fila de `resultados` es una combinación única `(id_ag, id_marca, modelo_id)`. Si un modelo tiene múltiples registros en `t_activo`, solo aparece una vez, con el `id_activo` menor (`MIN`).

---

## 5. Flujo de Registro (`ajax_registro_activo_n.php`)

### Datos enviados (POST)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `id_activo` | int | Sí | ID del registro en `t_activo` |
| `modelo_id` | int | Sí | ID del modelo en `t_modelos` |
| `color_hex` | string | Sí | Color en formato hexadecimal (#RRGGBB) |
| `placa` | string | Sí | Placa del activo (max 50 chars) |
| `serial` | string | Sí | Serial del activo (max 50 chars) |
| `id_fondos` | int | Sí | ID del fondo presupuestario |

### Cadena de validaciones (6 pasos)

```
Paso 1: Validar datos de entrada (POST)
    ↓ OK
Paso 2: Verificar modelo en t_modelos (mdl_elm = 0)
    ↓ OK
Paso 3: Verificar asociación a fondo (t_modelo_fondos)
    ↓ OK
Paso 4: Verificar id_activo en t_activo + INNER JOIN t_activo_general/t_marca
    ↓ OK
Paso 5: Verificar placa duplicada en t_placa (con detalle de departamento)
    ↓ OK
Paso 5b: Verificar serial duplicado en t_placa (con detalle de departamento)
    ↓ OK
Paso 6: INSERT en t_placa
```

> **Detalle de duplicados:** En los pasos 5 y 5b, si la placa o el serial ya existen, el sistema compara el `codigo` (centro educativo) de la placa existente con el del usuario actual para indicar si el duplicado pertenece al mismo centro o a otro:

### SQL de registro

```sql
INSERT INTO t_placa (
    placa, serial, id_activo, codigo, id_estado,
    prestar, activo, id_fondos, alias_id, id_lugar, color
) VALUES (?, ?, ?, ?, 1, 1, 1, ?, 0, 0, ?)
```

| Campo | Fuente |
|-------|--------|
| `placa` | Input del usuario |
| `serial` | Input del usuario |
| `id_activo` | Seleccionado en fase de búsqueda |
| `codigo` | `$usuario_azure['codigoPresu']` (centro educativo) |
| `id_estado` | 1 (hardcoded) |
| `prestar` | 1 (hardcoded) |
| `activo` | 1 (hardcoded) |
| `id_fondos` | Seleccionado en fase de registro |
| `alias_id` | 0 (hardcoded) |
| `id_lugar` | 0 (hardcoded) |
| `color` | Hexadecimal del color seleccionado |

---

## 6. Paleta de Colores

12 colores básicos hardcodeados en JavaScript (no consultados desde BD):

| Color | Hex |
|-------|-----|
| Negro | #424242 |
| Blanco | #F5F5F5 |
| Gris | #9E9E9E |
| Rojo | #E53935 |
| Azul | #1E88E5 |
| Verde | #43A047 |
| Amarillo | #FDD835 |
| Naranja | #FB8C00 |
| Marrón | #6D4C41 |
| Rosa | #EC407A |
| Morado | #8E24AA |
| Beige | #D7CCC8 |

El color se almacena como hexadecimal en `t_placa.color` (varchar(7), default '#000000'). El campo `t_activo.id_color` **no se usa** en búsquedas ni en filtrado.

---

## 7. Reglas de Negocio

1. **No se crean nuevos registros en `t_activo`**: El proceso solo registra unidades físicas en `t_placa` vinculadas a `t_activo` existentes.
2. **`id_color` no se usa en WHERE**: Solo se almacena el hex en `t_placa.color`.
3. **Fondo es opcional en búsqueda**, pero **requerido en registro**.
4. **Solo aparecen activos registrables**: Los `INNER JOIN` en la búsqueda garantizan que solo se muestran activos con cadena relacional completa.

---

## 8. Requisitos de Base de Datos

### Tabla `t_modelos` — esquema real (6 columnas)

> **IMPORTANTE:** La tabla `t_modelos` **NO contiene** las columnas `id_ag` ni `id_marca`. El tipo (clase) y la marca se derivan de `t_activo` a través de `modelo_id`.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id_modelo` | INT UNSIGNED PK AUTO_INCREMENT | Identificador del modelo |
| `modelo` | varchar(250) NOT NULL | Nombre del modelo (único) |
| `mdl_elm` | tinyint NOT NULL DEFAULT 0 | 0 = activo, 1 = eliminado (soft delete) |
| `created_at` | datetime | Fecha de creación |
| `updated_at` | datetime | Última actualización |
| `created_by` | varchar(50) NULL | Usuario que lo creó |

Índice único: `uq_modelo (modelo)`

### Tablas requeridas

| Tabla | Campos clave |
|-------|-------------|
| `t_modelos` | `id_modelo`, `modelo`, `mdl_elm` |
| `t_modelo_fondos` | `id_modelo`, `id_fondos` (PK compuesta) |
| `t_activo` | `id_activo`, `id_ag`, `id_marca`, `modelo_id` |
| `t_activo_general` | `id_ag`, `clase`, `imagen` |
| `t_marca` | `id_marca`, `marca` |
| `t_placa` | `id_placa`, `placa`, `serial`, `id_activo`, `codigo`, `id_estado`, `prestar`, `activo`, `id_fondos`, `color` |
| `t_fondos` | `id_fondos`, `fondos` |

### Datos mínimos requeridos

- `t_modelos` debe tener registros con `mdl_elm = 0`
- `t_modelo_fondos` debe vincular los modelos con fondos presupuestarios
- `t_activo` debe tener registros con `modelo_id` apuntando a `t_modelos.id_modelo`
- `t_activo_general` y `t_marca` deben tener los registros correspondientes a los `id_ag` e `id_marca` de `t_activo`

---

## 9. Notas

- El campo `codigo` en `t_placa` debe coincidir con el `CentrosEducativosDondeTrabaja` del funcionario Azure.
- La paleta de colores es fija (12 colores); no depende de la tabla `t_color`.
- El formulario existe en versiones legacy (`in_formulario_agregar_modelo_placa_serie_N.php`) que no deben ser modificadas.
- El registro en navegación (`sql/registro_formulario_registro_activos.sql`) debe ejecutarse en MySQL para que el formulario aparezca en el menú.

---

## 10. Gestión de Modelos (`gestor_catalogo_modelos_n.php`)

### 10.1 Propósito

CRUD del catálogo maestro de modelos de activos (`t_modelos`) y asignación de fuentes presupuestarias (`t_modelo_fondos`). Es el complemento administrativo del registro de activos: permite crear, editar, activar/desactivar modelos y asociarlos a fondos.

### 10.2 Arquitectura

| Archivo | Función |
|---------|---------|
| `gestor_catalogo_modelos_n.php` | UI: tabla de modelos con acciones, modales para crear/editar modelo, asignar fondos, confirmar/toggle |
| `sql/gestor_catalogo_modelos_n.php` | Endpoint GET de datos: retorna JSON con `modelos`, `fondos` y `fondos_por_modelo` |
| `actualizar_gestor_catalogo_modelos_n.php` | Endpoint POST de acciones: `crear_modelo`, `editar_modelo`, `toggle_modelo`, `guardar_fondos` |
| `funciones_modelos_n.php` | Funciones compartidas usadas por otros procesos de modelos |

### 10.3 Tablas involucradas

| Tabla | Lectura | Escritura |
|-------|---------|-----------|
| `t_modelos` | Sí (endpoint de datos) | Sí (crear/editar/toggle) |
| `t_modelo_fondos` | Sí (relación modelo-fondo) | Sí (guardar_fondos: DELETE + INSERT en transacción) |
| `t_fondos` | Sí (catálogo de fuentes presupuestarias) | No |
| `t_activo` | Sí (conteo de activos asociados por `modelo_id`) | No |

### 10.4 Acciones CRUD

| Acción | SQL | Descripción |
|--------|-----|-------------|
| `crear_modelo` | `INSERT INTO t_modelos (modelo, mdl_elm, created_by, created_at, updated_at) VALUES (?, 0, ?, NOW(), NOW())` | Crea un modelo activo solo con su nombre |
| `editar_modelo` | `UPDATE t_modelos SET modelo = ?, updated_at = NOW() WHERE id_modelo = ?` | Renombra el modelo |
| `toggle_modelo` | `UPDATE t_modelos SET mdl_elm = ?, updated_at = NOW() WHERE id_modelo = ?` | 0 = activo, 1 = desactivado |
| `guardar_fondos` | `DELETE FROM t_modelo_fondos WHERE id_modelo = ?` + `INSERT INTO t_modelo_fondos (id_modelo, id_fondos) VALUES (?, ?)` en transacción | Reemplaza todas las asociaciones modelo-fondo |

### 10.5 Modelo de datos — derivación de tipo y marca

La tabla `t_modelos` solo almacena el **nombre del modelo** (6 columnas, sin `id_ag` ni `id_marca`). El tipo (clase) y la marca de un modelo **no se guardan en `t_modelos`**: se derivan de los registros de `t_activo` que apuntan al modelo mediante `modelo_id`.

```
t_activo.modelo_id → t_modelos.id_modelo    (el modelo)
t_activo.id_ag     → t_activo_general.id_ag  (tipo/clase)
t_activo.id_marca  → t_marca.id_marca        (marca)
```

Por esta razón, la tabla de modelos no muestra columnas de tipo ni marca: solo nombre, activos asociados, fuentes presupuestarias, estado y acciones.

### 10.6 Registro en navegación

El formulario se registra en el menú vía `sql/catalogo_modelos_registro.sql` (módulo "Catálogos", id_modulo=19) con permisos `ver(1)`, `crear(2)`, `editar(3)`, `eliminar(4)`, `auditar(11)` asignados al rol Root (id_rol=1).

---

## 11. Scripts SQL obsoletos (NO EJECUTAR)

Los siguientes scripts intentan agregar o usar las columnas `id_ag` e `id_marca` en `t_modelos`, que **no existen** en el esquema real de la BD. **No deben ejecutarse.**

| Archivo | Problema |
|---------|----------|
| `sql/esquema_t_modelos.sql` | Contiene `ALTER TABLE t_modelos ADD COLUMN id_ag`, `ADD COLUMN id_marca` y elimina el índice único `uq_modelo`. La BD real usa `t_modelos` de 6 columnas con índice `uq_modelo (modelo)`. |
| `sql/migracion_modelos_fondos.sql` | Sus `INSERT INTO t_modelos (modelo, id_ag, id_marca, ...)` y `UPDATE ... AND a.id_ag = tm.id_ag` fallarían por columnas inexistentes. |

**Alternativa correcta:** La siembra de modelos y la asignación de `modelo_id` en `t_activo` deben hacerse mediante las funciones de `funciones_modelos_n.php` (deduplicación por nombre de modelo) o con `sql/catalogo_modelos.sql`, que crea `t_modelos` y `t_modelo_fondos` con el esquema real de 6 columnas.
