---
title: "Informe Técnico — TecnoPresta v1.1"
subtitle: "Principales cambios, mejoras y funcionalidades incorporadas"
sistema: "TecnoPresta — Sistema de Préstamo e Inventario de Equipos Tecnológicos"
version_sistema: "v1.1"
version_documento: "1.0"
fecha: "2026-11-04"
clasificacion: "Uso interno"
institucion: "Ministerio de Educación Pública de Costa Rica (MEP)"
dependencia: "Dirección de Recursos Tecnológicos en Educación (DRTE)"
documento_formal: "docs/Informe_Tecnico_TecnoPresta_v1.1.docx"
documento_consulta: "docs/Informe_Tecnico_TecnoPresta_v1.1.md"
---

# Informe Técnico — TecnoPresta v1.1

**Principales cambios, mejoras y funcionalidades incorporadas**

**Documento técnico consolidado**  
Dirección de Recursos Tecnológicos en Educación (DRTE)  
Ministerio de Educación Pública de Costa Rica  
Noviembre 2026 · Versión del documento 1.0 · Uso interno

> **Nota para consultas posteriores:** este archivo Markdown conserva el contenido completo del informe y está pensado como fuente de consulta rápida para personas y agentes. La entrega formal en Word es `docs/Informe_Tecnico_TecnoPresta_v1.1.docx`. Si existe alguna diferencia de presentación, prevalece el contenido de este documento y la documentación técnica v1.1 citada en las fuentes.

## Datos clave

- **Sistema:** TecnoPresta — Sistema de Préstamo e Inventario de Equipos Tecnológicos.
- **Versión analizada:** v1.1.
- **Institución:** Ministerio de Educación Pública de Costa Rica (MEP).
- **Dependencia:** Dirección de Recursos Tecnológicos en Educación (DRTE).
- **Arquitectura:** modular, dinámica y configurable por base de datos.
- **Jerarquía funcional:** Subsistemas → Módulos → Formularios → Acciones.
- **Documento formal:** `docs/Informe_Tecnico_TecnoPresta_v1.1.docx`.
- **Documento de consulta:** `docs/Informe_Tecnico_TecnoPresta_v1.1.md`.


## Ficha del documento

**Tabla 1. Identificación del documento**

| Campo | Detalle |
| --- | --- |
| Título | Informe Técnico — TecnoPresta v1.1 |
| Sistema | TecnoPresta — Sistema de Préstamo e Inventario de Equipos Tecnológicos |
| Institución | Ministerio de Educación Pública de Costa Rica (MEP) |
| Dependencia | Dirección de Recursos Tecnológicos en Educación (DRTE) |
| Versión analizada | TecnoPresta v1.1 |
| Versión del documento | 1.0 |
| Fecha de elaboración | 4 de noviembre de 2026 |
| Clasificación | Uso interno |
| Elaborado por | Dirección de Recursos Tecnológicos en Educación (DRTE) |
| Fuentes principales | Documentación técnica v1.1, documento ejecutivo v1.1, README, documentos SGEM v1.0, registro de activos y verificación puntual de código. |


## Control de versiones

**Tabla 2. Historial de versiones**

| Versión | Fecha | Descripción del cambio | Elaborado por |
| --- | --- | --- | --- |
| 1.0 | 04/11/2026 | Emisión inicial del informe técnico consolidado de TecnoPresta v1.1. | DRTE |


## Contenido

**Tabla 3. Estructura del informe**

| N.º | Sección |
| --- | --- |
| 1 | Resumen ejecutivo |
| 2 | Objetivo, alcance y método |
| 3 | Ficha técnica |
| 4 | Contexto: situación anterior |
| 5 | Arquitectura de TecnoPresta v1.1 |
| 6 | Cambios y mejoras por dominio |
| 7 | Clasificación de las funcionalidades incorporadas |
| 8 | Tabla comparativa: TecnoPresta anterior vs. TecnoPresta v1.1 |
| 9 | Impacto del cambio |
| 10 | Consideraciones, riesgos y pendientes |
| 11 | Conclusiones |
| 12 | Fuentes consultadas |
| A | Anexo A. Roles documentados |
| B | Anexo B. Acciones del sistema |
| C | Anexo C. Modos de consulta de activos |
| D | Anexo D. Archivos clave de la arquitectura |
| E | Anexo E. Glosario técnico |


---


## 1. Resumen ejecutivo

TecnoPresta v1.1 representa una evolución estructural del sistema: pasa de una aplicación monolítica, con menús y permisos embebidos en código y validaciones SQL repetidas en cada página, a una arquitectura modular, dinámica y gobernada por base de datos.

Los ejes principales del cambio son:

