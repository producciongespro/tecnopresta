<?php
session_start();
if (!isset($_SESSION['tipo']) || !in_array($_SESSION['tipo'], [1, 2, 3, 4])) {
    echo '<script>alert("No tienes permisos"); window.location.href = "formulario_menu_inventario.html";</script>';
    exit;
}

require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexión a MySQL: " . mysqli_connect_error();
    exit;
}

mysqli_set_charset($link, "utf8");

$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logcodigo = $_SESSION['codigo'];

// Variables para mantener los valores del formulario
$id_ag_value = '';
$id_marca_value = '';
$id_color_value = '';
$modelo_value = '';

// Procesar búsqueda si se envió el formulario
$resultados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])) {
    $id_ag = isset($_POST['s_id_ag']) ? intval($_POST['s_id_ag']) : 0;
    $id_marca = isset($_POST['s_id_marca']) ? intval($_POST['s_id_marca']) : 0;
    $id_color = isset($_POST['s_id_color']) ? intval($_POST['s_id_color']) : 0;
    $modelo = isset($_POST['modelo']) ? trim($_POST['modelo']) : '';
    
    // Guardar valores para repoblar el formulario
    $id_ag_value = $id_ag;
    $id_marca_value = $id_marca;
    $id_color_value = $id_color;
    $modelo_value = htmlspecialchars($modelo);
    
    $query = "SELECT a.*, ag.clase, m.marca, c.color 
              FROM t_activo a
              JOIN t_activo_general ag ON a.id_ag = ag.id_ag
              JOIN t_marca m ON a.id_marca = m.id_marca
              JOIN t_color c ON a.id_color = c.id_color
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($id_ag > 0) {
        $query .= " AND a.id_ag = ?";
        $params[] = $id_ag;
        $types .= 'i';
    }
    
    if ($id_marca > 0) {
        $query .= " AND a.id_marca = ?";
        $params[] = $id_marca;
        $types .= 'i';
    }
    
    if ($id_color > 0) {
        $query .= " AND a.id_color = ?";
        $params[] = $id_color;
        $types .= 'i';
    }
    
    if (!empty($modelo)) {
        $query .= " AND a.modelo LIKE ?";
        $params[] = "%$modelo%";
        $types .= 's';
    }
    
    $query .= " ORDER BY ag.clase, m.marca, a.modelo";
    
    $stmt = $link->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $resultados = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>TecnoPresta Modelo General</title>
  <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 y Select2 -->
  <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
  <link href="select2/select2.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    .button {
      background-color: #0080FF;
      border: none;
      color: white;
      padding: 15px 32px;
      text-align: center;
      text-decoration: none;
      display: inline-block;
      font-size: 18px;
      margin: 4px 2px;
      cursor: pointer;
      width: 70px;
      text-transform: uppercase;
      letter-spacing: 2px;
      border-radius: 10px;
      transition: all 300ms;
    }
    .search-results {
      max-height: 400px;
      overflow-y: auto;
    }
      .highlighted {
        animation: highlight 2s ease;
        border-left: 4px solid #0d6efd;
      }
      
      @keyframes highlight {
        0% { background-color: rgba(13, 110, 253, 0.1); }
        100% { background-color: transparent; }
      }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="formulario_menu_principal.html">
      <i class="bi bi-building"></i> Tecnopresta
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="collapsibleNavbar">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="inventario_activo.php">
            <i class="bi bi-arrow-left-circle"></i> Volver al Inventario
          </a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="#">
            <i class="bi bi-person-circle"></i> <?php echo $lognombre; ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="gameover.php">
            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container mt-4">
    
