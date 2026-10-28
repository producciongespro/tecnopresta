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
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
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
$logcodigo = $usuario_azure['codigoPresu'] ?? '';

if (isset($_POST['fondos'])) {
    $fondos = intval($_POST['fondos']);

    $sql = "SELECT Ta.id_activo, Tg.clase, Tm.marca, Ta.modelo, Tc.color, Tp.id_placa, Tp.placa, Tp.serial, Tp.id_estado, Tp.codigo, Tp.activo, Tp.marcado,
            EXISTS (
                SELECT 1
                FROM t_prestamo_detalle pd
                WHERE pd.prestamo_detalle_id_placa = Tp.id_placa
                  AND pd.prestamo_detalle_devuelto = 0
            ) AS prestado
        FROM t_activo Ta
        INNER JOIN t_marca Tm ON Ta.id_marca = Tm.id_marca 
        INNER JOIN t_color Tc ON Ta.id_color = Tc.id_color
        INNER JOIN t_placa Tp ON Ta.id_activo = Tp.id_activo
        INNER JOIN t_activo_general Tg ON Ta.id_ag = Tg.id_ag 
        WHERE Tp.codigo = ? AND Tp.id_fondos = ?
        ORDER BY Tp.placa ASC";

    $stmt = $link->prepare($sql);
    if (!$stmt) {
        die(mysqli_error($link));
    }
    $stmt->bind_param("si", $logcodigo, $fondos);
    $stmt->execute();
    $consulta = $stmt->get_result();

    if (!$consulta) {
        die('Error al obtener los activos: ' . mysqli_error($link));
    }

    if (mysqli_num_rows($consulta) > 0) {
        echo '<table class="activos-table">
            <thead>
                <tr>
                    <th class="th-check">Seleccione</th>
                    <th class="th-activo">Activo</th>
                    <th class="th-marca">Marca</th>
                    <th class="th-modelo">Modelo</th>
                    <th class="th-color">Color</th>
                    <th class="th-placa">Placa</th>
                    <th class="th-serial">Serial</th>
                    <th class="th-estado">Estado</th>
                </tr>
            </thead>
            <tbody class="BusquedaRapida">';

        while ($activos = mysqli_fetch_array($consulta)) {
            $es_marcado = $activos['marcado'] == 1;
            $es_prestado = (int) $activos['prestado'] === 1;
            $row_class = $es_marcado ? 'table-marcado' : '';
            $row_prestado = $es_prestado ? ' data-prestado="1"' : '';

            echo '<tr class="' . $row_class . '"' . $row_prestado . '>
                <td class="td-check">';
            if ($es_marcado) {
                echo '<div class="d-flex align-items-center gap-1 flex-wrap">
                    <input type="checkbox" disabled class="selectall" style="display:none">
                    <button type="button" class="btn-revertir" onclick="revertirMarcado(' . $activos['id_placa'] . ', this)" title="Revertir eliminación">
                        <i class="bi bi-arrow-counterclockwise"></i> Revertir
                    </button>
                </div>';
            } elseif ($es_prestado) {
                echo '<span title="No se puede eliminar: activo prestado"><input type="checkbox" class="selectall" disabled aria-label="Activo prestado, no se puede seleccionar"/></span>';
            } else {
                echo '<input type="checkbox" class="selectall" name="idsplacas[]" value="' . $activos['id_placa'] . '"/>';
            }
            echo '</td>
                <td class="td-activo">' . $activos['clase'] . '</td>
                <td class="td-marca">' . $activos['marca'] . '</td>
                <td class="td-modelo">' . $activos['modelo'] . '</td>
                <td class="td-color">' . $activos['color'] . '</td>
                <td class="td-placa">' . $activos['placa'] . '</td>
                <td class="td-serial"><span class="activos-serial">' . $activos['serial'] . '</span></td>
                <td class="td-estado">';
            if ($es_prestado) {
                echo '<span class="badge bg-info text-dark me-1" title="Activo con préstamo activo"><i class="bi bi-person-lock me-1"></i>Prestado</span>';
            }
            if ($es_marcado) {
                echo '<span class="badge-marcado"><i class="bi bi-exclamation-triangle-fill me-1"></i>Pendiente</span>';
            }
            echo '</td>
            </tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No se encontraron datos.</p>';
    }

    $stmt->close();
    mysqli_close($link);
}
?>
