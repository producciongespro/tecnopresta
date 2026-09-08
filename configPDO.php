<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'tecnopre_rootbd');
define('DB_PASS', '2020*tecnopresta');
define('DB_NAME', 'tecnopre_pntm');


// Conexión a la base de datos
try {
$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: No se pudo conectar. " . $e->getMessage());
}

// Función para subir archivos
function subirArchivo($file) {
    $target_dir = "contratos/";
    $target_file = $target_dir . basename($file["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Verificar si es un PDF
    if($fileType != "pdf") {
        return ["success" => false, "message" => "Solo se permiten archivos PDF."];
    }

    // Verificar tamaño (max 5MB)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "El archivo es demasiado grande."];
    }

    // Generar nombre único
    $new_filename = uniqid() . '.pdf';
    $target_path = $target_dir . $new_filename;

    if (move_uploaded_file($file["tmp_name"], $target_path)) {
        return ["success" => true, "filename" => $new_filename];
    } else {
        return ["success" => false, "message" => "Error al subir el archivo."];
    }
}
?>