1. **Navegación dinámica y jerárquica:** Subsistemas → Módulos → Formularios → Acciones.
2. **Seguridad centralizada:** dispatcher único (`navegar.php`), módulo de autorización (`auth.php`) y protección `ACCESO_SEGURO`.
3. **Autorización sin consulta SQL por petición:** las rutas permitidas se resuelven desde la sesión.
4. **Modelo multirrol y multicentro persistente:** un usuario puede tener varios roles por código presupuestario y las asignaciones ya no se sobrescriben en cada inicio de sesión.
5. **Gestor del Sistema:** administración visual de subsistemas, módulos, formularios, permisos y usuarios.
6. **Inventario modernizado:** registro de activos en dos fases, catálogo maestro de modelos y consulta unificada de activos con 13 modos.
7. **Préstamos con ámbito de prestador:** restricción de equipos por recintos o laboratorios asignados.
8. **Integraciones institucionales y movilidad:** Microsoft 365/Teams, Graph API, SSO Azure AD, notificaciones push y PWA.

La versión v1.1 no debe interpretarse como una sustitución total e inmediata del legado. La documentación indica una migración incremental: los archivos nuevos usan el sufijo `_n`, mientras los archivos sin sufijo se mantienen por compatibilidad. Por ello, el valor completo de la nueva arquitectura depende de completar la migración de los formularios pendientes.


## 2. Objetivo, alcance y método


### 2.1 Objetivo

Documentar los principales cambios, mejoras técnicas y funcionalidades incorporadas en TecnoPresta v1.1, y establecer una comparación estructurada con la versión anterior o legado.


### 2.2 Alcance

El informe cubre los siguientes ámbitos:

- Arquitectura general y navegación.
- Seguridad y autorización.
- Usuarios, roles y permisos.
- Gestor del Sistema.
- Gestión de inventario y registro de activos.
- Catálogo de modelos.
- Consulta y reportes de activos.
- Préstamos y ámbitos de prestador.
- Soporte técnico, citas y planificación.
- Integraciones externas y PWA.
- Migración, compatibilidad y pendientes.


### 2.3 Método

Se revisaron los documentos técnicos y ejecutivos disponibles, se contrastaron las afirmaciones principales con el código del repositorio y se clasificaron los cambios en tres categorías:

- **Nuevas funcionalidades.**
- **Mejoras de funcionalidades existentes.**
- **Cambios arquitectónicos o de infraestructura.**

Cuando hubo diferencias entre documentos, se priorizó la documentación técnica v1.1 sobre la documentación v1.0 y sobre los documentos SGEM de julio de 2026.

> **Nota:** Limitación: este informe es una síntesis documental y técnica. No constituye una certificación funcional completa ni reemplaza las pruebas de aceptación en un ambiente controlado.


## 3. Ficha técnica

**Tabla 4. Ficha técnica del sistema**

| Campo | Detalle |
| --- | --- |
| Nombre oficial | TecnoPresta — Sistema de Préstamo e Inventario de Equipos Tecnológicos |
| Institución | Ministerio de Educación Pública de Costa Rica |
| Dependencia técnica | Dirección de Recursos Tecnológicos en Educación (DRTE) |
| Versión analizada | v1.1 |
| Arquitectura | Modular, dinámica y configurable por base de datos |
| Jerarquía funcional | Subsistemas → Módulos → Formularios → Acciones |
| Backend | PHP 8.1, PDO, MySQL/MariaDB |
| Frontend | Bootstrap 5, Alpine.js 3, jQuery legado, SweetAlert2, Chart.js/ECharts |
| Autenticación | SSO con Microsoft Azure AD / MSAL.js |
| Integraciones | Microsoft Graph, Teams, Outlook, SMTP Office 365, SOAP/WS del MEP, Telegram, Web Push |
| Movilidad | PWA instalable, funcionamiento offline básico y notificaciones push |
| Portal de producción | https://tecnopresta.mep.go.cr/ |
| Población usuaria | Directores, bibliotecólogos, directores regionales, asesores de tecnología, docentes, mesa de servicios e ingenieros de soporte. |


## 4. Contexto: situación anterior


### 4.1 Problemas del modelo legado

La documentación identifica seis problemas principales en la versión anterior:

1. **Menús y permisos acoplados al código.** La visibilidad de formularios y las acciones permitidas se resolvían con consultas SQL dispersas o validaciones de rol embebidas en cada archivo. Agregar, mover u ocultar un formulario obligaba a modificar código fuente y, en muchos casos, varios archivos.
2. **Validación de permisos con consulta a base de datos en cada petición.** El dispatcher anterior ejecutaba un `SELECT COUNT(*)` por cada carga de página. Esto incrementaba la latencia, duplicaba lógica y podía generar inconsistencias.
3. **Un solo rol por usuario y centro educativo.** La función de obtención de rol usaba `LIMIT 1`. Los roles se sobrescribían en cada inicio de sesión a partir de `T_Lista_Blanca`, impidiendo asignaciones administrativas duraderas.
4. **Lógica de sesión duplicada.** La construcción de la sesión, la detección de Root y la consulta del menú estaban replicadas en variantes de archivos.
5. **Seguridad dependiente del desarrollador.** Cada página debía implementar manualmente la validación de sesión y el bloqueo de acceso directo. Un descuido dejaba formularios accesibles por URL.
6. **Personalización institucional limitada.** Íconos, orden y descripciones estaban codificados. No era posible reordenar, renombrar o desactivar elementos sin intervención técnica.


