<?php

class Telegram {
	public $token = '';

	public function __construct(string $token) {
		$this->token = $token;
	}

	public function call(string $method, array $params = []) {
		$ch = curl_init("https://api.telegram.org/bot{$this->token}/{$method}");

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Events Bot/1.0");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$json = curl_exec($ch);
		curl_close($ch);

		return json_decode($json, true);
	}

	public function send(int $chat_id, string $text, array $params = []) {
		$params['chat_id'] = $chat_id;
		$params['text'] = $text;

		return $this->call('sendMessage', $params);
	}

	public function sendPhoto(int $chat_id, string $text, string $photo, array $params = []) {
		$params['chat_id'] = $chat_id;
		$params['caption'] = $text;
		$params['photo'] = $photo;

		return $this->call('sendPhoto', $params);
	}

	public function sendAlbum(int $chat_id, string $text, array $photos, array $params = []) {
		$params['chat_id'] = $chat_id;
		$params['text'] = $text;
		$params['caption'] = $text;

		$media = [];
		foreach($photos as $key => $photo) {
			$media[] = [
				'type' => 'photo',
				'media' => $photo,
				'caption' => $text
			];
		}

		$params['media'] = json_encode($media, JSON_UNESCAPED_UNICODE);

		return $this->call('sendMessage', $params);
	}

	public function sendCbAnswer(string $cbq_id, array $params = []) {
		$params['callback_query_id'] = $cbq_id;

		return $this->call('answerCallbackQuery', $params);
	}

	public function getFile(string $file_id) { return $this->call('getFile', ['file_id' => $file_id]); }
}

class TelegramLongpoll extends Telegram {
	private $params = [];

	public function setLpParams(array $allowed_updates, int $timeout, int $offset = null, int $limit = null) {
		if($offset !== null) $this->params['offset'] = $offset;
		if($limit !== null) $this->params['limit'] = $limit;
		if($timeout !== null) $this->params['timeout'] = $timeout;
		if($allowed_updates !== null) $this->params['allowed_updates'] = json_encode($allowed_updates, JSON_UNESCAPED_UNICODE);
	}

	public function startLp(callable $onData) {
		while(true) {
			$r = $this->call('getUpdates', $this->params);
			if(isset($r['ok']) && $r['ok'] && isset($r['result']) && !empty($r['result'])) {
				$this->params['offset'] = $r['result'][count($r['result']) - 1]['update_id'] + 1;
				$onData($r);
			}
		}
	}
}

class InlineKeyboard {
	private $buttons = [];
	private $currentIndex = 0;

	public function addButton(string $text, array $params = []) {
		$params['text'] = $text;
		$this->buttons[$this->currentIndex][] = $params;
	}

	public function addLine() {
		$this->currentIndex++;
	}

	public function get(bool $json = true) {
		$kb = [
			'inline_keyboard' => $this->buttons
		];

		return $json ? json_encode($kb, JSON_UNESCAPED_UNICODE) : $kb;
	}
}

class ReplyKeyboard {
	private $buttons = [];
	private $currentIndex = 0;

	private $resize_keyboard = false;
	private $one_time_keyboard = false;
	private $selective = false;

	public function __construct(bool $resize_keyboard = false, bool $one_time_keyboard = false, bool $selective = false) {
		$this->resize_keyboard = $resize_keyboard;
		$this->one_time_keyboard = $one_time_keyboard;
		$this->selective = $selective;
	}

	public function addButton(string $text, array $params = []) {
		$params['text'] = $text;
		$this->buttons[$this->currentIndex][] = $params;
	}

	public function addLine() {
		$this->currentIndex++;
	}

	public function get(bool $json = true) {
		$kb = [
			'keyboard' => $this->buttons,
			'resize_keyboard' => $this->resize_keyboard,
			'one_time_keyboard' => $this->one_time_keyboard,
			'selective' => $this->selective
		];

		return $json ? json_encode($kb, JSON_UNESCAPED_UNICODE) : $kb;
	}
}

?>