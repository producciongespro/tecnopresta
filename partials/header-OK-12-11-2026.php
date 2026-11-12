<?php
    // ================== INCLUSIÓN DE USUARIO ==================
    //include 'usuarioAzure.php'; //Archivo para obtener los datos del usuario desde Azure
    //require_once 'usuarioAzure.php'; //Archivo para obtener los datos del usuario desde Azure
    //require_once 'usuarioAzure.php';
    require_once __DIR__ . '/../usuarioAzure.php';
    //require_once 'usuarioAzure.php';
    $usuario_azure = obtenerUsuarioSesion(); //Obtiene los datos del Azure captados en el archivo usuarioAzure.php -- Datos de Sesion
     // ================== VALIDACIÓN DE SESIÓN ==================
  /*  if (!$usuario_azure) {
        // Si no hay datos de usuario en la sesión, redirige al formulario de inicio de sesión
        header('Location: index.html');
        exit();
    }
  */  // ================== DATOS DEL USUARIO ==================
    $fotoPerfil = $usuario_azure['fotoPerfil'] ?? 'assets/img/avatarH.svg'; // Ruta por defecto si no hay foto de perfil
    $nombreCompleto = trim(
            ($usuario_azure['nombre'] ?? '') . ' ' . 
            ($usuario_azure['apellidos'] ?? '')
        ); // Combina nombre y apellidos para mostrar en el header

    $dependencia = $usuario_azure['dependencia'] ?? 'Dependencia no disponible'; // Muestra la dependencia del usuario o un mensaje por defecto

    // ================== ROL DEL USUARIO ==================
    $rolesUsuario = $usuario_azure['roles'] ?? [];
    $rolNombre = '';
    foreach ($rolesUsuario as $rol) {
        if (!empty($rol['rol'])) {
            $rolNombre = $rol['rol'];
            break;
        }
    }
?>

<!-- Este Script es para tener acceso a la función de OBTENER FOTO DE AZURE -->
<!-- <script src="js/formulario_login.js"></script> -->

