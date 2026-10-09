<?php

header('Content-Type: application/json');

// Hash de la clave de acceso del botón oculto (generado con password_hash(..., PASSWORD_DEFAULT)).
// El valor en claro NO debe existir en el cliente; solo se valida aquí en el servidor.
$hash = '$2y$10$1TvAoIcG12QJ81bj5m2iJet.R4rDgIjqD10zMDMtwftPWVMN7JN4e';

$codigo = $_POST['codigo'] ?? '';

$ok = is_string($codigo) && $codigo !== '' && password_verify($codigo, $hash);

echo json_encode(['ok' => $ok]);
