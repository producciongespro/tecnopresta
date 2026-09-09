<?php
// ajax/activo_general_acciones_n.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

// Conexión a la base de datos (ubicada en la raíz del servidor)
require_once(__DIR__ . '/../conexion.php');
$link = $mysqli;
if ($link->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos: ' . $link->connect_error]);
    exit();
}
mysqli_set_charset($link, "utf8");

// Carpeta de almacenamiento de imágenes (img/ ubicada en la raíz del servidor)
$ruta_img_base = __DIR__ . '/../img/';
if (!is_dir($ruta_img_base)) {
    @mkdir($ruta_img_base, 0755, true);
}

// Función para procesar y redimensionar PNG manteniendo fondo transparente
function procesarYRedimensionarPNG($tmp_file, $destino_final, $alto_final = 80) {
    if (!extension_loaded('gd')) {
        return move_uploaded_file($tmp_file, $destino_final);
    }

    if (!move_uploaded_file($tmp_file, $destino_final)) {
        return false;
    }

    try {
        $imagen_original = @imagecreatefrompng($destino_final);
        if (!$imagen_original) {
            return true;
        }

        $ancho_original = imagesx($imagen_original);
        $alto_original = imagesy($imagen_original);

        if ($alto_original > 0) {
            $ancho_final = (int)(($alto_final / $alto_original) * $ancho_original);
            $imagen_redimensionada = imagecreatetruecolor($ancho_final, $alto_final);

            imagealphablending($imagen_redimensionada, false);
            imagesavealpha($imagen_redimensionada, true);
            $transparent = imagecolorallocatealpha($imagen_redimensionada, 255, 255, 255, 127);
            imagefilledrectangle($imagen_redimensionada, 0, 0, $ancho_final, $alto_final, $transparent);

            imagecopyresampled(
                $imagen_redimensionada,
                $imagen_original,
                0, 0, 0, 0,
                $ancho_final,
                $alto_final,
                $ancho_original,
                $alto_original
            );

            imagepng($imagen_redimensionada, $destino_final, 9);

            imagedestroy($imagen_original);
            imagedestroy($imagen_redimensionada);
        }
    } catch (Exception $e) {
        // Si hay una advertencia leve se mantiene la imagen original
    }
    return true;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'listar':
        $consulta = $link->query("SELECT id_ag, clase, imagen FROM t_activo_general ORDER BY clase ASC");
        if (!$consulta) {
            echo json_encode(['success' => false, 'message' => 'Error al consultar activos generales: ' . $link->error]);
            exit();
        }

        $activos = [];
        while ($row = $consulta->fetch_assoc()) {
            $activos[] = [
                'id_ag' => (int)$row['id_ag'],
                'clase' => $row['clase'],
                'imagen' => $row['imagen'] ? $row['imagen'] : null,
                'ruta_imagen' => $row['imagen'] ? 'img/' . $row['imagen'] : 'img/default.png'
            ];
        }

        echo json_encode(['success' => true, 'data' => $activos]);
        break;

    case 'obtener':
        $id_ag = intval($_GET['id_ag'] ?? 0);
        if ($id_ag <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID no válido.']);
            exit();
        }

        $stmt = $link->prepare("SELECT id_ag, clase, imagen FROM t_activo_general WHERE id_ag = ?");
        $stmt->bind_param("i", $id_ag);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $row['ruta_imagen'] = $row['imagen'] ? 'img/' . $row['imagen'] : null;
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Activo general no encontrado.']);
        }
        $stmt->close();
        break;

    case 'guardar':
        $clase = trim($_POST['clase'] ?? '');
        $imagen = $_FILES['imagen'] ?? null;

        if (empty($clase)) {
            echo json_encode(['success' => false, 'message' => 'Debe ingresar el nombre de la clase de activo.']);
            exit();
        }

        if (!$imagen || $imagen['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar una imagen válida.']);
            exit();
        }

        $permitidos = ['image/png' => 'png'];
        $limite_kb = 180;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $imagen['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mime, $permitidos)) {
            echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes en formato PNG.']);
            exit();
        }

        if ($imagen['size'] > ($limite_kb * 1024)) {
            echo json_encode(['success' => false, 'message' => "La imagen supera el límite permitido ({$limite_kb} KB)."]);
            exit();
        }

        // Validación de duplicado
        $stmtCheck = $link->prepare("SELECT id_ag FROM t_activo_general WHERE LOWER(TRIM(clase)) = LOWER(TRIM(?))");
        $stmtCheck->bind_param("s", $clase);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            echo json_encode(['success' => false, 'message' => "La clase de activo '{$clase}' ya existe en el catálogo."]);
            exit();
        }
        $stmtCheck->close();

        // Generar nombre de archivo único
        $nombre_archivo = uniqid('ag_') . '.png';
        $destino_completo = $ruta_img_base . $nombre_archivo;

        if (!procesarYRedimensionarPNG($imagen['tmp_name'], $destino_completo, 80)) {
            echo json_encode(['success' => false, 'message' => 'No se pudo guardar la imagen en el servidor.']);
            exit();
        }

        // Insertar en base de datos
        $stmtInsert = $link->prepare("INSERT INTO t_activo_general (clase, imagen) VALUES (?, ?)");
        $stmtInsert->bind_param("ss", $clase, $nombre_archivo);

        if ($stmtInsert->execute()) {
            $nuevo_id = $stmtInsert->insert_id;
            echo json_encode([
                'success' => true,
                'message' => 'Clase de activo registrada exitosamente.',
                'data' => [
                    'id_ag' => $nuevo_id,
                    'clase' => $clase,
                    'imagen' => $nombre_archivo,
                    'ruta_imagen' => 'img/' . $nombre_archivo
                ]
            ]);
        } else {
            if (file_exists($destino_completo)) {
                @unlink($destino_completo);
            }
            echo json_encode(['success' => false, 'message' => 'Error al registrar activo general: ' . $stmtInsert->error]);
        }
        $stmtInsert->close();
        break;

    case 'actualizar':
        $id_ag = intval($_POST['id_ag'] ?? 0);
        $clase = trim($_POST['clase'] ?? '');
        $imagen = $_FILES['imagen'] ?? null;

        if ($id_ag <= 0 || empty($clase)) {
            echo json_encode(['success' => false, 'message' => 'Por favor complete los datos obligatorios.']);
            exit();
        }

        // Verificar si la clase ya existe en otro registro
        $stmtCheck = $link->prepare("SELECT id_ag FROM t_activo_general WHERE LOWER(TRIM(clase)) = LOWER(TRIM(?)) AND id_ag != ?");
        $stmtCheck->bind_param("si", $clase, $id_ag);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            echo json_encode(['success' => false, 'message' => "Ya existe otra clase con el nombre '{$clase}'."]);
            exit();
        }
        $stmtCheck->close();

        // Obtener imagen actual
        $stmtActual = $link->prepare("SELECT imagen FROM t_activo_general WHERE id_ag = ?");
        $stmtActual->bind_param("i", $id_ag);
        $stmtActual->execute();
        $resActual = $stmtActual->get_result();
        $rowActual = $resActual->fetch_assoc();
        $stmtActual->close();

        $imagen_actual = $rowActual ? $rowActual['imagen'] : null;

        // Si se seleccionó una nueva imagen
        if ($imagen && isset($imagen['error']) && $imagen['error'] === UPLOAD_ERR_OK) {
            $permitidos = ['image/png' => 'png'];
            $limite_kb = 180;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $imagen['tmp_name']);
            finfo_close($finfo);

            if (!array_key_exists($mime, $permitidos)) {
                echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes en formato PNG.']);
                exit();
            }

            if ($imagen['size'] > ($limite_kb * 1024)) {
                echo json_encode(['success' => false, 'message' => "La imagen no debe superar los {$limite_kb} KB."]);
                exit();
            }

            $nuevo_img_nombre = uniqid('ag_') . '.png';
            $destino_completo = $ruta_img_base . $nuevo_img_nombre;

            if (!procesarYRedimensionarPNG($imagen['tmp_name'], $destino_completo, 80)) {
                echo json_encode(['success' => false, 'message' => 'Error al almacenar la nueva imagen.']);
                exit();
            }

            $stmtUp = $link->prepare("UPDATE t_activo_general SET clase = ?, imagen = ? WHERE id_ag = ?");
            $stmtUp->bind_param("ssi", $clase, $nuevo_img_nombre, $id_ag);
            $ejecutado = $stmtUp->execute();
            $stmtUp->close();

            if ($ejecutado) {
                if ($imagen_actual && file_exists($ruta_img_base . $imagen_actual)) {
                    @unlink($ruta_img_base . $imagen_actual);
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'Activo general e imagen actualizados con éxito.',
                    'data' => [
                        'id_ag' => $id_ag,
                        'clase' => $clase,
                        'imagen' => $nuevo_img_nombre,
                        'ruta_imagen' => 'img/' . $nuevo_img_nombre
                    ]
                ]);
            } else {
                if (file_exists($destino_completo)) {
                    @unlink($destino_completo);
                }
                echo json_encode(['success' => false, 'message' => 'Error al actualizar en la base de datos: ' . $link->error]);
            }
        } else {
            // Solo actualizar nombre de clase
            $stmtUp = $link->prepare("UPDATE t_activo_general SET clase = ? WHERE id_ag = ?");
            $stmtUp->bind_param("si", $clase, $id_ag);
            if ($stmtUp->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Clase de activo actualizada con éxito.',
                    'data' => [
                        'id_ag' => $id_ag,
                        'clase' => $clase,
                        'imagen' => $imagen_actual,
                        'ruta_imagen' => $imagen_actual ? 'img/' . $imagen_actual : null
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar activo general: ' . $link->error]);
            }
            $stmtUp->close();
        }
        break;

    case 'eliminar':
        $id_ag = intval($_POST['id_ag'] ?? $_GET['id_ag'] ?? 0);
        if ($id_ag <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID no válido.']);
            exit();
        }

        // Validar si está en uso en t_activo
        $stmtActivo = $link->prepare("SELECT COUNT(*) AS total FROM t_activo WHERE id_ag = ?");
        $stmtActivo->bind_param("i", $id_ag);
        $stmtActivo->execute();
        $resActivo = $stmtActivo->get_result()->fetch_assoc();
        $stmtActivo->close();

        if ($resActivo && intval($resActivo['total']) > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar la clase de activo: se encuentra vinculada a ' . $resActivo['total'] . ' activo(s) en inventario.'
            ]);
            exit();
        }

        // Obtener imagen para borrar el archivo físico
        $stmtImg = $link->prepare("SELECT imagen FROM t_activo_general WHERE id_ag = ?");
        $stmtImg->bind_param("i", $id_ag);
        $stmtImg->execute();
        $rowImg = $stmtImg->get_result()->fetch_assoc();
        $stmtImg->close();

        $imagen_archivo = $rowImg ? $rowImg['imagen'] : null;

        $stmtDel = $link->prepare("DELETE FROM t_activo_general WHERE id_ag = ?");
        $stmtDel->bind_param("i", $id_ag);

        if ($stmtDel->execute()) {
            if ($imagen_archivo && file_exists($ruta_img_base . $imagen_archivo)) {
                @unlink($ruta_img_base . $imagen_archivo);
            }
            echo json_encode(['success' => true, 'message' => 'Clase de activo eliminada correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el registro: ' . $link->error]);
        }
        $stmtDel->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
        break;
}

$link->close();
?>