<div class="card border-primary mb-4">
  <div class="card-header bg-primary text-white">
    <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>Instrucciones para registrar activos</h5>
  </div>
  <div class="card-body">
    <div class="alert alert-info d-flex align-items-center">
      <i class="bi bi-lightbulb-fill fs-3 me-3"></i>
      <div>
        <h6 class="alert-heading mb-2">¡Sigue estos pasos para registrar tus activos correctamente!</h6>
        <ol class="mb-0">
          <li class="mb-2">Completa <strong>todos los campos de búsqueda</strong> (Tipo de activo, Marca, Color y Modelo)</li>
          <li class="mb-2">Haz clic en <button class="btn btn-sm btn-primary" disabled><i class="bi bi-search me-1"></i>Buscar Modelo</button></li>
          <li class="mb-2">Revisa los resultados en la tabla que aparecerá</li>
          <li>Si no encuentras el modelo:
            <ul>
              <li>El botón <button class="btn btn-sm btn-success" disabled><i class="bi bi-plus-circle me-1"></i>Crear Nuevo Modelo</button> se activará automáticamente</li>
              <li>Al hacer clic, los datos ingresados se transferirán al formulario de creación</li>
            </ul>
          </li>
        </ol>
      </div>
    </div>
    
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card h-100 border-success">
          <div class="card-header bg-success text-white py-2">
            <i class="bi bi-check-circle me-2"></i>¿Qué sucede si encuentro el modelo?
          </div>
          <div class="card-body">
            <p class="mb-0">Podrás seleccionarlo <button class="btn btn-sm btn-outline-primary" disabled>
                    <i class="bi bi-check-circle"></i> Seleccionar</button> de la lista y proceder a registrar las placas y series específicas del activo físico que estás ingresando al sistema.</p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100 border-warning">
          <div class="card-header bg-warning text-dark py-2">
            <i class="bi bi-exclamation-triangle me-2"></i>¿Qué sucede si NO encuentro el modelo?
          </div>
          <div class="card-body">
            <p class="mb-0">El sistema te permitirá crear el nuevo modelo general con los datos que ingresaste, para luego asociarle las placas y series correspondientes.</p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="alert alert-light mt-3 d-flex align-items-center">
      <i class="bi bi-shield-lock fs-4 text-primary me-3"></i>
      <div>
        <h6 class="alert-heading">Protección contra duplicados</h6>
        <p class="mb-0">Nuestro sistema verifica automáticamente para evitar modelos duplicados, garantizando la integridad de tu inventario.</p>
      </div>
    </div>
  </div>