### 4.2 Objetivos de la refactorización

- Desacoplar navegación y permisos de la implementación.
- Centralizar la seguridad en un único punto.
- Soportar múltiples roles por usuario y por código presupuestario.
- Permitir la administración visual del menú, formularios, acciones y roles.
- Reducir consultas a base de datos por petición.
- Mantener compatibilidad con el sistema en producción.


## 5. Arquitectura de TecnoPresta v1.1


### 5.1 Jerarquía funcional de cuatro niveles

La navegación y la autorización se modelan mediante cuatro niveles:

**Tabla 5. Jerarquía funcional**

| Nivel | Entidad | Ejemplo |
| --- | --- | --- |
| 1 | Subsistema | Administración del Sistema, Gestión de Servicios |
| 2 | Módulo | Gestor del Sistema, Planificación |
| 3 | Formulario | Asignación de Permisos a Roles → `gestor_roles_permisos_n.php` |
| 4 | Acción o permiso granular | ver, crear, editar, eliminar, exportar, importar, aprobar, asignar, cerrar, escalar, auditar |

Cada formulario se asocia a una ruta física. Cada permiso es la combinación `formulario × acción`. Los roles agrupan permisos y los usuarios reciben uno o más roles por centro educativo.


### 5.2 Flujo de acceso

El flujo documentado es el siguiente:

1. El usuario inicia sesión mediante Azure AD/MSAL.js.
2. `sql/formulario_principal.php` valida la sesión, crea o actualiza el usuario y asigna roles iniciales si corresponde.
3. Se construye el menú jerárquico y se guarda en sesión la lista de rutas permitidas.
4. El frontend muestra subsistemas, módulos y formularios.
5. Cada enlace interno pasa por `navegar.php?ruta=...`.
6. El dispatcher valida sesión, ruta, extensión, existencia física y permisos.
7. Se define la constante `ACCESO_SEGURO`.
8. Se carga el formulario solicitado.


### 5.3 Modelo de datos principal

**Tabla 6. Tablas principales de la arquitectura**

| Tabla | Propósito |
| --- | --- |
| `subsistemas` | Nivel 1 de la navegación |
| `modulos` | Agrupaciones funcionales dentro de un subsistema |
| `formularios` | Páginas registradas y su ruta física |
| `acciones` | Catálogo de operaciones granulares |
| `permisos` | Combinación formulario × acción |
| `roles_permisos` | Asignación de permisos a roles |
| `usuarios` | Funcionarios registrados |
| `t_roles` | Catálogo de roles |
| `usuarios_roles` | Asignación de rol por usuario, subsistema y código presupuestario |
| `t_lista_blanca` | Fuente heredada para la asignación inicial de roles |
| `t_ambitos_prestador` | Ámbitos de custodia de prestadores |
| `t_ambitos_prestador_lugar` | Lugares o recintos asociados a cada ámbito |

Las tablas `subsistemas`, `modulos`, `formularios` y `usuarios_roles` utilizan borrado lógico mediante la columna `eliminado`, lo que permite desactivar y reactivar sin perder relaciones ni datos.


### 5.4 Seguridad

Las medidas documentadas son:

- Punto único de entrada para formularios internos: `navegar.php`.
- Validación central de sesión mediante `auth.php`.
- Validación central de permisos con base en la sesión.
- Protección contra acceso directo mediante `ACCESO_SEGURO`.
- Lista blanca de extensiones permitidas: `php` y `html`.
- Sanitización de rutas con expresión regular, `basename()` y `realpath()`.
- Protección contra path traversal.
- Uso de consultas preparadas con PDO en los endpoints nuevos.
- Verificación del rol Root en los endpoints administrativos.
- Escape HTML en el frontend para prevenir XSS.
- Confirmación reforzada para operaciones sobre el rol Root.
- Transacciones en operaciones que afectan varias tablas.
- Validación de integridad al desactivar subsistemas, módulos o formularios con hijos activos.


### 5.5 Rendimiento

La mejora principal es que la autorización ya no requiere una consulta SQL por petición. La lista de rutas permitidas se construye al iniciar sesión y se consulta en memoria.

La documentación denomina a este comportamiento “autorización O(1)”. En términos técnicos, se trata de una comprobación en memoria con costo muy bajo y sin acceso a base de datos, aunque no necesariamente constante respecto al número de rutas.

El menú y la sesión se construyen en el inicio de sesión, no en cada clic. La documentación recomienda, como evolución futura, cachear también el JSON del menú.


### 5.6 Frontend

- Alpine.js como motor reactivo.
- Bootstrap 5 para la interfaz.
- Fetch al endpoint `sql/formulario_principal.php`.
- Buscador con normalización de acentos y escape HTML.
- Componentes reutilizables `partials/header.php` y `partials/footer.php`.
- Diseño institucional alineado con la identidad del MEP.


## 6. Cambios y mejoras por dominio


### 6.1 Navegación y menú dinámico

**Antes:** menús estáticos en código PHP.

**Ahora:**