<!-- FIN OBTNER ACCESO A función de OBTENER FOTO DE AZURE -->
<!-- this.fotoPerfil = await window.obtenerFotoPerfil(); -->
<!-- ================== HEADER ================== -->
<div class="mep-header">
    <div class="container">
        <div class="row align-items-center">
            <!-- ================== LOGO ================== -->
            <div class="col-md-8">
                <div class="mep-logo">
                    <div class="mep-logo-box">
                        <img src="assets/img/logo-mep.svg" 
                            alt="Logo del Ministerio de Educación Pública" 
                            class="mep-logo-icon" />
                    </div>
                    <!-- Texto institucional -->
                    <div class="ms-3">
                        <div>MINISTERIO DE EDUCACI&Oacute;N P&Uacute;BLICA</div>
                        <div class="gov-text">GOBIERNO DE COSTA RICA</div>
                        <div class="gov-text" style="font-size: small;">DIRECCI&Oacute;N DE RECURSOS TECNOL&Oacute;GICOS EN EDUCACI&Oacute;N</div>
                        <!-- <div class="unit-text">TecnoPresta -->
                        <div class="gov-text" style="font-size: medium;">
                            <span style="color: #fff" font-size="2.25rem">Tecno<span style="color: var(--mep-gold)">Presta</span></span>
                        </div>
                        
                    </div>
                </div>
            </div>

            <!-- ================== USUARIO ================== -->
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <!-- DROPDOWN -->
                <div class="dropdown position-relative">
                    <!-- BOTÓN DROPDOWN -->
                    <a href="#"
                        class="d-inline-flex align-items-center text-white dropdown-toggle"  
                        data-bs-toggle="dropdown" 
                        data-bs-display="static"  
                        aria-expanded="false"
                        style="text-decoration: none;"
                    >                        
                        <!-- Nombre -->
                        <span class="me-2 text-white-50">
                            <?= htmlspecialchars($nombreCompleto) ?>
                            <?php if ($rolNombre): ?>
                                <br><small class="opacity-75"><?= htmlspecialchars($rolNombre) ?></small>
                            <?php endif; ?>
                        </span>
                        <!-- FOTO Avatar -->
                        <img 
                            id="fotoUsuarioHeader"
                            src="<?= htmlspecialchars($fotoPerfil) ?>" 
                            class="rounded-circle"
                            width="60"
                            alt="Foto del usuario"
                            style="object-fit: cover; border: 2px solid #fff;"
                        >
        
                    </a>
                    <!-- Dropdown MENU -->
                    <div class="dropdown-menu dropdown-menu-end user-dropdown shadow animate">                    
                    <!-- Informamación superior -->                             
                            <div class="dropdown-user-info d-flex align-items-center px-3 py-2">
                            <!-- <div class="d-flex align-items-center px-3 py-2"> -->
                                <!-- FOTO PEQUEÑA DEL DROPDOWN -->
                                <img 
                                    id="fotoUsuarioMini"
                                    src="<?= htmlspecialchars($fotoPerfil) ?>"
                                    class="user-mini-avatar"
                                    alt="Foto mini usuario"
                                >
                                <div>
                                    <div class="user-name">
                                    <!-- <div class="fw-bold"> -->
                                        <?= htmlspecialchars($nombreCompleto) ?>
                                    </div>
                                    <?php if ($rolNombre): ?>
                                        <small class="text-muted fw-semibold">
                                            <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($rolNombre) ?>
                                        </small><br>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($dependencia) ?>
                                    </small>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <!-- Perfil -->
                            <a class="dropdown-item user-item" href="portal_perfil_n.php">
                                <!-- <i class="bi bi-person-circle"></i> -->
                                <i class="bi bi-person-circle me-2"></i>
                                <span>Mi Perfil</span>
                             </a>

                            <a class="dropdown-item user-item logout-item" href="gameover.php">                            
                                <!-- <i class="bi bi-box-arrow-right"></i> -->
                                <i class="bi bi-box-arrow-right me-2"></i>
                                <span>Cerrar Sesión</span>
                             </a>

                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>
<script>

/**
 * ==========================================
 * CARGAR FOTO DEL USUARIO DESDE LOCALSTORAGE
 * ==========================================
 */

document.addEventListener("DOMContentLoaded", function () {

    console.log("🚀 Inicializando foto de usuario...");
    // ================================
    // Avatar por defecto
    // ================================
    const avatarDefault = "assets/img/avatarH.svg";
    // ================================
    // Obtener imágenes del DOM
    // ================================
    const fotoHeader = document.getElementById("fotoUsuarioHeader");

    const fotoMini = document.getElementById("fotoUsuarioMini");

    // ================================
    // Validar existencia de elementos
    // ================================
    if (!fotoHeader || !fotoMini) {

        console.warn( "⚠️ No se encontraron elementos de foto");
        return;
    }

    // ================================
    // Obtener foto desde localStorage
    // ================================
    const fotoGuardada =
        localStorage.getItem("fotoPerfil");

    console.log( "📸 FOTO EN LOCALSTORAGE:", !!fotoGuardada);

    // ================================
    // Si existe foto real
    // ================================
    if ( fotoGuardada && fotoGuardada.trim() !== "" ) {
        console.log("✅ Aplicando foto real al header");
        // Foto principal
        fotoHeader.src = fotoGuardada;
        // Foto pequeña
        fotoMini.src = fotoGuardada;
    } else {
        console.warn("⚠️ No existe foto guardada");
        // Mantener avatar por defecto
        fotoHeader.src = avatarDefault;
        fotoMini.src = avatarDefault;
    }
    // ================================
    // Validar errores de carga
    // ================================
    fotoHeader.onerror = function () {
        console.warn("⚠️ Error cargando foto principal");
        this.src = avatarDefault;
    };

    fotoMini.onerror = function () {
        console.warn("⚠️ Error cargando foto mini");
        this.src = avatarDefault;
    };
});

</script>