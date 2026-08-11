<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
ob_start();

require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    error_log("Error de conexion a mysql: " . mysqli_connect_error());
}
if (!mysqli_set_charset($link, "utf8")) {
    error_log("Error cargando el conjunto de caracteres utf8");
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Usuario no autenticado'];
    header("Location: index.html");
    exit();
}

// === Parámetros de retorno ===
$subsistema_id = isset($_POST['subsistema_id']) ? intval($_POST['subsistema_id']) : 0;
$modulo_id = isset($_POST['modulo_id']) ? intval($_POST['modulo_id']) : 0;
$params_retorno = '';
if ($subsistema_id > 0 && $modulo_id > 0) {
    $params_retorno = '&subsistema_id=' . $subsistema_id . '&modulo_id=' . $modulo_id;
}
$ruta_admin = 'navegar.php?ruta=formulario_administracion_permisos_n.php' . $params_retorno;
$ruta_crear = 'navegar.php?ruta=formulario_crear_usuario_sistema_n.php' . $params_retorno;

// === Roles gestionables ===
$roles_gestionables = [2, 3, 4, 5];

// === Subsistema por defecto para la asignación de roles ===
$subsistema_rol = 1;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Acceso no permitido'];
    header("Location: $ruta_admin");
    exit();
}

$rol = isset($_POST['rol']) ? intval($_POST['rol']) : 0;
if (!in_array($rol, $roles_gestionables)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Rol no válido para la gestión de usuarios'];
    header("Location: $ruta_admin");
    exit();
}

$edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

// === MODO EDICIÓN (solo cambia el rol en usuarios_roles) ===
if ($edit_id > 0) {
    $check = mysqli_query($link, "SELECT ur.id, ur.usuario_id, ur.rol_id, ur.subsistema_id, ur.codigo_presu
                                  FROM usuarios_roles ur
                                  WHERE ur.id = $edit_id AND ur.eliminado = 0 LIMIT 1");
    if (!$check || mysqli_num_rows($check) == 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Registro no encontrado'];
        header("Location: $ruta_admin");
        exit();
    }
    $fila = mysqli_fetch_assoc($check);
    if (!in_array(intval($fila['rol_id']), $roles_gestionables)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'No puede editar el rol de este usuario'];
        header("Location: $ruta_admin");
        exit();
    }

    $uid = intval($fila['usuario_id']);
    $sid = intval($fila['subsistema_id']);
    $codigo_presu = mysqli_real_escape_string($link, $fila['codigo_presu']);

    $check_unique = mysqli_query($link, "SELECT id FROM usuarios_roles
                                         WHERE usuario_id = $uid AND rol_id = $rol
                                         AND subsistema_id = $sid AND codigo_presu = '$codigo_presu'
                                         AND id <> $edit_id LIMIT 1");
    if ($check_unique && mysqli_num_rows($check_unique) > 0) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'El usuario ya tiene asignado ese rol para el código presupuestario'];
        header("Location: $ruta_admin");
        exit();
    }

    $query_update = "UPDATE usuarios_roles SET rol_id = $rol, updated_at = NOW() WHERE id = $edit_id";
    if (mysqli_query($link, $query_update)) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Rol del usuario actualizado correctamente'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al actualizar el rol: ' . mysqli_error($link)];
    }
    header("Location: $ruta_admin");
    exit();
}

// === MODO CREACIÓN ===
$email = isset($_POST['email']) ? mysqli_real_escape_string($link, trim($_POST['email'])) : '';
$cedula = isset($_POST['cedula']) ? mysqli_real_escape_string($link, trim($_POST['cedula'])) : '';
$codigo_presupuestario = isset($_POST['codigo_presupuestario']) ? mysqli_real_escape_string($link, trim($_POST['codigo_presupuestario'])) : '';

if (empty($email) || empty($cedula) || empty($codigo_presupuestario)) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Todos los campos son obligatorios'];
    header("Location: $ruta_crear");
    exit();
}

// Validar que el correo sea @mep.go.cr
if (!preg_match('/^[^@\s]+@mep\.go\.cr$/i', $email)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'El correo debe ser de la cuenta @mep.go.cr'];
    header("Location: $ruta_crear");
    exit();
}

// Validar que el código presupuestario sea numérico de 3 o 4 dígitos
if (!preg_match('/^\d{3,4}$/', $codigo_presupuestario)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'El código presupuestario debe contener únicamente números de 3 o 4 dígitos'];
    header("Location: $ruta_crear");
    exit();
}

// === Buscar el usuario en la tabla usuarios por cédula ===
$usuario_id = 0;
$query_usuario = mysqli_query($link, "SELECT id, cedula, correo FROM usuarios WHERE cedula = '$cedula' LIMIT 1");
$usuario_existente = ($query_usuario && mysqli_num_rows($query_usuario) > 0) ? mysqli_fetch_assoc($query_usuario) : null;