- El menú se alimenta de las tablas `subsistemas`, `modulos` y `formularios`.
- Los administradores pueden crear, editar, ordenar, activar y desactivar elementos sin tocar código.
- Los formularios se agrupan por módulos y se asocian a acciones.
- La navegación mantiene una jerarquía visual de tres niveles y una autorización de cuatro niveles.

**Valor:** mayor capacidad de respuesta institucional, menor dependencia del equipo de desarrollo y menor riesgo de inconsistencias entre menú y permisos.


### 6.2 Usuarios, roles y permisos

**Antes:** un rol por usuario y centro; roles sobrescritos en cada login; asignaciones poco persistentes.

**Ahora:**

- Múltiples roles por usuario y por código presupuestario.
- Asignaciones persistentes; si el usuario ya tiene roles, no se sobrescriben.
- Asignación inicial desde `T_Lista_Blanca` solo cuando el usuario no tiene roles.
- Rol por defecto: Solicitante.
- Borrado lógico de asignaciones.
- Registro de `created_by` y marcas de tiempo.
- Administración desde el Gestor del Sistema.
- Validación del código presupuestario contra el servicio web del MEP: si el WS devuelve centros, el código debe coincidir; si el WS falla o no devuelve centros, se permite guardar con una advertencia para no bloquear la administración.

**Valor:** modelo más realista para funcionarios que trabajan en varios centros o desempeñan varios roles, con mayor trazabilidad y control.


### 6.3 Gestor del Sistema

El módulo “Gestor del Sistema” se ubica bajo el subsistema “Administración del Sistema” y contiene cuatro formularios principales:

1. **Gestión de módulos.** CRUD de subsistemas y módulos. Carga de íconos SVG con validación de extensión, tamaño máximo de 500 KB y MIME. Validación de integridad: no se puede desactivar un subsistema con módulos activos ni un módulo con formularios activos.
2. **Gestión de formularios.** CRUD de formularios. Asignación de acciones disponibles. Movimiento de formularios entre módulos. Validación de unicidad de nombre y ruta.
3. **Asignación de permisos a roles.** Árbol colapsable de subsistemas, módulos y formularios. Badges por acción. Asignación y revocación de permisos. Guardado por lotes en una transacción. Limpieza de permisos huérfanos.
4. **Administración de usuarios.** Asignación, edición y eliminación lógica de roles. Búsqueda por cédula. Asignación por subsistema y código presupuestario. Confirmación estricta para el rol Root. Verificación de duplicados y reactivación de registros eliminados lógicamente.

**Antecedente:** el SGEM v1.0 de julio de 2026 definió tres formularios administrativos. La versión v1.1 consolida el módulo y agrega la administración de usuarios.

**Valor:** gobernanza del sistema sin intervención manual en base de datos.


### 6.4 Registro de activos en dos fases

El registro de nuevos activos se rediseñó como un formulario tipo SPA con dos fases:

1. **Búsqueda en catálogo maestro.** El usuario busca por tipo, marca o modelo. El sistema retorna únicamente combinaciones que cumplen toda la cadena relacional. El filtro por fondo presupuestario existe, pero está oculto y es opcional en la búsqueda.
2. **Registro de la unidad física.** Se muestra imagen, tipo, marca y modelo. El usuario selecciona color de una paleta fija de 12 colores. Ingresa placa, serial y origen presupuestario. El sistema valida modelo activo, existencia del fondo, cadena relacional, placa duplicada y serial duplicado. Si hay duplicado, indica si pertenece al mismo centro o a otro.

**Reglas relevantes:**

- No se crean nuevos registros en `t_activo`; solo se registran unidades físicas en `t_placa`.
- La fuente presupuestaria se guarda directamente en `t_placa.id_fondos`.
- Cada unidad física puede tener su propio fondo.
- La relación modelo–fondo de `t_modelo_fondos` quedó como legado y no participa en la búsqueda ni en el registro.
- El fondo es opcional en la búsqueda, pero obligatorio en el registro.

**Valor:** mayor consistencia del inventario, reducción de duplicados y mejor trazabilidad del origen presupuestario.

> **Nota:** Mejora reciente relacionada: la eliminación nocturna ahora identifica activos con t_placa.activo = 0, los muestra con el estado “Desactivado” y permite seleccionarlos para eliminación. El badge se conserva al marcar y revertir el activo.


### 6.5 Catálogo maestro de modelos

El catálogo `gestor_catalogo_modelos_n.php` permite administrar:

- Modelos en `t_modelos`.
- Combinaciones Tipo + Marca + Modelo materializadas en `t_activo`.
- Conteo de unidades físicas por combinación.
- Creación, edición, activación y desactivación de modelos.
- Creación, edición y eliminación de combinaciones.

**Reglas técnicas:**

- `t_modelos` contiene únicamente el nombre del modelo y metadatos; no contiene `id_ag` ni `id_marca`.
- El tipo y la marca se derivan de `t_activo`.
- No se permite eliminar una combinación con unidades físicas registradas en `t_placa`.
- Los scripts antiguos que intentan agregar `id_ag` o `id_marca` a `t_modelos` no deben ejecutarse.

