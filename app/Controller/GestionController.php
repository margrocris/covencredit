<?php
// app/Controller/UsersController.php
App::uses('AppController', 'Controller');

/**
 * Clientes Controller
 *
 * @property User $User
 * @property PaginatorComponent $Paginator
 */

class GestionController extends AppController {
	public $components = array('Paginator', 'Attempt.Attempt');
	public $uses = array('User','Role','Cliente','Statu','Producto','Cobranza','Gestor','ClienGest','Data','Status','Contacto','Telefono','ClienPago','Dia','Datatel','Datastatu','ClienProd');
		
	public function admin_index() {
		//Datos para la busquedas
		$supervisors = $this->User->find('list',array(
			'fields' => array('id','nombre_completo'),
			'conditions' => array('User.rol' => 'supervisor')
		));
		$gestores_b = $this->Gestor->find('all',array( // operadores¿?
			'fields' => array('Clave','User.nombre_completo','User.supervisor_id'),
			'conditions' => array('User.supervisor_id <>' => '0')
		));
		foreach ($gestores_b as $g) {
			$gestores[$g['Gestor']['Clave']] = $g['User']['nombre_completo'];
		}
		$clientes = $this->Cliente->find('list',array(
			'fields' => array('rif','nombre')
		));
		$status = $this->Statu->find('list',array(
			'fields' => array('codigo','condicion')
		));
		$contactos = $this->Contacto->find('list',array(
			'fields' => array('codigo','nombre')
		));
		$productos = $this->Producto->find('list',array(
			'fields' => array('codigo','producto')
		));
		$this->set(compact('supervisors','clientes','status','productos','gestores','contactos'));
		
		//Deudores
//		$deudores = $this->Cobranza->find('all',array(
//
//			'fields' => array('ClienGest.*','Cobranza.*','Gestor.*'),
//			'group' => array('Cobranza.CEDULAORIF'),
//			'joins' => array(
//				array(
//					'table' => 'clien_gests',
//					'alias' => 'ClienGest',
//					'type' => 'INNER',
//					'conditions' => array(
//						'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
//						'ClienGest.numero = Cobranza.UltGestion',
//					)
//				),
//				array(
//					'table' => 'gestors',
//					'alias' => 'Gestor',
//					'type' => 'INNER',
//					'conditions' => array(
//						'Gestor.Clave = Cobranza.Gestor',
//					),
//				),
//				array(
//					'table' => 'users',
//					'alias' => 'User',
//					'type' => 'INNER',
//					'conditions' => array(
//						'User.id = Gestor.user_id',
//					)
//				)
//			),
//		));
//		$cedula_deudor = $deudores[0]['ClienGest']['cedulaorif'];
//		//Busco las empresas asociadas al primer deudor
//		$empresas = $this->Cobranza->buscarEmpresas($deudores[0]['ClienGest']['cedulaorif']);
//		//Busco las gestiones del primer deudor
//		$gestiones = $this->Cobranza->buscarGestiones($deudores[0]['ClienGest']['cedulaorif'],$empresas);
//		//Buscando el supervisor del primer deudor
//		$supervisor = $this->User->find('first',array('conditions' => array('User.id' => $gestiones[$empresas[0]['Cliente']['rif']][0]['User']['supervisor_id'])));
//		$supervisor = $supervisor['User']['nombre_completo'];
//		$data_deudor = $this->Data->buscarDatos($cedula_deudor);
//		$cond_pago = $this->Statu->findByCodigo($deudores[0]['ClienGest']['cond_deud']);
//		$telefonos  = $this->Telefono->buscarTelefonos($deudores[0]['ClienGest']['cedulaorif']);
		$dias_no_laborables = $this->Dia->find('all');
//		$telefonos_data_mol = $this->Datatel->find('all',array('conditions' => array('Datatel.CedulaOrif' => $cedula_deudor)));
//		$direccion_data_mol = $this->Data->find('first',array('conditions' => array('Data.CedulaOrif' => $cedula_deudor)));
		$status_data_mol = $this->Datastatu->find('list',array(
			'fields' => array('unique_id','descripcion')
		));
		$status_data_mol[0] = '';
		$this->set(compact('deudores','gestiones','supervisor','empresas','data_deudor','cond_pago','telefonos','dias_no_laborables','telefonos_data_mol','status_data_mol','direccion_data_mol'));
	}
	
	public function admin_gestion_dia(){
		$hoy = date('Y-m-d');
		$gestores = $this->Gestor->find('all');
		
		//Busco el numero de gestiones nuevas
		$nuevas = $this->ClienGest->buscar_gestiones_nuevas($hoy);
		$gestiones['Nuevas'] = Hash::combine($nuevas, '{n}.Gestor.id', '{n}.0.nuevas');
		
		//Agenda
		$agenda = $this->ClienGest->buscar_gestiones_agenda($hoy);
		$gestiones['Agenda'] = Hash::combine($agenda, '{n}.Gestor.id', '{n}.0.agenda');
		
		//Atrasadas
		$deudores = $this->Cobranza->find('all',array('fields' => array('DISTINCT CEDULAORIF'))); //Busco todos los deudores
		foreach ($deudores as $d) {
			$atrasadas = $this->ClienGest->buscar_gestiones_atrasadas($hoy,$d['Cobranza']['CEDULAORIF']);
			if (!empty($atrasadas)) {
				if (empty($gestiones['Atraso'][$atrasadas['Gestor']['id']])) {
					$gestiones['Atraso'][$atrasadas['Gestor']['id']] = 0;
				}
				$gestiones['Atraso'][$atrasadas['Gestor']['id']] = 1+$gestiones['Atraso'][$atrasadas['Gestor']['id']];
			}
		
		}
		
		//Busco el numero de gestiones realizadas
		$realizadas = $this->ClienGest->buscar_gestiones_realizadas($hoy);
		
		$gestiones['Realizadas'] = Hash::combine($realizadas, '{n}.Gestor.id', '{n}.0.realizadas');
		
		foreach ($gestores as $d) {
			//Busco supervisor
			$supervisor = $this->User->find('first',array(
				'conditions' => array('User.id' => $d['User']['supervisor_id'])
			));
			$gestiones['Supervisor'][$d['Gestor']['id']] = $supervisor['User']['nombre_completo'];
		}
		
		//Buscar supervisores para el filtro
		$supervisores = $this->User->find('list',array(
			'fields' => array('User.id','User.nombre_completo'),
			'conditions' => array('User.rol' => 'supervisor')
		));
		$supervisores[0] = 'Todos';
		//Buscar las gestiones realizadas del primer gestor 
		$gest_realizadas = $this->ClienGest->buscar_gestiones_realizadas_por_gestor($hoy,$gestores[0]['Gestor']['Clave']);
	
		$this->set(compact('gestores','gestiones','supervisores','gest_realizadas','hoy'));
	}
	
