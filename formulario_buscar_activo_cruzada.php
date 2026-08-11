<?php
session_start();
$tienellave = in_array($_SESSION['tipo'], [1, 7]);
if (!$tienellave) {
    echo '<script language="javascript">
    alert("No tienes permisos, por favor contacte con el administrador.");
    window.location.href = "formulario_menu_principal.html";
    </script>';
    exit();
}

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

$resultados = [];
$mensajes   = [];
$encontrado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $placa  = trim($_POST['placa']  ?? '');
    $serial = trim($_POST['serial'] ?? '');

    if (empty($placa) && empty($serial)) {
        $mensajes[] = ['tipo' => 'warning', 'texto' => 'Debe ingresar al menos un número de placa o serial para buscar.'];
    } else {

        /* ─────────────────────────────────────────────────────────────
         * FIX CLAVE: se selecciona p.codigo explícitamente con alias
         * propio (codigo_centro) para que nunca se pise con el JOIN.
         * i.codigo / i.institucion pueden llegar NULL cuando el centro
         * no está mapeado en t_instituciones, pero p.codigo SIEMPRE
         * llega con el valor real del activo.
         * ──────────────────────────────────────────────────────────── */
        $query = "SELECT p.*,
                         p.codigo            AS codigo_centro,
                         GROUP_CONCAT(DISTINCT CONCAT(i.institucion, '::: ', COALESCE(i.cod_saber, '')) ORDER BY i.id_ins SEPARATOR ' ||| ') AS satelites_concat
                  FROM   t_placa p
                  LEFT JOIN t_instituciones i ON p.codigo = i.codigo
                  WHERE ";

        $conditions = [];
        $params     = [];
        $types      = '';

        if (!empty($placa)) {
            $conditions[] = "p.placa = ?";
            $params[]      = $placa;
            $types        .= 's';
        }
        if (!empty($serial)) {
            $conditions[] = "p.serial = ?";
            $params[]      = $serial;
            $types        .= 's';
        }

        $query .= implode(" OR ", $conditions);
        $query .= " GROUP BY p.id_placa";

        $stmt = $link->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $resultados['activos'][] = $row;
                $encontrado = true;
            }
            $stmt->close();
        }

        /* ─────────────────────────────────────────────────────────────
         * Misma corrección para bitacora_eliminados:
         * b.codigo siempre trae el código real; i.institucion puede
         * llegar NULL si el centro no está en t_instituciones.
         * ──────────────────────────────────────────────────────────── */
        $query_elim = "SELECT b.*,
                              b.codigo            AS codigo_centro,
                              GROUP_CONCAT(DISTINCT CONCAT(i.institucion, '::: ', COALESCE(i.cod_saber, '')) ORDER BY i.id_ins SEPARATOR ' ||| ') AS satelites_concat
                       FROM   bitacora_eliminados b
                       LEFT JOIN t_instituciones i ON b.codigo = i.codigo
                       WHERE ";

        $conditions_elim = [];
        if (!empty($placa))  $conditions_elim[] = "b.placa = ?";
        if (!empty($serial)) $conditions_elim[] = "b.serial = ?";

        $stmt_elim = $link->prepare($query_elim . implode(" OR ", $conditions_elim) . " GROUP BY b.id");
        if ($stmt_elim) {
            $stmt_elim->bind_param($types, ...$params);
            $stmt_elim->execute();
            $result_elim = $stmt_elim->get_result();
            while ($row = $result_elim->fetch_assoc()) {
                $resultados['eliminados'][] = $row;
                $encontrado = true;
            }
            $stmt_elim->close();
        }

        if (!$encontrado) {
            $mensajes[] = ['tipo' => 'info', 'texto' => 'No se encontraron resultados para la búsqueda.'];
        } else {
            if (!empty($resultados['activos'])) {
                foreach ($resultados['activos'] as $activo) {
                    if (!empty($placa) && !empty($serial)) {
                        if ($activo['placa'] == $placa && $activo['serial'] == $serial) {
                            $mensajes[] = ['tipo' => 'success', 'texto' => 'El activo fue encontrado con placa y serial coincidentes.'];
                            break;
                        } elseif ($activo['placa'] == $placa && $activo['serial'] != $serial) {
                            $mensajes[] = ['tipo' => 'warning', 'texto' => 'La placa fue encontrada pero con un serial diferente.'];
                        } elseif ($activo['placa'] != $placa && $activo['serial'] == $serial) {
                            $mensajes[] = ['tipo' => 'warning', 'texto' => 'El serial fue encontrado pero asociado a otra placa.'];
                        }
                    }
                }
            }

            if (!empty($resultados['eliminados'])) {
                foreach ($resultados['eliminados'] as $eliminado) {
                    // extraer primer nombre de institución o texto fallback
                    $inst_texto = 'No mapeada en el sistema';
                    if (!empty($eliminado['satelites_concat'])) {
                        $p = explode('::: ', explode(' ||| ', $eliminado['satelites_concat'])[0], 2);
                        if (!empty($p[0])) { $inst_texto = $p[0]; }
                    }

                    $mensajes[] = [
                        'tipo'  => 'danger',
                        'texto' => 'El activo fue encontrado en registros eliminados. ' .
                                   'Institución: ' . htmlspecialchars($inst_texto) .
                                   ' (Código: ' . htmlspecialchars($eliminado['codigo_centro']) . ')'
                    ];
                }
            }
        }
    }
}

