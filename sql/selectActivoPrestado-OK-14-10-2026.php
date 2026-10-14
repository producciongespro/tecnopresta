<?php
class SelectActivoPrestado 
{

    private $pdo;
       	
	function __construct()
	{

        $pdo = new \PDO(DB_Str, DB_USER, DB_PASS, 
                    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
                    
        $this->pdo = $pdo;        
        
    }
    
    function selectActivoPrestado($arrayArticulos, $codigo,
                                $fechaRetiro, $fechaDevolucion,
                                $horaRetiro, $horaDevolucion)
    {

        if ($this->pdo != null) 
        {
                        
            foreach($arrayArticulos as $id_placa)
            {			

                $consultaSQL = "SELECT clase, modelo, marca, color, 
                                        numero_activo, t_placa.id_placa, 
                                        prestamo_detalle_fechaRetiro,
                                        prestamo_detalle_fechaDevolucion,
                                        prestamo_detalle_horaDevolucion,
                                        prestamo_detalle_horaRetiro, 
                                        prestamo_uso, 
                                        prestamo_nombre_solicitante 
                                FROM t_activo
                                INNER JOIN t_activo_general
                                    ON t_activo_general.id_ag = t_activo.id_ag
                                INNER JOIN t_marca 
                                    ON t_activo.id_marca = t_marca.id_marca
                                INNER JOIN t_color
                                    ON t_color.id_color = t_activo.id_color
                                INNER JOIN t_placa
                                    ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'  
                                INNER JOIN t_prestamo_detalle
                                    ON t_prestamo_detalle.prestamo_detalle_id_placa = t_placa.id_placa
                                INNER JOIN t_prestamo
                                    ON t_prestamo_detalle.prestamo_Id = t_prestamo.prestamo_Id
                                WHERE prestamo_detalle_devuelto = 0 
                                AND t_prestamo_detalle.prestamo_detalle_id_placa = " . $id_placa;


                $sql = $this->pdo->query($consultaSQL);

                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) 
                {
                    $ranges = array(
                        array('start' => 
                                $row['prestamo_detalle_fechaRetiro'] . " ". $row['prestamo_detalle_horaRetiro'], 
                            'end' => 
                                $row['prestamo_detalle_fechaDevolucion'] . " ". $row['prestamo_detalle_horaDevolucion']),
                        array('start' => 
                                $fechaRetiro . " ". $horaRetiro, 
                            'end' => 
                                $fechaDevolucion . " ". $horaDevolucion)    
                    );
                   
                    $res = $ranges[0];

                    $countRanges = count($ranges);
            
                    for ($i = 1; $i < $countRanges; $i++) {
                        
                        $r1s = new DateTime($res['start']);
                        $r1e = new DateTime($res['end']);
                                            
                        $r2s = new DateTime($ranges[$i]['start']);
                        $r2e = new DateTime($ranges[$i]['end']);
                                   
                        if ($r1s >= $r2s && $r1s <= $r2e || 
                            $r1e >= $r2s && $r1e <= $r2e || 
                            $r2s >= $r1s && $r2s <= $r1e || 
                            $r2e >= $r1s && $r2e <= $r1e) 
                        {   
                            date_default_timezone_set('America/Costa_Rica');		                            
                                        
                            return $row['modelo'] . " " . 
                                    $row['marca'] . " " .
                                    $row['numero_activo'] . " " .
                                    "ESTA PRESTADO A" . " " .
                                    $row['prestamo_nombre_solicitante'] . " " .
                                    "DESDE EL" . " " . 
                                    date_create($row['prestamo_detalle_fechaRetiro'])->format('d/m/Y') . " " .
                                    $row['prestamo_detalle_horaRetiro'] . " " .
                                    "HASTA EL" . " " .
                                    date_create($row['prestamo_detalle_fechaDevolucion'])->format('d/m/Y')  . " " .                                   
                                    $row['prestamo_detalle_horaDevolucion'];                            
            
                        }
            
                    }                    
                                               
                }
            }
            
		}
        
		$this->pdo = null;

        return "ok";

    }
    
            
}
?>