**Valor:** estandarización del catálogo técnico y reducción de modelos duplicados o inconsistentes.


### 6.6 Consulta unificada de activos

El formulario `consulta_activos_n.php` sustituye al reporte que solo listaba activos para donación. Ahora es un motor genérico con **13 modos de consulta**:

**Tabla 7. Modos de consulta de activos**

| Grupo | Modos |
| --- | --- |
| General | `inventario` |
| Condición del activo | `excelente`, `bueno`, `regular`, `malos`, `robados` |
| Disponibilidad y uso | `prestables`, `no_prestables`, `en_uso`, `no_uso` |
| Ubicación | `sin_ubicacion` |
| Situación administrativa | `donacion`, `baja` |

**Características técnicas:**

- El código presupuestario es obligatorio y se normaliza a cuatro dígitos.
- No existe un modo “todos los centros” por diseño.
- El modo por defecto es `inventario`.
- Una clave de modo inexistente aplica el modo por defecto y muestra un aviso, sin error SQL.
- Una fuente inexistente consulta todas las fuentes y muestra un aviso.
- La construcción del `WHERE` es única y la comparten el conteo, el listado y los indicadores.
- El filtro base exige activo y no marcado para eliminación; el modo `baja` es la excepción.
- La paginación es de 100 filas por página.
- Los cuatro indicadores —Donación, Robados, Prestables, En uso— se obtienen en una sola consulta agregada.
- Los valores `NULL` se tratan como estados propios, no como ceros.
- Exportación a Excel, PDF e impresión con el mismo conjunto de datos.
- La exportación usa una sola consulta para todas las filas.
- En Excel se preservan códigos con cero inicial mediante `setCellValueExplicit`.
- Los badges exportados usan tintes claros con contraste WCAG AA.

**Valor:** unificación de reportes dispersos, mayor consistencia de datos y mejor legibilidad de las exportaciones.


### 6.7 Préstamos y ámbitos de prestador

**Antes:** cualquier prestador podía prestar cualquier activo del centro educativo.

**Ahora:**

- Cada prestador puede tener una o varias ubicaciones asignadas.
- El ámbito se registra en `t_ambitos_prestador` y sus lugares en `t_ambitos_prestador_lugar`.
- La asignación se realiza desde el formulario de creación/edición de roles.
- El ámbito puede agrupar lugares con un nombre institucional, por ejemplo “Equipo INCO” o “Laboratorio de Inglés”.
- La selección de activos para préstamo se filtra por los lugares del ámbito.

**Valor:** aplicación del principio de menor privilegio, reducción de préstamos indebidos y mejor control de custodia por área.


### 6.8 Soporte técnico, citas y planificación

Capacidades documentadas:

- Tickets administrativos y técnicos.
- Dashboard de seguimiento.
- Escalación a servicio técnico.
- Cierre y valoración de casos.
- Citas virtuales integradas con Microsoft Teams y Outlook mediante Graph API.
- Creación automática de reuniones en línea.
- Panel de administración de citas.
- Planificación de giras en sitio, itinerarios, rutas y viáticos.

**Valor:** continuidad del servicio educativo y mejor coordinación entre mesa de servicios, soporte virtual y soporte presencial.


### 6.9 Supervisión y documentación nacional

- Carga y validación digital de certificaciones anuales de inventario firmadas por directores.
- Tablero de cumplimiento de centros con certificaciones entregadas versus pendientes.
- Reportes institucionales y nacionales para toma de decisiones.

**Valor:** control documental y presupuestario a nivel nacional.


### 6.10 Integraciones y movilidad

Integraciones documentadas:

- **Azure AD / MSAL.js:** inicio de sesión único con cuenta `@mep.go.cr`.
- **Microsoft Graph API:** perfil, foto, calendario de Outlook y reuniones de Teams.
- **SMTP Office 365:** notificaciones de solicitud, préstamo, devolución, rechazo y boletas.
- **Telegram:** bot `@tecnopresta_bot`.
- **Web Push / VAPID:** notificaciones push.
- **SOAP/WS del MEP:** validación de funcionarios y consulta de centros.
- **PWA:** instalación desde el navegador, página de respaldo offline y notificaciones.

**Valor:** menor coordinación manual, notificaciones oportunas y acceso desde dispositivos móviles.


### 6.11 Migración y compatibilidad

La estrategia documentada es incremental:

- Archivos nuevos con sufijo `_n`.
- Archivos sin sufijo se mantienen como legado compatible.
- Alrededor de 40 formularios ya cuentan con `ACCESO_SEGURO`.
- La migración se realiza formulario por formulario, sincronizando su registro en la tabla `formularios`.
- `migrar_roles.php` migra roles 2, 3 y 4 desde `T_Lista_Blanca` de forma idempotente.
- Existen scripts SQL de inicialización para el Gestor del Sistema, Planificación, registro de activos, catálogo de modelos y auditoría de solo lectura.
- Si se cambia `formularios.ruta`, los usuarios deben volver a iniciar sesión para actualizar `rutas_permitidas`.