/* ──────────────────────────────────────────────────────────────────────
 * Helper: recibe el array de la fila y devuelve las tres piezas de
 * información de institución listas para mostrar en el HTML.
 * Separa la lógica de presentación del HTML para no repetirla.
 * ─────────────────────────────────────────────────────────────────── */
function institucionInfo(array $row): array {
    $codigo        = $row['codigo_centro']   ?? null;   // campo propio, siempre presente
    $satelites_raw = $row['satelites_concat'] ?? null;

    $satelites = [];
    if (!empty($satelites_raw)) {
        $raw_items = explode(' ||| ', $satelites_raw);
        foreach ($raw_items as $item) {
            $parts = explode('::: ', $item, 2);
            $satelites[] = [
                'nombre' => $parts[0] ?? '',
                'cod_saber' => trim($parts[1] ?? '')
            ];
        }
    }

    $codigo_html   = $codigo
        ? htmlspecialchars($codigo)
        : '<span class="text-muted fst-italic">Sin código</span>';

    if (!empty($satelites)) {
        if (count($satelites) === 1) {
            $sat = $satelites[0];
            $nombre_html = htmlspecialchars($sat['nombre']);
            if (!empty($sat['cod_saber'])) {
                $nombre_html .= ' <span class="badge bg-light text-secondary border ms-1">SABER: ' . htmlspecialchars($sat['cod_saber']) . '</span>';
            }
            $badge = '';
        } else {
            $nombre_html = '<div class="fw-bold text-dark mb-1">' . htmlspecialchars($satelites[0]['nombre']) . '</div>';
            $nombre_html .= '<div class="ps-2 border-start border-2 border-primary-subtle"><small class="text-uppercase fw-bold text-muted d-block mb-1" style="font-size: 0.68rem; letter-spacing: 0.5px;">Sedes / Satélites asociados:</small><ul class="list-unstyled mb-0 ms-1">';
            foreach ($satelites as $sat) {
                $nombre_html .= '<li class="small py-1 border-bottom border-light"><i class="bi bi-geo-alt text-primary small me-1"></i><span class="fw-semibold">' . htmlspecialchars($sat['nombre']) . '</span>';
                if (!empty($sat['cod_saber'])) {
                    $nombre_html .= ' <span class="badge bg-light text-secondary border ms-1" style="font-size:0.7rem;">SABER: ' . htmlspecialchars($sat['cod_saber']) . '</span>';
                }
                $nombre_html .= '</li>';
            }
            $nombre_html .= '</ul></div>';
            $badge = '<span class="badge bg-info text-dark ms-2"><i class="bi bi-diagram-3-fill"></i> ' . count($satelites) . ' Sedes/Satélites</span>';
        }
    } else {
        $nombre_html = '<span class="text-muted fst-italic">No registrada en el sistema</span>';
        $badge       = '<span class="badge bg-warning text-dark ms-2">Centro no mapeado</span>';
    }

    return [$codigo_html, $nombre_html, $badge];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Activos</title>
    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <link href="bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .result-card      { margin-bottom: 20px; border-left: 4px solid #0d6efd; }
        .eliminado-card   { border-left: 4px solid #dc3545; }
        .institucion-info { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px; }
        .codigo-institucion {
            font-family: 'Courier New', monospace;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="formulario_menu_principal.html">
            <i class="bi bi-laptop"></i> Tecnopresta
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="herramientas.php">
                        <i class="bi bi-arrow-left-circle"></i> Regresar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">Consulta de Activos</h2>

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Buscador de Activos</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="placa" class="form-label">Número de Placa</label>
                        <input type="text" class="form-control" id="placa" name="placa"
                               value="<?= htmlspecialchars($_POST['placa'] ?? '') ?>"
                               placeholder="Ingrese el número de placa">
                    </div>
                    <div class="col-md-6">
                        <label for="serial" class="form-label">Número de Serial</label>
                        <input type="text" class="form-control" id="serial" name="serial"
                               value="<?= htmlspecialchars($_POST['serial'] ?? '') ?>"
                               placeholder="Ingrese el número de serial">
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Buscar Activo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($mensajes)): ?>
        <div class="mt-4">
            <?php foreach ($mensajes as $mensaje): ?>
                <div class="alert alert-<?= $mensaje['tipo'] ?> mensaje-alerta">
                    <?= $mensaje['texto'] ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($resultados)): ?>
        <div class="mt-4">
            <h4>Resultados de la Búsqueda</h4>

            <?php if (!empty($resultados['activos'])): ?>
                <h5 class="mt-3 text-success">
                    <i class="bi bi-check-circle"></i> Activos Encontrados (<?= count($resultados['activos']) ?>)
                </h5>

                <?php foreach ($resultados['activos'] as $activo):
                    [$codigo_html, $nombre_html, $badge] = institucionInfo($activo);
                ?>
                    <div class="card result-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-pc-display"></i> Activo #<?= $activo['id_placa'] ?>
                                <span class="badge bg-success float-end">ACTIVO</span>
                            </h5>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <p><strong>Placa:</strong>
                                        <span class="text-primary"><?= htmlspecialchars($activo['placa']) ?></span>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Serial:</strong>
                                        <span class="text-primary"><?= htmlspecialchars($activo['serial']) ?></span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>ID Interno:</strong>
                                        <code><?= htmlspecialchars($activo['id_placa']) ?></code>
                                    </p>
                                </div>
                            </div>

                            <div class="institucion-info">
                                <h6>
                                    <i class="bi bi-building"></i> Información de la Institución
                                    <?= $badge ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Nombre:</strong> <?= $nombre_html ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Código:</strong>
                                            <span class="codigo-institucion"><?= $codigo_html ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($resultados['eliminados'])): ?>
                <h5 class="mt-3 text-danger">
                    <i class="bi bi-exclamation-triangle"></i> Activos Eliminados (<?= count($resultados['eliminados']) ?>)
                </h5>

                <?php foreach ($resultados['eliminados'] as $eliminado):
                    [$codigo_html, $nombre_html, $badge] = institucionInfo($eliminado);
                ?>
                    <div class="card result-card eliminado-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-archive"></i> Activo Eliminado
                                <span class="badge bg-danger float-end">ELIMINADO</span>
                            </h5>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <p><strong>Placa:</strong>
                                        <span class="text-danger"><?= htmlspecialchars($eliminado['placa']) ?></span>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <p><strong>Serial:</strong>
                                        <span class="text-danger"><?= htmlspecialchars($eliminado['serial']) ?></span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>ID Original:</strong>
                                        <code><?= htmlspecialchars($eliminado['id_placa'] ?? 'N/A') ?></code>
                                    </p>
                                </div>
                            </div>

                            <div class="institucion-info">
                                <h6>
                                    <i class="bi bi-building-exclamation"></i> Institución que Eliminó
                                    <?= $badge ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Nombre:</strong> <?= $nombre_html ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Código:</strong>
                                            <span class="codigo-institucion"><?= $codigo_html ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($eliminado['fecha_eliminacion'])): ?>
                                <div class="mt-2">
                                    <p class="text-muted">
                                        <small><strong>Fecha de eliminación:</strong>
                                            <?= htmlspecialchars($eliminado['fecha_eliminacion']) ?>
                                        </small>
                                    </p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    <?php endif; ?>
</div>

<script src="bootstrap5/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const placa  = document.getElementById('placa').value.trim();
        const serial = document.getElementById('serial').value.trim();
        if (placa === '' && serial === '') {
            e.preventDefault();
            alert('Debe ingresar al menos un número de placa o serial para buscar.');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('placa').focus();
    });
</script>
</body>
</html>