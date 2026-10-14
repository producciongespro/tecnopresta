<?php

require_once 'conexion.php';

class sql {

	function conActivo($nombreActivo, $codigo){
		
		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if (empty($nombreActivo)) {

			$consultaSQL = "SELECT t_placa.id_activo, clase, marca, modelo, imagen, 
							t_placa.id_placa, placa, numero_activo
							FROM t_placa
							INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
							INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
							WHERE codigo = '$codigo' and prestar = 1 AND (alias_id is null OR alias_id = 0) 
							ORDER BY clase, placa, numero_activo ASC";

		} else {

			$consultaSQL = "SELECT t_placa.id_activo, clase, marca, modelo, imagen, 
							t_placa.id_placa, placa, numero_activo
							FROM t_placa
							INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
							INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
							WHERE codigo = '$codigo' and prestar = 1 AND (alias_id is null OR alias_id = 0)
							AND (clase LIKE '%$nombreActivo%' OR marca LIKE '%$nombreActivo%' OR modelo LIKE '%$nombreActivo%')
							ORDER BY clase, placa, numero_activo ASC";			
		}

		if ($pdo != null) {								

			$sql = $pdo->query($consultaSQL);

			$rs = [];

			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {

					$rs[] = [						
						'id_activo' => $row['id_activo'],	                
						'clase' => $row['clase'],
						'marca' => $row['marca'],
						'modelo' => $row['modelo'],
						'imagen' => $row['imagen'],
						'id_placa' => $row['id_placa'],
						'placa' => $row['placa'],
						'numero_activo' => $row['numero_activo']						
					];	
			}

            return $rs;
		}

		$pdo = null;

	}
	
