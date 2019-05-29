<?php

namespace PhpPdg\ProgramDependence;

use PHPCfg\AbstractVisitor;
use PHPCfg\Block;
use PHPCfg\Op;
use PhpPdg\ProgramDependence\Node\OpNode;

// 标记返回节点
class InitializingVisitor extends AbstractVisitor {
	private $func;

	public function __construct(Func $func) {
		$this->func = $func;
	}

	public function enterOp(Op $op, Block $block) {
		$op_node = new OpNode($op);
		$this->func->pdg->addNode($op_node);
		if ($op instanceof Op\Terminal\Return_) {
			assert(!isset($this->func->return_nodes[$op_node->getHash()]));
			$this->func->return_nodes[$op_node->getHash()] = $op_node;
		}
	}
}