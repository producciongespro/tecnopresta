<?php

require_once 'conexion.php';
require 'email_Solicitud.php';
require 'email_Solicitud_Notifica_Prestador.php';

class insertSolicitud {

    private $pdo;
    private $last;
    	
	function __construct()
	{
        $pdo = new \PDO(DB_Str, DB_USER, DB_PASS);		
        $this->pdo = $pdo;        
        
    }
        
    public function insertSolicitud($solicitud_fechaRetiro, $solicitud_horaRetiro, $solicitud_fechaDevolucion,
                                    $solicitud_horaDevolucion, $solicitud_uso, $arrayArticulos, 
                                    $solicitud_cedula_funcionario, $solicitud_nombre_funcionario, 
                                    $solicitud_codigo_presupuestario, $para, $arrayActivos, $Dependencia,
                                    $prestamo_fechaRetiro_formato, $prestamo_fechaDevolucion_formato,
                                    $arrayNombreAlias, $arraySoftwareDescripcion,
                                    $seccionDescripcion, $seccion_Id, $arraySoftwareId,
                                    $solicitud_uso_externo, $solicitud_boleta, $ambitoPrestadorSeleccionado){

        date_default_timezone_set('America/Costa_Rica');		
        $solicitud_Fecha = date_create('now')->format('Y-m-d H:i:s');
        $solicitud_Fecha_formato = date_create('now')->format('d/m/Y H:i:s');

        $sql = 'INSERT INTO t_solicitud (solicitud_fecha, solicitud_horaRetiro, 
                            solicitud_horaDevolucion, solicitud_fechaDevolucion, 
                            solicitud_fechaRetiro, seccion_Id, software_Id, solicitud_uso,
                            solicitud_cedula_funcionario, solicitud_nombre_funcionario, 
                            solicitud_email_funcionario, solicitud_codigo_presupuestario,
                            solicitud_uso_externo, solicitud_boleta) 
                VALUES (:solicitud_fecha, :solicitud_horaRetiro, :solicitud_horaDevolucion, 
                        :solicitud_fechaDevolucion, :solicitud_fechaRetiro, 
                        :seccion_Id, :software_Id, :solicitud_uso,
                        :solicitud_cedula_funcionario, 
                        :solicitud_nombre_funcionario,
                        :solicitud_email_funcionario,
                        :solicitud_codigo_presupuestario,
                        :solicitud_uso_externo,
                        :solicitud_boleta)';
                
        try {
		
		$stmt = $this->pdo->prepare($sql);				
        $this->pdo->beginTransaction(); 			
        $stmt->execute([
            ':solicitud_fecha' => $solicitud_Fecha,
            ':solicitud_horaRetiro' => $solicitud_horaRetiro,
            ':solicitud_horaDevolucion' => $solicitud_horaDevolucion,
            ':solicitud_fechaDevolucion' => $solicitud_fechaDevolucion,
            ':solicitud_fechaRetiro' => $solicitud_fechaRetiro,
            ':seccion_Id' => $seccion_Id,
            ':software_Id' => 1,
            ':solicitud_uso' => $solicitud_uso,
            ':solicitud_cedula_funcionario' => $solicitud_cedula_funcionario,
            ':solicitud_nombre_funcionario' => $solicitud_nombre_funcionario,
            ':solicitud_email_funcionario' => $para,
            ':solicitud_codigo_presupuestario' => $solicitud_codigo_presupuestario,
            ':solicitud_uso_externo' => $solicitud_uso_externo,
            ':solicitud_boleta' => $solicitud_boleta        
            ]);

        $solicitud_Id = $this->pdo->lastInsertId();

        $this->pdo->commit();     
        
        if(!empty($arrayArticulos)) {
            
            $this->pdo->beginTransaction();
            
            foreach($arrayArticulos as $key => $articulos) {
                
                $solicitud_detalle_alias_id = $articulos["solicitud_detalle_alias_id"];
                $solicitud_detalle_cantidad = $articulos["solicitud_detalle_cantidad"];               

                $sql = 'INSERT INTO t_solicitud_detalle (solicitud_Id, solicitud_detalle_alias_id, 
                                    solicitud_detalle_cantidad) 
                        VALUES (:solicitud_Id, :solicitud_detalle_alias_id, :solicitud_detalle_cantidad)';

                $stmt = $this->pdo->prepare($sql);

                $stmt->execute([':solicitud_detalle_alias_id' => $solicitud_detalle_alias_id,
                                ':solicitud_detalle_cantidad' => $solicitud_detalle_cantidad,  
                                ':solicitud_Id' => $solicitud_Id]);

            }

            $this->pdo->commit(); 
        }

        if(!empty($arrayActivos)) {
            
            $this->pdo->beginTransaction();
            
            foreach($arrayActivos as $key => $articulos) {
                
                $solicitud_detalle_id_activo = $articulos["solicitud_detalle_id_activo"];
                $solicitud_detalle_id_placa = $articulos["solicitud_detalle_id_placa"];                

                $sql = 'INSERT INTO t_solicitud_detalle (solicitud_Id, 
                                    solicitud_detalle_id_activo, solicitud_detalle_id_placa) 
                        VALUES (:solicitud_Id, :solicitud_detalle_id_activo,
                                :solicitud_detalle_id_placa)';

                $stmt = $this->pdo->prepare($sql);

                $stmt->execute([':solicitud_detalle_id_activo' => $solicitud_detalle_id_activo,
                                ':solicitud_Id' => $solicitud_Id, 
                                ':solicitud_detalle_id_placa' => $solicitud_detalle_id_placa]);

            }

            $this->pdo->commit(); 
        }

        if(!empty($arraySoftwareId)) {
            
            $this->pdo->beginTransaction();
            
            foreach($arraySoftwareId as $key => $softwareId) {
                
                //$solicitud_Id = $softwareId["solicitud_Id"];
                $id_cs = $softwareId["id_cs"];                

                $sql = 'INSERT INTO t_solicitud_detalle_cs (solicitud_Id, id_cs) 
                        VALUES (:solicitud_Id, :id_cs)';

                $stmt = $this->pdo->prepare($sql);

                $stmt->execute([':solicitud_Id' => $solicitud_Id,
                                ':id_cs' => $id_cs]);

            }

            $this->pdo->commit(); 
        }

        $stmt = null;
        $this->pdo = null;
        
        $email = new Email_Solicitud();
        		
        $evia_email = $email->email_Solicitud($Dependencia,
                                            $solicitud_uso, 
                                            $para,                                 
                                            $solicitud_nombre_funcionario,
                                            $solicitud_Fecha_formato,
                                            $prestamo_fechaRetiro_formato,
                                            $solicitud_horaRetiro,
                                            $prestamo_fechaDevolucion_formato,
                                            $solicitud_horaDevolucion,
                                            $arrayNombreAlias,
                                            $arraySoftwareDescripcion,
                                            $seccionDescripcion, $arrayActivos);
    
        $evia_email = null;

        $emailPrestador = new Email_Solicitud_Notifica_Prestador();

        $envia_emailPrestador = $emailPrestador->email_Solicitud_Notifica_Prestador($ambitoPrestadorSeleccionado);
    
        $envia_emailPrestador = null;
        
        return true; 
        
    } catch (\Throwable $th) {
        echo "Error al guardar solicitud: " . $th->getMessage() . "\n";				
        }    
              
    } 
} 

?>