if ($usuario_existente) {
    $usuario_id = intval($usuario_existente['id']);
    if (strcasecmp(trim($usuario_existente['correo']), $email) !== 0) {
        $query_correo = mysqli_query($link, "SELECT id FROM usuarios WHERE correo = '$email' AND id <> $usuario_id LIMIT 1");
        if ($query_correo && mysqli_num_rows($query_correo) > 0) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'El correo ingresado ya está registrado con otra cédula'];
            header("Location: $ruta_crear");
            exit();
        }
        mysqli_query($link, "UPDATE usuarios SET correo = '$email', updated_at = NOW() WHERE id = $usuario_id");
    }
} else {
    // No existe por cédula: evitar duplicar correo de otra cédula
    $query_correo = mysqli_query($link, "SELECT id FROM usuarios WHERE correo = '$email' LIMIT 1");
    if ($query_correo && mysqli_num_rows($query_correo) > 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'El correo ingresado ya está registrado con otra cédula'];
        header("Location: $ruta_crear");
        exit();
    }

    // === OBTENER NOMBRE REAL DESDE EL WS DEL MEP (Plan A) ===
    $nombre_real = obtenerNombreFuncionarioWS($cedula);

    // === FALLBACK: guardar el correo como nombre (Plan B, se corrige en el primer ingreso) ===
    $nombre = ($nombre_real !== '') ? $nombre_real : $email;

    $insert = mysqli_query($link, "INSERT INTO usuarios (cedula, nombre, correo, created_at, updated_at)
                                   VALUES ('$cedula', '$nombre', '$email', NOW(), NOW())");
    if (!$insert) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al crear el usuario: ' . mysqli_error($link)];
        header("Location: $ruta_crear");
        exit();
    }
    $usuario_id = intval(mysqli_insert_id($link));
}

// === created_by del administrador logueado ===
$created_by = $_SESSION['funcionario']['auth']['usuario_id'] ?? null;
$created_by_sql = ($created_by) ? intval($created_by) : 'NULL';

// === Buscar la asignación de rol existente (incluye eliminados lógicos) ===
$check_rol = mysqli_query($link, "SELECT id, eliminado FROM usuarios_roles
                                  WHERE usuario_id = $usuario_id AND subsistema_id = $subsistema_rol
                                  AND codigo_presu = '$codigo_presupuestario' LIMIT 1");

if ($check_rol && mysqli_num_rows($check_rol) > 0) {
    $rol_existente = mysqli_fetch_assoc($check_rol);
    if (intval($rol_existente['eliminado']) === 0) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'El usuario ya tiene un rol asignado para este código presupuestario'];
        header("Location: $ruta_crear");
        exit();
    }

    // === Reactivar el rol eliminado lógicamente ===
    $id_rol_existente = intval($rol_existente['id']);
    $reactivar = mysqli_query($link, "UPDATE usuarios_roles
                                      SET rol_id = $rol, eliminado = 0, created_by = $created_by_sql, updated_at = NOW()
                                      WHERE id = $id_rol_existente");
    if ($reactivar) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Usuario guardado correctamente'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al guardar el registro: ' . mysqli_error($link)];
    }
    mysqli_close($link);
    header("Location: $ruta_admin");
    exit();
}

$query = "INSERT INTO usuarios_roles (usuario_id, rol_id, subsistema_id, codigo_presu, created_by, created_at)
          VALUES ($usuario_id, $rol, $subsistema_rol, '$codigo_presupuestario', $created_by_sql, NOW())";

if (mysqli_query($link, $query)) {
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Usuario guardado correctamente'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al guardar el registro: ' . mysqli_error($link)];
}

mysqli_close($link);
header("Location: $ruta_admin");
exit();

// * OK *** ==== OBTENER NOMBRE COMPLETO DESDE EL WS DEL MEP ====
function obtenerNombreFuncionarioWS(string $cedula): string {
    if (!class_exists('SoapClient')) {
        return '';
    }
    $cedula_limpia = str_replace('"', '', $cedula);
    $url = "https://apps.mep.go.cr/wstecnopresta/servicio.asmx?WSDL";

    try {
        $client = new SoapClient($url, ['connection_timeout' => 15]);
        $res = $client->ConsultaFuncionario(array('str_identificacion' => $cedula_limpia));
        $jsonRaw = $res->ConsultaFuncionarioResult ?? '';
        $datos = json_decode($jsonRaw, true);
        if (!is_array($datos)) {
            return '';
        }
        if (isset($datos[0]) && is_array($datos[0])) {
            $f = $datos[0];
        } else {
            $f = $datos;
        }
        $nombre = trim((string)($f['Nombre'] ?? ''));
        $apellido1 = trim((string)($f['Apellido1'] ?? ''));
        $apellido2 = trim((string)($f['Apellido2'] ?? ''));
        return trim($nombre . ' ' . $apellido1 . ' ' . $apellido2);
    } catch (\Throwable $e) {
        return '';
    }
}
