<?php

namespace PhpPdg\CfgBridge;

use PHPCfg\Script;

// CFG的system，其实仅仅是存储所有cfg
class System {
	/** @var  Script[] */
	private $scripts = [];

	public function addScript($filename, Script $script) {
		if (isset($this->scripts[$filename]) === true) {
			throw new \InvalidArgumentException("CFG with filename `$filename` already exists");
		}
		$this->scripts[$filename] = $script;
	}

	/**
	 * @return string[]
	 */
	public function getFilenames() {
		return array_keys($this->scripts);
	}

	/**
	 * @param string $filename
	 * @return Script
	 * @throws \InvalidArgumentException
	 */
	public function getScript($filename) {
		if (isset($this->scripts[$filename]) === false) {
			throw new \InvalidArgumentException("No CFG with filename `$filename`");
		}
		return $this->scripts[$filename];
	}
}