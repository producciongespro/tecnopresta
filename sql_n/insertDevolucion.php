<?php

require 'conexion.php';
require 'email_Devolucion.php';

class insertDevolucion {

    private $pdo;
    	
	function __construct()
	{
        $pdo = new \PDO(DB_Str, DB_USER, DB_PASS);		
        $this->pdo = $pdo;        
        
    }
        
    public function insertDevolucion($arrayArticulos, $prestamo_Id, 
                                        $prestamo_email_solicitante,
                                        $prestamo_nombre_solicitante,
                                        $arrayArticulosNombre,
                                        $prestamo_incidente,
                                        $prestamo_incidente_comentario){
        
        try {

            if(!empty($arrayArticulos)) {

                $this->pdo->beginTransaction();

                date_default_timezone_set('America/Costa_Rica');		
                $prestamo_detalle_fechaDevuelto = date_create('now')->format('Y-m-d H:i:s');
                
                foreach($arrayArticulos as $key => $articulos) {
                
                    $prestamo_detalle_id_placa = $articulos;
                
                    $sql = "UPDATE t_prestamo_detalle 
                            SET prestamo_detalle_devuelto = :prestamo_detalle_devuelto,
                                prestamo_detalle_fechaDevuelto = :prestamo_detalle_fechaDevuelto 
                            WHERE prestamo_Id = :prestamo_Id AND prestamo_detalle_id_placa = :prestamo_detalle_id_placa";
                    
                    $stmt = $this->pdo->prepare($sql);
                    
                    $stmt->execute([':prestamo_Id' => $prestamo_Id, 
                                    ':prestamo_detalle_id_placa' => $prestamo_detalle_id_placa,
                                    ':prestamo_detalle_fechaDevuelto' => $prestamo_detalle_fechaDevuelto,
                                    ':prestamo_detalle_devuelto' => 1
                                ]);
                }

                if ($prestamo_incidente) {

                    $sql = "UPDATE t_prestamo SET prestamo_incidente = :prestamo_incidente,
                    prestamo_incidente_comentario = :prestamo_incidente_comentario 
                    WHERE prestamo_Id = :prestamo_Id";
                    
                    $stmt = $this->pdo->prepare($sql);
                    
                    $stmt->execute([':prestamo_Id' => $prestamo_Id, 
                                    ':prestamo_incidente' => 1,
                                    ':prestamo_incidente_comentario' => $prestamo_incidente_comentario
                                ]);
                }    

                $this->pdo->commit(); 
            }

            $stmt = null;
            $this->pdo = null;

            $email = new Email_Devolucion();		
            $evia_email = $email->email_Devolucion($prestamo_email_solicitante, 
                                                    $prestamo_nombre_solicitante, 
                                                    $arrayArticulosNombre,
                                                    $prestamo_incidente_comentario);
        
            $evia_email = null;

            return true;    
        
    } catch (\Throwable $th) {
                echo "Error al enviar email: " . $th->getMessage() . "\n";				
            }    
              
    }
} 

?>