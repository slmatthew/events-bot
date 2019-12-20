<?php

class CommandsEngine {
	private $commands = [];
	private $type = 'text';

	public function __construct(string $type = 'text') {
		$this->type = $type;
	}

	public function addCommand(string $name, callable $onData) {
		switch($this->type) {
			case 'text':
				$name = "/{$name}";
				break;

			default:
				$name = $name;
				break;
		}

		$this->commands[$name] = $onData;
	}

	public function checkCommand(string $name) {
		$name = mb_strtolower($name);

		return isset($this->commands[$name]);
	}

	public function runCommand(string $name, array $data) {
		$name = mb_strtolower($name);

		if($this->checkCommand($name)) {
			return $this->commands[$name]($data);
		}

		return -1;
	}
}

?>