	function conActivoId($id_activo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT id_activo, etiqueta, marca, 
							descripcion, imagen FROM t_activo						
								INNER JOIN t_marca 
									ON t_activo.id_marca = t_marca.id_marca 								
							WHERE id_activo = $id_activo";

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'id_activo' => $row['id_activo'],	                
						'etiqueta' => $row['etiqueta'],
						'marca' => $row['marca'],
						'descripcion' => $row['descripcion'],
						'imagen' => $row['imagen']									
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conMarca(){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT id_marca, marca FROM t_marca	ORDER BY marca";
			$sql = $pdo->query($consultaSQL);
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'id_marca' => $row['id_marca'],	                
						'marca' => $row['marca']									
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conAlias(){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT alias_id, alias FROM t_alias	ORDER BY alias";
			$sql = $pdo->query($consultaSQL);
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'alias_id' => $row['alias_id'],	                
						'alias' => $row['alias']									
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conActivoAliasId($alias_id){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT t_alias.alias_id, alias, alias_imagen, 
							(disponible_cantidad - disponible_prestado) AS disponible 
							FROM t_alias						
								INNER JOIN t_disponible 
									ON t_alias.alias_id = t_disponible.alias_id
									WHERE t_alias.alias_Id = $alias_id";

			$sql = $pdo->query($consultaSQL);
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'alias_id' => $row['alias_id'],	                
						'alias' => $row['alias'],
						'alias_imagen' => $row['alias_imagen'],
						'disponible' => $row['disponible']									
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conActivoAlias($aliasActivo, $codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if (empty($aliasActivo)) {

			$consultaSQL = "SELECT t_alias.alias_id, alias, alias_imagen							 
							FROM t_alias						
							WHERE codigo = $codigo ORDER BY alias";

		} else {

			$consultaSQL = "SELECT t_alias.alias_id, alias, alias_imagen 
							FROM t_alias						
							WHERE codigo = $codigo and alias like '%$aliasActivo%' 
							ORDER BY alias";			
		}

		if ($pdo != null) {								

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'alias_id' => $row['alias_id'],	                
						'alias' => $row['alias'],
						'alias_imagen' => $row['alias_imagen']						
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}


	function conSolicitud($codigo){
	    
		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
				
		$consultaSQL = "SELECT t_solicitud.solicitud_Id, solicitud_fecha, solicitud_fechaRetiro, 
						solicitud_fechaDevolucion, solicitud_uso, solicitud_nombre_funcionario,
						solicitud_horaRetiro, solicitud_horaDevolucion					
						FROM  t_solicitud
						WHERE t_solicitud.solicitud_codigo_presupuestario = '$codigo' and solicitud_aprobada = 0
						ORDER BY solicitud_fechaRetiro,solicitud_fechaDevolucion";
		
		if ($pdo != null) {								
            
			$sql = $pdo->query($consultaSQL);
            
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			        
					$rs[] = [											
						'solicitud_Id' => $row['solicitud_Id'],
						'solicitud_fecha' => $row['solicitud_fecha'],
						'solicitud_fechaRetiro' => $row['solicitud_fechaRetiro'],
						'solicitud_fechaDevolucion' => $row['solicitud_fechaDevolucion'],
						'solicitud_uso' => $row['solicitud_uso'],
						'solicitud_nombre_funcionario' => $row['solicitud_nombre_funcionario'],
						'solicitud_horaRetiro' => $row['solicitud_horaRetiro'],
						'solicitud_horaDevolucion' => $row['solicitud_horaDevolucion']					
					];
			}
			if (count($rs) == 0){
			    return null;
			}else{
			    return $rs;
			}
		}	
		$pdo = null;
	}

	function conPrestamoEncabezado($prestamo_Id, $codigo) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
				
		$consultaSQL = "SELECT prestamo_Id, prestamo_fecha, 
								prestamo_fechaRetiro, 
								prestamo_horaRetiro, 
								prestamo_fechaDevolucion, 
								prestamo_horaDevolucion, 
								prestamo_uso, 
								prestamo_nombre_funcionario,
								prestamo_email_solicitante,
								prestamo_nombre_solicitante,
								prestamo_incidente_comentario					
						FROM  t_prestamo
						WHERE prestamo_Id = $prestamo_Id and 
								prestamo_codigo_presupuestario = '$codigo'";
		
		if ($pdo != null) {								

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [											
						'prestamo_Id' => $row['prestamo_Id'],
						'prestamo_fecha' => $row['prestamo_fecha'],
						'prestamo_fechaRetiro' => $row['prestamo_fechaRetiro'],
						'prestamo_horaRetiro' => $row['prestamo_horaRetiro'],
						'prestamo_fechaDevolucion' => $row['prestamo_fechaDevolucion'],
						'prestamo_horaDevolucion' => $row['prestamo_horaDevolucion'],
						'prestamo_uso' => $row['prestamo_uso'],
						'prestamo_nombre_funcionario' => $row['prestamo_nombre_funcionario'],
						'prestamo_email_solicitante' => $row['prestamo_email_solicitante'],
						'prestamo_nombre_solicitante' => $row['prestamo_nombre_solicitante'],
						'prestamo_incidente_comentario' => $row['prestamo_incidente_comentario']										
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conPrestamoDetalleActivo($prestamo_Id, $codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT t_activo.id_activo, clase, modelo, marca, color, 
							numero_activo, t_placa.id_placa, placa 
							FROM t_activo						
							INNER JOIN t_marca 
								ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_color
								ON t_color.id_color = t_activo.id_color
							INNER JOIN t_activo_general
								ON t_activo_general.id_ag = t_activo.id_ag
							INNER JOIN t_placa
								ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'  
							INNER JOIN t_prestamo_detalle
								ON t_prestamo_detalle.prestamo_detalle_id_placa = t_placa.id_placa
							WHERE t_prestamo_detalle.prestamo_Id = $prestamo_Id AND prestamo_detalle_devuelto = 0";

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'id_placa' => $row['id_placa'],
						'clase' => $row['clase'],	                						
						'modelo' => $row['modelo'],
						'marca' => $row['marca'],
						'color' => $row['color'],
						'numero_activo' => $row['numero_activo'],
						'placa' => $row['placa']
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conSolicitudEncabezado($solicitud_Id) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
				
		$consultaSQL = "SELECT solicitud_Id, solicitud_fecha, solicitud_fechaRetiro,
						solicitud_horaRetiro, solicitud_fechaDevolucion, solicitud_horaDevolucion, 
						solicitud_uso, solicitud_nombre_funcionario, solicitud_email_funcionario,
						seccion_Id, solicitud_uso_externo, solicitud_boleta					
						FROM  t_solicitud
						WHERE solicitud_Id = $solicitud_Id";
		
		if ($pdo != null) {								

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [											
						'solicitud_Id' => $row['solicitud_Id'],
						'solicitud_fecha' => $row['solicitud_fecha'],
						'solicitud_fechaRetiro' => $row['solicitud_fechaRetiro'],
						'solicitud_horaRetiro' => $row['solicitud_horaRetiro'],
						'solicitud_fechaDevolucion' => $row['solicitud_fechaDevolucion'],
						'solicitud_horaDevolucion' => $row['solicitud_horaDevolucion'],
						'solicitud_uso' => $row['solicitud_uso'],
						'solicitud_nombre_funcionario' => $row['solicitud_nombre_funcionario'],
						'solicitud_email_funcionario' => $row['solicitud_email_funcionario'],
						'seccion_Id' => $row['seccion_Id'],
						'solicitud_uso_externo' => $row['solicitud_uso_externo'],
						'solicitud_boleta' => $row['solicitud_boleta']					
					];
			}
            return $rs;
		}	
		$pdo = null;
	}
	
	function conSolicitudDetalle($solicitud_Id) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
				
		$consultaSQL = "SELECT solicitud_detalle_cantidad, alias, alias_imagen 
						FROM t_solicitud_detalle
						INNER JOIN t_alias 
						ON t_solicitud_detalle.solicitud_detalle_alias_id = t_alias.alias_id
						WHERE solicitud_Id = $solicitud_Id";
		
		if ($pdo != null) {								

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [											
						'solicitud_detalle_cantidad' => $row['solicitud_detalle_cantidad'],
						'alias' => $row['alias'],
						'alias_imagen' => $row['alias_imagen']					
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conSolicitudDetalleActivos($solicitud_Id) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
				
		$consultaSQL = "SELECT solicitud_detalle_id_activo, clase, marca, 
								modelo, imagen, placa, numero_activo 
						FROM t_solicitud_detalle
						INNER JOIN t_activo ON t_solicitud_detalle.solicitud_detalle_id_activo = t_activo.id_activo
						INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
						INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
						INNER JOIN t_placa ON t_solicitud_detalle.solicitud_detalle_id_placa = t_placa.id_placa
						WHERE solicitud_Id = $solicitud_Id";
		
		if ($pdo != null) {								

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [											
						'solicitud_detalle_id_activo' => $row['solicitud_detalle_id_activo'],
						'clase' => $row['clase'],
						'marca' => $row['marca'],
						'modelo' => $row['modelo'],
						'imagen' => $row['imagen'],
						'placa' => $row['placa'],
						'numero_activo' => $row['numero_activo']					
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conSolicitudDetalleAlias($solicitud_Id, $codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {								

				$consultaSQL = "SELECT id_placa, clase, modelo, marca, color, 
								numero_activo, solicitud_detalle_cantidad, placa, serial 
								FROM t_activo
								INNER JOIN t_activo_general
								ON t_activo_general.id_ag = t_activo.id_ag						
								INNER JOIN t_marca 
									ON t_activo.id_marca = t_marca.id_marca
								INNER JOIN t_color
									ON t_color.id_color = t_activo.id_color
								INNER JOIN t_placa
									ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'
								INNER JOIN t_solicitud_detalle
									ON t_solicitud_detalle.solicitud_detalle_alias_id = t_placa.alias_id 
										and t_solicitud_detalle.solicitud_detalle_alias_id <> 0
								WHERE t_solicitud_detalle.solicitud_Id = $solicitud_Id";

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'id_placa' => $row['id_placa'],
						'modelo' => $row['modelo'],	                						
						'clase' => $row['clase'],
						'marca' => $row['marca'],
						'color' => $row['color'],
						'numero_activo' => $row['numero_activo'],
						'placa' => $row['placa'],
						'solicitud_detalle_cantidad' => $row['solicitud_detalle_cantidad']
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}


	function conSolicitudDetalleActivo($solicitud_Id, $codigo) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT id_placa, clase, modelo, marca, color, placa, numero_activo 
							FROM t_activo						
							INNER JOIN t_marca 
								ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_color
								ON t_color.id_color = t_activo.id_color
							INNER JOIN t_activo_general
								ON t_activo_general.id_ag = t_activo.id_ag
							INNER JOIN t_placa
								ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'
							INNER JOIN t_solicitud_detalle
								ON t_solicitud_detalle.solicitud_detalle_id_activo = t_placa.id_activo AND
								   t_solicitud_detalle.solicitud_detalle_id_placa = t_placa.id_placa
							WHERE t_solicitud_detalle.solicitud_Id = $solicitud_Id";

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'id_placa' => $row['id_placa'],
						'clase' => $row['clase'],	                						
						'modelo' => $row['modelo'],
						'marca' => $row['marca'],
						'color' => $row['color'],
						'placa' => $row['placa'],
						'numero_activo' => $row['numero_activo']								
					];	
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conCantidadSolicitudes($codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT COUNT(*) AS cantidad FROM t_solicitud							
							WHERE solicitud_codigo_presupuestario = '$codigo' and
							solicitud_aprobada = 0";

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'cantidad' => $row['cantidad']																		
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conCantidadPrestamo($codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT COUNT(*) AS cantidad FROM t_prestamo_detalle
							INNER JOIN t_prestamo 
							ON t_prestamo_detalle.prestamo_Id = t_prestamo.prestamo_Id
							WHERE prestamo_codigo_presupuestario = '$codigo' and
							prestamo_detalle_devuelto = 0";

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'cantidad' => $row['cantidad']																		
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conVistaDevolucion($codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT
							t_prestamo.prestamo_Id, 
							prestamo_fechaRetiro,
							prestamo_detalle_fechaDevolucion,
							prestamo_detalle_id_activo,
							prestamo_nombre_solicitante,
							alias_imagen,alias							
							FROM
								t_prestamo
							INNER JOIN
								t_prestamo_detalle ON t_prestamo_detalle.prestamo_Id = t_prestamo.prestamo_Id
							INNER JOIN     
								t_placa ON t_placa.id_activo = t_prestamo_detalle.prestamo_detalle_id_activo
							INNER JOIN
								t_alias ON t_alias.alias_id  = t_placa.alias_id
							WHERE
								prestamo_detalle_devuelto = 0 and prestamo_codigo_presupuestario = $codigo
							ORDER BY t_prestamo.prestamo_fecha DESC;";

				$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'prestamo_Id' => $row['prestamo_Id'],						
						'prestamo_fechaRetiro' => $row['prestamo_fechaRetiro'],
						'prestamo_detalle_fechaDevolucion' => $row['prestamo_detalle_fechaDevolucion'],
						'prestamo_nombre_solicitante' => $row['prestamo_nombre_solicitante'],
						'alias_imagen' => $row['alias_imagen'],
						'alias' => $row['alias']
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conVistaDevolucionEncabezado($codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT DISTINCT
							t_prestamo.prestamo_Id, prestamo_fecha, 
							prestamo_fechaRetiro, prestamo_horaRetiro,
							prestamo_fechaDevolucion, prestamo_horaDevolucion,						
							prestamo_nombre_solicitante											
							FROM
								t_prestamo
							INNER JOIN
								t_prestamo_detalle ON t_prestamo_detalle.prestamo_Id = t_prestamo.prestamo_Id
							WHERE
								prestamo_detalle_devuelto = 0 and prestamo_codigo_presupuestario = '$codigo'
							ORDER BY prestamo_fecha DESC;";

				$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'prestamo_Id' => $row['prestamo_Id'],						
						'prestamo_fechaRetiro' => $row['prestamo_fechaRetiro'],
						'prestamo_horaRetiro' => $row['prestamo_horaRetiro'],
						'prestamo_fechaDevolucion' => $row['prestamo_fechaDevolucion'],
						'prestamo_horaDevolucion' => $row['prestamo_horaDevolucion'],
						'prestamo_nombre_solicitante' => $row['prestamo_nombre_solicitante']
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conVistaDevolucionDetalle($prestamo_Id, $codigo) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT alias_imagen, alias, modelo, marca  
							FROM t_activo						
							INNER JOIN t_marca 
								ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_color
								ON t_color.id_color = t_activo.id_color
							INNER JOIN t_placa
								ON t_placa.id_activo = t_activo.id_activo and t_placa.codigo = '$codigo'  
							INNER JOIN t_prestamo_detalle
								ON t_prestamo_detalle.prestamo_detalle_id_activo = t_placa.id_activo
							INNER JOIN t_alias
								ON t_alias.alias_id  = t_placa.alias_id  and t_alias.codigo = '$codigo' 
							WHERE t_prestamo_detalle.prestamo_Id = $prestamo_Id AND prestamo_detalle_devuelto = 0";

				$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'alias_imagen' => $row['alias_imagen'],						
						'alias' => $row['alias'],
						'modelo' => $row['modelo'],
						'marca' => $row['marca']
					];
			}
            return $rs;
		}	
		$pdo = null;
	}

	function conVistaDevolucionDetalleActivos($prestamo_Id, $codigo) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		if ($pdo != null) {		
			 
			$consultaSQL = "SELECT clase, marca, modelo, imagen, placa, numero_activo   
							FROM t_activo						
							INNER JOIN t_marca 
								ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_activo_general
								ON t_activo_general.id_ag = t_activo.id_ag
							INNER JOIN t_placa
								ON t_placa.id_activo = t_activo.id_activo and t_placa.codigo = '$codigo'  
							INNER JOIN t_prestamo_detalle
								ON t_prestamo_detalle.prestamo_detalle_id_placa = t_placa.id_placa
							WHERE t_prestamo_detalle.prestamo_Id = $prestamo_Id AND prestamo_detalle_devuelto = 0";

			/* $consultaSQL = "SELECT clase, marca, modelo, imagen   
							FROM t_activo						
							INNER JOIN t_marca 
								ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_activo_general
								ON t_activo_general.id_ag = t_activo.id_ag							
							INNER JOIN t_prestamo_detalle
								ON t_prestamo_detalle.prestamo_detalle_id_activo = id_activo
							WHERE t_prestamo_detalle.prestamo_Id = $prestamo_Id AND prestamo_detalle_devuelto = 0"; */

			$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'imagen' => $row['imagen'],						
						'clase' => $row['clase'],
						'modelo' => $row['modelo'],
						'marca' => $row['marca'],
						'placa' => $row['placa'],
						'numero_activo' => $row['numero_activo']
					];
			}
            return $rs;
		}	
		$pdo = null;
	}


	function conDisponible($codigo, $alias_id) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		if ($pdo != null) {		
			
			$consultaSQL = "SELECT count(*) AS disponible FROM t_placa WHERE codigo = '$codigo' and prestar = 1
							and alias_id = $alias_id";

				$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'disponible' => $row['disponible']
							];
			}
            return $rs;
		}	
		$pdo = null;

	}

	function conRechazoSolictud() {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		if ($pdo != null) {
			
			$consultaSQL = "SELECT * FROM t_motivo_rechazo_solicitud";

				$sql = $pdo->query($consultaSQL);

			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = ['motivo_rechazo_solicitud' => $row['motivo_rechazo_solicitud']];
			}
            return $rs;
		}	
		$pdo = null;

	}

	function obtienePermisosMenuPrestamo($cedula, $codigo) {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		$rs = [];

		if ($pdo != null) {
			
			$consultaSQL = "SELECT id_rol FROM t_lista_blanca 
							WHERE cedula = '$cedula' AND 
							codigo = '$codigo'";

			$sql = $pdo->query($consultaSQL);			

			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = ['id_rol' => $row['id_rol']];
			}
            
		}

		$pdo = null;
		return $rs;
	}

	function conCaracteristica_software() {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		$rs = [];

		if ($pdo != null) {
			
			$consultaSQL = "SELECT * FROM t_caracteristica_software ORDER BY caracteristica";

			$sql = $pdo->query($consultaSQL);			

			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				$rs[] = [
					'id_cs' => $row['id_cs'],						
					'caracteristica' => $row['caracteristica']
				];
			}
            
		}

		$pdo = null;
		return $rs;
	}

