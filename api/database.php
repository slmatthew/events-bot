<?php

class Database {

	public $link = null;

	public function __construct() {
		$link = mysqli_connect(MYSQL_SERVER, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB) or die('Error: '.mysqli_error($link));
		if(!mysqli_set_charset($link, 'utf8')) {
			printf('Error: '.mysqli_error($link));
		}

		$this->link = $link;
	}

	public function format($string, ...$params) {
		return sprintf(mysqli_real_escape_string($this->link, $string), ...$params);
	}

	public function toJson($array) {
		return json_encode($array, JSON_UNESCAPED_UNICODE);
	}

	public function escape($string) {
		return mysqli_real_escape_string($this->link, $string);
	}
}

class ReportsTable extends Database {
	public function create(int $user_id, string $event, string $accident, array $proof) {
		$event = $this->escape($event);
		$accident = $this->escape($accident);

		$ps = [];
		foreach($proof as $key => $p) {
			$ps[] = $this->escape($p);
		}

		$ps = implode(',', $ps);
		
		$r = mysqli_query($this->link, sprintf("INSERT INTO reports VALUES (NULL, %d, '', '', '[]', %d, '%s', 0, 0, 0, 0)", $user_id, time(), json_encode(['plus' => (array)[], 'minus' => (array)[]])));
		if(!$r) throw new DatabaseException("RTC: ".mysqli_error($this->link));

		return mysqli_insert_id($this->link);
	}

	public function setEvent(int $id, string $event) {
		$r = mysqli_query($this->link, sprintf("UPDATE reports SET event = '%s' WHERE id = %d", $this->escape($event), $id));
		if(!$r) throw new DatabaseException('RTSE: '.mysqli_error($this->link));

		return true;
	}

	public function setAccident(int $id, string $accident) {
		$r = mysqli_query($this->link, sprintf("UPDATE reports SET accident = '%s' WHERE id = %d", $this->escape($accident), $id));
		if(!$r) throw new DatabaseException('RTSA: '.mysqli_error($this->link));

		return true;
	}

	public function setProof(int $id, array $proof) {
		$ps = [];
		foreach($proof as $key => $p) {
			$ps[] = $this->escape($p);
		}

		$ps = implode(',', $ps);

		$r = mysqli_query($this->link, sprintf("UPDATE reports SET proof = '%s' WHERE id = %d", $ps, $id));
		if(!$r) throw new DatabaseException('RTSP: '.mysqli_error($this->link));

		return true;
	}

	public function setUserCompleted(int $id, int $user_complete) {
		$r = mysqli_query($this->link, sprintf("UPDATE reports SET user_complete = %d WHERE id = %d", $user_complete, $id));
		if(!$r) throw new DatabaseException('RTSUC: '.mysqli_error($this->link));

		return true;
	}

	public function setCompleted(int $id, int $completed) {
		$r = mysqli_query($this->link, sprintf("UPDATE reports SET completed = %d WHERE id = %d", $completed, $id));
		if(!$r) throw new DatabaseException('RTSC: '.mysqli_error($this->link));

		return true;
	}

	public function setPosted(int $id, int $posted) {
		$r = mysqli_query($this->link, sprintf("UPDATE reports SET posted = %d WHERE id = %d", $posted, $id));
		if(!$r) throw new DatabaseException('RTSC: '.mysqli_error($this->link));

		return true;
	}

	public function getById(int $id) {
		$r = mysqli_query($this->link, sprintf("SELECT * FROM reports WHERE id = %d", $id));
		if(!$r) throw new DatabaseException("RTGBI: ".mysqli_error($this->link));

		return mysqli_fetch_assoc($r);
	}

	public function getLastByUserId(int $user_id) {
		$r = mysqli_query($this->link, sprintf("SELECT * FROM reports WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id));
		if(!$r) throw new DatabaseException("RTGLBUI: ".mysqli_error($this->link));

		return mysqli_fetch_assoc($r);
	}

	public function addMessageId(int $report_id, int $message_id) {
		$r = mysqli_query($this->link, sprintf("UPDATE reports SET moder_msg_id = %d WHERE id = %d", $message_id, $report_id));
		if(!$r) throw new DatabaseException("RTAMI: ".mysqli_error($this->link));

		return true;
	}

	public function addVote(int $report_id, int $user_id, string $vote_type) {
		if(!in_array($vote_type, ['plus', 'minus']) || $report_id <= 0 || $user_id <= 0) throw new ParamException('');

		$report = $this->getById($report_id);

		if(!empty($report)) {
			$poll = json_decode($report['poll'], true);
			if(!in_array($user_id, $poll['plus']) && !in_array($user_id, $poll['minus'])) {
				$poll[$vote_type][] = $user_id;

				$result = mysqli_query($this->link, sprintf("UPDATE reports SET poll = '%s' WHERE id = %d", json_encode($poll), $report_id));
				if(!$result) throw new DatabaseException("RTAV: ".mysqli_error($this->link));

				return true;
			} else throw new VoteException('User already vote');
		} else throw new DatabaseException("RTAV: invalid report_id");

		return false;
	}

	public function getCount(int $user_id) {
		return mysqli_fetch_assoc(mysqli_query($this->link, sprintf("SELECT COUNT(*) as count FROM reports WHERE user_id = %d", $user_id)))['count'];
	}

	public function getAllNotCompleted() {
		$r = mysqli_query($this->link, sprintf("SELECT * FROM reports WHERE completed = 0 ORDER BY id DESC"));
		if(!$r) throw new DatabaseException("RTGANC: ".mysqli_error($this->link));

		$reports = [];
		while($tmp = mysqli_fetch_assoc($r)) {
			$reports[] = $tmp;
		}

		return $reports;
	}
}

?>