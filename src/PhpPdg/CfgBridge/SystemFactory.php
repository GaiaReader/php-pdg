<?php

namespace PhpPdg\CfgBridge;

use PHPCfg\Parser as CfgParser;
use PhpParser\Parser as AstParser;
use PhpParser\ParserFactory;
use PhpPdg\AstBridge\System as AstSystem;

// 系统工厂
class SystemFactory implements SystemFactoryInterface {
	/** @var CfgParser */
	private $parser;

	public function __construct(AstParser $ast_parser) {
		$this->parser = new CfgParser($ast_parser);
	}

	public static function createDefault() {
		return new self((new ParserFactory())->create(ParserFactory::PREFER_PHP7));
	}

	public function create(AstSystem $ast_system) {
		$cfg_system = new System();
		foreach ($ast_system->getFilenames() as $file_path) {
			$script = $this->parser->parseAst($ast_system->getAst($file_path), $file_path);
			$cfg_system->addScript($file_path, $script);
		}
		return $cfg_system;
	}
}