	function conSeccion() {

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
		$rs = [];

		if ($pdo != null) {
			
			$consultaSQL = "SELECT * FROM t_seccion";

			$sql = $pdo->query($consultaSQL);			

			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				$rs[] = [
					'seccion_Id' => $row['seccion_Id'],						
					'seccion_descripcion' => $row['seccion_descripcion']
				];
			}
            
		}

		$pdo = null;
		return $rs;
	}

	function conSolicitudDetalle_cs($solicitud_Id){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {								
	
				$consultaSQL = "SELECT id_cs FROM t_solicitud_detalle_cs
								WHERE solicitud_Id = $solicitud_Id";
	
			$sql = $pdo->query($consultaSQL);
	
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [						
						'id_cs' => $row['id_cs']
					];	
			}
			return $rs;
		}	
		$pdo = null;
	}

	function conRoles($codigo){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {								
	
				$consultaSQL = "SELECT id_lista_blanca, codigo, cedula, nombre, rol, descripcion, imagen 
								FROM t_lista_blanca 
								INNER JOIN t_roles ON t_lista_blanca.id_rol = t_roles.id_rol 
								WHERE codigo = '$codigo'";
	
			$sql = $pdo->query($consultaSQL);
	
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'id_lista_blanca' => $row['id_lista_blanca'],						
						'codigo' => $row['codigo'],
						'cedula' => $row['cedula'],
						'nombre' => $row['nombre'],
						'rol' => $row['rol'],
						'descripcion' => $row['descripcion'],
						'imagen' => $row['imagen']
					];	
			}
			return $rs;
		}	
		$pdo = null;
	}

	function conRolesTabla(){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));

		if ($pdo != null) {								
	
			$consultaSQL = "SELECT * FROM t_roles";
	
			$sql = $pdo->query($consultaSQL);
	
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'id_rol' => $row['id_rol'],						
						'rol' => $row['rol']
					];	
			}
			return $rs;
		}	
		$pdo = null;
	}

	function conRol_x_id_lista_blanca($id_lista_blanca){

		// el array es para que el JSON funcione con tíldes
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		
		if ($pdo != null) {								
	
				$consultaSQL = "SELECT codigo, cedula, nombre, id_rol
								FROM t_lista_blanca
								WHERE id_lista_blanca = $id_lista_blanca";
	
			$sql = $pdo->query($consultaSQL);
	
			$rs = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$rs[] = [
						'codigo' => $row['codigo'],						
						'cedula' => $row['cedula'],
						'nombre' => $row['nombre'],
						'id_rol' => $row['id_rol']
					];	
			}
			return $rs;
		}	
		$pdo = null;
	}
	
