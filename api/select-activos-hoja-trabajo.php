<?php

require_once("db_config.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

try {
    
    $valor = $_GET['busqueda'];
    $codigo = $_GET['codigo'];

    if ($codigo == null OR $codigo === null OR $codigo == '') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo obtener el código presupuestario'
        ]);
        exit;
    }

    $pdo = getDBConnection();

    if (empty($valor)) {

        $sql = "SELECT t_placa.id_activo, clase, marca, modelo, placa, serial AS serie, imagen, id_placa
                FROM t_placa
                INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
                INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
                INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
                WHERE codigo = :codigo ORDER BY clase, marca, modelo, placa, serie ASC;";

        $params = [':codigo' => $codigo];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
                    'success' => true,
                    'articulos' => $articulos
                    ]);

        exit;
        
    } else {

        $sql = "SELECT t_placa.id_activo, clase, marca, modelo, placa, serial AS serie, imagen, id_placa
                FROM t_placa
                INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
                INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
                INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
                WHERE codigo = :codigo AND 
                (clase LIKE :valor1 OR marca LIKE :valor2 
                OR modelo LIKE :valor3 OR placa LIKE :valor4 OR serial LIKE :valor5)
                ORDER BY clase, marca, modelo, placa, serie ASC;";

        $search_term_with_wildcards = "%" . $valor . "%";

        $params = [
                    ':codigo' => $codigo,
                    ':valor1'=> $search_term_with_wildcards,
                    ':valor2'=> $search_term_with_wildcards,
                    ':valor3'=> $search_term_with_wildcards,
                    ':valor4'=> $search_term_with_wildcards,
                    ':valor5'=> $search_term_with_wildcards
                    ];
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
                    'success' => true,
                    'articulos' => $articulos
                    ]);

        exit;

    }

    } catch (PDOException $e) {
    error_log("Error en select-soportista-cedula.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar activos: ' . $e->getMessage()
    ]);
}
?>