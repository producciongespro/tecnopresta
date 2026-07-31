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

require_once __DIR__ . '/usuarioAzure.php';
$usuario_azure = obtenerUsuarioSesion();

if (!$usuario_azure) {
    header("Location: index.html");
    exit();
}


require_once("conexion.php");
$link = $mysqli;
if (mysqli_connect_errno()) {
    echo "Error de conexion a mysql: " . mysqli_connect_error();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
}

if (isset($_POST['fondos'])) {
    $fondos = intval($_POST['fondos']);
    $codigo_centro = trim($_POST['codigo_centro'] ?? '');
    
    // Construir la consulta con filtros opcionales
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($fondos != 0) {
        $where_conditions[] = "Tp.id_fondos = ?";
        $params[] = $fondos;
        $types .= 'i';
    }
    
    if ($codigo_centro !== '') {
        $where_conditions[] = "Tp.codigo = ?";
        $params[] = $codigo_centro;
        $types .= 's';
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql = "SELECT Tp.id_placa, Tp.placa, Tp.serial, Tp.id_activo, Tp.id_fondos
            FROM t_placa Tp
            $where_clause
            ORDER BY Tp.placa ASC";
    
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        echo "Error en la preparación de la consulta: " . mysqli_error($link);
        mysqli_close($link);
        exit();
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $consulta = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($consulta) > 0) {
        echo '<table class="table table-hover">
            <thead>
                <tr>
                    <th>Seleccionar</th>
                    <th>Placa</th>
                    <th>Serial</th>
                    <th>ID Activo Actual</th>
                    <th>Nuevo ID Activo</th>
                    <th>ID Fondo Actual</th>
                    <th>Nuevo ID Fondo</th>
                </tr>
            </thead>
            <tbody class="BusquedaRapida">';

        while ($activo = mysqli_fetch_array($consulta)) {
            echo '<tr>
                <td><input type="checkbox" name="idsplacas[]" value="' . $activo['id_placa'] . '"/></td>
                <td>' . $activo['placa'] . '</td>
                <td>' . $activo['serial'] . '</td>
                <td>' . $activo['id_activo'] . '</td>
                <td>
                    <input type="number" class="form-control" 
                           name="nuevo_id_activo[' . $activo['id_placa'] . ']" 
                           value="' . $activo['id_activo'] . '">
                </td>
                <td>' . $activo['id_fondos'] . '</td>
                <td>
                    <input type="number" class="form-control" 
                           name="nuevo_id_fondos[' . $activo['id_placa'] . ']" 
                           value="' . $activo['id_fondos'] . '">
                </td>
            </tr>';
        }

        echo '</tbody></table>';
        
        // Botón para enviar el formulario
        echo '<div class="mt-3">
                <button type="submit" class="btn btn-primary" name="btnEditar">
                    <i class="bi bi-pencil-square"></i> Actualizar Seleccionados
                </button>
              </div>';
    } else {
        echo '<p>No se encontraron activos con los filtros seleccionados.</p>';
    }

    mysqli_stmt_close($stmt);
    mysqli_free_result($consulta);
    mysqli_close($link);
}
?>