function conFondos() {

	// el array es para que el JSON funcione con tíldes
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));

	$rs = [];

	if ($pdo != null) {
		
		$consultaSQL = "SELECT * FROM t_fondos";

		$sql = $pdo->query($consultaSQL);			

		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
			$rs[] = [
				'id_fondos' => $row['id_fondos'],						
				'fondos' => $row['fondos']
			];
		}
		
	}

	$pdo = null;
	return $rs;
}

function conActivos_por_Fondos($id_fondos, $codigo){

	// el array es para que el JSON funcione con tíldes
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
	if ($pdo != null) {								

			$consultaSQL = "SELECT id_placa, clase, modelo, marca, placa, id_estado, enuso, donar 
							FROM t_activo
							INNER JOIN t_activo_general
							ON t_activo_general.id_ag = t_activo.id_ag						
							INNER JOIN t_marca 
								ON t_activo.id_marca = t_marca.id_marca							
							INNER JOIN t_placa
								ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'							
							WHERE id_fondos = $id_fondos and activo = 1
							ORDER BY placa ASC";

		$sql = $pdo->query($consultaSQL);

		$rs = [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				$rs[] = [						
					'id_placa' => $row['id_placa'],
					'modelo' => $row['modelo'],	                						
					'clase' => $row['clase'],
					'marca' => $row['marca'],				
					'placa' => $row['placa'],
					'id_estado' => $row['id_estado'],
					'enuso' => $row['enuso'],
					'donar' => $row['donar']
				];
		}
		return $rs;
	}	
	$pdo = null;
}

