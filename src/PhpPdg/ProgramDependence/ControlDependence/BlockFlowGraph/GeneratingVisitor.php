<?php

namespace PhpPdg\ProgramDependence\ControlDependence\BlockFlowGraph;

use PHPCfg\AbstractVisitor;
use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op;
use PhpPdg\Graph\GraphInterface;
use PhpPdg\Graph\Node\NodeInterface;
use PhpPdg\ProgramDependence\Node\BlockNode;

class GeneratingVisitor extends AbstractVisitor {
	/** @var GraphInterface */
	public $graph;
	/** @var  NodeInterface */
	public $entry_node;
	/** @var  NodeInterface */
	public $stop_node;
	/** @var  Block */
	private $last_block_seen;
	private $acceptableBlocks;  // we use this to filter blocks that are actually part of the current flow, i.e. not class declarations or default initializers

	public function __construct(GraphInterface $graph, NodeInterface $entry_node, NodeInterface $stop_node) {
		$this->graph = $graph;
		$this->entry_node = $entry_node;
		$this->stop_node = $stop_node;
		$this->acceptableBlocks = new \SplObjectStorage();
	}

	public function enterFunc(Func $func) {
		$this->acceptableBlocks->attach($func->cfg);
	}

	public function enterBlock(Block $block, Block $prior = null) {
		if ($this->acceptableBlocks->contains($block) === true) {
			$this->last_block_seen = $block;
			$blockNode = new BlockNode($block);
			$this->graph->addNode($blockNode);

			if ($prior === null) {
				$this->graph->addEdge($this->entry_node, $blockNode);
			}
		}
	}

	public function enterOp(Op $op, Block $block) {
		if ($this->acceptableBlocks->contains($block) === true) {
			if ($op instanceof Op\Stmt\Jump) {
				$this->acceptableBlocks->attach($op->target);
			} else if ($op instanceof Op\Stmt\JumpIf) {
				$this->acceptableBlocks->attach($op->if);
				$this->acceptableBlocks->attach($op->else);
			} else if ($op instanceof Op\Stmt\Switch_) {
				foreach ($op->cases as $i => $case) {
					$this->acceptableBlocks->attach($op->targets[$i]);
				}
				if ($op->default !== null) {
					$this->acceptableBlocks->attach($op->default);
				}
			}
		}
	}


	public function leaveOp(Op $op, Block $block) {
		if ($this->acceptableBlocks->contains($block) === true) {
			if ($op instanceof Op\Stmt\Jump) {
				$this->graph->addEdge(new BlockNode($block), new BlockNode($op->target));
			} else if ($op instanceof Op\Stmt\JumpIf) {
				$from_block_node = new BlockNode($block);
				$this->graph->addEdge($from_block_node, new BlockNode($op->if), [
					'case' => true
				]);
				$this->graph->addEdge($from_block_node, new BlockNode($op->else), [
					'case' => false
				]);
			} else if ($op instanceof Op\Stmt\Switch_) {
				$from_block_node = new BlockNode($block);
				foreach ($op->cases as $i => $case) {
					$this->graph->addEdge($from_block_node, new BlockNode($op->targets[$i]), [
						'case' => $case->value
					]);
				}
				if ($op->default !== null) {
					$this->graph->addEdge($from_block_node, new BlockNode($op->default), [
						'case' => null
					]);
				}
			}
		}
	}

	public function skipBlock(Block $block, Block $prior = null) {
		if ($this->acceptableBlocks->contains($block) === true) {
			$this->last_block_seen = $block;
		}
	}

	public function leaveBlock(Block $block, Block $prior = null) {
		if ($this->acceptableBlocks->contains($block) === true && $this->last_block_seen === $block) {
			$this->graph->addEdge(new BlockNode($block), $this->stop_node);
		}
	}
}