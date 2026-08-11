<?php
session_start();
$_SESSION['funcionario'] = [
    'cedula' => '000000000',
    'auth' => ['usuario_id' => 1, 'es_root' => true, 'roles' => ['Root']],
];
header('Content-Type: text/plain');
echo session_id();