function conActivos_Ubicacion_por_Fondos($id_fondos, $codigo){

	// el array es para que el JSON funcione con tíldes
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
	if ($pdo != null) {								

			$consultaSQL = "SELECT id_placa, clase, modelo, marca, placa, id_estado, 
								enuso, donar, lugar
							FROM t_activo
							INNER JOIN t_activo_general
							ON t_activo_general.id_ag = t_activo.id_ag						
							INNER JOIN t_marca
								ON t_activo.id_marca = t_marca.id_marca
							INNER JOIN t_placa
								ON t_placa.id_activo = t_activo.id_activo and codigo = '$codigo'
							INNER JOIN t_lugar
								ON t_placa.id_lugar = t_lugar.id_lugar							
							WHERE id_fondos = $id_fondos and activo = 1
							ORDER BY placa ASC";

		$sql = $pdo->query($consultaSQL);

		$rs = [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				$rs[] = [						
					'id_placa' => $row['id_placa'],
					'modelo' => $row['modelo'],	                						
					'clase' => $row['clase'],
					'marca' => $row['marca'],				
					'placa' => $row['placa'],
					'id_estado' => $row['id_estado'],
					'enuso' => $row['enuso'],
					'donar' => $row['donar'],
					'lugar' => $row['lugar']
				];
		}
		return $rs;
	}	
	$pdo = null;
}

