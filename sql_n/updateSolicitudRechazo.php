<?php

require 'conexion.php';
require 'email_SolicitudRechazo.php';

class updateSolicitudRechazo {

    private $pdo;
       	
	function __construct()
	{
        $pdo = new \PDO(DB_Str, DB_USER, DB_PASS);		
        $this->pdo = $pdo;        
        
    }
        
    public function updateSolicitudRechazo($solicitud_Id, 
                                           $solicitud_email_funcionario, 
                                           $solicitud_motivo_rechazo,
                                           $prestamo_nombre_solicitante, 
                                           $arrayArticulosNombre){
                        		
        $sql = 'UPDATE t_solicitud  SET solicitud_aprobada = :solicitud_aprobada,
                solicitud_motivo_rechazo = :solicitud_motivo_rechazo 
                WHERE solicitud_Id = :solicitud_Id';
                
        try {
		
        $stmt = $this->pdo->prepare($sql);
        				
        $this->pdo->beginTransaction(); 			        
        // 1 Aprobado
        // 2 Pendiente
        // 3 Rechazado
        $stmt->execute([':solicitud_Id' => $solicitud_Id, 
                        ':solicitud_aprobada' => 3,
                        ':solicitud_motivo_rechazo' => $solicitud_motivo_rechazo]);       

        $this->pdo->commit();                     

        $stmt = null;
        $this->pdo = null;  
        
        $email = new Email_SolicitudRechazo();		
	    $evia_email = $email->email_SolicitudRechazo($solicitud_email_funcionario,
                                                     $solicitud_motivo_rechazo, 
                                                     $prestamo_nombre_solicitante, 
                                                     $arrayArticulosNombre);
    
        $evia_email = null;
        return true; 
        
    } catch (\Throwable $th) {
                echo "Error al actualizar solicitud: " . $th->getMessage() . "\n";				
            }    
              
    }
} 

?>