</div>
<form method="post" id="searchForm">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Búsqueda de Modelos Existentes</h5>
        </div>
        <div class="card-body">
            <!-- Clase General del Activo -->
            <div class="form-group mb-3">
                <label class="form-label">Clase General del Activo:</label>
                <select id="s_id_ag" name="s_id_ag" class="form-control select2" required>
                    <option value="0" data-img-src="default">Seleccione: <i class="bi bi-question-circle"></i></option>
                    <?php
                    $regres1 = mysqli_query($link, "SELECT * FROM t_activo_general ORDER BY clase");
                    while ($regr1 = mysqli_fetch_array($regres1)) {
                        $selected = ($id_ag_value == $regr1['id_ag']) ? 'selected' : '';
                        echo '<option value="' . $regr1['id_ag'] . '" data-img-src="img/' . $regr1['imagen'] . '" ' . $selected . '>' . $regr1['clase'] . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Marca de fabricante -->
            <div class="form-group mb-3">
                <label class="form-label">Marca de fabricante:</label>
                <select id="s_id_marca" name="s_id_marca" class="form-control select2" required>
                    <option value="0" data-img-src="default">Seleccione: <i class="bi bi-question-circle"></i></option>
                    <?php
                    $regres = mysqli_query($link, "SELECT * FROM t_marca ORDER BY marca");
                    while ($regr = mysqli_fetch_array($regres)) {
                        $selected = ($id_marca_value == $regr['id_marca']) ? 'selected' : '';
                        echo '<option value="' . $regr['id_marca'] . '" data-img-src="ico/' . $regr['logo'] . '" ' . $selected . '>' . $regr['marca'] . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Color predominante del activo -->
            <div class="form-group mb-3">
                <label class="form-label">Color predominante del activo:</label>
                <select id="s_id_color" name="s_id_color" class="form-control select2" required>
                    <option value="0" data-img-src="default">Seleccione: <i class="bi bi-question-circle"></i></option>
                    <?php
                    $regres2 = mysqli_query($link, "SELECT * FROM t_color ORDER BY color");
                    while ($regr2 = mysqli_fetch_array($regres2)) {
                        $selected = ($id_color_value == $regr2['id_color']) ? 'selected' : '';
                        echo '<option value="' . $regr2['id_color'] . '" data-img-src="ico/' . $regr2['imagen'] . '" ' . $selected . '>' . $regr2['color'] . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Modelo del Activo -->
            <div class="form-group mb-3">
                <label for="modelo" class="form-label">Modelo del Activo</label>
                <input type="text" class="form-control" id="modelo" name="modelo" 
                       value="<?php echo $modelo_value; ?>" aria-describedby="modeloAyuda" required>
                <small id="modeloAyuda" class="form-text text-muted">Ingrese el modelo que desea buscar o crear.</small>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button type="submit" name="buscar" class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar Modelo
                </button>
                <button type="button" id="btnCrear" class="btn btn-success" disabled>
                    <i class="bi bi-plus-circle"></i> Crear Nuevo Modelo
                </button>
            </div>
        </div>
    </div>
</form>
  
  <!-- Resultados de Búsqueda -->
  <?php if (!empty($resultados)): ?>
  <div class="card mt-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0">Resultados de Búsqueda</h5>
    </div>
    <div class="card-body search-results">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>Id</th>
              <th>Clase</th>
              <th>Marca</th>
              <th>Modelo</th>
              <th>Color</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resultados as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['id_activo']) ?></td>
              <td><?= htmlspecialchars($row['clase']) ?></td>
              <td><?= htmlspecialchars($row['marca']) ?></td>
              <td><?= htmlspecialchars($row['modelo']) ?></td>
              <td><?= htmlspecialchars($row['color']) ?></td>
              <td>
                  <a href="formulario_agregar_placa_serie_v2.php?idx=<?= $row['id_activo'] ?>" 
                     class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check-circle"></i> Seleccionar
                  </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <div class="alert alert-info mt-4">
    No se encontraron modelos que coincidan con los criterios de búsqueda. 
    Puede proceder a crear un nuevo modelo.
  </div>
  <?php endif; ?>
</div>

<!-- Scripts -->
<script src="js/jquery-3.7.1.min.js"></script>
<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="select2/select2.min.js"></script>
<script>
  $(document).ready(function() {
    // Inicializar Select2
    $('.select2').select2({
      templateResult: formatOption,
      templateSelection: formatOption
    });

    function formatOption(option) {
      if (!option.id) return option.text;
      var imgSrc = $(option.element).data('img-src');
      var $option;

      if (imgSrc === "default") {
        $option = $(
          '<span><i class="bi bi-question-circle" style="margin-right: 10px;"></i>' + option.text + '</span>'
        );
      } else {
        $option = $(
          '<span><img src="' + imgSrc + '" class="img-fluid" style="width: 20px; margin-right: 10px;" />' + option.text + '</span>'
        );
      }
      return $option;
    }

    // Validar formulario antes de buscar
    $('#searchForm').on('submit', function(e) {
      let valid = true;
      $(this).find('[required]').each(function() {
        if (!$(this).val() || $(this).val() === '0') {
          valid = false;
          $(this).addClass('is-invalid');
        } else {
          $(this).removeClass('is-invalid');
        }
      });
      
      if (!valid) {
        e.preventDefault();
        alert('Por favor complete todos los campos requeridos.');
      }
    });

    // Habilitar botón de crear si no hay resultados
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($resultados)): ?>
      $('#btnCrear').prop('disabled', false);
    <?php endif; ?>

    // Prevenir apóstrofe en el campo modelo
    $('#modelo').on('keypress', function(event) {
      if (event.charCode === 39) {
        event.preventDefault();
      }
    });

    // Manejar selección de modelo existente
    $('.seleccionar-modelo').on('click', function() {
      const idActivo = $(this).data('id');
      // Redirigir o mostrar detalles del activo seleccionado
      alert('Has seleccionado el modelo con ID: ' + idActivo);
      // Aquí puedes redirigir a otra página o mostrar más detalles
    });

    // CORRECCIÓN COMPLETA: Manejar creación de nuevo modelo
    $('#btnCrear').on('click', function () {
        // Obtener valores de los SELECT principales
        const id_ag = $('#s_id_ag').val();
        const id_marca = $('#s_id_marca').val();
        const id_color = $('#s_id_color').val();
        const modelo = $('#modelo').val();
        
        // DEBUG: Verificar valores obtenidos
        console.log('Valores obtenidos del formulario:');
        console.log('ID AG:', id_ag);
        console.log('ID Marca:', id_marca);
        console.log('ID Color:', id_color);
        console.log('Modelo:', modelo);
    
        // Validación básica
        if (id_ag === '0' || id_marca === '0' || id_color === '0' || modelo.trim() === '') {
            alert('Por favor complete todos los campos antes de crear el modelo.');
            return;
        }
    
        // Obtener textos para mostrar
        const claseText = $('#s_id_ag option:selected').text();
        const marcaText = $('#s_id_marca option:selected').text();
        const colorText = $('#s_id_color option:selected').text();
    
        // ✅ CORRECCIÓN: Llenar los campos del modal CORRECTAMENTE
        $('#modal_clase').val(claseText);
        $('#modal_marca').val(marcaText);
        $('#modal_color').val(colorText);
        $('#modal_modelo').val(modelo);
        
        // ✅ CORRECCIÓN CRÍTICA: Asignar valores a los campos hidden DEL MODAL
        $('#modal_hidden_id_ag').val(id_ag);
        $('#modal_hidden_id_marca').val(id_marca);
        $('#modal_hidden_id_color').val(id_color);
    
        // DEBUG: Verificar que los hidden se llenaron
        console.log('Valores asignados a hidden del modal:');
        console.log('ID AG:', $('#modal_hidden_id_ag').val());
        console.log('ID Marca:', $('#modal_hidden_id_marca').val());
        console.log('ID Color:', $('#modal_hidden_id_color').val());
        console.log('Modelo en modal:', $('#modal_modelo').val());
    
        // Mostrar el modal
        const modal = new bootstrap.Modal(document.getElementById('modalCrearModelo'));
        modal.show();
    });

    // ✅ CORRECCIÓN: Verificar valores cuando el modal se muestra
    $('#modalCrearModelo').on('shown.bs.modal', function () {
        console.log('Modal MOSTRADO - Valores finales:');
        console.log('ID AG:', $('#modal_hidden_id_ag').val());
        console.log('ID Marca:', $('#modal_hidden_id_marca').val());
        console.log('ID Color:', $('#modal_hidden_id_color').val());
        console.log('Modelo:', $('#modal_modelo').val());
        
        // Verificar que todos los campos tengan name
        console.log('Campo modelo tiene name?:', $('#modal_modelo').attr('name') ? 'SÍ' : 'NO');
    });

    // Auto-scroll a resultados si existen
    <?php if (!empty($resultados)): ?>
    setTimeout(function() {
      // Crear notificación flotante
      const notification = $('<div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 1000;">' +
                            '<i class="bi bi-check-circle me-2"></i> Se encontraron <?= count($resultados) ?> resultados' +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                            '</div>').appendTo('body');
      
      // Desplazamiento suave a la tabla de resultados
      $('html, body').animate({
        scrollTop: $('.search-results').offset().top - 20
      }, 800, function() {
        // Resaltar la tabla después del desplazamiento
        $('.search-results').addClass('highlighted');
        setTimeout(function() {
          $('.search-results').removeClass('highlighted');
        }, 2000);
      });
      
      // Cerrar automáticamente la notificación después de 5 segundos
      setTimeout(function() {
        notification.alert('close');
      }, 5000);
    }, 300);
    <?php endif; ?>

    // Código para abrir automáticamente el modal si no hay resultados
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($resultados)): ?>
      // Ya habilitamos el botón arriba, ahora lo activamos después de un delay
      setTimeout(function() {
          $('#btnCrear').trigger('click');
      }, 500);
    <?php endif; ?>
  });
