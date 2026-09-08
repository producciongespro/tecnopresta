<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
$tienellave = in_array($_SESSION['tipo'], [1]);
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
}

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {

}

// === Verificar sesión de usuario Azure ===
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}

/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];
*/
// Contexto de navegación recibido vía campos ocultos del paso anterior
$subsistema_id = isset($_POST['subsistema_id']) ? intval($_POST['subsistema_id']) : 0;
$modulo_id     = isset($_POST['modulo_id']) ? intval($_POST['modulo_id']) : 0;
$formulario_id = isset($_POST['formulario_id']) ? intval($_POST['formulario_id']) : 0;

// ==== CONSTRUIR RUTA DE REGRESO (paso anterior conservando el contexto) =====
$ruta_regreso = 'navegar.php?ruta=in_formulario_redistribuir_n.php';
if ($subsistema_id > 0) {
    $ruta_regreso .= '&subsistema_id=' . $subsistema_id
        . '&modulo_id=' . $modulo_id
        . '&formulario_id=' . $formulario_id;
}

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST["input1"], $_POST["input2"], $_POST["fondos"])) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Debe seleccionar primero el fondo y los centros'];
    header("Location: navegar.php?ruta=in_formulario_redistribuir_n.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo_origen = $_POST["input1"];
    $codigo_destino = $_POST["input2"];
    $fondos = $_POST["fondos"];

}
?>
<!doctype html>
<html lang="es">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <!-- jQuery -->
    <script src="js/jquery-3.7.1.min.js"></script>
    
    <title>TecnoPresta</title>
  </head>
  <body class="layout-page">
    <?php include 'partials/header.php'; ?>
    
    <main class="contenido-principal">

      <div class="container">
        <h2 class="my-4 text-center">Activos a redistribuir</h2>
        <form id="form-redistribucion" action="in_redistribucion_realizada_n.php" method="POST">
          <input type="hidden" name="id_placas" id="id-placas" value="">
          <input type="hidden" name="cod_o" id="cod_o" value="<?php echo $codigo_origen;?>">
          <input type="hidden" name="cod_d" id="cod_d" value="<?php echo $codigo_destino;?>">
          <input type="hidden" name="fondos" id="fondos_id" value="<?php echo $fondos;?>">
          <input type="hidden" name="subsistema_id" value="<?php echo $subsistema_id; ?>">
          <input type="hidden" name="modulo_id" value="<?php echo $modulo_id; ?>">
          <input type="hidden" name="formulario_id" value="<?php echo $formulario_id; ?>">
          <div class="row">
            <!-- Primera columna -->
            <div class="col-md-5">
              <div class="p-3 bg-light border">
                <h5>Activos del centro de origen 
                  <?php 
                    if (strtoupper(trim($codigo_origen)) === 'LIMBO') {
                        echo '<span class="badge bg-warning text-dark">⚠️ Sin centro asignado (LIMBO)</span>';
                    } else {
                        echo htmlspecialchars($codigo_origen);
                    }
                  ?>
                </h5>
                <div class="table-responsive">
                  <table id="tabla-origen" class="table table-hover table-bordered">
                    <thead class="table-dark">
                      <tr>
                        <th>ID Placa</th>
                        <th>Placa</th>
                        <th>Serial</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      // Detectar si el origen es LIMBO (activos sin centro asignado)
                      $es_limbo = (strtoupper(trim($codigo_origen)) === 'LIMBO');

                      if ($es_limbo) {
                          // Consulta para activos en el limbo: codigo vacío o NULL
                          $sql = "SELECT id_placa, placa, serial
                                  FROM t_placa
                                  WHERE (codigo = '' OR codigo IS NULL) AND id_fondos = ?
                                  ORDER BY placa";
                          $stmt = mysqli_prepare($link, $sql);
                          if ($stmt) {
                              mysqli_stmt_bind_param($stmt, "i", $fondos);
                              mysqli_stmt_execute($stmt);
                              $resultado = mysqli_stmt_get_result($stmt);
                          }
                      } else {
                          // Consulta normal por código de centro
                          $sql = "SELECT id_placa, placa, serial
                                  FROM t_placa
                                  WHERE codigo = ? AND id_fondos = ?
                                  ORDER BY placa";
                          $stmt = mysqli_prepare($link, $sql);
                          if ($stmt) {
                              // "si": codigo es STRING, fondos es INTEGER
                              mysqli_stmt_bind_param($stmt, "si", $codigo_origen, $fondos);
                              mysqli_stmt_execute($stmt);
                              $resultado = mysqli_stmt_get_result($stmt);
                          }
                      }

                      if ($stmt) {
                          if (mysqli_num_rows($resultado) > 0) {
                              while ($fila = mysqli_fetch_assoc($resultado)) {
                                  echo '<tr>';
                                  echo '<td>' . htmlspecialchars($fila['id_placa']) . '</td>';
                                  echo '<td>' . htmlspecialchars($fila['placa']) . '</td>';
                                  echo '<td>' . htmlspecialchars($fila['serial']) . '</td>';
                                  echo '</tr>';
                              }
                          } else {
                              echo '<tr><td colspan="3" class="text-center">No se encontraron registros.</td></tr>';
                          }
                      } else {
                          echo '<tr><td colspan="3" class="text-center text-danger">Error en la consulta.</td></tr>';
                      }
                      ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <!-- Separación -->
            <div class="col-md-2">
                  <div class="card">
                    <div class="card-header text-center">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-right" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1 11.5a.5.5 0 0 0 .5.5h11.793l-3.147 3.146a.5.5 0 0 0 .708.708l4-4a.5.5 0 0 0 0-.708l-4-4a.5.5 0 0 0-.708.708L13.293 11H1.5a.5.5 0 0 0-.5.5m14-7a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 1 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L2.707 4H14.5a.5.5 0 0 1 .5.5"/>
                      </svg>
                    </div>
                    <div class="card-body">
                      <h5 class="card-title">Cómo Mover Registros entre Listas</h5>
                      <p class="card-text">
                        Puede hacer clic en cada registro para moverlo de una lista a la otra. De igual manera, puede hacer clic nuevamente para regresarlo a la lista original. Esta funcionalidad le permite gestionar los registros de manera eficiente y flexible.
                      </p>
                      <ul>
                        <li>Haga clic en un registro para moverlo a la lista de destino.</li>
                        <li>Haga clic nuevamente en el registro en la lista de destino para regresarlo a la lista original.</li>
                        <li>Haga clic en el bot&oacute;n Efectuar redistribuci&oacute;n para finalizar el proceso.</li>
                      </ul>
                    </div>
                  </div>          
            </div>
            <!-- Segunda columna -->
            <div class="col-md-5">
              <div class="p-3 bg-light border">
                <h5>Activos a otorgar al centro destino <?php echo $codigo_destino;?></h5>
                <div class="table-responsive">
                  <table id="tabla-destino" class="table table-striped table-bordered">
                    <thead class="table-dark">
                      <tr>
                        <th>ID Placa</th>
                        <th>Placa</th>
                        <th>Serial</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Esta tabla se llenará dinámicamente -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div> <!-- Cierre del row-->
        </form>
      </div> <!-- Cierre del container -->
      
      <!-- Botón flotante Efectuar redistribución -->
      <button type="submit" class="btn-guardar-flotante" form="form-redistribucion"
          style="bottom: 170px;" data-tooltip="Efectuar redistribución">
          <i class="bi bi-arrow-right-circle"></i>
      </button>

      <!-- Botón flotante Volver -->
        <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
            style="bottom: 100px;" data-tooltip="Regresar">
                <i class="bi bi-arrow-left-circle-fill"></i>
        </a>
    </main>
  
  <?php include 'partials/footer.php'; ?>

    <!-- Modal -->
    <div class="modal fade" id="corregirModal" tabindex="-1" aria-labelledby="corregirModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="corregirModalLabel">Corregir Placa y Serie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="corregirForm" method="POST" action="in_actualizar_placa_serie_n.php">
                        <div class="mb-3">
                            <label for="idPlaca" class="form-label">ID</label>
                            <input type="text" class="form-control" id="idPlaca" name="idPlaca">
                        </div>
                        <div class="mb-3">
                            <label for="placa" class="form-label">Placa</label>
                            <input type="text" class="form-control" id="placa" name="placa">
                        </div>
                        <div class="mb-3">
                            <label for="serial" class="form-label">Serie</label>
                            <input type="text" class="form-control" id="serial" name="serial">
                        </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="buscarBtn">Buscar</button>
                    <button type="submit" class="btn btn-success" id="buscarBtn">Actualizar</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

    <!-- JS para intercambios de activos y clasificación -->

