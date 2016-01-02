<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Web extends CI_Controller {

	private $isAuth = null;
	private $user = null;

	private $stats_auth = false;
	private $force_auth = false;


	function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library(array('ion_auth', 'twig', 'session'));
		$this->force_auth = $this->session->userdata('force_log_in');
		$this->stats_auth = $this->session->userdata('stats_log_in')==1;

		$isNotLoggedIn = !$this->ion_auth->logged_in();
		$isNotStatsView = !$this->stats_auth;
		if ($isNotLoggedIn && $isNotStatsView)
			$this->needAuth();
		$this->getUser(true);
//		$this->output->enable_profiler(TRUE);
	}

	public function index() {
		if ($this->stats_auth)
			redirect('/web/stats/');

		if ($this->input->server('REQUEST_METHOD')=='POST') {
			$update = [];
			$data = $this->input->get_post(null);

			// Время отчета
			if (isset($data['report_time']))
				$data['report_time'] = json_encode($data['report_time']);

			// Время отчета
			if (isset($data['report_time_single']))
				$data['report_time_single'] = json_encode($data['report_time_single']);

			// Значения минимального баланса
			if (isset($data['min_balance']))
				if (is_array($data['min_balance'])) {
					$data['min_balance_milestones'] = json_encode($data['min_balance']);
				} else {
					$data['min_balance_milestones'] = array("150", "100", "50");
				}
			$data['min_balance'] = null;

			// Обновляем файл с таблицей
			$update['file'] = $this->do_upload('file');
			if ($update['file']['status']) {
				$data['file'] = $update['file']['upload_data']['file_name'];
				$data['parsed_file'] = "";
			} else
				$data['file'] = null;
			$data = array_filter($data);
			if (isset($data['on'])) {
				if (!$this->user['sys_active']) {
					$data[ 'sys_active' ] = true;
					$data[ 'active_time' ] = date('Y-m-d H:i:s');
				}
			} else
				$data['sys_active'] = false;
			unset($data['on']);
			$update['upd'] = $this->db->update('users', $data, "id = {$this->user['id']}");
		}



		$_logs = $this->db->select('*')
			->where('user_id', $this->user['id'])
			->limit(15)
			->order_by('id', 'desc')
			->get('logs');


		$logs = [];
		foreach ($_logs->result() as $log)
			$logs[] = $log;



		$params = [
			'isAuth'	=> $this->ion_auth->logged_in(),
			'isAdmin'	=> $this->ion_auth->is_admin(),
			'user'		=> $this->getUser(),
			'logs'		=> $logs,
			'title'		=> 'Настройки',
			'force'     => $this->force_auth
		];
		$this->twig->render('index', $params);
	}

	public function admin() {
		if (!$this->ion_auth->is_admin())
			redirect('/web/');

		$edit 	= $this->input->get('edit');
		$create = $this->input->get('create');
		$delete = $this->input->post('delete');
		$copy 	= $this->input->post('copyid');

		$login = $this->input->post('login');
		$pass = $this->input->post('password');


		if ($edit) {
			if (!$delete) {
				$this->ion_auth->update($edit, ['password'=>$pass]);
			} else
				$this->db->delete('users', array('id' => $edit));
		} elseif ($create) {
			if ($login!=''&&$pass!='') {
				$additional = array(
					'username' => $login
				);
				if ($copy) {
					$user = $this->db->select('*')
						->where('id', $copy)
						->limit(1)
						->order_by('id', 'desc')
						->get('users')->row_array();
					$unset = ["id"=>null, "ip_address"=>null, "username"=>null, "password"=>null, "salt"=>null, "email"=>null, "activation_code"=>null, "forgotten_password_code"=>null, "forgotten_password_time"=>null, "remember_code"=>null, "created_on"=>null, "last_login"=>null];
					$additional = array_merge($additional, array_diff_key($user, $unset));
				}
				$this->ion_auth->register($login, $pass, $login, $additional);
			}
		}

		$params = [
			'isAuth'	=> $this->ion_auth->logged_in(),
			'isAdmin'	=> true,
			'user'		=> $this->getUser(),
			'title'		=> 'Управление Системой',
			'users'		=> $this->getUsers()
		];
		$this->twig->render('admin', $params);
	}

	public function stats() {
		$params = [
			'isAuth'	=> $this->ion_auth->logged_in(),
			'isAdmin'	=> $this->ion_auth->is_admin(),
			'user'		=> $this->getUser(),
			'title'		=> 'Управление Системой'
		];
		$this->twig->render('stats', $params);
	}

	public function log_in($id) {
		$user = $this->db->select('username, email, id, password, active, last_login')
			->where('id', $id)
			->limit(1)
			->order_by('id', 'desc')
			->get('users')->row();
		$this->ion_auth->set_session($user);
		if ($id==1)
			$this->session->set_userdata('force_log_in', '0');
		else
			$this->session->set_userdata('force_log_in', '1');
		redirect('/web/admin/');
	}

	public function ajax_index() {

		$users = [];
		if ($this->ion_auth->is_admin()) {
			$users = $this->getUsers();
		} else {
			$users[] = $this->getUser();
		}

		foreach ($users as &$user) {
			$_offers = $this->db->get_where('offers', array('user' => $user['id']));
			$user['offers'] = [];
			foreach ($_offers->result_array() as $offer) {
				$user['offers'][] = $offer;
			}
		}

		$params = [
			'isAuth'	=> $this->ion_auth->logged_in(),
			'isAdmin'	=> $this->ion_auth->is_admin(),
			'user'		=> $this->getUser(),
			'title'		=> 'Управление Системой',
			'users'		=> $users
		];
		$this->twig->render('stats/index', $params);
	}

	private function do_upload($file) {
		$this->load->helper('text');
		$config['upload_path'] = './uploads/';
		$config['allowed_types'] = 'xls|xlsx';
		$config['max_size']	= '15360';
		$_FILES['file']['name'] = strtolower($this->user['username']."_".convert_accented_characters($_FILES['file']['name']));

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload($file))
			return array('error' => $this->upload->display_errors(), 'status'=>false);
		else
			return array('upload_data' => $this->upload->data(), 'status'=>true);
	}

	private function getUser() {
		$this->user = $this->ion_auth->user()->row_array();
		foreach ($this->user as &$field)
			if ($json = json_decode($field, true))
				$field = $json;
		return $this->user;
	}

	private function getUsers() {
		$_users = $this->db->get('users');
		$users = [];
		foreach ($_users->result_array() as $user)
			$users[] = $user;
		return $users;
	}

	private function needAuth() {
		redirect('auth');
	}

}