function conActivos_Consolidado_por_Fondos($id_fondos){

	// el array es para que el JSON funcione con tíldes
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	
	if ($pdo != null) {								

			$consultaSQL = "SELECT t_placa.codigo, institucion, placa, serial, clase, modelo, 
							marca, estado, enuso, donar, lugar, fondos 
							FROM t_activo 
							INNER JOIN t_activo_general ON t_activo_general.id_ag = t_activo.id_ag 
							INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca 
							INNER JOIN t_placa ON t_placa.id_activo = t_activo.id_activo 
							INNER JOIN t_lugar ON t_placa.id_lugar = t_lugar.id_lugar 
							INNER JOIN t_estado ON t_placa.id_estado = t_estado.id_estado 
							INNER JOIN t_fondos ON t_placa.id_fondos = t_fondos.id_fondos 
							INNER JOIN instituciones ON instituciones.codigo = t_placa.codigo 
							WHERE t_placa.id_fondos = $id_fondos and activo = 1 
							ORDER BY t_placa.codigo, placa, clase, marca ASC";

		$sql = $pdo->query($consultaSQL);

		$rs = [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				$rs[] = [						
					'codigo' => $row['t_placa.codigo'],
					'institucion' => $row['institucion'],	                						
					'placa' => $row['placa'],
					'serie' => $row['serial'],				
					'clase' => $row['clase'],
					'modelo' => $row['modelo'],
					'marca' => $row['marca'],
					'estado' => $row['estado'],
					'enuso' => $row['enuso'],
					'donar' => $row['donar'],
					'lugar' => $row['lugar'],				
					'fondos' => $row['fondos']
					];
		}
		return $rs;
	}	
	$pdo = null;
}

