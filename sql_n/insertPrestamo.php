<?php

require_once 'conexion.php';
require 'email_Prestamo.php';
require 'email_Boleta.php';

class insertPrestamo {

    private $pdo;
    private $last;
    	
	function __construct()
	{
        $pdo = new \PDO(DB_Str, DB_USER, DB_PASS);		
        $this->pdo = $pdo;        
        
    }
        
    public function insertPrestamo($prestamo_fechaRetiro, $prestamo_horaRetiro,
                                   $prestamo_fechaDevolucion, $prestamo_horaDevolucion,
                                   $prestamo_uso, $arrayArticulos,  
                                   $prestamo_cedula_funcionario, 
                                   $prestamo_nombre_funcionario, 
                                   $prestamo_codigo_presupuestario,
                                   $prestamo_nombre_solicitante, $para, 
                                   $solicitud_Id, $Dependencia,
                                   $prestamo_fechaRetiro_formato,
                                   $prestamo_fechaDevolucion_formato,
                                   $arrayArticulosNombre,
                                   $arraySoftwareDescripcion,
                                   $seccionDescripcion, $seccion_Id, $arraySoftwareId,
                                   $arrayArticulosNombreNoSeleccionados, $boleta, 
                                   $destino,$codigo)
    {
                        		
        date_default_timezone_set('America/Costa_Rica');		
        $prestamo_Fecha = date_create('now')->format('Y-m-d H:i:s');
        $prestamo_Fecha_formato = date_create('now')->format('d/m/Y H:i:s');

        $sql = 'INSERT INTO t_prestamo (prestamo_fecha, 
                            prestamo_horaRetiro, 
                            prestamo_fechaDevolucion, 
                            prestamo_horaDevolucion,
                            prestamo_fechaRetiro, 
                            seccion_Id, 
                            software_Id, 
                            prestamo_uso,
                            prestamo_cedula_funcionario, 
                            prestamo_nombre_funcionario, 
                            prestamo_codigo_presupuestario, 
                            prestamo_nombre_solicitante,
                            prestamo_email_solicitante) 
                VALUES (:prestamo_fecha, 
                        :prestamo_horaRetiro, 
                        :prestamo_fechaDevolucion, 
                        :prestamo_horaDevolucion, 
                        :prestamo_fechaRetiro, 
                        :seccion_Id, 
                        :software_Id, 
                        :prestamo_uso, 
                        :prestamo_cedula_funcionario, 
                        :prestamo_nombre_funcionario, 
                        :prestamo_codigo_presupuestario, 
                        :prestamo_nombre_solicitante,
                        :prestamo_email_solicitante)';
                
