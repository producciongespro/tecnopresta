<?php
session_start();
$tienellave = ($_SESSION['tipo']==1 or $_SESSION['tipo']==2 or $_SESSION['tipo']==3 or $_SESSION['tipo']==4);
if ($tienellave == false){
echo '<script language = javascript>
alert("No tienes permisos")
self.location = "index.html"
</script>';
}
require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno())

{

echo "Error de conexion a mysql: " . mysqli_connect_error();

}

if (!mysqli_set_charset($link, "utf8")) {
    	echo "Error cargando el conjunto de caracteres utf8";
} else {

}
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = $_POST["codigo"];
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
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <script src="js/jquery-3.7.1.min.js"></script>
    <title>TecnoPresta</title>
  </head>
  <body>
    <nav class="navbar navbar-expand-md bg-dark navbar-dark">
      <img src="img/logodelgobierno.png" width="45" height="30" alt="" loading="lazy">
      <a class="navbar-brand" href="formulario_menu_principal.html">Tecnopresta</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="collapsibleNavbar">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="formulario_agregar_ubicacion_general.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-reply-all" viewBox="0 0 16 16">
                <path d="M8.098 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.7 8.7 0 0 0-1.921-.306 7 7 0 0 0-.798.008h-.013l-.005.001h-.001L8.8 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L4.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028zM9.3 10.386q.102 0 .223.006c.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96z"/>
                <path d="M5.232 4.293a.5.5 0 0 0-.7-.106L.54 7.127a1.147 1.147 0 0 0 0 1.946l3.994 2.94a.5.5 0 1 0 .593-.805L1.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028 4.012-2.954a.5.5 0 0 0 .106-.699"/>
                </svg> Seleccionar Origen de Presupuesto</a>
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



    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="bootstrap5/js/bootstrap.bundle.min.js"></script>

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
        $.post("traer_tablas_activo.php", { id_fondos: id_fondos, codigo: codigo, id_lugar: id_lugar }, function(data) {
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

    // Crear un objeto con los datos a enviar
    var datos = {
        activosIzquierda,
        activosDerecha,
        id_fondos,
        codigo,
        id_lugar
    };

    // Enviar los datos al archivo PHP utilizando AJAX
    $.ajax({
    type: "POST",
    url: "procesar_datos_tablas.php",
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

  </body>
</html>