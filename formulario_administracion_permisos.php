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

// Procesar búsqueda
$filtro_cedula = isset($_GET['cedula']) ? $_GET['cedula'] : '';
$filtro_nombre = isset($_GET['nombre']) ? $_GET['nombre'] : '';
$filtro_codigo = isset($_GET['codigo']) ? $_GET['codigo'] : '';
$filtro_rol = isset($_GET['rol']) ? $_GET['rol'] : '';

$query = "SELECT * FROM t_lista_blanca WHERE 1=1";
if (!empty($filtro_cedula)) {
    $query .= " AND cedula LIKE '%$filtro_cedula%'";
}
if (!empty($filtro_nombre)) {
    $query .= " AND nombre LIKE '%$filtro_nombre%'";
}
if (!empty($filtro_codigo)) {
    $query .= " AND codigo = '$filtro_codigo'";
}
if (!empty($filtro_rol)) {
    $query .= " AND id_rol = $filtro_rol";
}

$result = mysqli_query($link, $query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Permisos</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos adicionales para mejorar la apariencia */
        .form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .table-container {
            margin-top: 20px;
        }
        .btn-custom {
            margin: 5px;
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
                <a class="nav-link" href="herramientas.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply-all" viewBox="0 0 16 16">
                <path d="M8.098 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.7 8.7 0 0 0-1.921-.306 7 7 0 0 0-.798.008h-.013l-.005.001h-.001L8.8 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L4.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028zM9.3 10.386q.102 0 .223.006c.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96z"/>
                <path d="M5.232 4.293a.5.5 0 0 0-.7-.106L.54 7.127a1.147 1.147 0 0 0 0 1.946l3.994 2.94a.5.5 0 1 0 .593-.805L1.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028 4.012-2.954a.5.5 0 0 0 .106-.699"/>
                </svg> Principal</a>
            </li> 
            <li class="nav-item">
                <a class="nav-link" href="formulario_crear_usuario_sistema.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-plus-fill" viewBox="0 0 16 16">
                  <path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                  <path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5"/>
                </svg> Agregar permiso</a>
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
    <div class="container">
        <h1 class="text-center my-4">Administración de Permisos</h1>
        
        <!-- Formulario de búsqueda -->
        <div class="form-container">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="cedula" class="form-control" placeholder="Cédula" value="<?php echo $filtro_cedula; ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="nombre" class="form-control" placeholder="Correo MEP del funcionario" value="<?php echo $filtro_nombre; ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="codigo" class="form-control" placeholder="Código del Centro" value="<?php echo $filtro_codigo; ?>">
                </div>
                <div class="col-md-2">
                    <select name="rol" class="form-select">
                        <option value="">Seleccione un rol</option>
                        <option value="1" <?php echo ($filtro_rol == 1) ? 'selected' : ''; ?>>Root</option>
                        <option value="2" <?php echo ($filtro_rol == 2) ? 'selected' : ''; ?>>Administrador</option>
                        <option value="3" <?php echo ($filtro_rol == 3) ? 'selected' : ''; ?>>Prestador</option>
                        <option value="4" <?php echo ($filtro_rol == 4) ? 'selected' : ''; ?>>Inventariador</option>
                        <option value="7" <?php echo ($filtro_rol == 7) ? 'selected' : ''; ?>>Consultor</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>
        </div>

        <!-- Tabla de resultados -->
        <div class="table-container">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Cédula</th>
                        <th>Correo</th>
                        <th>Código del Centro</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $row['cedula']; ?></td>
                        <td><?php echo $row['nombre']; ?></td>
                        <td><?php echo $row['codigo']; ?></td>
                        <td>
                            <?php
                            switch ($row['id_rol']) {
                                case 1: echo 'Root'; break;
                                case 2: echo 'Administrador'; break;
                                case 3: echo 'Prestador'; break;
                                case 4: echo 'Inventariador'; break;
                                case 7: echo 'Consultor'; break;
                                default: echo 'Básico'; break;
                            }
                            ?>
                        </td>
                        <td>
                            <a href="eliminar_rol2.php?id=<?php echo $row['id_lista_blanca']; ?>" class="btn btn-danger btn-sm btn-custom" onclick="return confirm('¿Está seguro?');">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Scripts de Bootstrap (opcional, si necesitas funcionalidades JS) -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
</body>
</html>