function conInstitucion($institucion){
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
	if (empty($institucion)) {
		$consultaSQL = "SELECT * FROM instituciones ORDER BY institucion";
	} else {
		$consultaSQL = "SELECT * FROM instituciones						
						WHERE codigo like '%$institucion%' OR institucion like '%$institucion%' 
						ORDER BY institucion";			
	}
	if ($pdo != null) {								
		$sql = $pdo->query($consultaSQL);
		$rs = [];
		while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				$rs[] = [						
					'codigo' => $row['codigo'],	                
					'institucion' => $row['institucion']
				];	
		}
		return $rs;
	}	
	$pdo = null;
}

function conVisitasAsignadas_x_cedula($cedula, $estado) {
		
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
        
		if ($pdo != null){					           

            $consultaSQL = $pdo->prepare('SELECT * FROM t_analista 
										INNER JOIN analistas_visita ON
										t_analista.id_analista = analistas_visita.id_analista
										INNER JOIN visitas_sitio ON
										visitas_sitio.id_visita = analistas_visita.id_visita
										WHERE t_analista.cedula = :cedula 
										AND visitas_sitio.estado= :estado ORDER BY prioridad');

            $consultaSQL->bindParam(':cedula', $cedula, PDO::PARAM_STR);
            $consultaSQL->bindParam(':estado', $estado, PDO::PARAM_STR);
            $consultaSQL->execute();
            $rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);
            return $rs;
        }   
        $pdo = null;
}    

function conEstadoVisitas() {
		
		$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
        
		if ($pdo != null){	

            $consultaSQL = $pdo->prepare('SELECT * FROM t_estado_visitas');           
            $consultaSQL->execute();
            $rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

            return $rs;

        }  

        $pdo = null;
}

