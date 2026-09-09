<?php
// ajax/marca_acciones_n.php
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

// Carpeta de almacenamiento de logos (ico/ ubicada en la raíz del servidor)
$ruta_ico_base = __DIR__ . '/../ico/';
if (!is_dir($ruta_ico_base)) {
    @mkdir($ruta_ico_base, 0755, true);
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
        // En caso de advertencia no fatal, el archivo ya está en $destino_final
    }
    return true;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'listar':
        $consulta = $link->query("SELECT id_marca, marca, logo FROM t_marca ORDER BY marca ASC");
        if (!$consulta) {
            echo json_encode(['success' => false, 'message' => 'Error al consultar marcas: ' . $link->error]);
            exit();
        }

        $marcas = [];
        while ($row = $consulta->fetch_assoc()) {
            $marcas[] = [
                'id_marca' => (int)$row['id_marca'],
                'marca' => $row['marca'],
                'logo' => $row['logo'] ? $row['logo'] : null,
                'ruta_logo' => $row['logo'] ? 'ico/' . $row['logo'] : 'ico/default.png'
            ];
        }

        echo json_encode(['success' => true, 'data' => $marcas]);
        break;

    case 'obtener':
        $id_marca = intval($_GET['id_marca'] ?? 0);
        if ($id_marca <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de marca no válido.']);
            exit();
        }

        $stmt = $link->prepare("SELECT id_marca, marca, logo FROM t_marca WHERE id_marca = ?");
        $stmt->bind_param("i", $id_marca);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $row['ruta_logo'] = $row['logo'] ? 'ico/' . $row['logo'] : null;
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Marca no encontrada.']);
        }
        $stmt->close();
        break;

    case 'guardar':
        $marca = trim($_POST['marca'] ?? '');
        $imagen = $_FILES['imagen'] ?? null;

        if (empty($marca)) {
            echo json_encode(['success' => false, 'message' => 'Debe ingresar el nombre de la marca comercial.']);
            exit();
        }

        if (!$imagen || $imagen['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar un logotipo válido.']);
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
            echo json_encode(['success' => false, 'message' => "El logotipo supera el tamaño permitido ({$limite_kb} KB)."]);
            exit();
        }

        // Validación de marca duplicada (insensible a mayúsculas)
        $stmtCheck = $link->prepare("SELECT id_marca FROM t_marca WHERE LOWER(TRIM(marca)) = LOWER(TRIM(?))");
        $stmtCheck->bind_param("s", $marca);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            echo json_encode(['success' => false, 'message' => "La marca '{$marca}' ya existe en el catálogo."]);
            exit();
        }
        $stmtCheck->close();

        // Generar nombre de archivo único
        $nombre_archivo = uniqid('logo_') . '.png';
        $destino_completo = $ruta_ico_base . $nombre_archivo;

        if (!procesarYRedimensionarPNG($imagen['tmp_name'], $destino_completo, 80)) {
            echo json_encode(['success' => false, 'message' => 'No se pudo almacenar el logotipo en el servidor.']);
            exit();
        }

        // Insertar en base de datos
        $stmtInsert = $link->prepare("INSERT INTO t_marca (marca, logo) VALUES (?, ?)");
        $stmtInsert->bind_param("ss", $marca, $nombre_archivo);

        if ($stmtInsert->execute()) {
            $nuevo_id = $stmtInsert->insert_id;
            echo json_encode([
                'success' => true,
                'message' => 'Marca comercial guardada correctamente.',
                'data' => [
                    'id_marca' => $nuevo_id,
                    'marca' => $marca,
                    'logo' => $nombre_archivo,
                    'ruta_logo' => 'ico/' . $nombre_archivo
                ]
            ]);
        } else {
            if (file_exists($destino_completo)) {
                @unlink($destino_completo);
            }
            echo json_encode(['success' => false, 'message' => 'Error al registrar la marca: ' . $stmtInsert->error]);
        }
        $stmtInsert->close();
        break;

    case 'actualizar':
        $id_marca = intval($_POST['id_marca'] ?? 0);
        $marca = trim($_POST['marca'] ?? '');
        $imagen = $_FILES['imagen'] ?? null;

        if ($id_marca <= 0 || empty($marca)) {
            echo json_encode(['success' => false, 'message' => 'Por favor complete todos los datos requeridos.']);
            exit();
        }

        // Verificar duplicados en otras marcas
        $stmtCheck = $link->prepare("SELECT id_marca FROM t_marca WHERE LOWER(TRIM(marca)) = LOWER(TRIM(?)) AND id_marca != ?");
        $stmtCheck->bind_param("si", $marca, $id_marca);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            echo json_encode(['success' => false, 'message' => "Ya existe otra marca con el nombre '{$marca}'."]);
            exit();
        }
        $stmtCheck->close();

        // Obtener logo actual
        $stmtActual = $link->prepare("SELECT logo FROM t_marca WHERE id_marca = ?");
        $stmtActual->bind_param("i", $id_marca);
        $stmtActual->execute();
        $resActual = $stmtActual->get_result();
        $rowActual = $resActual->fetch_assoc();
        $stmtActual->close();

        $logo_actual = $rowActual ? $rowActual['logo'] : null;

        // Si se seleccionó un nuevo logotipo
        if ($imagen && isset($imagen['error']) && $imagen['error'] === UPLOAD_ERR_OK) {
            $permitidos = ['image/png' => 'png'];
            $limite_kb = 180;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $imagen['tmp_name']);
            finfo_close($finfo);

            if (!array_key_exists($mime, $permitidos)) {
                echo json_encode(['success' => false, 'message' => 'Solo se permiten logotipos en formato PNG.']);
                exit();
            }

            if ($imagen['size'] > ($limite_kb * 1024)) {
                echo json_encode(['success' => false, 'message' => "El logotipo no debe superar los {$limite_kb} KB."]);
                exit();
            }

            $nuevo_logo_nombre = uniqid('logo_') . '.png';
            $destino_completo = $ruta_ico_base . $nuevo_logo_nombre;

            if (!procesarYRedimensionarPNG($imagen['tmp_name'], $destino_completo, 80)) {
                echo json_encode(['success' => false, 'message' => 'Error al almacenar el nuevo logotipo.']);
                exit();
            }

            $stmtUp = $link->prepare("UPDATE t_marca SET marca = ?, logo = ? WHERE id_marca = ?");
            $stmtUp->bind_param("ssi", $marca, $nuevo_logo_nombre, $id_marca);
            $ejecutado = $stmtUp->execute();
            $stmtUp->close();

            if ($ejecutado) {
                if ($logo_actual && file_exists($ruta_ico_base . $logo_actual)) {
                    @unlink($ruta_ico_base . $logo_actual);
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'Marca y logotipo actualizados con éxito.',
                    'data' => [
                        'id_marca' => $id_marca,
                        'marca' => $marca,
                        'logo' => $nuevo_logo_nombre,
                        'ruta_logo' => 'ico/' . $nuevo_logo_nombre
                    ]
                ]);
            } else {
                if (file_exists($destino_completo)) {
                    @unlink($destino_completo);
                }
                echo json_encode(['success' => false, 'message' => 'Error al actualizar en la base de datos: ' . $link->error]);
            }
        } else {
            // Solo actualización de texto/nombre
            $stmtUp = $link->prepare("UPDATE t_marca SET marca = ? WHERE id_marca = ?");
            $stmtUp->bind_param("si", $marca, $id_marca);
            if ($stmtUp->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Marca actualizada con éxito.',
                    'data' => [
                        'id_marca' => $id_marca,
                        'marca' => $marca,
                        'logo' => $logo_actual,
                        'ruta_logo' => $logo_actual ? 'ico/' . $logo_actual : null
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la marca: ' . $link->error]);
            }
            $stmtUp->close();
        }
        break;

    case 'eliminar':
        $id_marca = intval($_POST['id_marca'] ?? $_GET['id_marca'] ?? 0);
        if ($id_marca <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de marca no válido.']);
            exit();
        }

        // Validar si está en uso en t_activo
        $stmtActivo = $link->prepare("SELECT COUNT(*) AS total FROM t_activo WHERE id_marca = ?");
        $stmtActivo->bind_param("i", $id_marca);
        $stmtActivo->execute();
        $resActivo = $stmtActivo->get_result()->fetch_assoc();
        $stmtActivo->close();

        if ($resActivo && intval($resActivo['total']) > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar la marca: se encuentra vinculada a ' . $resActivo['total'] . ' activo(s) registrado(s).'
            ]);
            exit();
        }

        // Validar si está en uso en t_software_general
        $stmtSoft = $link->prepare("SELECT COUNT(*) AS total FROM t_software_general WHERE id_marca = ?");
        $stmtSoft->bind_param("i", $id_marca);
        $stmtSoft->execute();
        $resSoft = $stmtSoft->get_result()->fetch_assoc();
        $stmtSoft->close();

        if ($resSoft && intval($resSoft['total']) > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar la marca: se encuentra vinculada a ' . $resSoft['total'] . ' registro(s) de software.'
            ]);
            exit();
        }

        // Obtener logo para borrar archivo físico
        $stmtLogo = $link->prepare("SELECT logo FROM t_marca WHERE id_marca = ?");
        $stmtLogo->bind_param("i", $id_marca);
        $stmtLogo->execute();
        $rowLogo = $stmtLogo->get_result()->fetch_assoc();
        $stmtLogo->close();

        $logo_archivo = $rowLogo ? $rowLogo['logo'] : null;

        $stmtDel = $link->prepare("DELETE FROM t_marca WHERE id_marca = ?");
        $stmtDel->bind_param("i", $id_marca);

        if ($stmtDel->execute()) {
            if ($logo_archivo && file_exists($ruta_ico_base . $logo_archivo)) {
                @unlink($ruta_ico_base . $logo_archivo);
            }
            echo json_encode(['success' => true, 'message' => 'Marca eliminada correctamente.']);
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