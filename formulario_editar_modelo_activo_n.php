<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte el administrador");
    window.location.href = "formulario_corregir_modelo.php";
    </script>';
    exit();
}*/
require_once("conexion.php");
require_once __DIR__ . '/funciones_modelos_n.php';
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

// ==== CONSTRUIR RUTA DE REGRESO =====
$ruta_regreso ='navegar.php?ruta=formulario_menu_principal.php';
if (isset($_GET['subsistema_id'], $_GET['modulo_id'])) {
    $ruta_regreso = 'navegar.php?ruta=formulario_sub_modulos.php'
    . '&subsistema_id=' . intval($_GET['subsistema_id'] ?? 0)
    . '&modulo_id=' . intval($_GET['modulo_id'] ?? 0);
}

/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];
*/
// Inicializar variables
$id_activo = "";
$activo = null;
$error = "";
$success = "";

// Procesar búsqueda del activo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buscar'])) {
    $id_activo = trim($_POST['id_activo']);
    
    if (empty($id_activo)) {
        $error = "Por favor, ingrese un ID de activo.";
    } else {
        // Buscar el activo en la base de datos
        $query = "SELECT * FROM t_activo WHERE id_activo = ?";
        $stmt = $link->prepare($query);
        $stmt->bind_param("i", $id_activo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $activo = $result->fetch_assoc();
        } else {
            $error = "No se encontró un activo con el ID proporcionado.";
        }
        $stmt->close();
    }
}

// Procesar actualización del activo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
    $id_activo = $_POST['id_activo'];
    $id_ag = $_POST['id_ag'];
    $id_marca = $_POST['id_marca'];
    $modelo = trim($_POST['modelo']);
    $id_color = $_POST['id_color'];
    
    if (empty($modelo)) {
        $error = "El campo modelo es obligatorio.";
    } else {
        // Obtener o crear el modelo en el catalogo maestro (t_modelos)
        $id_modelo = obtenerOCrearModelo($link, $modelo, $_SESSION['codigo'] ?? '');

        $query = "UPDATE t_activo SET id_ag = ?, id_marca = ?, modelo = ?, id_color = ?, modelo_id = ? WHERE id_activo = ?";
        $stmt = $link->prepare($query);
        $stmt->bind_param("iisiii", $id_ag, $id_marca, $modelo, $id_color, $id_modelo, $id_activo);
        
        if ($stmt->execute()) {
            $success = "Activo actualizado correctamente.";
            // Volver a cargar los datos actualizados
            $activo = [
                'id_activo' => $id_activo,
                'id_ag' => $id_ag,
                'id_marca' => $id_marca,
                'modelo' => $modelo,
                'id_color' => $id_color
            ];
        } else {
            $error = "Error al actualizar el activo: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar modelo del activo</title>
     <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous"> -->
    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet"> -->
    
    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <!-- <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script> -->
    
    <!-- Bootstrap 5 JS -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

    <style>
        .card-custom {
            background-color: #f0f4f8; /* Color frío y suave */
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            align-self: flex-end;
        }
        .btn-custom {
            background-color: #007bff; /* Color frío */
            border: none;
            border-radius: 5px;
        }
        .icon-large {
            font-size: 2em; /* Ajusta el tamaño del ícono */
        }
        .form-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: bold;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>  

    <div class="container mt-5">
        
        <h4>Formulario para editar modelo del activo.</h4>

        <!-- Mostrar mensajes de error o éxito -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulario de búsqueda -->
        <div class="form-section">
            <h5>Buscar activo por ID</h5>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="id_activo" class="form-label">ID del Activo</label>
                            <input type="number" class="form-control w-50" id="id_activo" name="id_activo" 
                                value="<?php echo htmlspecialchars($id_activo); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="buscar" class="btn btn-primary w-100">Buscar Activo</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Formulario de edición (solo visible si se encontró el activo) -->
        <?php if ($activo): ?>
        <div class="form-section">
            <h5>Editar información del activo</h5>
            <form method="POST" action="">
                <input type="hidden" name="id_activo" value="<?php echo $activo['id_activo']; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="id_ag" class="form-label">ID Ag</label>
                            <input type="number" class="form-control" id="id_ag" name="id_ag" 
                                value="<?php echo htmlspecialchars($activo['id_ag']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="id_marca" class="form-label">ID Marca</label>
                            <input type="number" class="form-control" id="id_marca" name="id_marca" 
                                value="<?php echo htmlspecialchars($activo['id_marca']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" 
                                value="<?php echo htmlspecialchars($activo['modelo']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="id_color" class="form-label">ID Color</label>
                            <input type="number" class="form-control" id="id_color" name="id_color" 
                                value="<?php echo htmlspecialchars($activo['id_color']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="actualizar" class="btn btn-success">Actualizar Activo</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="currentColor" class="bi bi-eraser-fill" viewBox="0 0 16 16">
            <path d="M8.086 2.207a2 2 0 0 1 2.828 0l3.879 3.879a2 2 0 0 1 0 2.828l-5.5 5.5A2 2 0 0 1 7.879 15H5.12a2 2 0 0 1-1.414-.586l-2.5-2.5a2 2 0 0 1 0-2.828zm.66 11.34L3.453 8.254 1.914 9.793a1 1 0 0 0 0 1.414l2.5 2.5a1 1 0 0 0 .707.293H7.88a1 1 0 0 0 .707-.293z"/>
            </svg>
        </div>
    </div>

    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
        style="bottom: 100px;" data-tooltip="Regresar">
            <i class="bi bi-arrow-left-circle-fill"></i>
    </a>
    
    <?php include 'partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

</body>
</html>