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
        // Fondos presupuestarios disponibles para el desplegable
        $listado_fondos = [];
        $q_fondos = $link->query("SELECT id_fondos, fondos FROM t_fondos ORDER BY fondos");
        if ($q_fondos) {
            while ($fila_f = $q_fondos->fetch_assoc()) {
                $listado_fondos[] = $fila_f;
            }
        }

        $total_registros = mysqli_num_rows($consulta);

        echo '<div class="contador-container">
                <div class="contador-registros">
                    <i class="bi bi-database me-2"></i>
                    Registros encontrados: <span>' . $total_registros . '</span>' .
                    ($total_registros == 1 ? ' registro' : ' registros') .
                '</div>
              </div>';

        echo '<div class="table-responsive edicion-tabla-scroll" style="max-height: 700px;">
            <table class="table table-striped table-hover mb-0">
            <thead class="header-fixed">
                <tr>
                    <th style="width: 40px; white-space: nowrap;">Sel.</th>
                    <th style="width: 18%;">Placa</th>
                    <th style="width: 14%;">Serial</th>
                    <th style="width: 130px;">ID Activo<br>Actual</th>
                    <th style="width: 130px;">Nuevo ID<br>Activo</th>
                    <th style="width: 130px;">ID Fondo<br>Actual</th>
                    <th style="width: 130px;">Nuevo ID<br>Fondo</th>
                    <th style="width: 16%;">Fondo Presupuestario</th>
                </tr>
            </thead>
            <tbody class="BusquedaRapida">';

        while ($activo = mysqli_fetch_array($consulta)) {
            $id_placa          = $activo['id_placa'];
            $id_fondos_actual  = $activo['id_fondos'];

            $opciones_fondos = '<option value="">— Seleccione…</option>';
            foreach ($listado_fondos as $fila_f) {
                $sel = (intval($fila_f['id_fondos']) === intval($id_fondos_actual)) ? ' selected' : '';
                $opciones_fondos .= '<option value="' . intval($fila_f['id_fondos']) . '"' . $sel . '>' . htmlspecialchars($fila_f['fondos']) . '</option>';
            }

            echo '<tr>
                <td><input type="checkbox" class="form-check-input check-editar" name="idsplacas[]" value="' . $id_placa . '"/></td>
                <td>' . htmlspecialchars($activo['placa']) . '</td>
                <td>' . htmlspecialchars($activo['serial']) . '</td>
                <td>' . $activo['id_activo'] . '</td>
                <td>
                    <input type="number" class="form-control form-control-sm campo-edicion" 
                           name="nuevo_id_activo[' . $id_placa . ']" 
                           value="' . $activo['id_activo'] . '" disabled>
                </td>
                <td>' . $id_fondos_actual . '</td>
                <td>
                    <input type="number" class="form-control form-control-sm campo-edicion nuevo-id-fondo" 
                           name="nuevo_id_fondos[' . $id_placa . ']" 
                           value="' . $id_fondos_actual . '" disabled>
                </td>
                <td>
                    <select class="form-select form-select-sm campo-edicion fondo-presupuestario" data-id="' . $id_placa . '" disabled>
                        ' . $opciones_fondos . '
                    </select>
                </td>
            </tr>';
        }

        echo '</tbody></table></div>';
    } else {
        echo '<p>No se encontraron activos con los filtros seleccionados.</p>';
    }

    mysqli_stmt_close($stmt);
    mysqli_free_result($consulta);
    mysqli_close($link);
}
?>