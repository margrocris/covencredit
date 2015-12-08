<?php
App::uses('AppModel', 'Model');

class ClienGest extends AppModel {

	public $validate = array(
		'fecha_cambio' => array(
			'message' => 'Enter a valid date in YY-MM-DD format.',
			'allowEmpty' => false,
			'required' => true,
		)
	);
		
	public function buscar_proxima_g($cedula){
		$ultima_gestion = $this->find('first', array(
			'conditions' => array('ClienGest.cedulaorif' => $cedula),
			'order' => array('ClienGest.numero DESC'),
		));
		return ($ultima_gestion);
	}
	public function buscar_gestiones_nuevas($hoy) {
		$nuevas = $this->find('all',array(
			'fields' => array('COUNT(ClienGest.id) as nuevas','Gestor.id'),
			'conditions' => array('DATE(ClienGest.fecha)' => $hoy),
			'joins' => array(
				array(
					'table' => 'cobranzas',
					'alias' => 'Cobranza',
					'type' => 'INNER',
					'conditions' => array(
						'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
						'ClienGest.numero = Cobranza.UltGestion',
					)
				),
				array(
					'table' => 'gestors',
					'alias' => 'Gestor',
					'type' => 'INNER',
					'conditions' => array(
						'Gestor.Clave = Cobranza.Gestor',
					),
				),
			),
			'group' => array('Cobranza.Gestor')
		));
		return $nuevas;
	}
	
	public function buscar_gestiones_agenda($hoy){
		$agenda = $this->find('all',array(
			'fields' => array('COUNT(ClienGest.id) as agenda','Gestor.id'),
			'conditions' => array('ClienGest.proximag' => $hoy),
			'joins' => array(
				array(
					'table' => 'cobranzas',
					'alias' => 'Cobranza',
					'type' => 'INNER',
					'conditions' => array(
						'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
						'ClienGest.numero = Cobranza.UltGestion',
					)
				),
				array(
					'table' => 'gestors',
					'alias' => 'Gestor',
					'type' => 'INNER',
					'conditions' => array(
						'Gestor.Clave = Cobranza.Gestor',
					),
				),
			),
			'group' => array('Cobranza.Gestor')
		));
		return $agenda;
	}
	
	public function buscar_gestiones_realizadas($hoy) { //Funcion que devuelve la cantidad de gestiones realizadas por gestor
		$realizadas = $this->find('all',array(
			'fields' => array('COUNT(ClienGest.id) as realizadas','Gestor.id'),
			'conditions' => array('ClienGest.fecha_reg' => $hoy),
			'joins' => array(
				array(
					'table' => 'cobranzas',
					'alias' => 'Cobranza',
					'type' => 'INNER',
					'conditions' => array(
						'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
						'ClienGest.numero = Cobranza.UltGestion',
					)
				),
				array(
					'table' => 'gestors',
					'alias' => 'Gestor',
					'type' => 'INNER',
					'conditions' => array(
						'Gestor.Clave = Cobranza.Gestor',
					),
				),
			),
			'group' => array('Cobranza.Gestor')
		));
		return $realizadas;	
	}
	
	public function buscar_gestiones_atrasadas($hoy,$cedula) { //Funcion que retorna si un deudor tiene gestiones atrasadas
		//Verifico la ultima gestion de cada deudor, si proximag < hoy es porque esta atrasada
			$atrasadas = $this->find('first',array(
				'fields' => array('Gestor.id'),
				'conditions' => array(	
					'ClienGest.proximag <' => $hoy,
					'ClienGest.cedulaorif' => $cedula
				),
				'joins' => array(
					array(
						'table' => 'cobranzas',
						'alias' => 'Cobranza',
						'type' => 'INNER',
						'conditions' => array(
							'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
							'ClienGest.numero = Cobranza.UltGestion',
						)
					),
					array(
						'table' => 'gestors',
						'alias' => 'Gestor',
						'type' => 'INNER',
						'conditions' => array(
							'Gestor.Clave = Cobranza.Gestor',
						),
					),
				),
				'group' => array('Cobranza.Gestor')
			));
			return $atrasadas;
	}
	
	public function buscar_gestiones_realizadas_por_gestor($hoy,$gestor) { //Funcion que devuelve la cantidad de gestiones realizadas por gestor
		$realizadas = $this->find('all',array(
			'fields' => array('ClienGest.*'),
			'conditions' => array('ClienGest.fecha_reg' => $hoy,'Cobranza.Gestor' => $gestor),
			'joins' => array(
				array(
					'table' => 'cobranzas',
					'alias' => 'Cobranza',
					'type' => 'INNER',
					'conditions' => array(
						'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
						'ClienGest.numero = Cobranza.UltGestion',
					)
				),
				array(
					'table' => 'gestors',
					'alias' => 'Gestor',
					'type' => 'INNER',
					'conditions' => array(
						'Gestor.Clave = Cobranza.Gestor',
					),
				),
			),
		));
		return $realizadas;	
	}
	
