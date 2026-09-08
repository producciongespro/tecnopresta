<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/*$tienellave = ($_SESSION['tipo']==1 or $_SESSION['tipo']==2 or $_SESSION['tipo']==3 or $_SESSION['tipo']==4);
if ($tienellave == false){
echo '<script language = javascript>
alert("No tienes permisos")
self.location = "index.html"
</script>';
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

$logcodigo = $usuario_azure['codigoPresu'];

$codigo = '';
$fondos = '';
$subsistema_id = 0;
$modulo_id = 0;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = $_POST["codigo"];
    $fondos = $_POST["fondos"];
    $subsistema_id = intval($_POST['subsistema_id'] ?? 0);
    $modulo_id = intval($_POST['modulo_id'] ?? 0);
}

// ==== CONSTRUIR RUTA DE REGRESO =====
$ruta_regreso = 'navegar.php?ruta=formulario_agregar_ubicacion_general_n.php';
if ($subsistema_id > 0 && $modulo_id > 0) {
    $ruta_regreso .= '&subsistema_id=' . $subsistema_id . '&modulo_id=' . $modulo_id;
}
?>
<!doctype html> 
<html lang="es">
  <head>
    <!-- Required meta tags --> 
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- Nueva Identidad Gráfica Gobierno de Costa Rica CSS -->
    <link rel="stylesheet" href="assets/css/nueva-identidad.css">
    <link rel="stylesheet" href="css/formulario_menu_principal.css" />

    <!-- ICONOS -->
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <title>TecnoPresta</title>
  </head>
  <body class="layout-page">
    <?php include 'partials/header.php'; ?>
    <main class="flex-grow-1">

      <div class="container">
        <h1 class="my-3">Ubicación o resguardo de activos</h1>

        <div class="row">

              <div class="col-md-6">
                  <!-- Contenido de la primera sección -->
                  <div class="p-3 border bg-light">Activos sin ubicar: <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-down" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1"/>
                    </svg> puede mover el activo haciendo clic sobre el ítem y devolverlo de la misma forma. Los cambios no se efectuarán hasta que se haga clic en el botón de actualizar datos.</div>
              </div>
              <div class="col-md-6">
              <form>
                <input type="hidden" id="subsistema_id" name="subsistema_id" value="<?= $subsistema_id ?>">
                <input type="hidden" id="modulo_id" name="modulo_id" value="<?= $modulo_id ?>">
                <input type="hidden" id="id_fondos" name="id_fondos" value="<?php echo $fondos;?>">
                <input type="hidden" id="codigo" name="codigo" value="<?php echo $codigo;?>">
                
                  <select class="form-select w-50" id="id_lugar" name="id_lugar" required>
                      <option value="" selected disabled>Selecciona un lugar</option>
                          <?php
                          $sql = "SELECT id_lugar, lugar FROM t_lugar 
                                  WHERE activo = 1
                                  ORDER BY id_lugar";

                                  $regGD = mysqli_query($link, $sql) or die(mysqli_error($link));

                                      while ($rege = mysqli_fetch_array($regGD)) {
                                            $id_gdd = $rege['id_lugar'];
                                            $etiqueta = $rege['lugar'];

                                              echo "<option value='$id_gdd'>$etiqueta</option>";
                                        }
                            ?>
                    </select>
            

                <button type="submit" id="mostrarBtn" class="btn btn-secondary my-3">Mostrar</button>
  
            </form>
              </div>
          

            <div class="col-md-4 mt-3">

        </div> <!-- Cierre del row -->
        <div class="d-grid gap-2 col-12 mx-auto">
          <button id="enviarDatosBtn" class="btn btn-success" onclick="enviarDatos()">Actualizar los Datos</button>
        </div>
        <div id="respuesta">

        
        </div>
        
      </div> <!-- Cierre del container -->

    </main>
    <!-- Botón flotante Volver -->
    <a href="<?= htmlspecialchars($ruta_regreso) ?>" class="btn-disponibilidad" 
        style="bottom: 100px;" data-tooltip="Regresar">
          <i class="bi bi-arrow-left-circle-fill"></i>
    </a>
    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <!-- JS para intercambios de activos y clasificación -->

  <script>
    $(document).ready(function() {
      // Manejar el evento click del botón "Mostrar"
      $("#mostrarBtn").click(function(e) {
        e.preventDefault(); // Evitar la recarga de la página por defecto

        // Obtener los valores del formulario
        var id_fondos = $("#id_fondos").val();
        var codigo = $("#codigo").val();
        var id_lugar = $("#id_lugar").val(); // Agregar el campo "id_lugar"

        // Enviar los datos al archivo PHP utilizando AJAX
        $.post("traer_tablas_activo_n.php", { id_fondos: id_fondos, codigo: codigo, id_lugar: id_lugar }, function(data) {
          // Mostrar la respuesta en el div "respuesta"
          $("#respuesta").html(data);
        });
      });
    });
  </script>

  <script>
    $(document).ready(function() {
      // Delegación de eventos para la tabla izquierda
      $("#respuesta").on("click", "#tabla-izquierda tbody tr", function() {
        var fila = $(this);
        var id = fila.attr("data-id");
        var nombre = fila.find("td:eq(1)").text();

        // Agregar la fila a la tabla de la derecha
        $("#tabla-derecha tbody").append('<tr data-id="' + id + '"><td>' + id + '</td><td>' + nombre + '</td></tr>');

        // Eliminar la fila de la tabla de la izquierda
        fila.remove();
      });

      // Delegación de eventos para la tabla derecha
      $("#respuesta").on("click", "#tabla-derecha tbody tr", function() {
        var fila = $(this);
        var id = fila.attr("data-id");
        var nombre = fila.find("td:eq(1)").text();

        // Agregar la fila a la tabla de la izquierda
        $("#tabla-izquierda tbody").append('<tr data-id="' + id + '"><td>' + id + '</td><td>' + nombre + '</td></tr>');

        // Eliminar la fila de la tabla de la derecha
        fila.remove();
      });
    });
  </script>

  <script>
    function enviarDatos() {
        var activosIzquierda = [];
        var activosDerecha = [];

        // Recopila los datos de la tabla izquierda
        $("#tabla-izquierda tbody tr").each(function () {
            var idplaca = $(this).find('td:eq(0)').text();
            var nombre = $(this).find('td:eq(1)').text();
            activosIzquierda.push({ idplaca: idplaca, nombre: nombre });
        });

        // Recopila los datos de la tabla derecha
        $("#tabla-derecha tbody tr").each(function () {
            var idplaca = $(this).find('td:eq(0)').text();
            var nombre = $(this).find('td:eq(1)').text();
            activosDerecha.push({ idplaca: idplaca, nombre: nombre });
        });

        // Obtener los valores de los input
        var id_fondos = $("#id_fondos").val();
        var codigo = $("#codigo").val();
        var id_lugar = $("#id_lugar").val();
        var subsistema_id = $("#subsistema_id").val();
        var modulo_id = $("#modulo_id").val();

        // Crear un objeto con los datos a enviar
        var datos = {
            activosIzquierda,
            activosDerecha,
            id_fondos,
            codigo,
            id_lugar,
            subsistema_id,
            modulo_id
        };

        // Enviar los datos al archivo PHP utilizando AJAX
        $.ajax({
        type: "POST",
        url: "procesar_datos_tablas_n.php",
        data: JSON.stringify(datos),
        contentType: "application/json",
        success: function (data) {
            var response = JSON.parse(data);

            if (response.redirect) {
                // Muestra un mensaje de éxito o cualquier otro manejo necesario
                console.log("Respuesta del servidor:", response.message);

                // Realiza la redirección
                window.location.href = response.redirect;
            } else {
                // Maneja la respuesta de error o cualquier otro caso
                console.log("Respuesta del servidor:", response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error("Error al enviar datos:");
            console.error("Estado de la solicitud: " + status);
            console.error("Error: " + error);
            console.error("Respuesta del servidor:", xhr.responseText);
        }
    });

    }
  </script>
  <?php include 'partials/footer.php'; ?>
  </body>
</html>