**Valor:** reducción del riesgo de una migración “big bang” y continuidad operativa.


## 7. Clasificación de las funcionalidades incorporadas

**Tabla 8. Clasificación de cambios**

| Tipo de cambio | Funcionalidad o mejora |
| --- | --- |
| Nueva funcionalidad | Menú dinámico y jerárquico administrable desde base de datos |
| Nueva funcionalidad | Dispatcher central `navegar.php` y módulo de autorización `auth.php` |
| Nueva funcionalidad | Gestor del Sistema con módulos, formularios, permisos y usuarios |
| Nueva funcionalidad | Registro de activos en dos fases con catálogo maestro |
| Nueva funcionalidad | Catálogo administrable de modelos y combinaciones |
| Nueva funcionalidad | Consulta unificada de activos con 13 modos |
| Nueva funcionalidad | Ámbitos de prestador por lugares o recintos |
| Mejora | Multirrol y multicentro persistente |
| Mejora | Validación del código presupuestario contra el WS del MEP |
| Mejora | Seguridad centralizada y protección contra acceso directo |
| Mejora | Rendimiento de autorización sin consulta SQL por petición |
| Mejora | Exportaciones Excel/PDF/impresión con criterios de legibilidad |
| Mejora | Registro de activos con validación de placa y serial duplicados |
| Mejora | Eliminación nocturna con identificación de activos desactivados |
| Cambio arquitectónico | Convención `_n`, endpoints JSON, partials y borrado lógico |
| Cambio arquitectónico | Modelo de datos de navegación, permisos y roles |
| Integración | Azure AD, Graph, Teams, Outlook, SMTP, Telegram, Web Push |
| Movilidad | PWA instalable, offline básico y notificaciones push |
| Parcial | Auditoría granular: existe la acción `auditar`, pero la bitácora completa está pendiente |


---


## 8. Tabla comparativa: TecnoPresta anterior vs. TecnoPresta v1.1

La comparación distingue el legado —archivos sin sufijo `_n`, menú estático y autorización dispersa— de la versión v1.1. Algunas filas representan funcionalidades nuevas; otras, mejoras de procesos existentes; otras, cambios exclusivamente arquitectónicos.

**Tabla 9. Comparación entre TecnoPresta anterior y TecnoPresta v1.1**

| Dimensión | TecnoPresta anterior (legado) | TecnoPresta v1.1 |
| --- | --- | --- |
| Arquitectura y navegación | Aplicación monolítica con enlaces directos y lógica de navegación embebida en cada página. | Dispatcher central, jerarquía de cuatro niveles y navegación gobernada por base de datos. |
| Menús y personalización | Menús estáticos en PHP; cambios requerían editar código y varios archivos. | Subsistemas, módulos y formularios administrables visualmente, con orden, íconos y activación/desactivación. |
| Autorización | Validaciones SQL dispersas y comprobaciones de rol en cada archivo. | `auth.php` centraliza la autorización; las rutas permitidas se resuelven desde la sesión. |
| Rendimiento de acceso | `SELECT COUNT(*)` y consultas a base de datos en cada clic. | Sin consulta SQL por petición para autorizar; menú y rutas se construyen al iniciar sesión. |
| Seguridad de acceso | Páginas expuestas a acceso directo por URL si no se validaban manualmente. | Punto único de entrada, lista blanca, `ACCESO_SEGURO`, anti-path-traversal y respuestas 401/403. |
| Esquema de roles | Un rol por usuario y centro con `LIMIT 1`; roles sobrescritos en cada login. | Multirrol y multicentro persistente; las asignaciones administrativas no se sobrescriben. |
| Administración del sistema | Cambios de menú y permisos mediante scripts SQL o intervención técnica. | Gestor del Sistema para módulos, formularios, permisos y usuarios, con confirmación Root. |
| Registro de activos | Formulario legado y procesos dispersos. | Flujo de dos fases, catálogo maestro, validaciones de placa/serial y origen presupuestario por unidad. |
| Catálogo de modelos | Gestión limitada y relación modelo–fondo heredada. | CRUD de modelos y combinaciones, conteo de unidades, bloqueo de eliminación con unidades y sin `t_modelo_fondos`. |
| Reportes de activos | Reportes aislados y dispersos por condición. | Motor unificado de 13 modos, cuatro indicadores y exportación Excel/PDF/impresión. |
| Préstamos y custodia | Cualquier prestador podía prestar cualquier activo del centro. | Ámbitos de prestador delimitados por lugares o recintos asignados. |
| Soporte y citas | Coordinación manual de soporte por correo. | Tickets, dashboard, escalación, citas Teams/Outlook y planificación de giras. |
| Integraciones | Integraciones básicas y procesos manuales. | Azure AD, Graph, Teams, Outlook, SMTP, Telegram, Web Push y SOAP/WS del MEP. |
| Movilidad | Portal web estándar de escritorio. | PWA instalable, página de respaldo offline y notificaciones push. |
| Auditoría y trazabilidad | No se documenta una bitácora centralizada de cambios administrativos. | `created_by`, marcas de tiempo, borrado lógico y acción `auditar`; bitácora completa pendiente. |
| Migración y compatibilidad | Sin convención de archivos nuevos ni estrategia explícita de coexistencia. | Archivos `_n` para la nueva versión, legado compatible y migración incremental por formulario. |