</script>

<!-- Modal Crear Nuevo Modelo - VERSIÓN CORREGIDA (CON CAMPO MODELO CON NAME) -->
<div class="modal fade" id="modalCrearModelo" tabindex="-1" aria-labelledby="modalCrearModeloLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="crear_modelo.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCrearModeloLabel">Confirmar Creación de Modelo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Clase General:</label>
          <input type="text" class="form-control" id="modal_clase" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Marca:</label>
          <input type="text" class="form-control" id="modal_marca" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Color:</label>
          <input type="text" class="form-control" id="modal_color" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Modelo:</label>
          <!-- ✅ CORRECCIÓN CRÍTICA: Agregar name="modelo" para que se envíe al PHP -->
          <input type="text" class="form-control" id="modal_modelo" name="modelo" readonly>
        </div>
        
        <!-- ✅ CORRECCIÓN CRÍTICA: Campos hidden con IDs ÚNICOS y NAMES correctos -->
        <input type="hidden" name="id_ag" id="modal_hidden_id_ag" value="">
        <input type="hidden" name="id_marca" id="modal_hidden_id_marca" value="">
        <input type="hidden" name="id_color" id="modal_hidden_id_color" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Confirmar Creación</button>
      </div>
    </form>
  </div>
</div>


</body>
</html>