	public function admin_gestiones_por_producto(){
		
		$gestores = $this->Gestor->find('all'); //Busco los gestores porque hay que listarlos todos
		
		if($this->request->is('post') && (!empty($this->request->data['User']['gestor']))){
			$gestores = $this->Gestor->find('all', array(
				'conditions' => array(
					'Gestor.Clave' => $this->request->data['User']['gestor']
				)
			) );
		}
		
		foreach ($gestores as $g) {
			//Como la busqueda tienes que darle al boton consultar creo que puedes recargar y llenar el array conditions para usar el mismo query que ya yo hice, por ahora conditions solo va a tener el gestor
			if($this->request->is('post')){
				$conditions = $this->ClienGest->busqueda_gestiones_producto($this->request->data, $g['Gestor']['Clave']);
				// debug($this->request->data);
			}else{
				$conditions = array('ClienGest.gest_asig' => $g['Gestor']['Clave']);
			}
			//Hago la busqueda por gestor, y guardo en el arreglo $gestiones['Clave'] para saber en la vista a cual pertenece cada gestion
			$gestiones[$g['Gestor']['Clave']] = $this->ClienGest->find('all',array(
				'fields' => array('COUNT(ClienGest.id) as contador','ClienGest.gest_asig','ClienGest.producto','ClienGest.rif_emp','Cliente.nombre'),
				'conditions' => $conditions,
				'group' => array('ClienGest.producto'),
				'joins' => array(
					array(
						'table' => 'clientes',
						'alias' => 'Cliente',
						'type' => 'INNER',
						'conditions' => array(
							'ClienGest.rif_emp = Cliente.rif'
						)
					),
				),
			));

			foreach($gestiones[$g['Gestor']['Clave']] as $gest) {
				//Tengo que ir almacenando todos los productos que se van a colocar en la tabla
				$productos[$gest['ClienGest']['producto']] = $gest['ClienGest']['producto'];
				//Para poderlos listar en la tabla con facilidad creo un arreglo de total de gestiones por producto en el mismo orden que el array productos
				$gestiones_producto[$g['Gestor']['Clave']][$gest['ClienGest']['producto']] = $gest[0]['contador'];
				//Voy calculando los totales
				if (empty($totales[$g['Gestor']['Clave']])) {
					$totales[$g['Gestor']['Clave']] = 0;
				}
				$totales[$g['Gestor']['Clave']] = $totales[$g['Gestor']['Clave']] + $gest[0]['contador'];
			}
		}
		
		$gestors = $this->Gestor->find('list', array(
		'fields' => array('Clave','Clave')));
		
		$empresas = $this->Cliente->find('list', array(
		'fields' => array('nombre','nombre')));
		
		$this->set(compact('gestores','gestiones_producto','productos','totales','gestors','empresas'));
	}
	
