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
    <div class="container-fluid px-3 px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <!-- ================== IDENTIDAD INSTITUCIONAL ================== -->
            <div class="mep-identity-container d-flex align-items-center">
                <!-- Bloque 1: Logo a la izquierda con recuadro blanco + Ministerio a la derecha alineados -->
                <div class="mep-inst-item mep-block-main d-flex flex-row align-items-center">
                    <div class="mep-logo-box">
                        <img src="assets/img/NE_CR_CC.svg" 
                            alt="Logo del Ministerio de Educación Pública" 
                            class="mep-logo-icon" />
                    </div>
                    <div class="mep-inst-text mep-text-collapsible ms-2">
                        <span class="mep-inst-title">MINISTERIO DE EDUCACI&Oacute;N P&Uacute;BLICA</span>
                        <span class="mep-inst-sub">GOBIERNO DE COSTA RICA</span>
                        <span class="mep-inst-sub">Dirección de Recursos Tecnológicos en Educación</span>
                    </div>
                </div>

                <!-- Bloque 2: Dirección de Recursos Tecnológicos en Educación 
                <div class="mep-inst-item mep-text-collapsible mep-divider-left">
                    <span class="mep-inst-title">DIRECCI&Oacute;N</span>
                    <span class="mep-inst-sub">DE RECURSOS TECNOL&Oacute;GICOS</span>
                    <span class="mep-inst-sub">EN EDUCACI&Oacute;N</span>
                </div> -->

                <!-- Bloque 3: Departamento de Investigación, Desarrollo e Implementación 
                <div class="mep-inst-item mep-text-collapsible mep-divider-left">
                    <span class="mep-inst-title">DEPARTAMENTO</span>
                    <span class="mep-inst-sub">DE INVESTIGACI&Oacute;N, DESARROLLO</span>
                    <span class="mep-inst-sub">E IMPLEMENTACI&Oacute;N</span>
                </div> -->

                <!-- Bloque 4: Tecno en Blanco + Presta en Dorado -->
                <div class="mep-inst-item mep-brand-item mep-divider-left">
                    <span class="mep-brand-tecnopresta">Tecno<span class="mep-brand-gold">Presta</span></span>
                </div>
            </div>

            <!-- ================== USUARIO / LOGIN ================== -->
            <div class="mep-user-container text-end">
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
                        <!-- Nombre (Oculto en móviles para mantener login compacto) -->
                        <span class="me-2 text-white-50 text-end d-none d-md-inline-block">
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
                            width="48"
                            height="48"
                            alt="Foto del usuario"
                            style="object-fit: cover; border: 2px solid #fff;"
                        >
        
                    </a>
                    <!-- Dropdown MENU -->
                    <div class="dropdown-menu dropdown-menu-end user-dropdown shadow animate">                    
                        <!-- Información superior -->                             
                        <div class="dropdown-user-info d-flex align-items-center px-3 py-2">
                            <!-- FOTO PEQUEÑA DEL DROPDOWN -->
                            <img 
                                id="fotoUsuarioMini"
                                src="<?= htmlspecialchars($fotoPerfil) ?>"
                                class="user-mini-avatar"
                                alt="Foto mini usuario"
                            >
                            <div>
                                <div class="user-name">
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
                            <i class="bi bi-person-circle me-2"></i>
                            <span>Mi Perfil</span>
                        </a>

                        <a class="dropdown-item user-item logout-item" href="gameover.php">                            
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