	public function busqueda_consulta_general($data = null){
			
			if($data == null) {
				$joins = array(  // join que se usara en común para todas las consultas
					array(
						'table' => 'clientes',
						'alias' => 'Cliente',
						'type' => 'INNER',
						'conditions' => array(
							'ClienGest.rif_emp = Cliente.rif'
						)
					),
					array(
						'table' => 'cobranzas',
						'alias' => 'Cobranza',
						'type' => 'INNER',
						'conditions' => array(
							'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
						),
					),
				);
			
				return $joins;
			}
			
			$conditions = array();
			
			// establecemos las fechas y las agregamos a la condición
			
			if(!empty($data['User']['fecha1'])) {
				$fecha1 = strtotime($data['User']['fecha1']);
				$fecha1 = date('Y-m-d:H:i:s',$fecha1); 
			}else{
				$fecha1 =  strtotime("10 September 2000");
				$fecha1 = date('Y-m-d:H:i:s', $fecha1);
			}
			if(!empty($data['User']['fecha2'])) {
				$fecha2 = strtotime($data['User']['fecha2']);
				$fecha2 = date('Y-m-d:H:i:s',$fecha2); 
			}else{
				$fecha2 =  strtotime("10 September 2020");
				$fecha2 = date('Y-m-d:H:i:s', $fecha2);
			}	
			
			array_push($conditions, array('ClienGest.fecha >= ' => $fecha1));
			array_push($conditions, array('ClienGest.fecha <= ' => $fecha2));
			
			// búsqueda por cédula, deudor o teléfono			
			
			if(!empty($data['User']['buscar'])) {
				$buscar = $data['User']['buscar'];
				$condicion_buscar = array(
					'OR' => array(
						'ClienGest.cedulaorif LIKE' => '%'. $buscar .'%',
						'ClienGest.telefono LIKE' => '%'. $buscar .'%',
						'Cobranza.NOMBRE LIKE' => '%'. $buscar. '%',
					)
				);		
				array_push($conditions, $condicion_buscar);
			}
			
			// Búsqueda por gestor (nombre)
			
			if(!empty($data['User']['gestore'])) {
				$gestor = $data['User']['gestore'];
				$condicion_buscar = array('ClienGest.gest_asig' => $gestor);
				array_push($conditions, $condicion_buscar);
			}
			
			// Búsqueda por nombre de empresa
			
			if(!empty($data['User']['empresa'])) {
				$empresa = $data['User']['empresa'];
				$condicion_buscar = array('Cliente.nombre' => $empresa);
				array_push($conditions, $condicion_buscar);
			}
			
			// Búsqueda por status
			
			if(!empty($data['User']['statu'])) {
				$status = $data['User']['statu'];
				$condicion_buscar = array('ClienGest.cond_deud' => $status);
				array_push($conditions, $condicion_buscar);
			}
			
			// Búsqueda por supervisor
			
			// Buscamos los gestores que corresponden a cada supervisor y los metemos en un arreglo.
			if(!empty($data['User']['supervisor'])) {
				$supervisor = $data['User']['supervisor'];
				$gestores = ClassRegistry::init('User')->find('all', array(
					'fields' => array('DISTINCT User.username'),
					'conditions' => array(
						'User.supervisor_id' => $supervisor
					),
					'recursive' => -1
				));
				
				// Dados los gestores, hacemos un arreglo con condiciones para meterlo en el OR del query general
				
				$or_condition = array();
				foreach($gestores as $g){
					array_push($or_condition, array('ClienGest.gest_asig' => $g['User']['username']));	
				}
				
				//Hacemos la busqueda según los gestores usando el arreglo OR conseguido arriba
				
				$condicion_buscar = array(
					'OR' => $or_condition
				);
				
				// debug($condicion_buscar);
				
				array_push($conditions, $condicion_buscar);
			}
						
			// debug($conditions);
			return $conditions;
			
	}
	
	public function busqueda_gestiones_producto($data = null, $gestor_data){
			
			// debug($data);
			$conditions = array();
			
			// establecemos las fechas y las agregamos a la condición
			
			if(!empty($data['User']['fecha1'])) {
				$fecha1 = strtotime($data['User']['fecha1']);
				$fecha1 = date('Y-m-d:H:i:s',$fecha1); 
			}else{
				$fecha1 =  strtotime("10 September 2000");
				$fecha1 = date('Y-m-d:H:i:s', $fecha1);
			}
			if(!empty($data['User']['fecha2'])) {
				$fecha2 = strtotime($data['User']['fecha2']);
				$fecha2 = date('Y-m-d:H:i:s',$fecha2); 
			}else{
				$fecha2 =  strtotime("10 September 2020");
				$fecha2 = date('Y-m-d:H:i:s', $fecha2);
			}	
			
			array_push($conditions, array('ClienGest.fecha >= ' => $fecha1));
			array_push($conditions, array('ClienGest.fecha <= ' => $fecha2));
			
			// Búsqueda por gestor (nombre)
			
			if(!empty($data['User']['gestor'])) {
				$gestor = $data['User']['gestor'];
				$condicion_buscar = array('ClienGest.gest_asig' => $gestor);
				array_push($conditions, $condicion_buscar);
			}else{
				$condicion_buscar = array('ClienGest.gest_asig' => $gestor_data);
				array_push($conditions, $condicion_buscar);
			}
			
			// Búsqueda por nombre de empresa
			
			if(!empty($data['User']['empresa'])) {
				$empresa = $data['User']['empresa'];
				$condicion_buscar = array('Cliente.nombre' => $empresa);
				array_push($conditions, $condicion_buscar);
			}				
						
			// debug($conditions);
			return $conditions;
			
	}
	