<script>
// Función para actualizar el input hidden con los ID de la tabla de destino
function actualizarInputHidden() {
  const tablaDestino = document.querySelector("#tabla-destino tbody");
  const filas = tablaDestino.querySelectorAll("tr");
  const ids = Array.from(filas).map(fila => fila.cells[0].textContent.trim());
  
  // Actualizamos el valor del input hidden con la lista de IDs
  const idsString = ids.join(",");
  document.querySelector("#id-placas").value = idsString;
  
  // Mostrar en consola para verificar
  console.log("Valor de id_placas:", idsString);
}

// Manejar clics en las filas de la tabla de origen
document.querySelector("#tabla-origen tbody").addEventListener("click", function (event) {
  if (event.target.tagName === "TD") {
    const fila = event.target.parentElement; // Fila clicada
    const tablaDestino = document.querySelector("#tabla-destino tbody");
    tablaDestino.appendChild(fila); // Mover la fila a la tabla de destino
    actualizarInputHidden(); // Actualizar el input hidden
  }
});

// Manejar clics en las filas de la tabla de destino
document.querySelector("#tabla-destino tbody").addEventListener("click", function (event) {
  if (event.target.tagName === "TD") {
    const fila = event.target.parentElement; // Fila clicada
    const tablaOrigen = document.querySelector("#tabla-origen tbody");
    tablaOrigen.appendChild(fila); // Mover la fila de regreso a la tabla de origen
    actualizarInputHidden(); // Actualizar el input hidden
  }
});
</script>
<!-- JS para buscar la placa y la serie -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('buscarBtn').addEventListener('click', function () {
            // Obtener el valor del input idPlaca
            const idPlaca = document.getElementById('idPlaca').value;

            // Validar que no esté vacío
            if (idPlaca.trim() === '') {
                alert('Por favor, ingrese un ID válido.');
                return;
            }

            // Hacer la solicitud AJAX
            fetch(`in_buscar_placa_serie_n.php?id=${encodeURIComponent(idPlaca)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error); // Mostrar error si no se encuentra el registro
                    } else {
                        // Llenar los campos con los datos obtenidos
                        document.getElementById('placa').value = data.placa;
                        document.getElementById('serial').value = data.serial;
                    }
                })
                .catch(error => {
                    console.error('Error en la solicitud:', error);
                    alert('Ocurrió un error al buscar los datos.');
                });
        });
    });
</script>

  </body>
</html>