# Registro de Nuevo Activo — Documentación Técnica

**Sistema:** TecnoPresta v1.1  
**Módulo:** Gestión de Inventario → Gestión de Activos  
**Fecha:** Agosto 2026  
**Archivos involucrados:** 3 archivos PHP

---

## 1. Descripción Funcional

Formulario de dos fases (SPA-like) para registrar un nuevo activo con placa, serial y color:

1. **Fase de búsqueda:** El usuario escribe un texto de búsqueda (tipo, marca o modelo) y opcionalmente selecciona un fondo presupuestario. El sistema retorna **únicamente** los activos que cumplan todas las condiciones de la cadena relacional.
2. **Fase de registro:** Al seleccionar un activo, se muestra su detalle (imagen, tipo, marca, modelo). El usuario selecciona un color de la paleta, ingresa placa, serial y origen presupuestario para completar el registro.

---

## 2. Archivos

| Archivo | Descripción |
|---------|-------------|
| `formulario_agregar_nuevo_activo_n.php` | Formulario principal (HTML + CSS + JS) |
| `ajax/ajax_buscar_activos_nuevo_n.php` | Endpoint AJAX para búsqueda de activos registrables |
| `ajax/ajax_registro_activo_n.php` | Endpoint AJAX para registro de nueva placa |

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

```sql
SELECT DISTINCT
    a.id_activo,
    tm.id_modelo,
    tm.modelo,
    ag.id_ag,
    ag.clase,
    ag.imagen,
    m.id_marca,
    m.marca
FROM t_modelos tm
INNER JOIN t_modelo_fondos mf ON tm.id_modelo = mf.id_modelo
INNER JOIN t_activo a ON a.modelo_id = tm.id_modelo
INNER JOIN t_activo_general ag ON a.id_ag = ag.id_ag
INNER JOIN t_marca m ON a.id_marca = m.id_marca
WHERE tm.mdl_elm = 0
  [AND mf.id_fondos = ?]
  [AND (tm.modelo LIKE ? OR ag.clase LIKE ? OR m.marca LIKE ?)]
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
            "id_modelo": 1,
            "modelo": "Latitude 3420",
            "id_ag": 2,
            "clase": "Portátil",
            "imagen": "latitude.png",
            "id_marca": 4,
            "marca": "DELL"
        }
    ],
    "total": 1
}
```

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
Paso 5: Verificar placa/serial duplicados en t_placa
    ↓ OK
Paso 6: INSERT en t_placa
```

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

### Tablas requeridas

| Tabla | Campos clave |
|-------|-------------|
| `t_modelos` | `id_modelo`, `modelo`, `mdl_elm` |
| `t_modelo_fondos` | `id_modelo`, `id_fondos` |
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
