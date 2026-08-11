<?php
session_start();
$tienellave = ($_SESSION['tipo'] == 1); // Permitir root solamente
if ($tienellave == false) {
    echo '<script language = javascript>
    alert("No tienes permisos")
    self.location = "formulario_menu_principal.html"
    </script>';
}
require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}
if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logcodigo = $_SESSION['codigo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario con Barra de Progreso</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .progress {
            margin-top: 20px;
            height: 30px; /* Altura de la barra de progreso */
        }
        .progress-bar {
            transition: width 0.5s ease; /* Animación suave */
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md bg-dark navbar-dark">
        <img src="img/logodelgobierno.png" width="35" height="30" alt="" loading="lazy">
        <a class="navbar-brand" href="formulario_menu_principal.html">Tecnopresta</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="collapsibleNavbar">
            <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="formulario_administracion_permisos.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply-all" viewBox="0 0 16 16">
                <path d="M8.098 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.7 8.7 0 0 0-1.921-.306 7 7 0 0 0-.798.008h-.013l-.005.001h-.001L8.8 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L4.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028zM9.3 10.386q.102 0 .223.006c.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96z"/>
                <path d="M5.232 4.293a.5.5 0 0 0-.7-.106L.54 7.127a1.147 1.147 0 0 0 0 1.946l3.994 2.94a.5.5 0 1 0 .593-.805L1.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028 4.012-2.954a.5.5 0 0 0 .106-.699"/>
                </svg> Regresar</a>
            </li> 
            <li class="nav-item">
                <a class="nav-link" href="gameover.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-door-open" viewBox="0 0 16 16">
                <path d="M8.5 10c-.276 0-.5-.448-.5-1s.224-1 .5-1 .5.448.5 1-.224 1-.5 1"/>
                <path d="M10.828.122A.5.5 0 0 1 11 .5V1h.5A1.5 1.5 0 0 1 13 2.5V15h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V1.5a.5.5 0 0 1 .43-.495l7-1a.5.5 0 0 1 .398.117M11.5 2H11v13h1V2.5a.5.5 0 0 0-.5-.5M4 1.934V15h6V1.077z"/>
                </svg> Cerrar Sesión</a>
            </li>  
            </ul>
        </div>  
    </nav>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Asignar rol a un usuario</h1>
        
        <!-- Formulario con Radio Buttons -->
        <form action="guardar_rol_del_usuario2.php" method="POST">
            <!-- Campo para correo electrónico -->
            <div class="mb-3">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input type="email" class="form-control w-75" id="email" name="email" required>
            </div>

            <!-- Campo para cédula -->
            <div class="mb-3">
                <label for="cedula" class="form-label">Cédula</label>
                <input type="text" class="form-control w-50" id="cedula" name="cedula" required>
            </div>

            <!-- Campo para código presupuestario -->
            <div class="mb-3">
                <label for="codigo_presupuestario" class="form-label">Código Presupuestario</label>
                <input type="text" class="form-control w-25" id="codigo_presupuestario" name="codigo_presupuestario" required>
            </div>

        <!-- Barra de Progreso Segmentada -->
        <div class="progress">
            <div id="segment1" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">Inventariador</div>
            <div id="segment2" class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">Prestador</div>
            <div id="segment3" class="progress-bar bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">Administrador</div>
            <div id="segment4" class="progress-bar bg-warning" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">Consultor</div>
            <div id="segment5" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">Root super vaca</div>
        </div>
            <!-- Radio Buttons para seleccionar el rol -->
            <div class="mb-3">
                <label class="form-label">Selecciona un Rol</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="rol" id="inventariador" value="4">
                    <label class="form-check-label" for="inventariador">Inventariador</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="rol" id="prestador" value="3">
                    <label class="form-check-label" for="prestador">Prestador</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="rol" id="administrador" value="2">
                    <label class="form-check-label" for="administrador">Administrador</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="rol" id="consultor" value="7">
                    <label class="form-check-label" for="consultor">Consultor</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="rol" id="root" value="1">
                    <label class="form-check-label" for="root">Root</label>
                </div>
            </div>

            <!-- Botón de envío -->
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>

    </div>

    <!-- Script para actualizar la barra de progreso -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('input[name="rol"]');
            const segments = [
                document.getElementById('segment1'),
                document.getElementById('segment2'),
                document.getElementById('segment3'),
                document.getElementById('segment4'),
                document.getElementById('segment5')
            ];

            radios.forEach(radio => {
                radio.addEventListener('change', function () {
                    // Reiniciar todos los segmentos
                    segments.forEach(segment => {
                        segment.style.width = '0%';
                    });

                    // Actualizar los segmentos según la opción seleccionada
                    switch (this.value) {
                        case '4': // Inventariador (1/5)
                            segments[0].style.width = '20%';
                            break;
                        case '3': // Prestador (2/5)
                            segments[0].style.width = '20%';
                            segments[1].style.width = '20%';
                            break;
                        case '2': // Administrador (3/5)
                            segments[0].style.width = '20%';
                            segments[1].style.width = '20%';
                            segments[2].style.width = '20%';
                            break;
                        case '7': // Consultor (4/5)
                            segments[0].style.width = '20%';
                            segments[1].style.width = '20%';
                            segments[2].style.width = '20%';
                            segments[3].style.width = '20%';
                            break;
                        case '1': // Root (5/5)
                            segments[0].style.width = '20%';
                            segments[1].style.width = '20%';
                            segments[2].style.width = '20%';
                            segments[3].style.width = '20%';
                            segments[4].style.width = '20%';
                            break;
                    }
                });
            });
        });
    </script>

    <!-- Scripts de Bootstrap -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
</body>
</html>