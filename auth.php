<?php
/**
 * ============================================================
 * AUTH.PHP --- Archivo Central de Autenticación y Autorización
 * ============================================================
 * ARCHIVO CENTRAL DE AUTENTICACIÓN Y AUTORIZACIÓN
 * 
 * FUNCIONES:
 *  - Validar sesión
 *  - Obtener usuario autenticado
 *  - Validar usuario Root
 *  - Validar permisos por ruta
 *  - Centralizar seguridad
 * 
 * IMPORTANTE:
 * Este archivo NO consulta Base de Datos.
 * Todo se obtiene desde $_SESSION['funcionario'], que es cargado y validado previamente en formulario_principal.php
 * previamente cargado desde formulario_principal.php
 *
 * BENEFICIOS:
 * ------------
 * ✔ Evita duplicación de código.
 * ✔ Facilita mantenimiento.
 * ✔ Facilita escalabilidad.
 * ✔ Permite arquitectura empresarial.
 * ✔ Mantiene responsabilidades separadas.
 */

// ============================================================
// INICIAR SESIÓN si no existe
// ============================================================

// Verifica si la sesión ya fue iniciada.
// Esto evita errores de sesiones duplicadas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// IMPORTAR CONEXIÓN A BASE DE DATOS
//require_once __DIR__ . '/sql/bd.php';

// VALIDAR SI EXISTE SESIÓN ACTIVA
function usuarioAutenticado(): bool 
{
    return (
        // Identidad básica de sesión
        isset($_SESSION['funcionario']) && 
        is_array($_SESSION['funcionario']) && // Verificar que el usuario tenga datos básicos
        
        //Autorización secundaria (Roles y Permisos)
        isset($_SESSION['funcionario']['auth']) && // Verificar que tenga datos de autenticación
        is_array($_SESSION['funcionario']['auth'])
    ); 
}

// === OBTENER DATOS DE AUTENTICACIÓN --Devuelve los datos de autenticación. ==
function obtenerUsuarioAuth(): ?array 
{
    // Validar sesión
    if (!usuarioAutenticado()) {
        return null;
    }

    return $_SESSION['funcionario']['auth'] ?? null;
}

// === OBTENER DATOS DE AUTENTICACIÓN --Devuelve los datos de autenticación. ==
function obtenerUsuario(): ?array 
{
    // Validar sesión
    if (!usuarioAutenticado()) {
        return null;
    }

    return $_SESSION['funcionario'] ?? null;
}

// === OBTENER ID DEL USUARIO --- Devuelve el ID del usuario autenticado. ====
function obtenerUsuarioId(): ?int
{
    $usuario = obtenerUsuarioAuth();
    return $usuario['usuario_id'] ?? null; //Es mejor retornar null, que 0, para evitar confusiones con IDs válidos.
}

// ====  OBTENER CÉDULA DEL USUARIO ====
function obtenerCedulaUsuario(): ?string
{
    $usuario = obtenerUsuario();

    return $usuario['cedula'] ?? null;
}

// === OBTENER CÓDIGO PRESUPUESTARIO ==== 
function obtenerCodigoPresupuestario(): ?string
{
    $usuario = obtenerUsuario();

    return $usuario['codigo_presu'] ?? null;
}

// ============================================================
// VALIDAR SI EL USUARIO ES ROOT
// ============================================================
function esUsuarioRoot(): bool
{
    $usuario = obtenerUsuarioAuth();

    return (bool)($usuario['es_root'] ?? false);
}

//====  OBTENER ROLES DEL USUARIO -- Devuelve los roles del usuario autenticado. ===
function obtenerRolesUsuarioAuth(): array
{
    $usuario = obtenerUsuarioAuth();

    return $usuario['roles'] ?? [];
}

// === VALIDAR ACCESO A FORMULARIO ====
function usuarioTieneRuta(string $ruta): bool
{
     // FORMULARIOS PÚBLICOS INTERNOS DEL SISTEMA
    // NO requieren permiso explícito
    $rutasPublicasInternas = [
        'formulario_menu_principal.php',
        'formulario_modulos.php',
        'formulario_sub_modulos.php',
        'navegar.php',
        'acerca_de_n.php',
        'ayuda_n.php'
    ];

    // Si la ruta pertenece a formularios públicos internos, se permite el acceso sin validar permisos específicos
    if (in_array($ruta, $rutasPublicasInternas, true)) {
        return true;
    }

    // ROOT tiene acceso total
    if (esUsuarioRoot()) {
        return true;
    }

    // OBTENER USUARIO Y PERMISOS
    $usuario = obtenerUsuarioAuth();

    // Obtener rutas permitidas
    $rutasPermitidas = $usuario['rutas_permitidas'] ?? [];

    // Validar permiso específico para la ruta solicitada
    return in_array($ruta, $rutasPermitidas, true);
}

// === BLOQUEAR ACCESO SI NO ESTÁ AUTENTICADO ===
function validarSesion(): void
{
    if (!usuarioAutenticado()) {

        http_response_code(401);

        //die("Sesión inválida");
        //throw new Exception("Sesión inválida");
        //header('Location: login.php');
        header('Location: index.html');
        exit;
    }
}

// ==== BLOQUEAR ACCESO SI NO TIENE PERMISOS =====

function validarPermisoRuta(string $ruta): void
{
    
    if (!usuarioTieneRuta($ruta)) {

        http_response_code(403);

        throw new Exception("Acceso denegado");
    }
}
