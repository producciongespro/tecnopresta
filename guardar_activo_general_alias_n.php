<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
/*
if (!$_SESSION){
echo '<script language = javascript>
alert("usuario no autenticado")
self.location = "index.html"
</script>';
}
*/
require_once("conexion.php");
require_once __DIR__ . '/funciones_modelos_n.php';
$link = $mysqli;
 

if (mysqli_connect_errno())

{

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

// Inicia detecci&oacute;n de campos vacios y el pase de variables 

$post = (isset($_POST['clase']) && !empty($_POST['clase'])) &&
        (isset($_POST['marca']) && !empty($_POST['marca'])) &&
		(isset($_POST['modelo']) && !empty($_POST['modelo'])) &&
		(isset($_POST['alias']) && !empty($_POST['alias'])) &&
        (isset($_POST['color']) && !empty($_POST['color']));

if (isset($_POST['numero']) && !empty($_POST['numero'])) {
	$numero = $_POST['numero'];
} else {
	$numero=0;
}

if($post)
{

$id_ag = $_POST['clase'];
$id_marca = $_POST['marca'];
$modelo = $_POST['modelo'];
$id_color = $_POST['color'];
$alias_id = $_POST['alias'];
$logcodigo = $_SESSION['codigo'] ?? '';

	          $stmtCheck = $link->prepare("SELECT id_activo FROM t_activo WHERE modelo = ?");
                  $stmtCheck->bind_param("s", $modelo);
                  $stmtCheck->execute();
                  $stmtCheck->store_result();

		  if($stmtCheck->num_rows >= 1){
                    $stmtCheck->close();
			echo '<script language = javascript>
		        alert("El modelo ya existe en la lista")
		        self.location = "formulario_agregar_activo.php"
		        </script>';
	          } else {
                    $stmtCheck->close();
                    // Obtener o crear el modelo en el catalogo maestro (t_modelos)
                    $id_modelo = obtenerOCrearModelo($link, $modelo, $logcodigo);

                    // El alias_id vive en t_placa (por placa), no en t_activo.
                    // Insertar el activo solo con las columnas reales de t_activo.
                    $stmtIns = $link->prepare("INSERT INTO t_activo (id_ag, id_marca, modelo, id_color, modelo_id) VALUES (?, ?, ?, ?, ?)");
                    $stmtIns->bind_param("iisii", $id_ag, $id_marca, $modelo, $id_color, $id_modelo);
                    $stmtIns->execute();
                    $stmtIns->close();
			echo '<script language = javascript>
		        alert("Guardado correctamente")
		        self.location = "formulario_agregar_activo.php"
		        </script>';
		  } // Fin del if interno
  
} else {

			echo"<script type=\"text/javascript\">
                        alert(\"Debe completar todos los campos\");
                        window.location=\"formulario_agregar_activo.php\"
                        </script>";
} //Cierre del if principal
?>
