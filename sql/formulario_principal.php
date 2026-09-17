<?php
/**
 * ============================================================
 * FORMULARIO PRINCIPAL
 * ============================================================
 * 
 * Este archivo:
 * ✔ Valida sesión Azure
 * ✔ Crea usuarios automáticamente si no están en la tabla Usuarios
 * ✔ Valida roles por código presupuestario
 * ✔ Asigna rol inicial desde T_Lista_Blanca Solo si el usuario NO tiene registros en "usuarios_roles"
 * ✔ Construción del menú Dinámico
 * ✔ Retorna JSON del menú y permisos
 * ✔ Actualiza el último acceo del usuario.
 * 
 * BENEFICIOS:
 * ------------
 * ✔ Evita duplicación de código.
 * ✔ Facilita mantenimiento.
 * ✔ Facilita escalabilidad.
 * ✔ Permite arquitectura empresarial.
 * ✔ Mantiene responsabilidades separadas.
 * 
 *  * ===================================================================
 *  * LÓGICA DE NEGOCIO:
 * =========================================================================
 * 1. El usuario inicia sesión Azure y elige un código presupuestario.
 * 2. SOLO se cargan roles del código presupuestario actual.
 * 3. Si NO tiene roles:
 *      - se consulta Lista Blanca
 *      - se inserta rol inicial
 * 4. Si YA tiene roles:
 *      - NO se modifican
 *      - NO se sincronizan
 * 5. ROOT (rol_id = 1):
 *      - acceso total
 * 6. Usuarios normales:
 *      - acceso según permisos reales
 */
declare(strict_types=1); //Habilita el modo estricto para una mejor gestión de tipos de datos y errores en tiempo de ejecución

require_once 'bd.php';
//require_once 'conexion.php';
//require_once '../usuarioAzure.php';
require_once __DIR__ . '/../usuarioAzure.php';

//session_start(); // Asegúrate de iniciar la sesión para acceder a las variables de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//Devuelve la información en formato Json
header('Content-Type: application/json'); 
header('Cache-Control: no-store'); //Evita que proxys/CDNs sirvan el menú (rutas de imágenes) desde caché 

