<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class Twig
{
	private $CI;
	private $_twig;
	private $_template_dir;
	private $cache_dir;
	
	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
	    $this->CI =& get_instance();
	    $this->CI->config->load('twig');
		log_message('debug', "Twig Autoloader Loaded");

		$this->_template_dir = $this->CI->config->item('template_dir');
		$this->_cache_dir = $this->CI->config->item('cache_dir');
		$loader = new Twig_Loader_Filesystem($this->_template_dir, $this->_cache_dir);

//		prod
//      $this->_twig = new Twig_Environment($loader);

//		dev
		$this->_twig = new Twig_Environment($loader, array('debug' => true));
		$this->_twig->addExtension(new Twig_Extension_Debug());
		
	}

	public function build($template, $data = array()) {
        return $this->template($template)->render($data);
	}

	public function render($template, $data = array()) {
		echo $this->build($template, $data);
	}



	private function template($template) {
		return $this->_twig->loadTemplate($template.'.twig');
	}
}

?>