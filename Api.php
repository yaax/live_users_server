<?php

class Api {
	private $db_name="./db.csv";
	private $db=null;
	private $uri=null;
	private $requestMethod;
	private $user_agent;
	private $user_ip;

	private function getRealIpAddr()
	{
		if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
		{
			$ip=$_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
		{
			$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else
		{
			$ip=$_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	public function __construct($uri, $requestMethod, $user_agent)
	{
		if (!empty($uri[1])) {
			$this->uri=$uri[1];
		}
		$this->db = new TextDB($this->db_name);
		$this->requestMethod = $requestMethod;
		$this->user_agent = $user_agent;
		$this->user_ip = $this->getRealIpAddr();
	}

	public function processRequest()
	{
		$response = $this->notFoundResponse();

		switch ($this->requestMethod) {
			case 'GET': //make API working by GET method for easier development, may be removed in production
			case 'POST':
				switch ($this->uri) {
					//Save new user - can be insert or update
					case "save_data":
						if (!$this->validateUser($_REQUEST)) {
							return $this->unprocessableEntityResponse();
						}

						$users = $this->db->readDB();

						$input['name'] = $_REQUEST['name'];
						$input['email'] = $_REQUEST['email'];
						$input['session_id'] = $_REQUEST['session_id'];
						$input['user-agent'] = $this->user_agent;
						$input['ip'] = $this->user_ip;
						$input['updated'] = date("Y-m-d H:i:s");

						$update = false;
						if (!empty($users[0]['email']) && !empty($_REQUEST['email'])) {
							$sessions = array_reduce($users, function ($result, $item) {
								$result[$item['id']] = $item['email'] . ' ' . $item['session_id'];
								return $result;
							});

							//if found user with same email and session_id then need to update its visits counter
							$id = array_search($_REQUEST['email'] . ' ' . $_REQUEST['session_id'], $sessions);
							if ($id !== FALSE) {
								$update = true;
								$response = $this->db->updateData($input);
							}
						}

						//If this is a new user - then just insert it
						if (!$update) {
							$response = $this->createUserFromRequest();
						}
						break;

					//Get list of active users, so here we need also to check which users are inactive too long
					case "list_users":
						$response['status_code_header'] = 'HTTP/1.1 200 Ok';
						$response['body'] = json_encode($this->db->readDB($_REQUEST['session_id']));
						if (!empty($_REQUEST['session_id']) && preg_match("/[a-z0-9-]/i",$_REQUEST['session_id'])) {
							$input = ['session_id'=>$_REQUEST['session_id']];
							$update_result = $this->db->updateData($input,true);
						}
						break;

					// Get user details for display in popup
					case "get_user":
						if (empty($_REQUEST['hash']) || !preg_match('/[a-z0-9]+/',$_REQUEST['hash']) ) {
							return $this->unprocessableEntityResponse();
						}
						$session_id = null;
						if (!empty($_REQUEST['session_id']) && preg_match("/[a-z0-9-]/i",$_REQUEST['session_id'])) {
							$session_id=$_REQUEST['session_id'];
						}
						$users = $this->db->readDB($session_id);
						$hashes = array_column($users,'hash');
						$id = array_search($_REQUEST['hash'], $hashes);
						$response['status_code_header'] = 'HTTP/1.1 200 Ok';
						if ($id !== FALSE) {
							$response['body'] = json_encode($users[$id]);
						} else {
							$response['body'] = "Error: user not found";
						}
						break;

					default:
						$response = $this->notFoundResponse();
						break;
				}

				break;
			default:
				$response = $this->notFoundResponse();
				break;
		}
		header($response['status_code_header']);
		if ($response['body']) {
			echo $response['body'];
		}
	}

	private function createUserFromRequest()
	{
		$input['name']=$_REQUEST['name'];
		$input['email']=$_REQUEST['email'];
		$input['session_id']=$_REQUEST['session_id'];
		$input['user-agent']=$this->user_agent;
		$input['ip']=$this->user_ip;
		$input['created']=$input['updated']=date("Y-m-d H:i:s");
		$input['visits']=1;

		$this->db->insert($input);
		$response['status_code_header'] = 'HTTP/1.1 201 Created';
		$response['body'] = json_encode($input);
		return $response;
	}

	private function validateUser($input)
	{
		if (empty($input['name']) || !preg_match("/[a-z0-9\s\.\"\']/i",$input['name'])) {
			return false;
		}
		if (empty($input['email']) || !preg_match("/^([a-zA-Z0-9\.\+]+@+[a-zA-Z]+(\.)+[a-zA-Z]{2,3})$/",$input['email'])) {
			return false;
		}
		if (empty($input['session_id']) || !preg_match("/[a-z0-9-]/i",$input['session_id'])) {
			return false;
		}
		return true;
	}

	private function unprocessableEntityResponse()
	{
		$response['status_code_header'] = 'HTTP/1.1 422 Unprocessable Entity';
		$response['body'] = json_encode([
			'error' => 'Invalid input'
		]);
		return $response;
	}

	private function notFoundResponse()
	{
		$response['status_code_header'] = 'HTTP/1.1 404 Not Found';
		$response['body'] = null;
		return $response;
	}
}