function conVisita_id($id_visita) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null){	

		$consultaSQL = $pdo->prepare('SELECT * FROM visitas_sitio WHERE visitas_sitio.id_visita =:id_visita');
		$consultaSQL->bindParam(':id_visita', $id_visita, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function convisitas_sitio_hoja_trabajo_id($id_visita) {
		
	//$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	]);        
	
	if ($pdo != null){	

		$consultaSQL = $pdo->prepare('SELECT * FROM visitas_sitio_hoja_trabajo
									WHERE id_visita =:id_visita');
		$consultaSQL->bindParam(':id_visita', $id_visita, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function confirma($visitas_sitio_hoja_trabajo_id) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null){	

		$consultaSQL = $pdo->prepare('SELECT visitas_sitio_hoja_trabajo_firma 
									FROM visitas_sitio_hoja_trabajo_firma
									WHERE visitas_sitio_hoja_trabajo_id = :visitas_sitio_hoja_trabajo_id');
		$consultaSQL->bindParam(':visitas_sitio_hoja_trabajo_id', $visitas_sitio_hoja_trabajo_id, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function con_articulo_en_hoja_de_trabajo($valor,$codigo) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null){	

		if (empty($valor)) {

		$consultaSQL = $pdo->prepare('SELECT t_placa.id_activo, clase, marca, modelo, placa, serial AS serie, imagen, id_placa
									FROM t_placa
									INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
									INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
									INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
									WHERE codigo = :codigo ORDER BY clase, marca, modelo, placa, serie ASC;');
		$consultaSQL->bindParam(':codigo', $codigo, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);
		return $rs;

	} else {
		$consultaSQL = $pdo->prepare('SELECT t_placa.id_activo, clase, marca, modelo, placa, serial AS serie, imagen, id_placa
									FROM t_placa
									INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
									INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
									INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag 
									WHERE codigo = :codigo AND 
									(clase LIKE :valor OR marca LIKE :valor 
									OR modelo LIKE :valor OR placa LIKE :valor OR serial LIKE :valor)
									ORDER BY clase, marca, modelo, placa, serie ASC;');

		$consultaSQL->bindParam(':codigo', $codigo, PDO::PARAM_STR);
		$search_term_with_wildcards = "%" . $valor . "%";
		$consultaSQL->bindParam(':valor', $search_term_with_wildcards, PDO::PARAM_STR);

		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);
		return $rs;
	}

	return false;

	}

	$pdo = null;
	return false;
}

function conlista_activos_sitio_hoja_trabajo($visitas_sitio_hoja_trabajo_id) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null) {

		$consultaSQL = $pdo->prepare('SELECT t_placa.id_activo, clase, marca, modelo, placa, serial AS serie, imagen, id_placa 
                                    FROM visitas_sitio_hoja_trabajo_activos
                                    INNER JOIN t_placa ON visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_id_placa=t_placa.id_placa
                                    INNER JOIN t_activo ON t_placa.id_activo = t_activo.id_activo
                                    INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca
                                    INNER JOIN t_activo_general ON t_activo.id_ag = t_activo_general.id_ag
									WHERE visitas_sitio_hoja_trabajo_id = :visitas_sitio_hoja_trabajo_id');
		$consultaSQL->bindParam(':visitas_sitio_hoja_trabajo_id', $visitas_sitio_hoja_trabajo_id, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function con_Procedimiento($valor) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null){	

		if (empty($valor)) {

		$consultaSQL = $pdo->prepare('SELECT *
									FROM visitas_procedimiento 
									ORDER BY visitas_procedimiento_id');
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);
		return $rs;

	} else {
		$consultaSQL = $pdo->prepare('SELECT *
									FROM visitas_procedimiento
									WHERE 
									visitas_procedimiento_descripcion LIKE :valor
									ORDER BY visitas_procedimiento_id');
		$search_term_with_wildcards = "%" . $valor . "%";
		$consultaSQL->bindParam(':valor', $search_term_with_wildcards, PDO::PARAM_STR);

		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);
		return $rs;
	}

	return false;

	}

	$pdo = null;
	return false;
}

function conlista_procedimiento($visitas_sitio_hoja_trabajo_id) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null) {

		$consultaSQL = $pdo->prepare('SELECT *
                                    FROM visitas_sitio_hoja_trabajo_procedimiento
									INNER JOIN
									visitas_procedimiento ON 
									visitas_sitio_hoja_trabajo_procedimiento.visitas_procedimiento_id =visitas_procedimiento.visitas_procedimiento_id
									WHERE visitas_sitio_hoja_trabajo_id = :visitas_sitio_hoja_trabajo_id');
		$consultaSQL->bindParam(':visitas_sitio_hoja_trabajo_id', $visitas_sitio_hoja_trabajo_id, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function conlista_ingenieros_asignados($id_visita) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null) {

		$consultaSQL = $pdo->prepare('SELECT * FROM t_analista 
							INNER JOIN analistas_visita ON
							t_analista.id_analista = analistas_visita.id_analista
							WHERE analistas_visita.id_visita = :id_visita');
		$consultaSQL->bindParam(':id_visita', $id_visita, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function conlista_hoja_trabajo_procedimientos($id_visita) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null) {

		$consultaSQL = $pdo->prepare('SELECT * FROM visitas_sitio_hoja_trabajo
						INNER JOIN visitas_sitio_hoja_trabajo_procedimiento
						ON visitas_sitio_hoja_trabajo.visitas_sitio_hoja_trabajo_id = 
						visitas_sitio_hoja_trabajo_procedimiento.visitas_sitio_hoja_trabajo_id
						INNER JOIN visitas_procedimiento 
						ON visitas_sitio_hoja_trabajo_procedimiento.visitas_procedimiento_id = 
						visitas_procedimiento.visitas_procedimiento_id
						WHERE id_visita = :id_visita 
						ORDER BY visitas_procedimiento.visitas_procedimiento_id');
		$consultaSQL->bindParam(':id_visita', $id_visita, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function conlista_hoja_trabajo_activos($id_visita) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null) {

		$consultaSQL = $pdo->prepare('SELECT * FROM t_activo 
						INNER JOIN t_activo_general ON t_activo_general.id_ag = t_activo.id_ag 
						INNER JOIN t_marca ON t_activo.id_marca = t_marca.id_marca 
						INNER JOIN t_placa ON t_placa.id_activo = t_activo.id_activo
						INNER JOIN visitas_sitio_hoja_trabajo_activos ON visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_id_placa= t_placa.id_placa
						AND visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_activos_id_activo=
						t_activo.id_activo
						INNER JOIN visitas_sitio_hoja_trabajo ON
						visitas_sitio_hoja_trabajo_activos.visitas_sitio_hoja_trabajo_id=visitas_sitio_hoja_trabajo.visitas_sitio_hoja_trabajo_id 
						WHERE
						visitas_sitio_hoja_trabajo.id_visita = :id_visita');
		$consultaSQL->bindParam(':id_visita', $id_visita, PDO::PARAM_STR);           
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}

function convisitas_sitio_hoja_trabajo_id_visita($id_visita) {
		
	$pdo = new \PDO(DB_Str, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));        
	
	if ($pdo != null){	

		$consultaSQL = $pdo->prepare('SELECT * FROM visitas_sitio_hoja_trabajo WHERE id_visita = :id_visita');
		$consultaSQL->bindParam(':id_visita', $id_visita, PDO::PARAM_STR);
		$consultaSQL->execute();
		$rs = $consultaSQL->fetchAll(PDO::FETCH_ASSOC);

		return $rs;

	}  

	$pdo = null;
}


}

?>