## 9. Impacto del cambio


### 9.1 Impacto técnico

- Menor acoplamiento entre navegación, permisos y código.
- Menos duplicación de lógica de sesión y autorización.
- Mayor facilidad para agregar módulos y formularios.
- Estandarización del acceso a datos en endpoints nuevos.
- Mejor consistencia transaccional en operaciones administrativas.
- Base preparada para evolucionar hacia servicios, router formal y migraciones versionadas.


### 9.2 Impacto operativo

- Los administradores pueden reorganizar el menú sin esperar una liberación de código.
- Los roles pueden asignarse de forma más realista y persistente.
- El registro de activos reduce duplicados y mejora la calidad del inventario.
- Los reportes unificados reducen el trabajo manual y mejoran la toma de decisiones.
- Los ámbitos de prestador disminuyen el riesgo de préstamos indebidos.
- La integración con Teams y Outlook reduce la coordinación manual.


### 9.3 Impacto institucional

- Mayor transparencia sobre el inventario y el origen presupuestario.
- Mejor control de custodia de equipos.
- Gobernanza centralizada de accesos y permisos.
- Capacidad de respuesta más rápida ante cambios organizativos.
- Fortalecimiento del principio de menor privilegio.


### 9.4 Impacto en seguridad y datos

- Reducción de la superficie de acceso directo.
- Mayor control sobre rutas y acciones.
- Protección del rol Root.
- Borrado lógico para evitar pérdida de trazabilidad.
- Pendiente: completar la bitácora de auditoría y migrar los formularios legados que aún no tienen `ACCESO_SEGURO`.


## 10. Consideraciones, riesgos y pendientes

1. **Migración incompleta del legado.** La documentación indica que alrededor de 40 formularios ya tienen `ACCESO_SEGURO`, pero los archivos sin protector aún pueden ejecutarse directamente. El riesgo disminuye a medida que avanza la migración.
2. **Auditoría completa pendiente.** La acción `auditar` existe en el catálogo y las tablas nuevas tienen metadatos, pero la bitácora completa de quién cambió qué y cuándo aún se documenta como recomendación.
3. **Dependencia del inicio de sesión para rutas.** Si se modifica `formularios.ruta`, los usuarios deben volver a iniciar sesión para actualizar `rutas_permitidas`.
4. **Integridad de t_placa.id_fondos.** La columna no tiene clave foránea; la integridad se valida en la aplicación. Conviene monitorear datos huérfanos o inconsistentes.
5. **Legado de t_modelo_fondos.** La tabla existe, pero está vacía y no debe utilizarse. Los scripts antiguos que la reactivan o que alteran `t_modelos` no deben ejecutarse.
6. **Reporte de activos sin consolidado nacional.** El código presupuestario es obligatorio y no existe un modo “todos los centros” por diseño. Si se requiere un reporte nacional, debe abordarse como una funcionalidad distinta.
7. **Migraciones SQL no versionadas.** La documentación recomienda implementar `schema_migrations` y dejar de depender de scripts sueltos.
8. **Pruebas de regresión.** Los cambios en permisos, ámbitos, exportaciones y registro de activos requieren pruebas funcionales y de datos antes de considerarse cerrados.
9. **Consistencia documental.** Conviene mantener una sola versión normativa —v1.1— y marcar explícitamente los documentos v1.0 y SGEM como antecedentes.


## 11. Conclusiones

1. **TecnoPresta v1.1 es una modernización estructural, no solo un cambio visual.** La incorporación de un dispatcher, un módulo central de autorización y una jerarquía de navegación configurable cambia la forma en que el sistema se administra y evoluciona.
2. **El principal valor está en la gobernanza y la seguridad.** La posibilidad de administrar menús, formularios, permisos y roles desde la plataforma reduce la dependencia técnica y fortalece el control institucional.
3. **La gestión de inventario gana consistencia.** El registro en dos fases, el catálogo de modelos y la consulta unificada de 13 modos reducen dispersión, duplicados y esfuerzo manual.
4. **Los préstamos y el soporte se alinean mejor con la operación real.** Los ámbitos de prestador y la integración con Teams/Outlook responden a necesidades concretas de custodia y coordinación.
5. **La versión v1.1 es una base sólida, pero su madurez depende de completar la migración.** La coexistencia con el legado es una decisión prudente, aunque implica mantener temporalmente dos modelos de seguridad y navegación.
6. **La deuda técnica prioritaria es acotada y conocida.** Completar `ACCESO_SEGURO`, implementar la bitácora de auditoría, versionar migraciones y ampliar pruebas de regresión son los siguientes pasos naturales.


## 12. Fuentes consultadas

**Tabla 10. Fuentes documentales y técnicas**

