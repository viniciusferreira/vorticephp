<?php
//TODO: Description of Class Dispatcher and description of methods
class Dispatcher{

	private $fw;
	private $masterLoaded;
	private $view = '';
	
	public function __construct($fw){
		
		$this->fw = $fw;
		$this->masterLoaded = false;
		
		require_once 'Controller.php';
		require_once 'Crypt.php';
		require_once 'Session.php';
		require_once 'Response.php';
		require_once 'DTO.php';
		require_once 'Database.php';
		
	}
	
	private function &loadPars($uri){
		preg_match_all('@(([a-z0-9\-\_]+):([^/]*))@', $uri, $match, PREG_SET_ORDER);
		$pars = array();
		foreach ($match as $m) $pars[$m[2]] = $m[3];
		$_POST = array_merge($pars, $_POST);
		return $_POST;
	}
	
	private function decomposeRequest($uri){
		$request = array(
			'module' => $this->fw->env->modulepath,
			'controller' => 'index',
			'action' => 'index',
			'view' => 'index:index',
			'template' => '',
			'format' => 'html',
			'code' => 200,
			'pars' => array()
		);
		
		if ($uri === '/'){
			return $request;
		}
		
		if (!preg_match('@^/([a-z0-9\-_]+/)?([a-z0-9\-_]+/)?([a-z0-9\-_]+:[^/]+/)*$@', $uri))
			throw new VorticeException ('Invalid URI format!', 404);
		
		if (preg_match('@^/([a-z0-9_\-]+)/([a-z0-9_\-]+)/@', $uri, $match)){
			$request['controller'] = $match[1];
			$request['action'] = $match[2];
		}elseif (preg_match('@^/([a-z0-9_\-]+)/@', $uri, $match))
			$request['controller'] = $match[1];
		
		$request['view'] = $request['controller'] . ':' . $request['action'];
		$this->view = $request['view'];
			
		$request['pars'] = $this->load_pars($uri);
		return $request;
	}
	
	public function executeUri($uri){
		return $this->execute($this->decomposeRequest($uri));
	}
	
	public function execute($request){
		
		if (!defined('action')){
			define ('action', $request['action']);
			define ('controller', $request['controller']);		
		}	
	
		$request['pars'] = array_merge($_POST, $request['pars']);
		$_POST = & $request['pars'];
		ob_start();
		extract($request);
		$class = camelize($controller) . 'Controller';
		$path = "{$module}controller/";
		if (!$this->masterLoaded){
			$this->execMaster($path, $pars);
			$this->masterLoaded = true;
		}
		$request = $this->execController($path, $class, $request);
		
		new Response($request);
		$content = ob_get_clean();

		if (isset($request['code']))
			set_header($request['code']);
				
		if ($request['format'] == 'html'){
			require_once 'Template.php';
			$template = new Template($content, $request['template']);
		}
		return $template->execute();
	}
	
	private function execMaster($path, $pars){
		$file = $path . 'MasterController.php';
		if (file_exists($file)){
			require_once ($file);
			if (class_exists('MasterController')){
				$obj = new MasterController();
				$obj->pars = $pars;
				if (method_exists($obj, 'app')) $obj->app();
			}else throw new VorticeException ('MasterController not found in the MasterController file: '. $file, 500);
		}
	}
	
	private function execController($path, $class, &$request){
		$file = $path . $class . '.php';
		if (file_exists($file)){
			require_once ($file);
			if (class_exists($class)){
				$action = &$request['action'];
				$obj = new $class();
				$obj->pars = $request['pars'];
				$obj->_request = &$request;
				if (!method_exists($obj, $action)) throw new VorticeException ($class . '->' . $action . ' not found in the class ' . $class, 404);
				if (method_exists($obj, $action)) $obj->$action();
				$request = $obj->_request;
				return $request;
			}else
				throw new VorticeException ('Class ' . $class . ' not found in the file: '. $file, 500);
		}else 
			if ($class !== 'MasterController') throw new VorticeException ('Controller file not found: '. $file, 404);
	}
	
	public function setView($view){
		$this->view = $view;
	}

}
