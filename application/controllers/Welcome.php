<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	/**
	 * @property CI_DB_active_record $db
	 * @property CI_DB_forge $dbforge
	 * @property CI_Benchmark $benchmark
	 * @property CI_Calendar $calendar
	 * @property CI_Cart $cart
	 * @property CI_Config $config
	 * @property CI_Controller $controller
	 * @property CI_Email $email
	 * @property CI_Encrypt $encrypt
	 * @property CI_Exceptions $exceptions
	 * @property CI_Form_validation $form_validation
	 * @property CI_Ftp $ftp
	 * @property CI_Hooks $hooks
	 * @property CI_Image_lib $image_lib
	 * @property CI_Input $input
	 * @property CI_Language $language
	 * @property CI_Loader $load
	 * @property CI_Log $log
	 * @property CI_Model $model
	 * @property CI_Output $output
	 * @property CI_Pagination $pagination
	 * @property CI_Parser $parser
	 * @property CI_Profiler $profiler
	 * @property CI_Router $router
	 * @property CI_Session $session
	 * @property CI_Sha1 $sha1
	 * @property CI_Table $table
	 * @property CI_Trackback $trackback
	 * @property CI_Typography $typography
	 * @property CI_Unit_test $unit_test
	 * @property CI_Upload $upload
	 * @property CI_URI $uri
	 * @property CI_User_agent $user_agent
	 * @property CI_Validation $validation
	 * @property CI_Xmlrpc $xmlrpc
	 * @property CI_Xmlrpcs $xmlrpcs
	 * @property CI_Zip $zip
	 *
	 * Add additional libraries you wish
	 * to use in your controllers here
	 *
	 * @property Accounts_model $Accounts_model
	 *
	 */

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
		$this->load->library('twig');
		echo $this->twig->render('welcome_message', array('text'=>'Hello world!'));
	}

	public function cli() {
		$this->load->library('test');
		echo "Hello world!\n";
	}
}