| Abreviatura | Documento |
| --- | --- |
| [ARQ] | `docs/DOCUMENTACION_NUEVA_ESTRUCTURA_REFACTORIZACION.md` — v1.1, octubre 2026 |
| [EJE] | `docs/Documento_Ejecutivo_TecnoPresta.html` — v1.1, octubre 2026 |
| [ACT] | `docs/documento_registro_nuevo_activo.md` — agosto 2026, actualizado octubre 2026 |
| [README] | `README.md` — documentación general del repositorio |
| [SGEM-R] | `docs/Requerimientos_Funcionales_SGEM.docx` — v1.0, julio 2026 |
| [SGEM-P] | `docs/Plan_Implementacion_SGEM.docx` — v1.0, julio 2026 |
| [ARQ0] | `docs/Documentacion_Nueva_Arquitectura_TecnoPresta.docx` — v1.0, septiembre 2026 |
| [AMB] | `PROPUESTA_AMBITOS_PRESTADORES.md` — propuesta técnica |
| Código | `actualizar_gestor_usuarios_n.php`, `formulario_crear_roles_n.php`, `sql_n/select.php`, `consulta_activos_n.php` — verificación puntual de implementación |

**Criterio de prioridad documental:** ante discrepancia, prevalece la documentación técnica v1.1 sobre la documentación v1.0 y sobre los documentos SGEM. Las referencias al código se utilizaron para verificar funcionalidades concretas, no para sustituir la documentación funcional.


---


## Anexo A. Roles documentados

**Tabla 11. Roles del sistema**

| ID | Rol |
| --- | --- |
| 1 | Root |
| 2 | Administrador |
| 3 | Prestador |
| 4 | Inventariador |
| 5 | Solicitante |
| 7 | Consultor |
| 8 | Mesa de Servicios |
| 9 | Soporte Virtual |
| 10 | Coordinador en Sitio |
| 11 | Soporte en Sitio |


## Anexo B. Acciones del sistema

El catálogo de acciones granulares está compuesto por:

`ver`, `crear`, `editar`, `eliminar`, `exportar`, `importar`, `aprobar`, `asignar`, `cerrar`, `escalar`, `auditar`.


## Anexo C. Modos de consulta de activos

**Tabla 12. Catálogo de los 13 modos de consulta**

| Grupo | Modos |
| --- | --- |
| General | `inventario` |
| Condición | `excelente`, `bueno`, `regular`, `malos`, `robados` |
| Disponibilidad y uso | `prestables`, `no_prestables`, `en_uso`, `no_uso` |
| Ubicación | `sin_ubicacion` |
| Situación administrativa | `donacion`, `baja` |


## Anexo D. Archivos clave de la arquitectura

**Tabla 13. Referencia de archivos principales**

| Archivo o grupo | Descripción |
| --- | --- |
| `navegar.php` | Dispatcher central de formularios. |
| `auth.php` | Autenticación y autorización central sin consulta a base de datos. |
| `sql/formulario_principal.php` | Construye la sesión, el menú y las rutas permitidas. |
| `formulario_menu_principal.php` | Vista de subsistemas. |
| `formulario_modulos.php` | Vista de módulos. |
| `formulario_sub_modulos.php` | Vista de formularios. |
| `gestor_modulos_n.php` | Administración de subsistemas y módulos. |
| `gestor_formularios_n.php` | Administración de formularios y acciones. |
| `gestor_roles_permisos_n.php` | Asignación de permisos a roles. |
| `gestor_usuarios_n.php` | Administración de usuarios y roles. |
| `consulta_activos_n.php` | Reporte genérico de activos con 13 modos. |
| `sql/consulta_activos_helper.php` | Capa de datos y utilidades del reporte. |
| `sql/consulta_activos_modos.php` | Catálogo declarativo de los modos de consulta. |
| `formulario_agregar_nuevo_activo_n.php` | Registro de activos en dos fases. |
| `gestor_catalogo_modelos_n.php` | Catálogo maestro de modelos y combinaciones. |
| `partials/header.php` y `partials/footer.php` | Componentes reutilizables de layout. |


## Anexo E. Glosario técnico

**Tabla 14. Términos técnicos utilizados**

| Término | Definición |
| --- | --- |
| SSO | Single Sign-On. Inicio de sesión único mediante la cuenta institucional. |
| PWA | Progressive Web App. Aplicación web instalable con capacidades de aplicación nativa. |
| Dispatcher | Componente que recibe una ruta, valida seguridad y carga el formulario correspondiente. |
| `ACCESO_SEGURO` | Constante que impide ejecutar un formulario sin pasar por el dispatcher. |
| Soft delete | Borrado lógico mediante la columna `eliminado`; el registro se conserva. |
| O(1) | Expresión utilizada para indicar una validación en memoria, sin consulta SQL por petición. |
| Graph API | API de Microsoft 365 para perfil, calendario, Outlook y reuniones de Teams. |
| SOAP / WS | Servicio web del MEP para validación de funcionarios y consulta de centros. |
| Prestador | Rol encargado de gestionar préstamos de equipos. |
| Ámbito de prestador | Agrupación de lugares o recintos sobre los que un prestador tiene custodia. |