try {
    
    // *** 1. SE VALIDA LA SESION AZURE *****
    $usuario_azure = obtenerUsuarioSesion(); //Obtiene los datos del Azure captados en el archivo usuarioAzure.php
    // Guarda debug de sesión
    $debug = [
        //"session" => $_SESSION,
        "DatosFuncionarios" => $usuario_azure

    ];
    //Se valida existe la sesion
    if (!$usuario_azure) {
        echo json_encode([
            "error" => "Sesión Inválida",
            "debug" => $debug
        ]);
        exit();
    }

    // === 2. OBTENER DATOS DE USUARIO ====
    //Obtiene la Cédula desde el azure
    //$funcionario_cedula = trim($usuario_azure['cedula'] ?? '');
    $funcionario_cedula = trim((string)($usuario_azure['cedula'] ?? ''));

    //Obtiene le nombre completo del usuario
    $funcionario_nombre = trim((string)($usuario_azure['nombre'] ?? ''));

    // El correo del funcionario obtenido del Azure, o un valor por defecto si no está disponible
    $funcionario_correo = trim((string)($usuario_azure['correo'] ?? ''));

    //Obteien el código presupestario desde el azure.
    $funcionario_codigo_presu = trim((string)($usuario_azure['codigoPresu'] ?? '')); 

    // El ID de Azure del funcionario obtenido del Azure, o un valor por defecto si no está disponible
    //$funcionario_azure_id = trim($usuario_azure['id'] ?? '');
    $funcionario_azure_id = trim((string)($usuario_azure['id'] ?? '')); 

    // El sexo del funcionario obtenido del Azure, o un valor por defecto si no está disponible
    $sexoAzure = strtolower(trim((string)($usuario_azure['sexo'] ?? ''))); 

    // ==== 3. VALIDAR DATOS OBLIGATORIOS ===

    if (empty($funcionario_cedula)) {
        throw new Exception("La cédula del usuario es requerida.");
    }
    if (empty($funcionario_codigo_presu)) {
        throw new Exception("El código presupuestario es requerido.");
    }

    // ==== 4. Mapear el SEXO del Azure al formato esperado en la base de datos ====
    $sexo = null;

    if ($sexoAzure === 'm' || $sexoAzure === 'masculino' || $sexoAzure === 'hombre') {
        $sexo = 1;
    }
    if ($sexoAzure === 'f' || $sexoAzure === 'femenino' || $sexoAzure === 'mujer') {
        $sexo = 2;
    }
    
    // === Roles válidos para insertar ====
     $rolesValidos = [2, 3, 4, 7]; //2=administrador | 3 = Prestador | 4 = Inventariador | 7 = Consultor
    
    // ***  5. CONEXIÓN A LA BD ****
    $conexionBD=BD::crearInstancia();    //Se crea la instancia de la conexión

    // === 6. INICIAR TRANSACCIÓN === Para integridad de la BD
    if (!$conexionBD->inTransaction()) { //Verifica si hay una transacción activa
        $conexionBD->beginTransaction();
    }
    

    //* *** 7. BUSCA EL USUARIO ****  // Tabla Usuarios
    $usuario = buscaUsuario($conexionBD, $funcionario_cedula); //Busca el usuario en la base de datos para obtener su id y verificar si es superadmin o no

    $rolActualId = 0; //Variable para almacenar el id del rol actual.
    // ===  8. SI EL USUARIO NO EXISTE ( se debe de crear en la BD) ===
    if (!$usuario) {
    
        // ==== A.2.1.1 ... INSERTAR EL USUARIO -> Se crea =====
        $sql = "INSERT INTO usuarios (
            cedula, 
            nombre, 
            correo, 
            azure_id, 
            sexo, 
            created_at, 
            updated_at
            ) 
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())"; //Prepara la consulta SQL para insertar el id del funcionario en la tabla de usuarios
        
        $consulta = $conexionBD->prepare($sql); //Prepara la consulta SQL para insertar el id del funcionario en la tabla de usuarios
        
        $consulta->execute([
            $funcionario_cedula, 
            $funcionario_nombre, 
            $funcionario_correo,
            $funcionario_azure_id,
            $sexo
        ]); //Ejecuta la consulta para insertar el id del funcionario en la tabla de usuarios
      
        // === OBTENER EL USUARIO RECIEN INSERTADO ====
        $usuario_id =(int)$conexionBD->lastInsertId(); //Obtiene el id del usuario recién insertado en la base de datos para su uso posterior en la consulta de permisos

    } else { // === USUARIO YA EXISTE ** TABLA USUARIOS =====
        $usuario_id = (int)$usuario['id'];
    }

    // === 8.1 CORREGIR NOMBRE SI SE GUARDÓ UN CORREO ====
    if ($usuario && str_contains($usuario['nombre'] ?? '', '@')) {
        $nombre_real = trim((string)($usuario_azure['nombre'] ?? ''));
        if ($nombre_real !== '') {
            $sqlCorregir = "UPDATE usuarios SET nombre = ? WHERE id = ?";
            $stmtCorregir = $conexionBD->prepare($sqlCorregir);
            $stmtCorregir->execute([$nombre_real, $usuario_id]);
        }
    }

    // === 9. ACTUALIZA EL ÚLTIMO INICIO DE SESIÓN DEL USUARIO ====
    $sql = "UPDATE usuarios 
            SET ultimo_acceso = NOW() 
            WHERE id = ?"; //Prepara la consulta SQL para actualizar el último inicio de sesión del usuario
    $consulta = $conexionBD->prepare($sql); //Prepara la consulta SQL para actualizar el último inicio de sesión del usuario
    $consulta->execute([$usuario_id]); //Ejecuta la consulta para actualizar el último inicio de sesión del usuario
  
    //===10. OBTENER ROLES DEL USUARIO, PARA CODIGO ACTUAL --- PARA LUEGO CONSTRUIR EL MENÚ CORRECTAMENTE ===
    $rolesUsuario = obtenerRolesUsuario($conexionBD, $usuario_id, $funcionario_codigo_presu);
    
    //$rolActual = obtenerRolUsuario($conexionBD, $usuario_id, $funcionario_codigo_presu);
    // === 11. Si USUARIO NO TIENE ROLES ====
    //if (!$rolesUsuario) { 
    if (empty($rolesUsuario)) {
        // ==== A.2.1.2 CONSULTAR USUARIO EN T_LISTA_BLANCA: Si obtiene el rol, sino está, es USUARIO BÁSICO ====
        $rolListaBlanca = verificarListaBlanca($conexionBD, $funcionario_cedula, $funcionario_codigo_presu); //Verifica si el usuario tiene un rol asignado en la lista blanca según su cédula y código presupuestario,         
         // ==== SI USUARIO EXISTE EN LISTA BLANCA ======
        if (!empty($rolListaBlanca)) { 
            $rol_id = (int)$rolListaBlanca['id_rol']; 
            // === VALIDAR ROL Permitido ====
            if (in_array($rol_id, $rolesValidos, true)) { // Si el rol de la LISTA BLANCA es válido (está en el arreglo)
                insertarRolUsuario($conexionBD, $usuario_id, $rol_id, 1, $funcionario_codigo_presu); //Ejecuta la consulta para asignar el rol, y el subsistema por defecto 1,                 
            };
        } else { //SI NO EXISTE EN LISTA BLANCA -> Asigna rol SOLICITANTE
            insertarRolUsuario($conexionBD, $usuario_id, 5, 1, $funcionario_codigo_presu); //Ejecuta la consulta para asignar el rol por defecto al usuario    
        }

        // Recargar roles
        $rolesUsuario = obtenerRolesUsuario($conexionBD, $usuario_id, $funcionario_codigo_presu);
    }
    
    // ==== 12.VALIDAR SI ES ROOT ==== Verifica si tiene un rol Root
    $esRoot =  false;
    foreach ($rolesUsuario as $rol) {
        if ((int)$rol['rol_id'] === 1) {
            $esRoot = true;
            break;
        }
    }

    // === 13. CONSULTAR MENÚ
    if ( $esRoot) { //Usuario es Root -- Acceso Total
        $permisos = consultaMenuRoot($conexionBD);
    } else { //Usuario diferente a Root
        $permisos = consultaMenuUsuarios($conexionBD, $usuario_id, $funcionario_codigo_presu);
    }
    
    //=== 14. CONSTRUIR EL MENU ====
    $menu = crearMenu($permisos);
    
    //15.  INSERTAR LA SESIÓN EMPRESARIAL ==== 
    // lA IDEAS ES VALIDAR EL ACCESO RÁPIDO DESDE NAVEGAR.PHP SIN CONUSLTAR A LA BD
    $rutasPermitidas = [];
    //Recorre todos los permisos
    foreach ($permisos as $permiso) {
        //Obtener ruta del formulario
        $rutaFormulario = trim($permiso['ruta'] ?? '');
        //Validar que exista
        if (!empty($rutaFormulario)) {
            $rutaFormulario = basename($rutaFormulario); //Agrega solo el nombre del archivo a la lista de rutas permitidas
            $extension = strtolower(pathinfo($rutaFormulario, PATHINFO_EXTENSION)); //Obtiene la extensión del archivo para validar que sea una extensión permitida antes de agregarla a la lista de rutas permitidas
            if (in_array($extension, ['php', 'html'])) { //Valida que la extensión del archivo sea permitida antes de agregarla a la lista de rutas permitidas
                $rutasPermitidas[] = $rutaFormulario;
            }
        }
    }   

    //=== Eliminar rutas dupblicadas ====
    $rutasPermitidas = array_values(array_unique($rutasPermitidas));

    // Evita Session Fixation
    //session_regenerate_id(true);
    // ==== CREAR SESIÓN CENTRAL DEL SISTEMA =====
    $_SESSION['funcionario']['auth'] = [
        //DATOS PRINCIPALES DEL USUARIO
        'usuario_id' => $usuario_id,

        //VALIDACIÓN ROOT
        'es_root' => $esRoot,

        //ROLES DEL USUARIO
        'roles' => $rolesUsuario,

        //MENÚ COMPLETO
        //'menu' => $menu,

        //RUTAS PERMITIDAS -- Utilizadas por navegar.php
        'rutas_permitidas' => $rutasPermitidas,
        
        //FECHA DE CREACIÓN DE SESIÓN
        'session_created_at' => date('Y-m-d H:i:s')
    ];    

    // ==== 15. COMMIT DE LA TRANSACCIÓN =====
    $conexionBD->commit();
