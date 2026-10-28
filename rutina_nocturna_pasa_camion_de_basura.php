<?php
require_once("conexion.php");
$link = $mysqli;

if (mysqli_connect_errno()) {
    echo "Error de conexión a MySQL: " . mysqli_connect_error();
    exit();
}

if (!mysqli_set_charset($link, "utf8")) {
    echo "Error cargando el conjunto de caracteres utf8";
    $link->close();
    exit();
}

$omitidos = [];
$eliminados = 0;

try {
    if (!mysqli_begin_transaction($link)) {
        throw new Exception('No se pudo iniciar la transacción');
    }

    // Activos marcados que tienen un préstamo activo: se desmarcan para que
    // requieran una nueva revisión después de la devolución.
    $sqlOmitidos = "SELECT p.id_placa, p.placa
                    FROM t_placa p
                    WHERE p.marcado = 1
                      AND EXISTS (
                          SELECT 1
                          FROM t_prestamo_detalle pd
                          WHERE pd.prestamo_detalle_id_placa = p.id_placa
                            AND pd.prestamo_detalle_devuelto = 0
                      )
                    ORDER BY p.placa";

    $resOmitidos = $link->query($sqlOmitidos);
    if (!$resOmitidos) {
        throw new Exception('Error al consultar los activos prestados: ' . $link->error);
    }

    while ($row = $resOmitidos->fetch_assoc()) {
        $omitidos[] = [
            'id_placa' => (int) $row['id_placa'],
            'placa' => $row['placa'],
        ];
    }

    if (!empty($omitidos)) {
        $idsOmitidos = implode(',', array_map(function ($item) {
            return (int) $item['id_placa'];
        }, $omitidos));

        $updateOmitidos = "UPDATE t_placa
                           SET marcado = 0
                           WHERE marcado = 1 AND id_placa IN ($idsOmitidos)";
        if (!$link->query($updateOmitidos)) {
            throw new Exception('Error al desmarcar los activos prestados: ' . $link->error);
        }

        // Al desmarcar, se elimina su registro pendiente de bitácora para que
        // no aparezca como un activo marcado para eliminación.
        $deleteBitacora = "DELETE FROM bitacora_eliminados WHERE id_placa IN ($idsOmitidos)";
        if (!$link->query($deleteBitacora)) {
            throw new Exception('Error al limpiar la bitácora de los activos prestados: ' . $link->error);
        }
    }

    // Eliminar únicamente los activos marcados que no tengan préstamo activo.
    $sqlDelete = "DELETE FROM t_placa
                  WHERE marcado = 1
                    AND NOT EXISTS (
                        SELECT 1
                        FROM t_prestamo_detalle pd
                        WHERE pd.prestamo_detalle_id_placa = t_placa.id_placa
                          AND pd.prestamo_detalle_devuelto = 0
                    )";

    if (!$link->query($sqlDelete)) {
        throw new Exception('Error al eliminar los activos marcados: ' . $link->error);
    }

    $eliminados = $link->affected_rows;

    if (!mysqli_commit($link)) {
        throw new Exception('No se pudo confirmar la transacción');
    }
} catch (Throwable $e) {
    mysqli_rollback($link);
    echo "Error al ejecutar la rutina nocturna: " . $e->getMessage();
    $link->close();
    exit();
}

echo "Rutina nocturna finalizada. Activos eliminados: " . $eliminados . ".";

if (!empty($omitidos)) {
    $placas = array_map(function ($item) {
        return $item['placa'];
    }, $omitidos);

    echo " Activos desmarcados por tener préstamo activo: "
        . htmlspecialchars(implode(', ', $placas), ENT_QUOTES, 'UTF-8') . ".";

    error_log("Rutina nocturna: activos desmarcados por préstamo activo: " . implode(', ', $placas));
}

$link->close();
?>