	public function busqueda_cambio_fecha($data = null){
			
		$conditions = array();
		
		
		// establecemos las fechas y las agregamos a la condición
		
		// Búsqueda por gestor (nombre)
		
		if(!empty($data['gestor'])) {
			$gestor = $data['gestor'];
			$condicion_buscar = array('ClienGest.gest_asig' => $gestor);
			array_push($conditions, $condicion_buscar);
		}
		
		// Búsqueda por nombre de empresa
		
		if(!empty($data['empresa'])) {
			$empresa = $data['empresa'];
			$condicion_buscar = array('Cliente.rif' => $empresa);
			array_push($conditions, $condicion_buscar);
		}
		
		// Búsqueda por status
			
		if(!empty($data['status'])) {
			$status = $data['status'];
			$condicion_buscar = array('ClienGest.cond_deud' => $status);
			array_push($conditions, $condicion_buscar);
		}
		
		// Busqueda de gestiones atrasadas -- < a hoy
		
		if($data['atraso']){
			$fecha1 = strtotime("now");
			$fecha1 = date('Y-m-d:H:i:s',$fecha1); 
			array_push($conditions, array('ClienGest.proximag < ' => $fecha1));
		}else{

		}
		
		if(!empty($data['del_dia'])) {
			$fecha2 = strtotime($data['del_dia']);
			$fecha2 = date('Y-m-d:H:i:s',$fecha2);
			array_push($conditions, array('ClienGest.proximag = ' => $fecha2));
		}		
		// return $data['atraso'];
		return $conditions;
	}
	
	public function busqueda_generar_archivo($data = null){
			
			// debug($data);
			$conditions = array();		
			
			
			// establecemos las fechas y las agregamos a la condición, si las fechas vienen vacias, muestra solo el mes recurrente
			
			$fecha_primero_mes = date('Y-m-01:00:00:00');
			$fecha_ultimo_mes = date('Y-m-t:00:00:00');
			
			if(!empty($data['User']['fecha1'])) {
				$fecha1 = strtotime($data['User']['fecha1']);
				$fecha1 = date('Y-m-d:H:i:s',$fecha1);
			}else{
				$fecha1 = $fecha_primero_mes;
			}
			if(!empty($data['User']['fecha2'])) {
				$fecha2 = strtotime($data['User']['fecha2']);
				$fecha2 = date('Y-m-d:H:i:s',$fecha2); 
			}else{
				$fecha2 = $fecha_ultimo_mes;
			}		
			
			
			// condiciones para cuentas sin gestion vigente
			
			if(!empty($data['User']['sin_gestionv'])) {
				$fecha1 =  strtotime("10 September 2000");
				
				// PAOLA!!!
				// aquí tiene que ir la condición esa de que muestre la ultima de cada persona, tomando como fecha base 10 de septiembre de 2000 que es una fecha bastante vieja.
				// una vez que la condición o lo que sea que se va a agregar, hacerle push al arreglo $conditions como hago abajo.
			}
			
			// condiciones para cuentas sin gestion
			// si este checkbox viene marcado, retorna null para que desde el controlador se haga una nueva busqueda.

			if(!empty($data['User']['sin_gestion'])) {
				return null;
			}	
			
			
			array_push($conditions, array('ClienGest.fecha >= ' => $fecha1));
			array_push($conditions, array('ClienGest.fecha <= ' => $fecha2));
			
			// Búsqueda por cedula o rif
			
			if(!empty($data['User']['cedula'])) {
				$cedula = $data['User']['cedula'];
				$condicion_buscar = array('ClienGest.cedulaorif' => $cedula);
				array_push($conditions, $condicion_buscar);
			}
			
			// Búsqueda por nombre de empresa
			
			if(!empty($data['User']['empresa'])) {
				$empresa = $data['User']['empresa'];
				$condicion_buscar = array('ClienGest.rif_emp' => $empresa);
				array_push($conditions, $condicion_buscar);
			}				
						
			// debug($conditions);
			return $conditions;	
	}
	
}

?>