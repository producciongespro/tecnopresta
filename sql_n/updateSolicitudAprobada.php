<?php

require_once 'conexion.php';

class updateSolicitudAprobada {

    private $pdo;
       	
	function __construct()
	{
        $pdo = new \PDO(DB_Str, DB_USER, DB_PASS);		
        $this->pdo = $pdo;        
        
    }
        
    public function updateSolicitudAprobada($solicitud_Id){
                        		
        $sql = 'UPDATE t_solicitud  SET solicitud_aprobada = :solicitud_aprobada 
                WHERE solicitud_Id = :solicitud_Id';
                
        try {
		
        $stmt = $this->pdo->prepare($sql);
        				
        $this->pdo->beginTransaction(); 			
        
        $stmt->execute([':solicitud_Id' => $solicitud_Id, ':solicitud_aprobada' => 1]);       

        $this->pdo->commit();                     

        $stmt = null;
        $this->pdo = null;  
        
    
        return true; 
        
    } catch (\Throwable $th) {
                echo "Error al actualizar solicitud: " . $th->getMessage() . "\n";				
            }    
              
    }
} 

?>