/*
    echo "<pre>";
    print_r($_SESSION['usuario_auth']);
    echo "</pre>";
    exit; */
    
    // *** 16. RESPUESTA FINAL ****
    echo json_encode([
        "Ok" => true,
        //"usuario" => [$usuario['nombre'],
        "usuario" => [
            "id" => $usuario_id,
            "nombre" => $funcionario_nombre,
            "cedula" => $funcionario_cedula,
            "correo" => $funcionario_correo,
            "codigo_presupuestario" => $funcionario_codigo_presu
        ],
        //"dataS" => array_values($menu),
        "Root" => $esRoot,
        "roles" => $rolesUsuario,
        "data" => $menu,
        "funcionario" => $usuario_azure, //Agrega los datos del funcionario obtenidos del Azure a la respuesta JSON para su uso en el frontend
        
        //"datos" => $_SESSION['funcionario'] ?? null, //Agrega los datos de la sesión completa del funcionario obtenidos del Azure a la respuesta JSON para su uso en el frontend
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    // === ROLLBACK si ocurre un error ===
    if (isset($conexionBD) && $conexionBD->inTransaction()) {
        $conexionBD->rollBack();
    }

    // === RESPUESTA DE ERROR ===
    echo json_encode([
        "OK" => false,
        "error" => $e->getMessage()
    ]);
}

 /**===============================
  * ======= FUNCIONES ===========
  ===============================*/ 

  //****  BUSCA EL USUARIO **** Busca en la tabla Usuarios *****
function buscaUsuario(PDO $conexionBD, string $cedula): array|false {
    $sql = "SELECT id, cedula, nombre, correo 
            FROM usuarios 
            WHERE cedula = ? 
            LIMIT 1"; //Prepara la consulta SQL para seleccionar el usuario con el id del funcionario
    
    $consulta = $conexionBD->prepare($sql); //Prepara la consulta SQL para seleccionar el usuario con el id del funcionario
    
    $consulta->execute([$cedula]); //Ejecuta la consulta con el id del funcionario
    
    return $consulta->fetch(PDO::FETCH_ASSOC); //Devuelve el usuario encontrado en la base de datos, o false si no se encuentra el usuario
}

// ** OK *** ==== FUNCIÓN: OBTENER ROL ACTUAL: para verificar si el usuario tiene un rol específico asignado en la tabla usuarios_roles según el código presupuestario
function obtenerRolesUsuario(PDO $conexionBD, int $usuarioId, string $codigoPresu): array { //: array indica que la función puede devolver un arreglo (si se encuentra el rol) 
    // Prepara la consulta SQL para verificar si el usuario tiene el rol específico asignado en la tabla usuarios_roles según el código presupuestario
    //AND rol_id = ? Un usuairo solo tiene un rol por codigo prresupuestario,
    $sql = "SELECT DISTINCT u.id, u.rol_id, u.subsistema_id, u.codigo_presu, r.rol
            FROM usuarios_roles u
            INNER JOIN t_roles r ON r.id_rol = u.rol_id
            WHERE usuario_id = ?
            AND codigo_presu = ?
            AND u.eliminado = 0";
            //AND subsistema_id = ?

    $consulta = $conexionBD->prepare($sql);

    $consulta->execute([
        $usuarioId,
        $codigoPresu
        // $subsistemaId
    ]);
    
    $resultado = $consulta->fetchAll(PDO::FETCH_ASSOC); // Devuelve el registro si existe, o false si no existe, lo que indica si el usuario tiene el rol asignado o no

    //return $resultado ?: false; // Si $resultado es un arreglo vacío, devuelve false para indicar que no se encontraron roles para el usuario con el código presupuestario dado
    return $resultado ?: []; // Si $resultado es un arreglo vacío, devuelve un arreglo vacío para indicar que no se encontraron roles para el usuario con el código presupuestario dado
}
 
// *OK *** ==== VERIFITAR T_LISTA_BLANCA: Si obtiene el rol, sino está, es USUARIO BÁSICO ====
function verificarListaBlanca(PDO $conexionBD, string $cedula, string $codigoPresu): array|false {
// Prepara la consulta SQL para verificar si el usuario tiene un rol específico asignado en la tabla usuarios_roles según el código presupuestario
    $sql = "SELECT id_rol 
            FROM t_lista_blanca
            WHERE cedula = ?
            AND codigo = ?
            LIMIT 1";

    $consulta = $conexionBD->prepare($sql);

    $consulta->execute([
        $cedula,
        $codigoPresu
    ]);
    
    return $consulta->fetch(PDO::FETCH_ASSOC); // Devuelve el registro si existe, o false si no existe, lo que indica si el usuario tiene el rol asignado o no
}

// * OK **** === FUNCIÓN: INSERTAR ROL ===
function insertarRolUsuario(PDO $conexionBD, int $usuarioId, int $rolId, int  $subsistemaId, string $codigoPresu): void {
   $sql = "INSERT INTO usuarios_roles
            (
                usuario_id,
                rol_id,
                subsistema_id,
                codigo_presu,
                created_at
            )
            VALUES (?, ?, ?, ?, NOW())";

    $consulta = $conexionBD->prepare($sql);

    $consulta->execute([
        $usuarioId,
        $rolId,
        $subsistemaId,
        $codigoPresu
    ]);
}

// * OK *** ==== FUNCIÓN: ACTUALIZAR ROL ===
function actualizarRolUsuario(PDO $conexionBD, int $usuarioId, int $rolId, string $codigoPresu, int $subsistemaId): void {
    
    $sql = "UPDATE usuarios_roles
            SET rol_id = ?
            WHERE usuario_id = ?
            AND codigo_presu = ?
            AND subsistema_id = ?";

    $consulta = $conexionBD->prepare($sql);

    $consulta->execute([$rolId, $usuarioId, $codigoPresu, $subsistemaId]);
}


// * OK *** ===== FUNCION: CONSULTAR MENU ROOT =====
//function consultaMenuRoot(PDO $conexionBD, int $usuario_id): array {
function consultaMenuRoot(PDO $conexionBD): array {

        $sql = 
        "SELECT DISTINCT

            s.id AS subsistema_id,
            s.nombre AS subsistema,
            s.descripcion AS subsistema_descripcion,
            s.imagen AS subsistema_imagen,

            m.id AS modulo_id,
            m.nombre AS modulo,
            m.descripcion AS modulo_descripcion,
            m.ruta_base,
            m.imagen AS modulo_imagen,

            f.id AS formulario_id,
            f.nombre AS formulario,
            f.descripcion AS formulario_descripcion,
            f.ruta,
            f.imagen AS formulario_imagen,
            f.orden,

            a.id AS accion_id,
            a.nombre AS accion

        FROM permisos p

        INNER JOIN formularios f
            ON f.id = p.formulario_id

        INNER JOIN modulos m
            ON m.id = f.modulo_id

        INNER JOIN subsistemas s
            ON s.id = m.subsistema_id

        INNER JOIN acciones a
            ON a.id = p.accion_id

        WHERE
            s.eliminado = 0
            AND m.eliminado = 0
            AND f.eliminado = 0

        ORDER BY
            s.nombre,
            m.nombre,
            f.orden,
            a.nombre";

    $consulta = $conexionBD->prepare($sql); //Prepara la consulta SQL para obtener los roles y permisos del usuario
   
    $consulta->execute();
    return $consulta->fetchAll(PDO::FETCH_ASSOC); //Devuelve todos los permisos del usuario
    
}
// * OK *** === FUNCIÓN: CONSULTAR MENU USUARIOS =====
function consultaMenuUsuarios(PDO $conexionBD, int $usuario_id, string $codigo_presu): array {
  
    $sql = 
        "SELECT

            s.id AS subsistema_id,
            s.nombre AS subsistema,
            s.descripcion AS subsistema_descripcion,
            s.imagen AS subsistema_imagen,

            m.id AS modulo_id,
            m.nombre AS modulo,
            m.descripcion AS modulo_descripcion,
            m.ruta_base,
            m.imagen AS modulo_imagen,

            f.id AS formulario_id,
            f.nombre AS formulario,
            f.descripcion AS formulario_descripcion,
            f.ruta,
            f.imagen AS formulario_imagen,
            f.orden,

            a.id AS accion_id,
            a.nombre AS accion

        FROM usuarios_roles ur

        INNER JOIN roles_permisos rp
            ON rp.rol_id = ur.rol_id

        INNER JOIN permisos p
            ON p.id = rp.permiso_id

        INNER JOIN formularios f
            ON f.id = p.formulario_id

        INNER JOIN modulos m
            ON m.id = f.modulo_id

        INNER JOIN subsistemas s
            ON s.id = m.subsistema_id

        INNER JOIN acciones a
            ON a.id = p.accion_id

        WHERE
            ur.usuario_id = ?
            AND ur.codigo_presu = ?
            AND ur.eliminado = 0

            AND s.eliminado = 0
            AND m.eliminado = 0
            AND f.eliminado = 0

        ORDER BY
            s.nombre,
            m.nombre,
            f.orden,
            a.nombre";
    
    $consulta = $conexionBD->prepare($sql); //Prepara la consulta SQL para obtener los roles y permisos del usuario
    $consulta->execute([$usuario_id, $codigo_presu]); //Ejecuta la consulta para obtener los roles y permisos del usuario
    return $consulta->fetchAll(PDO::FETCH_ASSOC); //Devuelve todos los permisos del usuario
}

// * ok *** === FUNCIÓN: CREAR MENU ====
function crearMenu(array $permisos): array {
    //* *** 5. CONSTUIR EL MENÚ DINÁMICO **** JSON ***
    //Extraigo unicamente los subsistemas, lo cuales son los cards del menú principal
    $menu = [];

    foreach ($permisos as $permiso) {

        $subsistemaId = $permiso['subsistema_id'];
        $moduloId = $permiso['modulo_id'];
        $formularioId = $permiso['formulario_id'];

        // ** SUBSISTEMA ***
        if (!isset($menu[$subsistemaId])) {
            
            $menu[$subsistemaId] = [
                "id" => $subsistemaId,
                "nombre" => $permiso['subsistema'],
                "descripcion" => $permiso['subsistema_descripcion'] ?? '', //Aquí se puede agregar la descripción del subsistema si está disponible en la consulta SQL
                "imagen" => $permiso['subsistema_imagen'], //Aquí se puede agregar la ruta de la imagen correspondiente al subsistema
                "modulos" => []
            ];
        }

        // ** MODULOS ****
        //Si el módulo del permiso no está en la lista de módulos del subsistema, se agrega a la lista de módulos del subsistema.
        if (!isset($menu[$subsistemaId]["modulos"][$moduloId])) { 

            $menu[$subsistemaId]["modulos"][$moduloId] = [ //Agrega el módulo a la lista de módulos del subsistema
                "id" => $moduloId,
                "nombre" => $permiso['modulo'],
                "ruta" => $permiso['ruta_base'],
                //"ruta" => $rutaCompleta,
                "descripcion" => $permiso['modulo_descripcion'],
                "imagen" => $permiso['modulo_imagen'],
                "formularios" => []
            ];
         }

        // *** FORMULARIOS ***
        if (!isset($menu[$subsistemaId]["modulos"][$moduloId]['formularios'][$formularioId])) { 
            // ====== Contrucción de la ruta Completa (MODULO + FORMULARIO) =========
            //Construye la ruta completa del formulario concatenando la ruta base del módulo con la ruta del formulario, eliminando cualquier barra adicional al inicio o al final de cada parte.
            
            $rutaFormulario = '';
            //Valida si existe la ruta(nombre del archivo), si existe, Extrae solo el nombre del archivo del formulario para evitar problemas con rutas relativas
            if (!empty($permiso['ruta'])) { 
                $rutaFormulario = basename((string)($permiso['ruta'] ?? '')); //Extrae solo el nombre del archivo. Ej. formularios/usuarios.php => usuarios.php
            }
            $menu[$subsistemaId]["modulos"][$moduloId]['formularios'][$formularioId] = [ //Agrega la lista de formularios dentro de los módulos
                "id" => $formularioId,
                "nombre" => $permiso['formulario'],
                //"ruta" => $permiso['ruta'],
                //"ruta" => basename($permiso['ruta']), // Extrae solo el nombre del archivo del formulario para evitar problemas con rutas relativas
                "ruta" => $rutaFormulario,
                "descripcion" => $permiso['formulario_descripcion'],
                "imagen" => $permiso['formulario_imagen'],
                "orden" => $permiso['orden'],
                "acciones" => [] //Acciones (crear/editar/etc.)
            ];
        } 
        
        //== Verifica si la acción ya existe en la lista de acciones del formulario para evitar duplicados ==
        $accionExiste = false;

        foreach ( $menu[$subsistemaId]["modulos"][$moduloId]['formularios'][$formularioId]['acciones'] as $accion ) { 
            if ((int)$accion['id'] === (int)$permiso['accion_id']) {
                $accionExiste = true;
                break;
            }
        }

        if (!$accionExiste) { //Si la acción no existe en la lista de acciones del formulario, se agrega a la lista de acciones del formulario
            $menu[$subsistemaId]["modulos"][$moduloId]['formularios'][$formularioId]['acciones'][] = [
                "id" => (int)$permiso['accion_id'],
                "nombre" => $permiso['accion']
            ];
        }
    }

    /** *** 6. REINDEXAR + VERSIONAR IMÁGENES (cache-busting) *** */
    //Anexa ?v=filemtime a cada imagen existente para que, al cambiar el icono,
    //la URL cambie y ninguna caché (CDN/proxy/navegador) sirva bytes viejos.
    $dirRaiz = __DIR__ . '/../';
    foreach ($menu as &$subsistema) { //Reindexa los subsistemas
        if (!empty($subsistema['imagen']) && strpos($subsistema['imagen'], '?') === false) {
            $ruta = $dirRaiz . ltrim($subsistema['imagen'], '/');
            if (file_exists($ruta)) {
                $subsistema['imagen'] .= '?v=' . filemtime($ruta);
            }
        }
        //Reindexa los módulos dentro de cada subsistema
        foreach ($subsistema['modulos'] as &$modulo) {
            if (!empty($modulo['imagen']) && strpos($modulo['imagen'], '?') === false) {
                $ruta = $dirRaiz . ltrim($modulo['imagen'], '/');
                if (file_exists($ruta)) {
                    $modulo['imagen'] .= '?v=' . filemtime($ruta);
                }
            }
            foreach ($modulo['formularios'] as &$formulario) {
                if (!empty($formulario['imagen']) && strpos($formulario['imagen'], '?') === false) {
                    $ruta = $dirRaiz . ltrim($formulario['imagen'], '/');
                    if (file_exists($ruta)) {
                        $formulario['imagen'] .= '?v=' . filemtime($ruta);
                    }
                }
            }
            unset($formulario);
            $modulo['formularios'] = array_values($modulo['formularios']); //Reindexa los formularios dentro de cada módulo
        }
        unset($modulo);
        $subsistema['modulos'] = array_values($subsistema['modulos']); //Reindexa los módulos dentro de cada subsistema
    }
    unset($subsistema);

    return array_values($menu);
}
?>