<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
$tienellave = ($_SESSION['tipo'] == 1 || $_SESSION['tipo'] == 2 || $_SESSION['tipo'] == 3 || $_SESSION['tipo'] == 4);
if ($tienellave == false) {
    echo '<script language="javascript">
          alert("No tienes permisos");
          self.location = "formulario_menu_inventario.html";
          </script>';
    exit();
}
*/
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexión a MySQL: " . mysqli_connect_error();
    exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

// Verificar si se recibió el ID del activo
if (!isset($_GET['idx']) || !is_numeric($_GET['idx'])) {
    die("ID de activo no válido");
}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

//$activo = '405';
$activo = intval($_GET['idx']);
//$logcodigo = $_SESSION['codigo'] ?? '';
$logcodigo = $_SESSION['codigo'] ?? '';

// Consulta para obtener información del activo
$preguntar = mysqli_query($link,"SELECT Ta.id_activo, Tag.clase, Tag.imagen, Ta.modelo, Tm.marca, Tc.color
     FROM t_activo Ta
     INNER JOIN t_activo_general Tag ON Ta.id_ag = Tag.id_ag
     INNER JOIN t_marca Tm ON Ta.id_marca = Tm.id_marca 
     INNER JOIN t_color Tc ON Ta.id_color = Tc.id_color 
     WHERE Ta.id_activo = '".$activo."'
     ORDER BY Tag.clase ASC") or die(mysqli_error($link));  
$respuesta = mysqli_fetch_array($preguntar);
$clase = $respuesta['clase'];
$marca = $respuesta['marca'];
$modelo = $respuesta['modelo'];
$color = $respuesta['color'];
$imagen = $respuesta['imagen'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Placa y Serial</title>
    <!-- Bootstrap 5 CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="select2/select2.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link href="sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .select2-container .select2-selection--single {
            height: 38px;
            padding-top: 5px;
        }
        .asset-card {
            max-width: 600px;
            margin: 20px auto 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .asset-image {
            height: 150px;
            object-fit: contain;
            background-color: #f8f9fa;
        }
        .asset-details {
            padding: 15px;
        }

.asset-card {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.asset-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.12);
}

.asset-card .card-header {
    font-weight: 600;
    letter-spacing: 0.5px;
    font-size: 0.95rem;
}

.asset-image {
    max-height: 180px;
    width: auto;
    object-fit: contain;
    padding: 15px;
}

.detail-item {
    transition: all 0.2s ease;
}

.detail-item:hover {
    transform: translateX(5px);
}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="formulario_menu_principal.html">
                <i class="bi bi-laptop"></i> Tecnopresta
            </a>
            <!-- Botón para móviles (actualizado a BS5) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <!-- Menú colapsable -->
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="formulario_busqueda_creacion_activo_n.php">
                            <i class="bi bi-arrow-left-circle"></i> Regresar
                        </a>
                    </li>   
                </ul>
            </div>
        </div>
    </nav>
    <div class="container">
        <!-- Tarjeta con información del activo -->
<div class="card asset-card mb-4 border-primary">
    <!-- Cabecera decorativa -->
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-pc-display-horizontal me-2"></i>
            <span>REGISTRO DE ACTIVO TECNOLÓGICO</span>
        </div>
        <div class="badge bg-light text-primary">
            <i class="bi bi-tag-fill me-1"></i> ID: <?php echo htmlspecialchars($activo); ?>
        </div>
    </div>
    
    <div class="row g-0">
        <!-- Sección de imagen con marco decorativo -->
        <div class="col-md-4 position-relative">
            <div class="p-2 h-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                <img src="img/<?php echo htmlspecialchars($imagen); ?>" class="img-fluid asset-image" alt="<?php echo htmlspecialchars($clase); ?>">
            </div>
            <div class="position-absolute bottom-0 start-0 end-0 text-center p-2" style="background-color: rgba(13, 110, 253, 0.1);">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> Imagen de referencia
                </small>
            </div>
        </div>
        
        <!-- Sección de detalles con mejor presentación -->
        <div class="col-md-8">
            <div class="card-body asset-details">
                <h5 class="card-title text-primary mb-3">
                    <i class="bi bi-pc-display me-2"></i><?php echo htmlspecialchars($clase); ?>
                </h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-item mb-2">
                            <span class="badge bg-light text-dark w-100 text-start">
                                <i class="bi bi-tag me-2 text-primary"></i>
                                <strong>Marca:</strong> <?php echo htmlspecialchars($marca); ?>
                            </span>
                        </div>
                        <div class="detail-item mb-2">
                            <span class="badge bg-light text-dark w-100 text-start">
                                <i class="bi bi-palette me-2 text-primary"></i>
                                <strong>Color:</strong> <?php echo htmlspecialchars($color); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item mb-2">
                            <span class="badge bg-light text-dark w-100 text-start">
                                <i class="bi bi-upc-scan me-2 text-primary"></i>
                                <strong>Modelo:</strong> <?php echo htmlspecialchars($modelo); ?>
                            </span>
                        </div>
                        <div class="detail-item mb-2">
                            <span class="badge bg-light text-dark w-100 text-start">
                                <i class="bi bi-calendar-check me-2 text-primary"></i>
                                <strong>Fecha Registro:</strong> <?php echo date('d/m/Y'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Mensaje decorativo -->
                <div class="alert alert-light mt-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-4"></i>
                    <div>
                        <small class="text-muted">Verifique cuidadosamente la información antes de registrar placas y seriales.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Formulario para agregar placa y serial -->
        <div class="form-container">
            <h2 class="text-center mb-4">Agregar Placa y Serial</h2>
            <form id="formPlaca">
                <input type="hidden" name="id_activo" value="<?php echo $activo; ?>">
                <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($logcodigo); ?>">
                
                <div class="mb-3">
                    <label for="placa" class="form-label">Placa</label>
                    <input type="text" class="form-control" id="placa" name="placa" required>
                </div>
                
                <div class="mb-3">
                    <label for="serial" class="form-label">Serial</label>
                    <input type="text" class="form-control" id="serial" name="serial" required>
                </div>
                
                <div class="mb-3">
                    <label for="id_fondos" class="form-label">Origen Presupuestario</label>
                        <select class="form-select select2" id="id_fondos" name="id_fondos" required style="width: 100%;">
                            <option value="">Seleccione el origen presupuestario</option>
                            <?php 
                            $query = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos");
                            while ($row = mysqli_fetch_array($query)) {
                                echo '<option value="'.htmlspecialchars($row['id_fondos']).'">'.htmlspecialchars($row['fondos']).'</option>';
                            }
                            ?>
                        </select>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="select2/select2.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="sweetalert2/sweetalert2.all.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Inicializar Select2
        $('.select2').select2({
            placeholder: "Seleccione el origen presupuestario",
            allowClear: true,
            language: {
                noResults: function() {
                    return "No se encontraron resultados";
                },
                searching: function() {
                    return "Buscando...";
                }
            },
            width: '100%'
        });
        
        // Manejar el envío del formulario
        $('#formPlaca').on('submit', function(e) {
            e.preventDefault();
            
            // Mostrar loader
            Swal.fire({
                title: 'Procesando...',
                html: 'Por favor espere mientras guardamos la información',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar datos por AJAX
            $.ajax({
                url: 'guardar_placa_n.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        Swal.fire({
                            title: '¡Éxito!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            // Limpiar formulario
                            $('#formPlaca')[0].reset();
                            $('.select2').val('').trigger('change');
                            
                            // Opcional: cerrar modal o redirigir
                            // window.location.reload();
                        });
                    } else {
                        // Mostrar mensaje de error
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    Swal.fire({
                        title: 'Error',
                        text: 'Ocurrió un error al procesar la solicitud: ' + error,
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                }
            });
        });
    });
    </script>
</body>
</html>