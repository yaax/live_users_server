<?php

if(!function_exists('str_putcsv'))
{
	function str_putcsv($input, $delimiter = ',', $enclosure = '"')
	{
		// Open a memory "file" for read/write...
		$fp = fopen('php://temp', 'r+');
		// ... write the $input array to the "file" using fputcsv()...
		fputcsv($fp, $input, $delimiter, $enclosure);
		// ... rewind the "file" so we can read what we just wrote...
		rewind($fp);
		// ... read the entire line into a variable...
		$data = fread($fp, 1048576);
		// ... close the "file"...
		fclose($fp);
		// ... and return the $data to the caller, with the trailing newline from fgets() removed.
		return rtrim($data, "\n");
	}
}

// Text Database class to manage access for the CSV database file db.csv
class TextDB
{
	private $db_path=null;
	private $db_data=null;
	const CSV_HEADERS="name,email,session_id,user-agent,ip,created,updated,visits,active,hash,last_active";

	// How many times to try to write to the DB file when many users are trying to access
	const MAX_RETRIES = 1000;

	// User considered inactive if during last 15 minutes were not accepted any ajax requests from his session id
	const OFFLINE_TIMEOUT = 900; //900 secs = 15 minutes

	public function __construct($db_filename)
	{
		if (!empty($db_filename) && file_exists($db_filename)) {
			$this->db_path = $db_filename;
		}
	}

	private function getRandomString($length = 8) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
		$string = '';

		for ($i = 0; $i < $length; $i++) {
			$string .= $characters[mt_rand(0, strlen($characters) - 1)];
		}

		return $string;
	}

	public function insert($new_data) {
		$new_data['active']=1;

		// This way hash will be unique for almost all cases unless number of users will grow to billions which is not supported yet by this task
		$new_data['hash']=$this->getRandomString(32);

		// This is a last active field
		$new_data['last_active']=date("Y-m-d H:i:s");
		$csv_line = str_putcsv($new_data,',','"');

		$write_result = $this->addData($csv_line,"a");
		return $write_result;
	}

	public function readDB($session_id=null) {
		$fp = fopen($this->db_path, 'r');
		if(!$fp) {
			return false;
		}
		$this->db_data = [];
		$i=0;
		$header=[];
		$need_update = false;
		while(!feof($fp) && ($line = fgetcsv($fp)) !== false) {
			if (!$i) {
				$header=$line;
			} else {
				$new_row = array_combine($header, $line);
				if (!empty($session_id)) {
					$last_active_time = strtotime($new_row['last_active']);
					$now = time();

					//check if user is offline too long
					if (($now-$last_active_time)>self::OFFLINE_TIMEOUT) {
						$new_row['active']=0;
						$need_update=true;
					}
				}
				if ($new_row['active']==1) {
					$this->db_data[] = $new_row;
				}
			}
			$i++;
		}
		fclose($fp);

		if ($need_update) { // need deactivate offline users
			$result = $this->updateData(["session_id"=>$session_id],true);
		}

		return $this->db_data;
	}

	/**
	 * Add Data - just add new line into CSV file
	 *
	 * @param string $data The data to write.
	 * @param string $mode Suggested mode 'a' for writing to the end of the
	 *        file.
	 * @return boolean
	 */
	private function addData($data, $mode = 'a')
	{
		$fp = fopen($this->db_path, $mode);

		$retries = 0;

		if (! $fp)
		{
			// failure
			return false;
		}

		// keep trying to get a lock as long as possible
		do
		{
			if ($retries > 0)
			{
				usleep(rand(100, 5000));
			}
			$retries += 1;
		}
		while (! flock($fp, LOCK_EX) and $retries <= self::MAX_RETRIES);

		// couldn't get the lock, give up
		if ($retries == self::MAX_RETRIES)
		{
			// failure
			return false;
		}

		// got the lock, write the data
		fwrite($fp, "$data" . PHP_EOL);

		// release the lock
		flock($fp, LOCK_UN);
		fclose($fp);

		// success
		return true;
	}

	/**
	 * Update Data - this function will rewrite entire DB file with applying requested changes
	 *
	 * @param array $data Updated user data
	 * @param boolean $by_session_only update all users by session id only or by email and session id
	 * @param string $mode Suggested mode 'r+' waiting for file to be unlocked - then locking it, then reading its actual data then writing updated matching row file.
	 * @return array
	 */
	public function updateData($data, $by_session_only=false, $mode = 'r+')
	{
		$fp = fopen($this->db_path, $mode);

		$retries = 0;

		if (!$fp)
		{
			// failure
			return false;
		}

		// keep trying to get a lock as long as possible
		do
		{
			if ($retries > 0)
			{
				usleep(rand(100, 5000));
			}
			$retries += 1;
		}
		while (!flock($fp, LOCK_EX) and $retries <= self::MAX_RETRIES);

		// couldn't get the lock, give up
		if ($retries == self::MAX_RETRIES)
		{
			// failure
			return false;
		}

		$this->db_data = [];
		$header = [];
		$i=0;
		while(!feof($fp) && ($line = fgetcsv($fp)) !== false) {
			if (!$i) {
				$header=$line;
			} else {
				$this->db_data[] = array_combine($header, $line);
			}
			$i++;
		}

		if (!$by_session_only) { // update single user - just add visit and update date
			$sessions = array_reduce($this->db_data, function ($result, $item) {
				$result[] = $item['email'] . ' ' . $item['session_id'];
				return $result;
			});
			$id = array_search($data['email'].' '.$data['session_id'], $sessions);
			if ($id!==FALSE) {
				$this->db_data[$id]['name']=$data['name'];
				$this->db_data[$id]['user-agent']=$data['user-agent'];
				$this->db_data[$id]['ip']=$data['ip'];
				$this->db_data[$id]['visits']++;
				$this->db_data[$id]['active']=1;
				$this->db_data[$id]['updated']=date("Y-m-d H:i:s");
				$this->db_data[$id]['last_active']=date("Y-m-d H:i:s");
			} else {
				return false;
			}
		} else { // update multiple users - mark active and deactivate sessions which are not online
			$sessions = array_column($this->db_data,'session_id');
			$ids = array_keys($sessions,$data['session_id']);
			if (!empty($ids)) {
				foreach ($ids as $id) {
					$this->db_data[$id]['last_active']=date("Y-m-d H:i:s");
					$this->db_data[$id]['active']=1;
				}
			}

			foreach ($this->db_data as $id=>$user) {
				if (!empty($user['last_active'])) {
					$last_active_time = strtotime($user['last_active']);
					$now = time();
					if (($now-$last_active_time)>self::OFFLINE_TIMEOUT) {
						$this->db_data[$id]['active']=0;
					}
				}
			}
		}

		rewind($fp);
		$headers = explode(',',self::CSV_HEADERS);
		fputcsv($fp, $headers);
		foreach ($this->db_data as $csv_data) {
			fputcsv($fp, $csv_data);
		}
		fflush($fp);
		//ftruncate($fp, ftell($fp));

		// release the lock
		flock($fp, LOCK_UN);
		fclose($fp);

		// success
		$response['status_code_header'] = 'HTTP/1.1 200 OK';
		$response['body'] = json_encode($this->db_data[$id]);
		return $response;
	}

}