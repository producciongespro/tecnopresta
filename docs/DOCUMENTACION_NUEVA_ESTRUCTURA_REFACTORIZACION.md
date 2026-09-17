# TecnoPresta — Documentación de la Nueva Arquitectura y Refactorización

**Sistema:** TecnoPresta — Ministerio de Educación Pública de Costa Rica
**Ámbito del documento:** Estructura del sistema, menú dinámico, dispatcher (enrutador), usuarios, roles y permisos.
**Versión del documento:** 1.0
**Fecha:** Septiembre 2026

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Contexto del sistema](#2-contexto-del-sistema)
3. [Motivos de la refactorización](#3-motivos-de-la-refactorización)
   - 3.1 [Problemas del modelo anterior](#31-problemas-del-modelo-anterior)
   - 3.2 [Objetivos de la refactorización](#32-objetivos-de-la-refactorización)
4. [Nueva arquitectura general](#4-nueva-arquitectura-general)
   - 4.1 [Jerarquía funcional](#41-jerarquía-funcional-subsistemas--módulos--formularios--acciones)
   - 4.2 [Vista de componentes](#42-vista-de-componentes)
5. [Dispatcher central: navegar.php (router)](#5-dispatcher-central-navegarphp-router)
6. [Autenticación y autorización: auth.php](#6-autenticación-y-autorización-authphp)
7. [Backend de sesión y menú: sql/formulario_principal.php](#7-backend-de-sesión-y-menú-sqlformulario_principalphp)
8. [Menú dinámico (frontend)](#8-menú-dinámico-frontend)
9. [Gestores administrativos](#9-gestores-administrativos)
   - 9.1 [Gestión de Módulos](#91-gestión-de-módulos)
   - 9.2 [Gestión de Formularios](#92-gestión-de-formularios)
   - 9.3 [Asignación de Permisos a Roles](#93-asignación-de-permisos-a-roles)
   - 9.4 [Administración de Usuarios](#94-administración-de-usuarios)
10. [Modelo de datos](#10-modelo-de-datos)
11. [Usuarios, roles y permisos](#11-usuarios-roles-y-permisos)
12. [Seguridad](#12-seguridad)
13. [Diagrama de flujo de acceso](#13-diagrama-de-flujo-de-acceso)
14. [Ventajas de la nueva estructura](#14-ventajas-de-la-nueva-estructura)
15. [Importancia de la refactorización](#15-importancia-de-la-refactorización)
16. [Migración y compatibilidad](#16-migración-y-compatibilidad)
17. [Convenciones de nomenclatura](#17-convenciones-de-nomenclatura)
18. [Evolución futura y recomendaciones](#18-evolución-futura-y-recomendaciones)
19. [Anexos](#19-anexos)

---

## 1. Resumen ejecutivo

TecnoPresta evolucionó de un aplicación **monolítica, con menús y permisos embebidos en código y validados con consultas a base de datos en cada petición**, hacia una **arquitectura modular, dinámica y configurable por base de datos**, gobernada por un **enrutador (dispatcher) central**, un **módulo único de autenticación/autorización** y una **jerarquía de navegación de 4 niveles**:

```
Subsistemas → Módulos → Formularios → Acciones (Permisos)
```

Esta documentación describe la nueva estructura, las razones técnicas y operativas que motivaron la refactorización, sus ventajas y su importancia institucional.

---

## 2. Contexto del sistema

TecnoPresta es la plataforma web del MEP para la gestión del préstamo, inventario y soporte técnico de equipos tecnológicos. Opera sobre:

| Componente | Tecnología |
|------------|------------|
| Lenguaje | PHP 8.1 (procedural + PDO) |
| Base de datos | MySQL / MariaDB |
| Servidor | Apache (XAMPP local / cPanel producción) |
| Frontend | Bootstrap 5, Alpine.js 3, jQuery (legado), SweetAlert2 |
| Autenticación | SSO Microsoft Azure AD (MSAL.js) |
| Integración SOAP | Validación de funcionarios del MEP |

El sistema contiene cientos de formularios `.php` que atienden dominios de negocio como inventario, préstamos, devoluciones, soporte técnico (tickets), citas con Teams, reportes nacionales e institucionales, y el nuevo módulo administrativo **"Gestor del Sistema"**.

---

## 3. Motivos de la refactorización

### 3.1 Problemas del modelo anterior

El sistema anterior (anterior a la versión "nueva", ver convenciones en la sección 17) presentaba las siguientes deficiencias:

1. **Menú y permisos acoplados al código.**
   - La visibilidad de cada formulario y las acciones permitidas se resolvían con consultas SQL dispersas en cada página (p. ej. `selectPermisosMenuPrestamoGestor.php`, `selectLoginGestor.php`) o con validaciones de `$_SESSION['id_rol']` embebidas en cada archivo.
   - Agregar, mover u ocultar un formulario obligaba a editar código fuente y, en muchos casos, a modificar varios archivos.

2. **Validación de permisos con consulta a BD en cada petición.**
   - El dispatcher anterior (`navegar -FUNCIONA 25-5-26.php`) ejecutaba un `SELECT COUNT(*) ... WHERE u.cedula = ? AND f.ruta = ?` por cada carga de página, lo que:
     - Incrementaba latencia y carga sobre la base de datos.
     - Duplicaba lógica de negocio entre el backend del menú y el propio dispatcher.
     - Podía derivar en inconsistencias si el esquema de permisos cambiaba.

3. **Un solo rol por usuario/código presupuestario.**
   - La función `obtenerRolUsuario()` consultaba con `LIMIT 1`, por lo que un usuario solo podía tener **un único rol** por centro educativo.
   - Los roles se **sobrescribían en cada inicio de sesión** a partir de `T_Lista_Blanca`, impidiendo que un administrador asignara roles de forma manual y duradera.

4. **Lógica de negocio de sesión duplicada.**
   - La construcción de la sesión, la detección de Root y la consulta del menú estaban replicadas en variantes de archivos (`formulario_principal` con múltiples copias con fecha en el nombre), dificultando el mantenimiento.

5. **Seguridad dependiente del desarrollador.**
   - Cada página debía implementar manualmente la validación de sesión, el bloqueo de acceso directo y la comprobación de rol. Un descuido dejaba formularios accesibles por URL.

6. **Personalización institucional limitada.**
   - Los íconos, el orden y las descripciones de módulos estaban codificados; no se podía reordenar, renombrar o desactivar elementos sin intervención técnica.

### 3.2 Objetivos de la refactorización

- Desacoplar la **navegación y los permisos** de la implementación.
- Centralizar la **seguridad** en un único punto (`navegar.php` + `auth.php`).
- Soportar **múltiples roles por usuario** por código presupuestario.
- Permitir la **administración visual** completa del menú, los formularios, las acciones y los roles.
- Reducir **consultas a BD por petición** acelerando la respuesta del sistema.
- Mantener **compatibilidad** con el sistema en producción (no romper los formularios existentes).

---

## 4. Nueva arquitectura general

### 4.1 Jerarquía funcional: Subsistemas → Módulos → Formularios → Acciones

La base de datos define una jerarquía de 4 niveles que alimenta tanto el menú como la autorización:

| Nivel | Entidad | Ejemplo |
|-------|---------|---------|
| 1 | **Subsistema** | "Administración del Sistema", "Gestión de Servicios" |
| 2 | **Módulo** | "Gestor del Sistema", "Planificación" |
| 3 | **Formulario** | "Asignación de Permisos a Roles" → `gestor_roles_permisos_n.php` |
| 4 | **Acción (permiso granular)** | ver, crear, editar, eliminar, exportar, importar, aprobar, asignar, cerrar, escalar, auditar |

Cada **formulario** se asocia a una **ruta física** (nombre de archivo `.php` o `.html`). Cada **permiso** es la combinación `formulario × acción`. Los **roles** se componen de permisos. Los **usuarios** reciben uno o más roles por centro (código presupuestario + subsistema).

> **Clave de diseño:** la navegación no consulta rutas físicas complejas; cada formulario se referencia por su nombre de archivo y existe una **lista blanca** de extensiones (`php`, `html`) que el dispatcher valida.

### 4.2 Vista de componentes

```
┌──────────────────────────────────────────────────────────────────┐
│                        NAVEGACIÓN (Frontend)                      │
│  formulario_menu_principal.php   → Cards de Subsistemas          │
│  formulario_modulos.php          → Grid de Módulos               │
│  formulario_sub_modulos.php      → Grid de Formularios           │
│  (Alpine.js + Bootstrap 5, búsqueda con normalización de acentos)│
└───────────────▲──────────────────────────────────────────────────┘
                │ navegar.php?ruta=<archivo>&subsistema_id&modulo_id...
┌───────────────┴──────────────────────────────────────────────────┐
│                     DISPATCHER (Router)                          │
│  navegar.php  → valida sesión, ruta (regex+basename+whitelist)   │
│                 valida permisos contra la sesión, carga archivo   │
│                 define ACCESO_SEGURO                             │
└──────┬───────────────────────────────┬───────────────────────────┘
       │ auth.php                      │ define ACCESO_SEGURO
       ▼                               ▼
┌──────────────────┐        ┌─────────────────────────────┐
│  AUTH / SEGURIDAD │        │  FORMULARIOS DEL SISTEMA    │
│  usuarioAutenticado│       │  gestor_*.php, formulario_* │
│  esUsuarioRoot     │       │  - Protector de acceso      │
│  usuarioTieneRuta  │       │    directo (ACCESO_SEGURO)  │
│  validarSesion     │       │  - Header/Footer partials   │
│  validarPermisoRuta│       └─────────────▲────────────────┘
└────────┬───────────┘                     │
         │ Lee $_SESSION['funcionario']    │ fetch JSON
         ▼                                 │
┌──────────────────────────────────────────┴───────────────────────┐
│  BACKEND DE SESIÓN Y MENÚ  sql/formulario_principal.php          │
│  Valida sesión Azure → crea/actualiza usuario → asigna roles      │
│  iniciales (Lista Blanca) → construye menú → escribe la sesión   │
│  'auth' con rutas_permitidas                                     │
│  Endpoints de datos: gestor_roles.php, gestor_formularios.php,   │
│  gestor_modulos.php, gestor_usuario_n.php, buscar_usuario_cedula │
│  Endpoints de acción: actualizar_gestor_*.php                    │
└──────────────────────────────┬───────────────────────────────────┘
                               ▼
              ┌──────────────────────────────┐
              │        BASE DE DATOS          │
              │ subsistemas, modulos,         │
              │ formularios, acciones,        │
              │ permisos, roles_permisos,     │
              │ usuarios, usuarios_roles,     │
              │ t_roles, t_lista_blanca       │
              └──────────────────────────────┘
```

---

## 5. Dispatcher central: navegar.php (router)

**Archivo:** `navegar.php`

Es el **único punto de entrada** de los formularios internos. Cualquier enlace interno usa el patrón:

```url
navegar.php?ruta=nombre_del_archivo.php&subsistema_id=X&modulo_id=Y&formulario_id=Z
```

### Flujo del dispatcher (8 pasos)

1. **Iniciar sesión** si no existe (`session_status()`).
2. **Importar `auth.php`** (todo el marco de seguridad).
3. **Validar sesión** mediante `validarSesion()`; si no hay usuario autenticado redirige a `index.html` con código 401.
4. **Obtener la ruta** desde `$_GET['ruta']`.
   - Si no se envía → `400 Ruta no especificada`.
5. **Validar caracteres permitidos** con la expresión regular:
   ```php
   /^[a-zA-Z0-9_\-\.\/]+$/
   ```
   - Bloquea `..`, `:` y otros caracteres peligrosos.
6. **Limpiar la ruta** con `basename()` (anti path-traversal).
7. **Validar extensión** contra una **lista blanca** (`php`, `html`); cualquier otra extensión → `403`.
8. **Validar existencia física**:
   - Resuelve `realpath()` del directorio base y del archivo.
   - Verifica que el archivo resuelto esté **dentro** del directorio base (`strpos`).
   - Verifica `file_exists()` → `403 Archivo no encontrado`.
9. **Validar permisos** con `validarPermisoRuta($ruta)` (ver sección 6). Root siempre pasa; los demás usuarios consultan `rutas_permitidas` en su sesión.
10. **Definir `ACCESO_SEGURO`** (constante) y **cargar el archivo real**.

### Bloqueo de acceso directo (protección en cada formulario)

Todos los formularios de la nueva versión inician con:

```php
if (!defined('ACCESO_SEGURO')) {
    http_response_code(403);
    die('Acceso directo no permitido');
}
```

Esto impide que los archivos sean ejecutados escribiendo su URL directamente, **omitiendo** la validación de sesión y permisos.

---

## 6. Autenticación y autorización: auth.php

**Archivo:** `auth.php`

Es el módulo único de seguridad. **No consulta la base de datos**: toda la información de autorización se lee de `$_SESSION['funcionario']['auth']`, el cual es construido previamente por `sql/formulario_principal.php`.

### Funciones expuestas

| Función | Propósito |
|---------|-----------|
| `usuarioAutenticado(): bool` | Verifica que exista `$_SESSION['funcionario']` con su bloque `auth`. |
| `obtenerUsuarioAuth(): ?array` | Devuelve el bloque de autorización (`auth`). |
| `obtenerUsuario(): ?array` | Devuelve todos los datos del funcionario en sesión. |
| `obtenerUsuarioId(): ?int` | ID interno del usuario (para auditorías). |
| `obtenerCedulaUsuario(): ?string` | Cédula del funcionario. |
| `obtenerCodigoPresupuestario(): ?string` | Código presupuestario activo. |
| `esUsuarioRoot(): bool` | Indica si el usuario tiene acceso Root/Total. |
| `obtenerRolesUsuarioAuth(): array` | Roles vigentes del usuario en la sesión. |
| `usuarioTieneRuta(string $ruta): bool` | Indica si el usuario tiene permiso para una ruta. |
| `validarSesion(): void` | Bloquea el acceso si no está autenticado. |
| `validarPermisoRuta(string $ruta): void` | Bloquea (403) si la ruta no está permitida. |

### Reglas de `usuarioTieneRuta()`

1. **Formularios públicos internos** no requieren permiso explícito:
   - `formulario_menu_principal.php`
   - `formulario_modulos.php`
   - `formulario_sub_modulos.php`
   - `navegar.php`, `acerca_de_n.php`, `ayuda_n.php`
2. **Root** tiene acceso total automático.
3. Cualquier otra ruta debe estar en `$_SESSION['funcionario']['auth']['rutas_permitidas']`.

> **Beneficio arquitectónico:** la validación es **O(1)** por petición (hay una lista en memoria), a diferencia del modelo anterior que ejecutaba SQL cada vez.

---

## 7. Backend de sesión y menú: sql/formulario_principal.php

**Archivo:** `sql/formulario_principal.php` (endpoint JSON)

Es el corazón del flujo de inicio de sesión. Este archivo:

1. **Valida la sesión Azure** (`obtenerUsuarioSesion()`).
2. **Crea automáticamente** al usuario en la tabla `usuarios` si no existe (cédula, nombre, correo, `azure_id`, sexo).
3. **Corrige nombres** si se almacenó un correo en el campo nombre (limpieza de datos heredada).
4. **Actualiza `ultimo_acceso`**.
5. **Obtiene los roles vigentes** del usuario para el código presupuestario actual (`SELECT DISTINCT` — soporta múltiples roles). Si el usuario **no tiene roles**, consulta `T_Lista_Blanca`:
   - Si existe en lista blanca y el rol es válido → lo asigna.
   - Si no → le asigna el rol **Solicitante (5)** por defecto.
   - **Nota:** si el usuario **ya tiene roles**, no se modifican ni se sincronizan (se respeta la asignación administrativa).
6. **Detecta Root** entre los roles.
7. **Consulta el menú**:
   - Root → `consultaMenuRoot()` (todos los subsistemas/módulos/formularios activos).
   - Otros → `consultaMenuUsuarios()` (permisos derivados de `roles_permisos` → `permisos` → `formularios` → `modulos` → `subsistemas`).
8. **Construye el menú jerárquico** con `crearMenu()` (evita duplicados y reindexa arreglos).
9. **Prepara `rutas_permitidas`**: lista única de nombres de archivo (`basename`) con extensión permitida, que se guarda en sesión.
10. **Escribe la sesión del sistema**:
    ```php
    $_SESSION['funcionario']['auth'] = [
        'usuario_id', 'es_root', 'roles', 'rutas_permitidas', 'session_created_at'
    ];
    ```
11. **Responde JSON** con el menú, los roles, el indicador Root y los datos del funcionario.

La función `crearMenu()` construye:

```
[
  { "id": subsistema, "nombre", "descripcion", "imagen",
    "modulos": [
      { "id": modulo, "nombre", "ruta", "descripcion", "imagen",
        "formularios": [
          { "id", "nombre", "ruta", "descripcion", "imagen", "orden",
            "acciones": [ { "id", "nombre" } ] }
        ] }
    ] }
]
```

---

## 8. Menú dinámico (frontend)

Los tres formularios de navegación representan el menú **visual** de 3 niveles:

| Archivo | Nivel | Contenido |
|---------|-------|-----------|
| `formulario_menu_principal.php` | Subsistemas | Cards de subsistemas + badges de módulos. Cada card enlaza a `navegar.php?ruta=formulario_modulos.php&subsistema_id=...` |
| `formulario_modulos.php` | Módulos | Grid de módulos del subsistema + buscador con resaltado seguro. Enlaza a `formulario_sub_modulos.php`. |
| `formulario_sub_modulos.php` | Formularios | Grid de formularios del módulo, ordenados por `orden`, con badges de acciones. Enlaza a `navegar.php?ruta=<formulario.ruta>`. |

### Características del frontend

- **Alpine.js** como motor reactivo (`menuApp()`, `modulosApp()`, `subModulosApp()`).
- **Fetch al endpoint** `sql/formulario_principal.php` con `credentials: "include"`.
- **Buscador** con normalización Unicode (elimina diacríticos) y escape HTML (`escapeHtml`), evitando XSS al resaltar coincidencias.
- **Diseño institucional MEP** (`assets/css/nueva-identidad.css`, `css/formulario_menu_principal.css`).
- **Header/footer parciales**: `partials/header.php` y `partials/footer.php` (drop-down de usuario, foto de Azure, rol en sesión y cierre de sesión).

---

## 9. Gestores administrativos

El nuevo módulo **"Gestor del Sistema"** (bajo el subsistema "Administración del Sistema") expone cuatro formularios administrativos, todos protegidos por `ACCESO_SEGURO`, sesión y, en su mayoría, por **rol Root**.

### 9.1 Gestión de Módulos

- **Frontend:** `gestor_modulos_n.php`
- **Datos (JSON):** `sql/gestor_modulos.php`
- **Acciones (CRUD):** `actualizar_gestor_modulos_n.php`

Permite crear/editar/activar/desactivar **subsistemas** y **módulos**, incluyendo la subida de íconos SVG (validados por extensión, tamaño ≤ 500 KB y MIME). Valida integridad: no se puede desactivar un subsistema con módulos activos, ni un módulo con formularios activos.

### 9.2 Gestión de Formularios

- **Frontend:** `gestor_formularios_n.php`
- **Datos (JSON):** `sql/gestor_formularios.php`
- **Acciones (CRUD):** `actualizar_gestor_formularios_n.php`

Permite crear/editar/activar/desactivar **formularios** dentro de módulos, asignar las **acciones** disponibles (permisos `formulario × acción`), mover formularios entre módulos (reubicando el ícono y validando el módulo destino) y validar unicidad de nombre/ruta.

### 9.3 Asignación de Permisos a Roles

- **Frontend:** `gestor_roles_permisos_n.php`
- **Datos (JSON):** `sql/gestor_roles.php`
- **Acciones:** `actualizar_gestor_roles_permisos_n.php`

Interfaz con **árbol colapsable** de subsistemas → módulos → formularios. Para cada formulario muestra **badges por acción** (ver, crear, editar, eliminar, exportar, asignar, auditar, etc.):
- Al **asignar**: `INSERT IGNORE` en `permisos` y en `roles_permisos`.
- Al **revocar**: `DELETE` de `roles_permisos` y, si el permiso quedó huérfano, también de `permisos`.
- Contador de cambios pendientes y guardado por lotes en una **transacción**.

### 9.4 Administración de Usuarios

- **Frontend:** `gestor_usuarios_n.php`
- **Datos (JSON):** `sql/gestor_usuario_n.php` + `sql/buscar_usuario_cedula_n.php`
- **Acciones:** `actualizar_gestor_usuarios_n.php`

Permite **asignar**, **editar** y **eliminar** (soft delete) roles de usuarios:
- Asignación por **cédula** (búsqueda), **subsistema** y **código presupuestario**.
- **Protección del rol Root:** requiere escribir la palabra `ROOT` para confirmar y muestra avisos de advertencia y contador de usuarios Root.
- Verifica duplicados y reactiva registros eliminados lógicamente.

---

## 10. Modelo de datos

### 10.1 Tablas del nuevo gestor de navegación/permisos

| Tabla | Propósito | Relaciones |
|-------|-----------|------------|
| `subsistemas` | Nivel 1 de la navegación (cards del menú). | 1:N → `modulos` |
| `modulos` | Nivel 2 (agrupaciones funcionales). | N:1 `subsistemas`; 1:N → `formularios` |
| `formularios` | Nivel 3, una fila por página del sistema con su `ruta`. | N:1 `modulos`; 1:N → `permisos` |
| `acciones` | Catálogo de operaciones granulares (ver, crear, editar…). | 1:N → `permisos` |
| `permisos` | Combinación `formulario_id × accion_id`. | N:N `roles` vía `roles_permisos` |
| `roles_permisos` | Asignación de permisos a roles. | N:1 `t_roles`; N:1 `permisos` |

Catálogo de acciones del sistema (11):

| ID | Acción |
|----|--------|
| 1 | ver |
| 2 | crear |
| 3 | editar |
| 4 | eliminar |
| 5 | exportar |
| 6 | importar |
| 7 | aprobar |
| 8 | asignar |
| 9 | cerrar |
| 10 | escalar |
| 11 | auditar |

### 10.2 Tablas de usuarios y roles

| Tabla | Propósito |
|-------|-----------|
| `usuarios` | Funcionarios (cédula única, correo, `azure_id`, sexo, `ultimo_acceso`). |
| `t_roles` | Catálogo de roles (Root, Administrador, Prestador, Inventariador, Solicitante, Consultor, Mesa de Servicios, Soporte Virtual, Coordinador en Sitio, Soporte en Sitio). |
| `usuarios_roles` | Asignación de rol por **usuario + subsistema + código presupuestario**; incluye `eliminado` (soft delete) y `created_by` (auditoría). |
| `t_lista_blanca` | Fuente heredada de rol inicial automático (cédula + código → rol). |

> **Soft delete:** `subsistemas`, `modulos`, `formularios` y `usuarios_roles` usan la columna `eliminado` (0 activo / 1 inactivo), permitiendo reactivación sin pérdida de datos ni relacional.

---

## 11. Usuarios, roles y permisos

### 11.1 Modelo de autorización

```
Usuario ──(1:N)── usuarios_roles ──(N:1)── t_roles ──(N:N)── roles_permisos
                                                              │
                                                  (N:1) permisos
                                                              │
                                            formulario_id × accion_id
                                                              │
                                        ──► formulaforms (ruta archivo)
```

**Regla de acceso a un formulario:** el usuario debe tener al menos un rol (para su código presupuestario) cuyo conjunto de permisos incluya el permiso `ver` (o cualquier acción) del formulario cuya `ruta` coincide con el archivo solicitado. Root (`rol_id = 1`) omite esta validación.

### 11.2 Roles del sistema

| ID | Rol | Descripción |
|----|-----|-------------|
| 1 | Root | Acceso total, administra módulos, formularios, permisos y usuarios. |
| 2 | Administrador | Gestión administrativa del centro educativo. |
| 3 | Prestador | Préstamos de equipos. |
| 4 | Inventariador | Gestión de inventario. |
| 5 | Solicitante | Solicita equipos (rol por defecto). |
| 7 | Consultor | Solo lectura / reportes. |
| 8 | Mesa de Servicios | Atención de mesa de servicio. |
| 9 | Soporte Virtual | Soporte técnico remoto. |
| 10 | Coordinador en Sitio | Coordinación presencial. |
| 11 | Soporte en Sitio | Soporte técnico presencial. |

### 11.3 Asignación automática vs. manual

- **Automática (primer inicio):** si el usuario no tiene roles, se le asigna el rol de `T_Lista_Blanca` (si aplica y es válido) o **Solicitante (5)**.
- **Manual (posterior):** los administradores (Root) asignan/editan roles desde `gestor_usuarios_n.php`. **Los roles ya asignados no se sobreescriben** en inicios de sesión posteriores, a diferencia del modelo anterior.

---

## 12. Seguridad

Medidas implementadas en la nueva estructura:

1. **Punto único de entrada** (`navegar.php`) que antecede a **todo** formulario interno.
2. **Validación de sesión central** (`auth.php::validarSesion()`) con redirección a login.
3. **Validación de permisos central** (`auth.php::validarPermisoRuta()`) basada en sesión, no en BD (O(1)).
4. **Protección contra acceso directo** mediante la constante `ACCESO_SEGURO` verificada al inicio de cada formulario.
5. **Lista blanca de extensiones** (`php`, `html`).
6. **Sanitización de rutas:** regex de caracteres permitidos + `basename()` + `realpath()` confinado al directorio base (anti path-traversal).
7. **Prepared statements** (PDO) en todos los endpoints nuevos de datos y acciones.
8. **Control de acceso Root en endpoints**: todos los gestores y endpoints verifican `esUsuarioRoot()` y devuelven `401/403`.
9. **Escape HTML en el frontend** para evitar XSS en búsquedas/visualización de datos.
10. **Confirmación reforzada para rol Root** (escribir "ROOT") en la administración de usuarios.
11. **Transacciones** en operaciones multi-tabla (asignación de permisos, CRUD de formularios, roles de usuario) con `ROLLBACK` ante errores.
12. **Validación de integridad** al desactivar subsistemas/módulos/formularios con hijos activos.

---

## 13. Diagrama de flujo de acceso

```
Inicio de sesión Azure (index.html / MSAL.js)
        │
        ▼
sql/formulario_principal.php  (JSON)
        ├─ ¿Usuario existe? → NO → INSERT usuarios
        ├─ ¿Usuario tiene roles? → NO → T_Lista_Blanca / Solicitante(5)
        ├─ Consulta menú (Root → todo | Otros → roles_permisos)
        ├─ Construye $_SESSION['funcionario']['auth']
        └─ Devuelve menú JSON → frontend
        │
        ▼
formulario_menu_principal.php  (Cards de subsistemas)
        │
        ▼
formulario_modulos.php  (Módulos)
        │
        ▼
formulario_sub_modulos.php  (Formularios con acciones)
        │
        ▼
navegar.php?ruta=gestor_X_n.php&subsistema_id&modulo_id&formulario_id
        │
        ├─ validarSesion()            → 401/redirección si no hay sesión
        ├─ regex + basename           → 400 si la ruta es inválida
        ├─ whitelist de extensión     → 403 si la extensión no es permitida
        ├─ realpath dentro del base   → 403 si intenta escapar del directorio
        ├─ file_exists()              → 403 si el archivo no existe
        ├─ validarPermisoRuta()       → 403 si no tiene permiso
        ├─ define('ACCESO_SEGURO', true)
        └─ require_once archivo → se ejecuta el formulario
```

---

## 14. Ventajas de la nueva estructura

### Mantenibilidad
- Configuración del menú y permisos **sin tocar código**: se hace desde la interfaz administrativa.
- `auth.php` elimina la duplicación de validaciones en decenas de archivos.
- Módulos y formularios se **activan/desactivan** sin borrar archivos ni datos.

### Rendimiento
- La autorización se evalúa contra la **sesión** (arreglos en memoria), no con consultas SQL por petición.
- El dispatcher ya no ejecuta `COUNT(*)` por cada carga; solo validaciones de bajo costo.

### Flexibilidad y escalabilidad
- **Múltiples roles por usuario** por centro, ampliando los perfiles de un funcionario.
- Jerarquía de 4 niveles permite granularidad fina (acción por formulario por rol).
- Estructura lista para crecer: se agregan módulos/formularios vía SQL o interfaz.

### Seguridad
- Un único punto de control reduce el riesgo de "puertas traseras" por formularios sin validar.
- Defensa en profundidad (sesión + permisos + bloqueo de acceso directo + sanitización de rutas + prepared statements).

### Control institucional
- Root puede **auditar y reportar** qué formularios/acciones tiene cada rol.
- La asignación de usuarios y roles queda registrada (`created_by`, timestamps).

### Consistencia de datos
- Soft delete + restricciones únicas/integridad evitan huérfanos e inconsistencias.
- Transacciones protegen la coherencia en operaciones multi-tabla.

---

## 15. Importancia de la refactorización

1. **Resiliencia operativa.** Un sistema que administra el inventario tecnológico nacional del MEP requiere que el acceso a cada módulo sea **predecible y auditable**. La centralización evita fallos de configuración y accesos indebidos.

2. **Rapidez de respuesta al cambio institucional.** Si el MEP reestructura servicios, un administrador puede **reorganizar el menú en minutos** (crear módulos, mover formularios, ajustar roles) sin esperar liberaciones de código.

3. **Gobernanza y seguridad de datos.** La separación estricta de roles (Root, Prestador, Inventariador, Consultor, etc.) y el permiso fino por acción reducen el riesgo de **alteración indebida de inventario** y refuerzan el principio de **menor privilegio**.

4. **Sostenibilidad del código.** Eliminar el acoplamiento y la repetición hace al sistema **mantenible por más de un desarrollador** y reduce el costo futuro de cambios.

5. **Base para evolución.** La arquitectura prepara el terreno para migrar a un framework (por ejemplo, un `Router` formal, MVC con servicios y repositorios) y para integrar **auditoría completa de acciones** (la acción `auditar` ya forma parte del catálogo).

---

## 16. Migración y compatibilidad

- **Compatibilidad:** el dispatcher sigue cargando los formularios existentes que ya validan `ACCESO_SEGURO`. Los archivos legados (sin el protector) siguen funcionando si se invocan directamente, aunque **se recomienda migrarlos**.
- **Convención de archivos nuevos:** la nueva versión usa el sufijo **`_n`** (p. ej. `gestor_roles_permisos_n.php`, `plataforma_soporte_n.php`). Los archivos sin sufijo son **legado** que se mantiene por compatibilidad.
- **Migración de roles:** `migrar_roles.php` migra `t_lista_blanca → usuarios + usuarios_roles` para los roles 2, 3 y 4 (idempotente; omite duplicados).
- **Scripts de inicialización de BD:** `sql/gestor_sistema_init.sql` (módulo Gestor del Sistema), `sql/planificacion_modulo.sql` (módulo Planificación), `sql/registro_formulario_registro_activos.sql`, `sql/catalogo_modelos_registro.sql` y `sql/auditor_solo_lectura.sql` crean módulos/formularios/permisos mediante inserts parametrizados por BD.
- **Estado actual:** se detectaron ~40 formularios con protector `ACCESO_SEGURO` ya implementado; la migración es incremental (un formulario a la vez sincronizado con su registro en la tabla `formularios`).

**Guía para migrar un formulario nuevo:**
1. Crear el formulario (o actualizar el existente) en la tabla `formularios` (campo `ruta` = nombre del archivo, `modulo_id`, `orden`).
2. Agregar en la tabla `permisos` las combinaciones `formulario_id × accion_id` deseadas.
3. Asignar en `roles_permisos` los permisos a los roles correspondientes.
4. Añadir al inicio del archivo el bloque de protección `ACCESO_SEGURO`.
5. Reemplazar los enlaces directos por `navegar.php?ruta=...`.

---

## 17. Convenciones de nomenclatura

- **`*_n.php`** → versión nueva/refactorizada del formulario.
- **`*.php` (sin sufijo)** → versión anterior/legado, compatible por un tiempo.
- **`*copia*`, `* - copia *`** → respaldos (ignorados por `.gitignore`).
- **`*.php` en `sql/`** → **endpoints JSON** de datos o acciones (Backend-as-a-API liviano).
- **`partials/header.php`, `partials/footer.php`** → componentes reutilizables de layout.
- **`menu/`** → componentes de menú específicos de páginas legado (`menu_izquierdo.php`).
- **`assets/img/formularios`**, `assets/img/modulos`, `assets/img/subsistemas` → íconos SVG subidos por los gestores, organizados por módulo.

---

## 18. Evolución futura y recomendaciones

1. **Migrar el 100% de los formularios** a la protección `ACCESO_SEGURO` + registro en `formularios` para eliminar accesos directos legados.
2. **Registrar las acciones de auditoría:** usar el permiso `auditar (11)` para crear bitácoras de quién asigna permisos/roles y cuándo.
3. **Cachear el menú en sesión** (ya se guarda `rutas_permitidas`) y evaluar también cachear el JSON del menú para reducir aún más consultas.
4. **Extraer `sql/formulario_principal.php`** hacia servicios (clases `UsuarioService`, `MenuService`, `PermisoService`) respetando la arquitectura emergente con capas.
5. **Implementar migraciones versionadas** en SQL (tabla `schema_migrations`) en vez de scripts sueltos.
6. **Considerar la adopción de un router formal** (p. ej. patrones PSR-7/PSR-15 o un micro-framework) a partir del dispatcher actual, conservando la lógica de permisos central.

---

## 19. Anexos

### A. Referencia de archivos clave de la nueva arquitectura

| Tipo | Archivo | Descripción |
|------|---------|-------------|
| Router | `navegar.php` | Dispatcher central de formularios. |
| Seguridad | `auth.php` | Autenticación/autorización central (sin BD). |
| Sesión | `sql/formulario_principal.php` | Construye sesión, crea usuario, asigna roles y menú (JSON). |
| Menú | `formulario_menu_principal.php` | Cards de subsistemas. |
| Menú | `formulario_modulos.php` | Grid de módulos. |
| Menú | `formulario_sub_modulos.php` | Grid de formularios. |
| Gestor | `gestor_modulos_n.php` + `actualizar_gestor_modulos_n.php` + `sql/gestor_modulos.php` | CRUD de subsistemas/módulos. |
| Gestor | `gestor_formularios_n.php` + `actualizar_gestor_formularios_n.php` + `sql/gestor_formularios.php` | CRUD de formularios y sus acciones. |
| Gestor | `gestor_roles_permisos_n.php` + `actualizar_gestor_roles_permisos_n.php` + `sql/gestor_roles.php` | Árbol de permisos por rol. |
| Gestor | `gestor_usuarios_n.php` + `actualizar_gestor_usuarios_n.php` + `sql/gestor_usuario_n.php` | Administración de usuarios y roles. |
| Layout | `partials/header.php`, `partials/footer.php` | Encabezado/pie reutilizables. |
| BD | `ESTRUCTURA_TABLAS_LTECNOPRE.sql` | Esquema de `usuarios`, `usuarios_roles`, `t_roles`. |
| BD | `sql/gestor_sistema_init.sql` | Inicialización del módulo Gestor del Sistema. |
| Migración | `migrar_roles.php` | Lista Blanca → usuarios/roles. |

### B. Correspondencia de rutas navegables

```
navegar.php?ruta=formulario_menu_principal.php        → Dashboard (subsistemas)
navegar.php?ruta=formulario_modulos.php&subsistema_id → Módulos de un subsistema
navegar.php?ruta=formulario_sub_modulos.php&subsistema_id&modulo_id
                                                     → Formularios de un módulo
navegar.php?ruta=<archivo>.php&subsistema_id&modulo_id&formulario_id
                                                     → Formulario ejecutable
```