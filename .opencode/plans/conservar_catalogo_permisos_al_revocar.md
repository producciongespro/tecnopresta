# Plan: conservar el catálogo `permisos` al revocar una asignación de rol

## Objetivo
Al revocar la asignación de un permiso (formulario × acción) de un rol, **no** borrar la fila
del catálogo `permisos`. Así la acción sigue ligada al formulario aunque ningún rol la tenga,
y puede volver a asignarse a cualquier rol. El ciclo de vida del catálogo queda en manos de
Gestión de Formularios.

## Diagnóstico
- `actualizar_gestor_roles_permisos_n.php`, rama de revocación (`:144-165`), borra la asignación
  en `roles_permisos` y, si ningún rol usa el permiso, borra la fila del catálogo `permisos`
  (`:156-162`), con statements en `:104-108`. Ese borrado es un efecto colateral indebido.

## Verificación de factibilidad (BD real `ltecnopre`)
- `permisos`: `UNIQUE (formulario_id, accion_id)`, FK a `acciones` y `formularios` ON DELETE CASCADE.
- `roles_permisos`: `UNIQUE (rol_id, permiso_id)`, FK `permiso_id -> permisos.id` ON DELETE CASCADE.
- Catálogo acotado: 64 formularios × 11 acciones = 704 filas máx. (hoy 313). Sin problema de volumen.
- Sin cambios de esquema ni migración. Se elimina una operación destructiva => riesgo neto baja.
- Consumidores verificados sin impacto/beneficio:
  - Root: `consultaMenuRoot()` usa el catálogo directo (conservar es correcto).
  - No-Root: `consultaMenuUsuarios()` parte de `roles_permisos` (huérfanas no otorgan acceso).
  - `auth.php` `usuarioTieneRuta` usa `rutas_permitidas` desde el menú => sin escalada de privilegios.
  - `sql/gestor_formularios.php` y `sql/gestor_roles.php`: se benefician (acciones permanecen).

## Cambios (archivo único)
**`actualizar_gestor_roles_permisos_n.php`**

1. Docblock `:18-22`: describir que `activo=false` solo borra de `roles_permisos`.
2. Eliminar statements de limpieza `:104-108`:
   - `$sqlCheckPermisoUsado = "SELECT COUNT(*) AS total FROM roles_permisos WHERE permiso_id = ?";`
   - `$stmtCheckUsado = $conexionBD->prepare($sqlCheckPermisoUsado);`
   - `$sqlDeletePermiso = "DELETE FROM permisos WHERE id = ?";`
   - `$stmtDeletePerm = $conexionBD->prepare($sqlDeletePermiso);`
3. Reemplazar la rama de revocación `:144-165` por una versión que solo haga
   `DELETE FROM roles_permisos WHERE rol_id = ? AND permiso_id = ?`.

### Diff conceptual (rama revocar)
```php
} else {
    // REVOCAR permiso
    // Solo se elimina la asignacion del rol. La fila del catalogo 'permisos'
    // se conserva para que la accion siga ligada al formulario; su ciclo de
    // vida lo administra Gestion de Formularios.
    $stmtBuscar->execute([$formulario_id, $accion_id]);
    $row = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $permiso_id = (int)$row['id'];
        $stmtDeleteRP->execute([$rol_id, $permiso_id]);
        $procesados++;
    }
}
```

## No se toca
- Esquema de BD, `gestor_roles.php`, `gestor_formularios_n.php`, `actualizar_gestor_formularios_n.php`.
- La rama de asignación (sigue con `INSERT IGNORE INTO permisos`).

## Verificación posterior
1. `php -l actualizar_gestor_roles_permisos_n.php`.
2. Revocar la última acción de un formulario de un rol -> guardar -> recargar:
   - El badge sigue disponible y se puede reasignar.
   - `SELECT ... FROM permisos WHERE formulario_id=? AND accion_id=?` conserva la fila.
3. Confirmar que un usuario no-Root no gana acceso de ruta.

## Remediación opcional (fuera de este cambio)
- 2 formularios con 0 acciones en catálogo (`id 17`, `id 19`) y 50 permisos huérfanos:
  no reconstruibles automáticamente; reactivar en Gestión de Formularios o restaurar desde backup.