	public function actualizar_status_productos(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cliente_id = $this->request->data['cliente_id'];
			
			if(empty($cliente_id)) {
				$conditions_p = array();
				$conditions_s = array();
			} else {
				$conditions_p = array('Producto.rif_emp' => $cliente_id);
				$conditions_s = array('Statu.rif_emp' => $cliente_id);
			}
			$result = array(
				'status'=> array(),
				'productos' => array(),
			);			
			$productos = $this->Producto->find('list',array(
				'fields' => array('codigo','producto'),
				'conditions' => $conditions_p
			));			
			if (!empty($productos)) {
				$result['productos'] = $productos;
			}			
			$status = $this->Statu->find('list',array(
				'fields' => array('codigo','condicion'),
				'conditions' => $conditions_s
			));			
			if (!empty($status)) {
				$result['status'] = $status;
			}			
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	public function actualizar_gestores(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$supervisor_id = $this->request->data['supervisor_id'];
			
			if(empty($supervisor_id)) {
				$conditions = array('User.supervisor_id <>' => 0);
			} else {
				$conditions = array('User.supervisor_id' => $supervisor_id);
			}

			$result = array(
				'gestores'=> array()
			);

			$gestores_b = $this->Gestor->find('all',array( // operadores¿?
				'fields' => array('Clave','User.nombre_completo','User.supervisor_id'),
				'conditions' => $conditions
			));
			foreach ($gestores_b as $g) {
				$gestores[$g['Gestor']['Clave']] = $g['User']['nombre_completo'];
			}	
			
			if (!empty($gestores)) {
				$result['gestores'] = $gestores;
			}	
			
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	public function actualizar_deudores(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$supervisor_id = $this->request->data['supervisor_id'];
			$gestor_clave = $this->request->data['gestor_clave'];
			$producto = $this->request->data['producto'];
			$statu = $this->request->data['statu'];
			$cliente = $this->request->data['cliente'];
			$tipo = $this->request->data['tipo'];
			$fecha = $this->request->data['fecha'];
			//$cedula = $this->request->data['cedula'];
			
			$conditions = array();
			
			if (!empty($gestor_clave)) {
				$conditions2 = array('Cobranza.GESTOR' => $gestor_clave);
				array_push($conditions, $conditions2);
			} else {
				if(empty($supervisor_id)) {
					$conditions1 = array('User.supervisor_id <>' => 0);
					array_push($conditions, $conditions1);
				} else {
					$conditions1 = array('User.supervisor_id' => $supervisor_id);
					array_push($conditions, $conditions1);
				}
				
			}
			
			//Busco por cliente
			if (!empty($cliente)) {
				$conditions1 = array('ClienGest.rif_emp' => $cliente);
				array_push($conditions, $conditions1);
			}
			
			//Filtro por producto			
			if (!empty($producto)) {
				$conditions1 = array('ClienGest.producto' => $producto);
				array_push($conditions, $conditions1);
			}
			
			if (!empty($statu)) {
				$conditions1 = array('ClienGest.cond_deud' => $statu,'ClienGest.numero = Cobranza.UltGestion');
				array_push($conditions, $conditions1);
			}
			
			//Filtro por tipo
			if (!empty($tipo)) {
				$hoy = date('Y-m-d');
				if ($tipo == 'atraso') {
					$conditions1 = array('ClienGest.proximag <' => $hoy,'ClienGest.numero = Cobranza.UltGestion');
					array_push($conditions, $conditions1);
				} elseif ($tipo == 'agenda') {
					$conditions1 = array('ClienGest.proximag ' => $hoy,'ClienGest.numero = Cobranza.UltGestion');
					array_push($conditions, $conditions1);
				} elseif ($tipo == 'nuevas') {
					$conditions1 = array('Cobranza.FECH_ASIG ' => $hoy,'ClienGest.numero = Cobranza.UltGestion');
					array_push($conditions, $conditions1);
				} elseif ($tipo == 'gesthoy') {
					$conditions1 = array('ClienGest.proximag ' => $hoy,'ClienGest.numero = Cobranza.UltGestion');
					array_push($conditions, $conditions1);
				} elseif ($tipo == 'cartera') {
					$conditions1 = array('ClienGest.proximag ' => $hoy,'ClienGest.numero = Cobranza.UltGestion');
					array_push($conditions, $conditions1);
				}
			}
			
			//filtro por fecha
			if (!empty($fecha)) {
				$fecha=date("Y-m-d",strtotime($fecha));
				$conditions1 = array('Cobranza.FECH_ASIG' => $fecha);
				array_push($conditions, $conditions1);
			}
			
			//Filtro por cedula del deudor
			// if (!empty($cedula)) {
				// $conditions1 = array('ClienGest.cedulaorif' => $cedula);
				// array_push($conditions, $conditions1);
			// }
			
			$deudores = $this->Cobranza->find('all',array(
			'fields' => array('ClienGest.*','Cobranza.*'),
			'group' => array('Cobranza.CEDULAORIF'),
			'conditions' => $conditions,
			'joins' => array(
				array(
					'table' => 'clien_gests',
					'alias' => 'ClienGest',
					'type' => 'INNER',
					'conditions' => array(
						'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
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
				array(
					'table' => 'users',
					'alias' => 'User',
					'type' => 'INNER',
					'conditions' => array(
						'User.id = Gestor.user_id',
					)
				)
			),
			));
           // print_r($deudores);
			return json_encode($deudores);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function cargar_info_deudor() { //funcion para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula'];
			$telefonos = $this->Telefono->buscarTelefonos($cedula);
			$empresas = $this->Cobranza->buscarEmpresas($cedula);
			$gestiones = $this->Cobranza->buscarGestiones($cedula,$empresas);
			$supervisor = $this->User->find('first',array('conditions' => array('User.id' => $gestiones[$empresas[0]['Cliente']['rif']][0]['User']['supervisor_id'])));
			$supervisor = $supervisor['User']['nombre_completo'];
			$data_deudor = $this->Data->buscarDatos($cedula);
			// $cond_pago = $this->Statu->findByCodigo($empresas[0]['Cliente']['rif'][0]['ClienGest']['cond_deud']);
			$cond_pago = 'Promesa de Pago';
			$result = array(
				'gestiones' => $gestiones,
				'supervisor' => $supervisor,
				'empresas' => $empresas,
				'data_deudor' => $data_deudor,
				'cond_pago' => $cond_pago,
				'telefonos' => $telefonos
			);
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function cargar_info_comentario() { //funcion para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$gestion_id = $this->request->data['gestion_id'];
			$gestion = $this->ClienGest->find('first',array('conditions' => array('id' => $gestion_id)));
			$observacion = $gestion['ClienGest']['observac'];
            $comentario2 = $gestion['ClienGest']['Observac1'];
            $return = array('observacion' => $observacion,'comentario2' => $comentario2);
			return json_encode($return);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function cargar_info_producto() { //funcion para ajax, carga producto asociado al deudor?
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula'];
			$empresas = $this->Cobranza->buscarEmpresas($cedula);
			$productos = $this->Producto->buscarProductos($cedula,$empresas);
			$result = array(
				'productos' => $productos,
				'empresas' => $empresas
			);
			return json_encode($result);
			
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function cargar_info_pagos() { //funcion para ajax, carga pagos de productos asociados al deudor?
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula'];
			$empresas = $this->Cobranza->buscarEmpresas($cedula);
			$pagos = $this->Producto->buscarPagos($cedula,$empresas);
			$result = array(
				'pagos' => $pagos,
				'empresas' => $empresas
			);
			return json_encode($result);
			
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function cargar_empresas() { //funcion para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula'];
			$empresas = $this->Cobranza->buscarEmpresas($cedula);
			return json_encode($empresas);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	public function editar_prueba(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			
			return json_encode('prueba');
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	public function editar_deudor(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$nombre = $this->request->data['nombre'];
			$cedula = $this->request->data['cedula'];
			$proxima_g =  $this->request->data['proxima_g'];
			$gestor = $this->request->data['gestor'];
			
			$datos_cobranza = $this->Cobranza->find('first', array(
				'conditions' => array(
					'Cobranza.CEDULAORIF' => $cedula,
				)
			));
			
			$update_deudor = array('Cobranza' => array(
				'id' => $datos_cobranza['Cobranza']['id'],
				'NOMBRE' => $nombre,
				'GESTOR' => $gestor
			));
			
			$operador_datos = $this->User->find('first',array('conditions' => array('User.username' => $gestor)));
			$supervisor_id = $operador_datos['User']['supervisor_id'];
			$supervisor = $this->User->find('first',array('conditions' => array('User.id' => $supervisor_id)));
			$supervisor = $supervisor['User']['nombre_completo'];
			
			$date = strtotime($proxima_g);
			$proxima_g =  date('Y-m-d', $date);
			
			$update_proxima_g = $this->ClienGest->buscar_proxima_g($cedula);
			$update_proxima_g['ClienGest']['proximag'] = $proxima_g;
			
			$this->ClienGest->save($update_proxima_g);
			$this->Cobranza->save($update_deudor);
			// $this->Data->save($update_deudor_data);
			
				$result = array(
					'nombre' => $nombre,
					// 'telefono' => $telefono,
					'cedula' => $cedula,
					'proxima_g' => $proxima_g,
					// 'status' => $status,
					// 'direccion' => $direccion,
					'gestor' => $gestor,
					'supervisor' => $supervisor 
					// 'supervisor' => $supervisor // no hace falta cambiar supervisor
				);
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	public function agregar_telefono_deudor(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$telefono = $this->request->data['telefono'];
			$ubicacion = $this->request->data['ubicacion'];
			$cedula = $this->request->data['cedula'];
			$direccion = $this->request->data['direccion'];
			
			$add_telefono = array('Telefono' => array(
				'cedulaorif' => $cedula,
				'ubicacion' => $ubicacion,
				'telefono' => $telefono,
				'direccion' => $direccion
			));
						
			$this->Telefono->save($add_telefono);
			
			return json_encode($add_telefono);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	public function editar_gestion(){ // función para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$id_gestion = $this->request->data['id_gestion'];
			$fecha_reg = $this->request->data['fecha_reg'];
			$proximag = $this->request->data['proximag'];
			$telefono = $this->request->data['telefono'];
			$status = $this->request->data['status'];
			$contacto = $this->request->data['contacto'];
			$producto = $this->request->data['producto'];
			$gestor = $this->request->data['gestor'];
			if ($status == 'PP' || $status == 'MM') {
				if ($status == 'PP') {
					$fecha_pago = $this->request->data['fecha_pago'];
					$bolivares = $this->request->data['bolivares'];
					$comentario1 = $this->Statu->concatenar_comentario($status,$fecha_pago,$bolivares,1);
				} else {
					$nombre = $this->request->data['nombre'];
					$apellido = $this->request->data['apellido'];
					$parentesco = $this->request->data['parentesco'];
					$comentario1 = $this->Statu->concatenar_comentario($status,$nombre,$apellido,$parentesco);
				}
			} else {
				$find_producto = $this->Producto->find('first',array(
					'conditions' => array('Producto.codigo' => $producto)
				));
				$rif = $find_producto['Cliente']['rif'];
				$comentario = $this->Statu->find('first',array(
					'conditions' => array(
						'Statu.rif_emp' => $rif,
						'Statu.codigo' => $status
					)
				));
				$comentario1 = $comentario['Statu']['condicion'];
			}
			$comentario2 = $this->request->data['comentario2'];
			$cedula = $this->request->data['cedula'];
			$result = array(
				'numero' => '',
				'gestor' => '',
				'id' => '',
			);
			$fecha_hora = date('Y-m-d H:i:s');
			if ($id_gestion != 0) {
				$update_gestion = array('ClienGest' => array(
					'id' => $id_gestion,
					'fecha_reg' => $fecha_reg,
					'proximag' => $proximag,
					'telefono' => $telefono,
					'cond_deud' => $status,
					'contacto' => $contacto,
					'producto' => $producto,
					'observac' => $comentario1,
					'Observac1' => $comentario2,
					'fecha' => $fecha_hora,
					'gest_asig' => $gestor
				));
				$this->ClienGest->save($update_gestion);
				$result = array(
					'comentario' => $comentario1
				);
			} else {
				$find_gestiones = $this->ClienGest->find('first',array(
					'conditions' => array('ClienGest.cedulaorif' => $cedula),
					'order' => array('ClienGest.numero DESC'),
				));
				$numero = $find_gestiones['ClienGest']['numero']+1;
				$find_producto = $this->Producto->find('first',array(
					'conditions' => array('Producto.codigo' => $producto)
				));
				$find_cobranza =  $this->Cobranza->find('first',array(
					'conditions' => array('Cobranza.CEDULAORIF' => $cedula,'Cobranza.RIF_EMP' => $find_producto['Cliente']['rif']),
				));
				$update_gestion = array('ClienGest' => array(
					'cedulaorif' => $cedula,
					'fecha' => $fecha_hora,
					'fecha_reg' => $fecha_reg,
					'proximag' => $proximag,
					'telefono' => $telefono,
					'cond_deud' => $status,
					'contacto' => $contacto,
					'producto' => $producto,
					'observac' => $comentario1,
					'Observac1' => $comentario2,
					'rif_emp' => $find_producto['Cliente']['rif'],
					'numero' => $numero,
					'gest_asig' => $gestor
				));
				$this->ClienGest->save($update_gestion);
				$id = $this->ClienGest->id;
				//Cambio el numero de la ultima gestion
				$update_cobranza = array('Cobranza' => array(
					'id' => $find_cobranza['Cobranza']['id'],
					'UltGestion' => $numero
				));
				$this->Cobranza->save($update_cobranza);
				$result = array(
					'numero' => $numero,
					'gestor' => $find_cobranza['Cobranza']['GESTOR'],
					'id' => $id,
					'rif' => $find_producto['Cliente']['rif'],
					'comentario' => $comentario1
				);
			}
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function buscar_proximas_gestiones_y_comentario() { //funcion para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;
			$gestor = $this->request->data['gestor'];
			$fecha = date('Y-m-d');
			$usuarios_gestores = $this->Cobranza->find('all',array(
				'fields' => array('DISTINCT Cobranza.CEDULAORIF'),
				'conditions' => array('Cobranza.gestor' => $gestor)
			));
			$cedulas = Hash::combine($usuarios_gestores, '{n}.Cobranza.CEDULAORIF', '{n}.Cobranza.CEDULAORIF');
			$proximas_gestiones = $this->ClienGest->find('all',array(
				'fields' => array('ClienGest.proximag','COUNT(*) as numeroGestiones'),
				'conditions' => array(
					'ClienGest.proximag >' => $fecha,
					'ClienGest.cedulaorif' => $cedulas
				),
				'group' => array('ClienGest.proximag')
			));
			
			//Busco el comentario
			$status = $this->request->data['status'];
			$producto = $this->request->data['producto'];
			$comentario = $this->Statu->buscar_comentario($status,$producto);
			$result = array(
				'proximas_gestiones' => $proximas_gestiones,
				'comentario' => $comentario
			);
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function buscar_productos_por_deudor(){
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula_deudor'];
			$empresas = $this->Cobranza->buscarEmpresas($cedula);
			$productos = $this->Producto->buscarProductos($cedula,$empresas);
			$productos = Hash::combine($productos, '{n}.{n}.ClienProd.COD_PROD', '{n}.{n}.Producto.producto');
			return json_encode($productos);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function buscar_status(){
		if($this->request->isAjax()){
			$this->autoRender = false;
			$producto = $this->request->data['producto'];
			$empresa = $this->Producto->find('first',array(
				'conditions' => array('Producto.codigo' => $producto)
			));
			$rif = $empresa['Cliente']['rif'];
			$status = $this->Statu->find('all',array(
				'conditions' => array('rif_emp' => $rif)
			));
			return json_encode($status);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function buscar_comentario() { //funcion para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;	
			//Busco el comentario
			$status = $this->request->data['status'];
			$producto = $this->request->data['producto'];
			$comentario = $this->Statu->buscar_comentario($status,$producto);
			return json_encode($comentario);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function guardar_pago() { //funcion para ajax
		if($this->request->isAjax()){
			$this->autoRender = false;	
			$cedula = $this->request->data['cedula'];
			$gestor = $this->request->data['gestor'];
			$fecha_reg = $this->request->data['fecha_reg'];
			$date = strtotime($fecha_reg);
			$fecha_reg =  date('Y-m-d', $date);
			$fecha = $this->request->data['fecha'];
			$date = strtotime($fecha);
			$fecha =  date('Y-m-d', $date);
			$efectivo_monto = $this->request->data['efectivo_monto'];
			$efectivo_documento = $this->request->data['efectivo_documento'];
			$cheque_monto = $this->request->data['cheque_monto'];
			$otro_monto = $this->request->data['otro_monto'];
			$otro_documento = $this->request->data['otro_documento'];
			$cheque_documento = $this->request->data['cheque_documento'];
			$total_pago = $this->request->data['total_pago'];
			$producto = $this->request->data['producto'];
			$empresa = $this->Producto->find('first',array(
				'conditions' => array('Producto.codigo' => $producto)
			));
			$rif = $empresa['Cliente']['rif'];
			// $user_logueado = $this->Auth->User('id');
			// $usuario_gestor = $this->Gestor->find('first',array(
				// 'conditions' => array('Gestor.user_id' => $user_logueado)
			// ));
			// if (!empty($usuario_gestor)) {
				// $login_reg = $usuario_gestor['Gestor']['Clave'];
			// } else {
				// $usuario_logueado = $this->User->findById($user_logueado);
				// $login_reg = $usuario_logueado['User']['username'];
			// }
			//guardo pago
			$pago = array('ClienPago' => array(
				'RIF_EMP' => $rif,
				'FECH_PAGO' => $fecha,
				'CEDULAORIF' => $cedula,
				'FECH_REG' => $fecha_reg,
				'PRODUCTO' => $empresa['Producto']['producto'],
				'COD_PROD' => $producto,
				'TOTAL_PAGO' => $total_pago,
				'EFECTIVO' => $efectivo_monto,
				'Nro_Efect' => $efectivo_documento,
				'MTO_CHEQ1' => $cheque_monto,
				'MTO_OTROS' => $otro_monto,
				'Nro_Efect' => $efectivo_documento,
				'NRO_CHEQ1' => $cheque_documento,
				'NRO_OTRO' => $otro_documento,
				'EST_PAGO' => 'Pendiente',
				'LOGIN_REG' => $gestor
			));
			$this->ClienPago->save($pago);
			return json_encode($pago);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function listar_telefonos_deudor(){
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula_deudor'];
			$telefonos  = $this->Telefono->buscarTelefonos($cedula);
			$telefonos = Hash::combine($telefonos, '{n}.Telefono.telefono', '{n}.Telefono.telefono');
			return json_encode($telefonos);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function info_datamol(){
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula_deudor = $this->request->data['cedula'];
			$telefonos_data_mol = $this->Datatel->find('all',array('conditions' => array('Datatel.CedulaOrif' => $cedula_deudor)));
			if (empty($telefonos_data_mol)) {
				$telefonod_data_mol[0]['Datatel']['Telefono'] = 0;
			}
			$direccion_data_mol = $this->Data->find('first',array('conditions' => array('Data.CedulaOrif' => $cedula_deudor)));
			if (empty($direccion_data_mol)) {
				$direccion_data_mol['Data']['Direccion'] = "";
			}
			$result = array(
				'telefonos_data_mol' => $telefonos_data_mol,
				'direccion_data_mol' => $direccion_data_mol
			);
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function actualizar_status_telefono(){
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula'];
			$telefono = $this->request->data['telefono'];
			$status = $this->request->data['status'];
			$registro = $this->Datatel->find('first',array(
				'conditions' => array(
					'Datatel.CedulaOrif' => $cedula,
					'Datatel.Telefono' => $telefono
				)
			));
			$update = array('Datatel' => array(
				'id' => $registro['Datatel']['id'],
				'status' => $status
			));
			$this->Datatel->save($update);
			return json_encode($status);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function actualizar_status_direccion__datamol(){
		if($this->request->isAjax()){
			$this->autoRender = false;
			$cedula = $this->request->data['cedula'];
			$status = $this->request->data['status'];
			
			$update = array('Data' => array(
				'CedulaOrif' => $cedula,
				'Status' => $status
			));
			$this->Data->save($update);
			return json_encode($status);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function buscar_gestores(){ //FUncion AJAX
		if($this->request->isAjax()){
			$this->autoRender = false;
			$supervisor = $this->request->data['supervisor'];
			$hoy = date('Y-m-d');
			
			if ($supervisor != 0) {
				$gestores = $this->Gestor->find('all',array(
					'conditions' => array('User.supervisor_id' => $supervisor)
				));
			} else {
				$gestores = $this->Gestor->find('all');
			}
			//Busco el numero de gestiones nuevas
			$nuevas = $this->ClienGest->buscar_gestiones_nuevas($hoy);
			$gestiones['Nuevas'] = Hash::combine($nuevas, '{n}.Gestor.id', '{n}.0.nuevas');
			
			//Agenda
			$agenda = $this->ClienGest->buscar_gestiones_agenda($hoy);
			$gestiones['Agenda'] = Hash::combine($agenda, '{n}.Gestor.id', '{n}.0.agenda');
			
			//Atrasadas
			$deudores = $this->Cobranza->find('all',array('fields' => array('DISTINCT CEDULAORIF'))); //Busco todos los deudores
			foreach ($deudores as $d) {
				$atrasadas = $this->ClienGest->buscar_gestiones_atrasadas($hoy,$d['Cobranza']['CEDULAORIF']);
				if (!empty($atrasadas)) {
					if (empty($gestiones['Atraso'][$atrasadas['Gestor']['id']])) {
						$gestiones['Atraso'][$atrasadas['Gestor']['id']] = 0;
					}
					$gestiones['Atraso'][$atrasadas['Gestor']['id']] = 1+$gestiones['Atraso'][$atrasadas['Gestor']['id']];
				}
			
			}
			
			//Busco el numero de gestiones realizadas
			$realizadas = $this->ClienGest->buscar_gestiones_realizadas($hoy);
			
			$gestiones['Realizadas'] = Hash::combine($realizadas, '{n}.Gestor.id', '{n}.0.realizadas');
			
			foreach ($gestores as $d) {
				//Completo arreglos con 0
				if (empty($gestiones['Nuevas'][$d['Gestor']['id']])) {
					$gestiones['Nuevas'][$d['Gestor']['id']] = 0;
				}
				if (empty($gestiones['Agenda'][$d['Gestor']['id']])) {
					$gestiones['Agenda'][$d['Gestor']['id']] = 0;
				}
				if (empty($gestiones['Atraso'][$d['Gestor']['id']])) {
					$gestiones['Atraso'][$d['Gestor']['id']] = 0;
				}
				if (empty($gestiones['Realizadas'][$d['Gestor']['id']])) {
					$gestiones['Realizadas'][$d['Gestor']['id']] = 0;
				}
				//Busco supervisor
				$b_supervisor = $this->User->find('first',array(
					'conditions' => array('User.id' => $d['User']['supervisor_id'])
				));
				$gestiones['Supervisor'][$d['Gestor']['id']] = $b_supervisor['User']['nombre_completo'];
			}
			
			//Buscar las gestiones realizadas del primer gestor 
			$gest_realizadas = $this->ClienGest->buscar_gestiones_realizadas_por_gestor($hoy,$gestores[0]['Gestor']['Clave']);
	
			$result = array(
				'gestiones' => $gestiones,
				'gestores' => $gestores,
				'gest_realizadas' => $gest_realizadas
			);
			
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function buscar_gestiones_realizadas(){ //FUncion AJAX
		if($this->request->isAjax()){
			$this->autoRender = false;
			$gestor = $this->request->data['gestor'];
			$hoy = date('Y-m-d');
			//Buscar las gestiones realizadas del primer gestor 
			$gest_realizadas = $this->ClienGest->buscar_gestiones_realizadas_por_gestor($hoy,$gestor);
	
			$result = array(
				'gest_realizadas' => $gest_realizadas
			);
			
			return json_encode($result);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}
	
	function admin_gestiones_por_status(){
	
		$empresas = $this->Cliente->find('list', array(
		'fields' => array('rif','nombre')));

		$gestores = $this->Gestor->find('list', array(
		'fields' => array('Nombre','Nombre')));
	
		if($this->request->is('post')){ // si trae algún filtro...
			// debug($this->request->data);
			
			if(!empty($this->request->data['User']['fecha1'])) {
				$fecha1 = strtotime($this->request->data['User']['fecha1']);
				$fecha1 = date('Y-m-d:H:i:s',$fecha1); 
			}else{
				$fecha1 =  strtotime("10 September 2000");
				$fecha1 = date('Y-m-d:H:i:s', $fecha1);
			}
			if(!empty($this->request->data['User']['fecha2'])) {
				$fecha2 = strtotime($this->request->data['User']['fecha2']);
				$fecha2 = date('Y-m-d:H:i:s',$fecha2); 
			}else{
				$fecha2 =  strtotime("10 September 2020");
				$fecha2 = date('Y-m-d:H:i:s', $fecha2);
			}			
			
			if(!empty($this->request->data['User']['empresa'])){ // cuando la busqueda es por empresa
			// buscamos los operadores asociados a la empresa seleccionada y hacemos la busqueda en base a los operadores luego.
				$gestores_por_empresa = $this->ClienGest->find('all', array(
					'conditions' => array(
						'rif_emp' => $this->request->data['User']['empresa']
					),
					'fields' => 'DISTINCT gest_asig'
				));
				
				foreach($gestores_por_empresa as $g){
					$deudores[$g['ClienGest']['gest_asig']] = $this->ClienGest->find('all', array(
						'conditions' => array(
							'gest_asig' => $g['ClienGest']['gest_asig'],
							'fecha >=' => $fecha1,
							'fecha <=' => $fecha2,
						),
					));
					// debug($g);
				}
			} else 
				if(!empty($this->request->data['User']['gestore'])){
					$deudores[$this->request->data['User']['gestore']] = $this->ClienGest->find('all', array(
						'conditions' => array(
							'gest_asig' => $this->request->data['User']['gestore'],
							'fecha >=' => $fecha1,
							'fecha <=' => $fecha2,
						)
					));
			}else{
				foreach($gestores as $g){
				$deudores[$g] = $this->ClienGest->find('all', array(
					'conditions' => array(
						'gest_asig' => $g,
						'fecha >=' => $fecha1,
						'fecha <=' => $fecha2,
					)
				));
				}
			}
		}else{ // sin ningun submit
			
			foreach($gestores as $g){
				$deudores[$g] = $this->ClienGest->find('all', array(
					'conditions' => array(
						'gest_asig' => $g,
					)
				));
			}
		}		
		$this->set(compact('empresas','gestores','deudores'));
	}
	
	
	function admin_gestiones_general(){		
																// si no le pasas parametro, se trae el join
		$joins = $this->ClienGest->busqueda_consulta_general(); // se trae del modelo el join que se usara en común
		
		if($this->request->is('post')){ // si trae algún filtro...
			// debug($this->request->data);
			
			$conditions = $this->ClienGest->busqueda_consulta_general($this->request->data);
			
			$consultas = $this->ClienGest->find('all', array( // listamos todas las gestiones
				'fields' => array('ClienGest.*','Cliente.nombre','Cobranza.NOMBRE'),
				'joins' => $joins,
				'conditions' => $conditions
			));
			
		}else{ //  vista sin filtro
			$consultas = $this->ClienGest->find('all', array( // listamos todas las gestiones
				'fields' => array('ClienGest.*','Cliente.nombre','Cobranza.NOMBRE'),
				'joins' => $joins
			));
		}
		
		// datos que se pasarán siempre para llenar los select
		
		$empresas = $this->Cliente->find('list', array(
		'fields' => array('nombre','nombre')));

		$gestores = $this->Gestor->find('list', array(
		'fields' => array('Nombre','Nombre')));
		
		$supervisors = $this->User->find('list', array(
			'fields' => array('id','username'),
				'conditions' => array(
					'rol' => 'supervisor'
				)
			));
		
		$status = $this->Statu->find('list', array(
		'fields' => array('codigo','codigo')));
		
		$this->set(compact('consultas','supervisors','empresas','gestores','status'));
	}
	
	function admin_cambio_fecha(){
	
		if(!empty($this->request->data)){
			debug($this->request->data);
			$empresa = $this->request->data['User']['empresa'];
			$gestor = $this->request->data['User']['gestore'];
			$status = $this->request->data['User']['statu'];
			$fecha = $this->request->data['User']['fecha'];
			if(!empty($this->request->data['User']['atraso'])){
				$atraso = $this->request->data['User']['atraso'];
			}else{
				$atraso = null;
			}			
			$cantidad = $this->request->data['User']['cantidad'];
			
			$data = array('empresa' => $empresa, 'gestor' => $gestor, 'status' => $status, 'atraso'=> $atraso, 'del_dia' => $fecha);
			
			$conditions = $this->ClienGest->busqueda_cambio_fecha($data);
			
			$consultas = $this->ClienGest->find('all', array(
				'conditions' => $conditions,
				'joins' => array(
					array(
						'table' => 'clientes',
						'alias' => 'Cliente',
						'type' => 'INNER',
						'conditions' => array(
							'ClienGest.rif_emp = Cliente.rif'
						)
					),
				),
			));
			// debug($consultas);
			$fecha_cambio_nueva = strtotime($this->request->data['ClienGest']['fecha_cambio']);
			$fecha_cambio_nueva = date('Y-m-d:H:i:s',$fecha_cambio_nueva);
			foreach($consultas as $c){
				$update_proxima_g['ClienGest']['proximag'] = $fecha_cambio_nueva;
				$update_proxima_g['ClienGest']['id'] = $c['ClienGest']['id'];
				$this->ClienGest->saveAll($update_proxima_g);
				// debug($update_proxima_g);
			}
			
		}
		$empresas = $this->Cliente->find('list', array(
		'fields' => array('rif','nombre')));

		$gestores = $this->Gestor->find('list', array(
		'fields' => array('Clave','Nombre')));
		
		$supervisors = $this->User->find('list', array(
			'fields' => array('id','username'),
				'conditions' => array(
					'rol' => 'supervisor'
				)
			));
		
		$status = $this->Statu->find('list', array(
		'fields' => array('codigo','codigo')));
		
		$this->set(compact('consultas','supervisors','empresas','gestores','status'));
	}
	
	function cargar_info_cambio_fecha() { //funcion para ajax, vista cambio_fecha
		if($this->request->isAjax()){
			$this->autoRender = false;
			$empresa = $this->request->data['empresa'];
			$gestor = $this->request->data['gestor'];
			$status = $this->request->data['status'];
			$atraso = $this->request->data['atraso'];
			$del_dia = $this->request->data['del_dia'];
			
			$data = array('empresa' => $empresa, 'gestor' => $gestor, 'status' => $status, 'atraso'=> $atraso, 'del_dia' => $del_dia);
			
			$conditions = $this->ClienGest->busqueda_cambio_fecha($data);
			
			$consultas = $this->ClienGest->find('count', array(
				'conditions' => $conditions,
				'joins' => array(
					array(
						'table' => 'clientes',
						'alias' => 'Cliente',
						'type' => 'INNER',
						'conditions' => array(
							'ClienGest.rif_emp = Cliente.rif'
						)
					),
				),
			));
			
			return json_encode($consultas);
		}else{
			$this->redirect($this->defaultRoute);
		}
	}

	function admin_generar_archivo(){
	
		ini_set('memory_limit', '256M');
		set_time_limit(0);
	
		// debug(date("Y-m-01:00:00:00"));
		// debug(date("Y-m-t:00:00:00"));
		
		if(!empty($this->request->data)){
			// debug($this->request->data);
			$empresa = $this->request->data['User']['empresa'];
			$cedula = $this->request->data['User']['cedula'];
			$fecha1 = $this->request->data['User']['fecha_desde'];
			$fecha2 = $this->request->data['User']['fecha_hasta'];
			
			if(!empty($this->request->data['User']['sin_gestion'])){
				$sin_gestion = $this->request->data['User']['sin_gestion'];
			}else{
				$sin_gestion = 0;
			}
			if(!empty($this->request->data['User']['sin_gestionv'])){
				$sin_gestionv = $this->request->data['User']['sin_gestionv'];
			}else{
				$sin_gestionv = 0;
			}
				
			
			$data = array('User' => array('empresa' => $empresa, 'cedula' => $cedula, 'fecha1' => $fecha1, 'fecha2'=> $fecha2, 'sin_gestion' => $sin_gestion, 'sin_gestionv' => $sin_gestionv));
			
			// debug($data);
			
			$conditions = $this->ClienGest->busqueda_generar_archivo($data);
			
			if($conditions == null){
				
				$consultas = $this->ClienProd->find('all', array(
					'conditions' => $conditions,
					'fields' => array('ClienProd.*','Cobranza.NOMBRE'),
					'joins' => array(
						array(
							'table' => 'cobranzas',
							'alias' => 'Cobranza',
							'type' => 'INNER',
							'conditions' => array(
								'ClienProd.CEDULAORIF = Cobranza.CEDULAORIF',
							)
						),
					),
				));
				
			}else{
			
				/* PAOLA Esta es la consulta principal index, aquí es donde tienen que listarse las personas de este mes pero solo la ultima gestion, arriba esta el arreglo conditions donde se ven bien las condiciones */ 
			
				$consultas = $this->ClienGest->find('all', array(
					'conditions' => $conditions,
					'fields' => array('ClienGest.*','Cobranza.NOMBRE'),
					'joins' => array(
						array(
							'table' => 'cobranzas',
							'alias' => 'Cobranza',
							'type' => 'INNER',
							'conditions' => array(
								'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
							)
						),
						array(
							'table' => 'clientes',
							'alias' => 'Cliente',
							'type' => 'INNER',
							'conditions' => array(
								'ClienGest.rif_emp = Cliente.rif'
							)
						
						),
					),
				));
			}
			
			// debug($data);
			// debug($conditions);
		}else{
			$sin_gestion = 0;
			$fecha_primero_mes = date('Y-m-01:00:00:00');
			$fecha_ultimo_mes = date('Y-m-t:00:00:00');
			$consultas = $this->ClienGest->find('all', array(
					'conditions' => array(
						'ClienGest.fecha >= ' => $fecha_primero_mes,
						'ClienGest.fecha <= ' => $fecha_ultimo_mes,
					),
					'fields' => array('ClienGest.*','Cobranza.NOMBRE','Cliente.rif'),
					'order' =>  array('fecha' => 'DESC'),
					'joins' => array(
						array(
							'table' => 'cobranzas',
							'alias' => 'Cobranza',
							'type' => 'INNER',
							'conditions' => array(
								'ClienGest.cedulaorif = Cobranza.CEDULAORIF',
								),
						),
						array(
							'table' => 'clientes',
							'alias' => 'Cliente',
							'type' => 'INNER',
							'conditions' => array(
								'ClienGest.rif_emp = Cliente.rif'
							)
						
						),
					),
				));
		}
		
		$empresas = $this->Cliente->find('list', array(
		'fields' => array('rif','nombre')));
		
		// debug($consultas);
		
		// aquí mandamos a la vista la empresa que fue seleccionada en la misma vista
		if(!empty($this->request->data['User']['empresa'])){ 
			$empresa = $this->request->data['User']['empresa'];
			$this->set('empresa', $empresa);
			// debug($empresa);
		}
		$this->set(compact('empresas','consultas','sin_gestion'));
	}
}

?>
