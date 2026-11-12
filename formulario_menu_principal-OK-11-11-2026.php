<?php // include 'templates/cabecera.php'; 
    //session_start(); // Asegúrate de iniciar la sesión para acceder a las variables de sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

  /*  echo defined('ACCESO_SEGURO')
    ? 'ACCESO_SEGURO SI existe'
    : 'ACCESO_SEGURO NO existe';
    exit;
    */
    // === Bloquear acceso directo ===
    if (!defined('ACCESO_SEGURO')) {
        http_response_code(403);
        exit('Acceso directo no permitido');
    }
?> 

<!doctype html>
<html lang="es">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
        <meta http-equiv="X-UA-Compatible" content="ie=edge" />
        <link rel="manifest" href="manifest.json" />

        <!-- Bootstrap 5 CSS + Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

        <link rel="stylesheet" href="css/formulario_menu_principal.css?v=2" />

        <!-- Alpine js -- Defer es para utilizar alpine antes de definrlo -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <link rel="stylesheet" href="assets/css/nueva-identidad.css"/>

    </head>
    <body class="layout-page">
        <?php include 'partials/header.php'; ?>
        <main class="contenido-principal">
            <div x-data="menuApp()" x-init="init()">

                <!-- MENSAJES -->
                <div class="container mt-3">
                    <div x-show="error" class="alert alert-danger" x-text="error"></div>
                </div>

                <!-- LOADER -->
                <div class="text-center mt-5" x-show="loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Cargando módulos...</p>
                </div>
                <!-- Saludo de Bienvenida-->
                <!-- <div class="contenido-principal mb-4"> -->
                <div class="container mb-4"> 
                        <h4 class="fw-semibold mb-1" style="color: var(--mep-blue);">
                            Bienvenido/a, <span x-text="usuario?.nombre ? usuario.nombre.split(' ')[0] : 'Usuario'"></span>
                            <!-- <span x-text="usuario.nombre.split(' ')[0]"></span>   Solo muestra el primer nombre -->
                        </h4>
                        <!-- retorna la fecha formateada en español-->
                        <p class="text-muted small mb-0" x-text="fechaHoy()"></p>
                        <hr>
                        <p class="text-uppercase text-muted small fw-semibold mb-3 ls-wide"
                            style="letter-spacing: 6px;">
                            Módulos del Sistema
                        </p>
                    <!-- </div> -->
                </div>
                <!-- Contenedor Principal -->
                <!-- <div class="container mt-4" x-show="!loading">  ***11-5-26*** -->
                <div class="container mt-4" x-show="!loading && subsistemas.length > 0">
                    <div class="row">
                        <template x-for="subsistema in subsistemas" :key="subsistema.nombre">
                            <!-- Se agrega cada subsistema como un elemento de lista -->
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 mb-4" role="listitem">

                                <a :href="'navegar.php?ruta=formulario_modulos.php&subsistema_id=' + subsistema.id"
                                    class="card h-100 shadow-sm text-decoration-none d-block"
                                    :aria-label="'Ingresar al módulo ' + subsistema.nombre"
                                >
                                <!-- <a :href="'formulario_modulos.php?subsistema_id=' + subsistema.id"
                                    class="card h-100 shadow-sm text-decoration-none d-block"
                                    :aria-label="'Ingresar al módulo ' + subsistema.nombre"
                                > -->
                                    <!-- <div class="card-body d-flex flex-column p-4"> -->
                                    <div class="card card-premium h-100 d-flex flex-column p-4 fade-enter">
                                        <div class="img-container">
                                            <img
                                                :src="subsistema.imagen"                                                
                                                class="img-fluid"
                                                loading="lazy"
                                                alt="Imagen del subsistema"
                                            />
                                        </div>
                                        <!-- Nombre del Modulo -->
                                        <h5
                                            class="card-title fw-semibold mb-2"
                                            style="font-size: 1 rem; color: #0f1f3d;"
                                            x-text="subsistema.nombre"
                                        ></h5>
                                        <!-- Descripción del Módulo -->
                                        <p class="card-text text-muted small flex-grow-1" 
                                            style="line-height: 1.6;"
                                            x-text="subsistema.descripcion">
                                        </p>
                                        <!-- Pie del CARD-->                                    
                                        <div class="border-top pt-3 mt-3">
                                            
                                            <small class="text-muted d-block mb-2 fw-semibold">
                                                Módulos disponibles
                                            </small>

                                            <div class="d-flex flex-wrap gap-2">

                                                <template x-for="modulo in subsistema.modulos" :key="modulo.id">

                                                    <!-- <span class="badge badge-modulo"                                                        
                                                        x-text="modulo.nombre">
                                                    </span> -->
                                                    <a :href="'navegar.php?ruta=formulario_sub_modulos.php&subsistema_id=' 
                                                                + subsistema.id 
                                                                + '&modulo_id=' 
                                                                + modulo.id"

                                                        class="badge badge-modulo text-decoration-none"
                                                        :title="'Ir al módulo: ' + modulo.nombre"
                                                    >

                                                        <!-- Nombre del módulo -->
                                                        <span x-text="modulo.nombre"></span>

                                                    </a>

                                                </template>

                                            </div>

                                        </div>
                                    </div>
                                </a>   
                            </div> 
                        </template>

                        <!-- CARD ESTÁTICA: ACERCA DE / MANUAL DE USUARIO (información institucional al final de la grilla) -->
                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 mb-4" role="listitem">
                            <div class="card card-premium h-100 d-flex flex-column p-4 fade-enter"
                                 style="border-top: 3px solid rgba(207, 172, 101, 0.55);">

                                <a href="navegar.php?ruta=acerca_de_n.php&seccion=acerca"
                                    class="text-decoration-none d-block flex-grow-1"
                                    aria-label="Ir a Acerca de..."
                                >
                                    <div class="img-container">
                                        <img
                                            src="assets/img/acercaDe.svg"
                                            class="img-fluid"
                                            loading="lazy"
                                            alt="Imagen de Acerca de"
                                        />
                                    </div>
                                    <h5 class="card-title fw-semibold mb-2"
                                        style="font-size: 1rem; color: #0f1f3d;"
                                    >Acerca de...</h5>
                                    <p class="card-text text-muted small" style="line-height: 1.6;">
                                        Información General del Sistema TecnoPresta y su Manual de Usuario.
                                    </p>
                                </a>

                                <div class="border-top pt-3 mt-3">
                                    <small class="text-muted d-block mb-2 fw-semibold">
                                        Información
                                    </small>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="navegar.php?ruta=acerca_de_n.php&seccion=acerca"
                                            class="badge badge-modulo text-decoration-none"
                                            title="Ver información del sistema"
                                        >Acerca de...</a>
                                        <a href="navegar.php?ruta=acerca_de_n.php&seccion=manual"
                                            class="badge badge-modulo text-decoration-none"
                                            title="Ver el manual de usuario"
                                        >Manual de usuario</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>
        
            <!-- Este Script es para tener acceso a la función de OBTENER FOTO DE AZURE -->
            <script src="js/formulario_login_e.js"></script>
            <!-- FIN OBTNER ACCESO A función de OBTENER FOTO DE AZURE -->

            <!-- SCRIPT -->
            <script>
                // Función para devolver la fecha actual en español
                function fechaHoy() {
                    const opciones = {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    };

                    return new Date().toLocaleDateString('es-ES', opciones);
                }

                //function menuApp() {
                window.menuApp = function () {
                    return {
                    //variables reactivas
                    //usuario: "",
                    usuario: {
                        nombre: "",
                        institucion: "",
                    },
                    loading: true,
                    error: "",
                    subsistemas: [], //datos del menú
                    fotoPerfil: "", //Aquí se guarda la URL de la foto o imagen de Perfil

                    //INIT se ejecuta al cargar
                    async init() {
                        console.log("INIT Ejecutando");

                        // Verifica que exite sesión en FrontEnd
                        const sesion = sessionStorage.getItem("sesion");

                        if (!sesion) {
                            console.log("No hay sesión.");
                            window.location.href = "index.html";
                            //window.location.href ="navegar.php?ruta=formulario_menu_principal.php";
                        return;
                        }
                        // 👤 Obtiene la foto del usuario desde Microsoft Graph
                        //this.fotoPerfil = await window.obtenerFotoPerfil();
                        // 👤 Obtiene la foto del usuario desde Microsoft Graph
                        if (typeof window.obtenerFotoPerfil === "function") {

                            try {

                                this.fotoPerfil = await window.obtenerFotoPerfil();

                            } catch (e) {

                                console.warn("No se pudo obtener foto perfil:", e);

                                this.fotoPerfil = "assets/img/avatarH.svg";
                            }

                        } else {

                            console.warn("obtenerFotoPerfil no existe todavía");

                            this.fotoPerfil = "assets/img/avatarH.svg";
                        }
                        //****  FIN OBTENER FOTO DE PERFIL */
                        //Carga módulos desde el backend
                        await this.cargaModulos();
                },

                async cargaModulos() {
                    console.log("Iniciando CargaModulos");
                    //const sesion = sessionStorage.getItem('sesion'); // simulación de verificación de sesión

                    try {
                        const response = await fetch("sql/formulario_principal.php", {
                            method: "GET",
                            credentials: "include", // nesario para la sesión en PHP
                        });

                        const data = await response.json();
                        console.log("Respuesta BackEnd: ", data);

                        if (data.error) {
                            this.error = data.error;
                            return;
                        }

                        this.subsistemas = data.data || [];

                        //Cargar usuario desde sessionStorage
                        const sesion = JSON.parse(sessionStorage.getItem("sesion"));
                        this.usuario = {
                            nombre: sesion?.Nombre || "Usuario",
                            institucion: sesion?.Dependencia || "",
                        };

                        //this.usuario = sesion || {}; //Carga los datos de la sesión en usuario
                        } catch (e) {
                            this.error = "Error al cargar módulos";
                            console.error(e);
                    } finally {
                        //Ocultar Loader
                        this.loading = false;
                    }
                },
                };
            };
            </script>
        </main>
        <?php include 'partials/footer.php'; ?>
    </body>
</html>
<?php //include 'templates/pie.php'; ?>