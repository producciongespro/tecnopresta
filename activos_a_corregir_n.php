<?php
if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
/*
$tienellave = in_array($_SESSION['tipo'], [1, 2, 3, 4]);
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
/*
$logusuario = $_SESSION['cedula'];
$lognombre = $_SESSION['nombre'];
$logtipo = $_SESSION['tipo'];
$logcodigo = $_SESSION['codigo'];
*/
require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}
$logcodigo = $usuario_azure['codigoPresu'];

// Verificar que el parámetro POST está presente
if (!isset($_POST['id_fondos'])) {
    echo "Error: id_fondos no está definido.";
    exit();
}
$id_fondos = $_POST['id_fondos'];

// Ejecutar la consulta con prepared statement
$sql = "SELECT Ta.id_activo, Tg.clase, Tm.marca, Ta.modelo, Tc.color, Tp.id_placa, Tp.placa, Tp.serial, Tp.id_estado, Tp.codigo, Tp.activo
    FROM t_activo Ta
    INNER JOIN t_marca Tm ON Ta.id_marca = Tm.id_marca 
    INNER JOIN t_color Tc ON Ta.id_color = Tc.id_color
    INNER JOIN t_placa Tp ON Ta.id_activo = Tp.id_activo
    INNER JOIN t_activo_general Tg ON Ta.id_ag = Tg.id_ag 
    WHERE Tp.codigo = ? AND Tp.id_fondos = ?
    ORDER BY Tp.placa ASC";

$stmt = mysqli_prepare($link, $sql);
if (!$stmt) {
    echo "Error en la preparación de la consulta: " . mysqli_error($link);
    exit();
}

mysqli_stmt_bind_param($stmt, "ss", $logcodigo, $id_fondos);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) > 0) {
    echo '<table class="activos-table">
        <thead>
            <tr>
                <th class="th-check"><input type="checkbox" id="selectAll" /></th>
                <th class="th-activo">Activo</th>
                <th class="th-marca">Marca</th>
                <th class="th-modelo">Modelo</th>
                <th class="th-color">Color</th>
                <th class="th-placa">Placa</th>
                <th class="th-serial">Serial</th>
            </tr>
        </thead>
        <tbody class="BusquedaRapida">';

    while ($activos = mysqli_fetch_array($resultado)) {
        echo '<tr>
            <td class="td-check"><input type="checkbox" class="selectall" name="idsplacas[]" value="' . $activos['id_placa'] . '"/></td>
            <td class="td-activo">' . htmlspecialchars($activos['clase']) . '</td>
            <td class="td-marca">' . htmlspecialchars($activos['marca']) . '</td>
            <td class="td-modelo">' . htmlspecialchars($activos['modelo']) . '</td>
            <td class="td-color">' . htmlspecialchars($activos['color']) . '</td>
            <td class="td-placa">' . htmlspecialchars($activos['placa']) . '</td>
            <td class="td-serial">' . htmlspecialchars($activos['serial']) . '</td>
        </tr>';
    }

    echo '</tbody></table>';
} else {
    echo '<p>No se encontraron datos.</p>';
}

mysqli_stmt_close($stmt);
mysqli_close($link);
?>

