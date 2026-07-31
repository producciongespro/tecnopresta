<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1, 7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte a su director(a) institucional para que se los brinde, si es usted prestador o inventariador");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}
*/
require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
    exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
    exit();
}

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}


// Verificar que se enviaron datos para editar
if (isset($_POST['btnEditar']) && isset($_POST['idsplacas'])) {
    $ids_placas = $_POST['idsplacas'];
    $nuevos_id_activo = $_POST['nuevo_id_activo'];
    $nuevos_id_fondos = $_POST['nuevo_id_fondos'];
    
    $contador_actualizaciones = 0;
    $errores = array();
    
    // Iniciar transacción para asegurar la integridad de los datos
    mysqli_begin_transaction($link);
    
    try {
        foreach ($ids_placas as $id_placa) {
            // Verificar que existen los nuevos valores para este id_placa
            if (isset($nuevos_id_activo[$id_placa]) && isset($nuevos_id_fondos[$id_placa])) {
                $nuevo_activo = intval($nuevos_id_activo[$id_placa]);
                $nuevo_fondo = intval($nuevos_id_fondos[$id_placa]);
                
                // Validar que los valores no estén vacíos
                if ($nuevo_activo > 0 && $nuevo_fondo > 0) {
                    // Preparar la consulta de actualización
                    $query = "UPDATE t_placa 
                              SET id_activo = ?, id_fondos = ? 
                              WHERE id_placa = ?";
                    
                    $stmt = mysqli_prepare($link, $query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "iii", $nuevo_activo, $nuevo_fondo, $id_placa);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $contador_actualizaciones++;
                        } else {
                            $errores[] = "Error al actualizar placa ID $id_placa: " . mysqli_error($link);
                        }
                        
                        mysqli_stmt_close($stmt);
                    } else {
                        $errores[] = "Error preparando consulta para placa ID $id_placa: " . mysqli_error($link);
                    }
                } else {
                    $errores[] = "Valores inválidos para placa ID $id_placa";
                }
            }
        }
        
        // Confirmar la transacción si todo salió bien
        mysqli_commit($link);
        
        // Mostrar mensaje de resultado
        if ($contador_actualizaciones > 0) {
            $mensaje = "Se actualizaron exitosamente $contador_actualizaciones registros.";
            if (!empty($errores)) {
                $mensaje .= " Pero hubo " . count($errores) . " errores.";
            }
        } else {
            $mensaje = "No se realizaron actualizaciones. Verifique los datos.";
        }
        
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        mysqli_rollback($link);
        $mensaje = "Error en la transacción: " . $e->getMessage();
    }
    
} else {
    $mensaje = "No se seleccionaron registros para editar.";
}

// Cerrar conexión
mysqli_close($link);

// ==== RUTA DE REGRESO A LA CARD PADRE =====
$subsistema_id = intval($_POST['subsistema_id'] ?? 0);
$modulo_id = intval($_POST['modulo_id'] ?? 0);

$ruta_padre = 'navegar.php?ruta=formulario_menu_principal.php';
$ruta_formulario = 'navegar.php?ruta=herramienta_formulario_editar_activos_n.php';
if ($subsistema_id > 0 && $modulo_id > 0) {
    $ruta_padre = 'navegar.php?ruta=formulario_sub_modulos.php'
        . '&subsistema_id=' . $subsistema_id
        . '&modulo_id=' . $modulo_id;
    $ruta_formulario .= '&subsistema_id=' . $subsistema_id . '&modulo_id=' . $modulo_id;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado de Edición</title>
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <style>
        .container {
            margin-top: 50px;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="layout-page">
    <?php include 'partials/header.php'; ?>

    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3>Resultado de la Edición</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($mensaje)): ?>
                            <div class="alert alert-info">
                                <?php echo $mensaje; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errores)): ?>
                            <div class="alert alert-warning">
                                <h5>Errores detallados:</h5>
                                <ul>
                                    <?php foreach ($errores as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <a href="<?= htmlspecialchars($ruta_formulario) ?>" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Volver a Editar Activos
                            </a>
                            <a href="<?= htmlspecialchars($ruta_padre) ?>" class="btn btn-secondary">
                                <i class="bi bi-house"></i> Volver al Menú Principal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <?php include 'partials/footer.php'; ?>

</body>
</html>