        try {
		
		$stmt = $this->pdo->prepare($sql);	

        $this->pdo->beginTransaction(); 			
        
        $stmt->execute([
            ':prestamo_fecha' => $prestamo_Fecha,
            ':prestamo_fechaDevolucion' => $prestamo_fechaDevolucion,
            ':prestamo_fechaRetiro' => $prestamo_fechaRetiro,
            ':prestamo_horaRetiro' => $prestamo_horaRetiro,
            ':prestamo_horaDevolucion' => $prestamo_horaDevolucion,
            ':seccion_Id' => $seccion_Id,
            ':software_Id' => 1,
            ':prestamo_uso' => $prestamo_uso,
            ':prestamo_cedula_funcionario' => $prestamo_cedula_funcionario, 
            ':prestamo_nombre_funcionario' => $prestamo_nombre_funcionario, 
            ':prestamo_codigo_presupuestario' => $prestamo_codigo_presupuestario,
            ':prestamo_nombre_solicitante' => $prestamo_nombre_solicitante,
            ':prestamo_email_solicitante' => $para    
            ]);

        $prestamo_Id = $this->pdo->lastInsertId();

        $this->pdo->commit();     
        
        if(!empty($arrayArticulos)) {

            $this->pdo->beginTransaction();

            foreach($arrayArticulos as $key => $articulos) 
            {
                $prestamo_detalle_id_placa = $articulos;

                $sql = 'INSERT INTO t_prestamo_detalle (prestamo_Id, 
                                    prestamo_detalle_id_placa, 
                                    prestamo_detalle_devuelto, 
                                    prestamo_detalle_irregularidad, 
                                    prestamo_detalle_observacion, 
                                    prestamo_detalle_fechaDevolucion,
                                    prestamo_detalle_fechaRetiro,
                                    prestamo_detalle_horaDevolucion,
                                    prestamo_detalle_horaRetiro) 
                        VALUES (:prestamo_Id,
                                :prestamo_detalle_id_placa, 
                                :prestamo_detalle_devuelto, 
                                :prestamo_detalle_irregularidad, 
                                :prestamo_detalle_observacion, 
                                :prestamo_detalle_fechaDevolucion,
                                :prestamo_detalle_fechaRetiro,
                                :prestamo_detalle_horaDevolucion,
                                :prestamo_detalle_horaRetiro)';

                        $stmt = $this->pdo->prepare($sql);

                        $stmt->execute([
                            ':prestamo_Id' => $prestamo_Id,
                            ':prestamo_detalle_id_placa' => $prestamo_detalle_id_placa,
                            ':prestamo_detalle_devuelto' => 0,
                            ':prestamo_detalle_irregularidad' => 0,
                            ':prestamo_detalle_observacion' => "registrado mediante solicitud",
                            ':prestamo_detalle_fechaDevolucion' => $prestamo_fechaDevolucion,
                            ':prestamo_detalle_fechaRetiro' => $prestamo_fechaRetiro,
                            ':prestamo_detalle_horaDevolucion' => $prestamo_horaDevolucion,
                            ':prestamo_detalle_horaRetiro' => $prestamo_horaRetiro           
                            ]);
            }

            $this->pdo->commit(); 
        }
      
        $sql = 'UPDATE t_solicitud  SET solicitud_aprobada = :solicitud_aprobada WHERE solicitud_Id = :solicitud_Id';
        
        $stmt = $this->pdo->prepare($sql);
        
        $this->pdo->beginTransaction(); 			
        
        $stmt->execute([':solicitud_Id' => $solicitud_Id, ':solicitud_aprobada' => 1]);       
        
        $this->pdo->commit();       

        if(!empty($arraySoftwareId)) {
            
            $this->pdo->beginTransaction();
            
            foreach($arraySoftwareId as $key => $softwareId) {
                
                $id_cs = $softwareId["id_cs"];                

                $sql = 'INSERT INTO t_prestamo_detalle_cs (prestamo_Id, id_cs) 
                        VALUES (:prestamo_Id, :id_cs)';

                $stmt = $this->pdo->prepare($sql);

                $stmt->execute([':prestamo_Id' => $prestamo_Id,
                                ':id_cs' => $id_cs]);

            }

            $this->pdo->commit(); 
        }

        $stmt = null;
        $this->pdo = null;

        $email = new Email_Prestamo();
        		
        $evia_email = $email->email_Prestamo($Dependencia,
                                            $prestamo_uso, 
                                            $para, 
                                            $arrayArticulosNombre,
                                            $prestamo_nombre_solicitante,
                                            $prestamo_Fecha_formato,
                                            $prestamo_fechaRetiro_formato,
                                            $prestamo_horaRetiro,
                                            $prestamo_fechaDevolucion_formato,
                                            $prestamo_horaDevolucion,
                                            $arraySoftwareDescripcion,
                                            $seccionDescripcion,
                                            $arrayArticulosNombreNoSeleccionados);
    
        $evia_email = null;
       
        if ($boleta) {

            $emailBoleta = new Email_Boleta();
                
            $envia_Boleta = $emailBoleta->email_Boleta($Dependencia,
                                                    $para, 
                                                    $prestamo_nombre_solicitante,
                                                    $prestamo_Fecha_formato,
                                                    $prestamo_fechaRetiro_formato,
                                                    $prestamo_fechaDevolucion_formato,
                                                    $destino, $prestamo_Id, $codigo, $prestamo_uso);
        
            $envia_Boleta = null;
        }
         
        return true;
        
    } catch (\Throwable $th) {
                echo "Error al enviar email: " . $th->getMessage() . "\n";				
            }    
              
    }
} 

?>