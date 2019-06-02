<?php

namespace PhpPdg\ProgramDependence\ControlDependence\BlockDependenceGraph;

use PhpPdg\Graph\FactoryInterface;
use PhpPdg\Graph\GraphInterface;
use PhpPdg\Graph\Node\NodeInterface;
use PhpPdg\Graph\Printer\GraphvizPrinter;

class Generator implements GeneratorInterface {
	private $graph_factory;

	public function __construct(FactoryInterface $graph_factory) {
		$this->graph_factory = $graph_factory;
	}

	public function generate(GraphInterface $cfg, GraphInterface $pdt) {
		$cdg = $this->graph_factory->create();
		// 将cfg中的所有节点复用到cdg中
		foreach ($cfg->getNodes() as $node) {
			$cdg->addNode($node);
		}
		foreach ($cfg->getNodes() as $node_a) {
			foreach ($cfg->getEdges($node_a) as $edge) {
				$node_b = $edge->getToNode();
				// 将所有CFG边评估为A-B，其中B不支配A
				// Evaluate all CFG edges as A-B where B does not post-dominate A
				if ($pdt->hasEdges($node_a, $node_b) === false) {
					$this->addNodeControlDependences($cdg, $pdt, $node_a, $node_b, $edge->getAttributes());
				}
			};
		}
		return $cdg;
	}

	private function addNodeControlDependences(GraphInterface $cdg, GraphInterface $pdt, NodeInterface $node_a, NodeInterface $node_from_b_to_l, $edge_attributes) {
		if ($cdg->hasEdges($node_a, $node_from_b_to_l, $edge_attributes) === false) {
			$cdg->addEdge($node_a, $node_from_b_to_l, $edge_attributes);
		}

		// If $node_from_b_to_l equals $node_a, we have found Case 2 from Ferrante et al. (loop dependence) and are done.
		if ($node_a->getHash() !== $node_from_b_to_l->getHash()) {
			$parent_node_edges = $pdt->getEdges($node_from_b_to_l);
			assert(count($parent_node_edges) === 1);
			$node_from_b_to_l_parent = $parent_node_edges[0]->getToNode();
			// If $node_from_b_to_l is the parent of $node_a, we have found Case 1 from Ferrante et al. and are done.
			if ($pdt->hasEdges($node_a, $node_from_b_to_l_parent) === false) {
				$this->addNodeControlDependences($cdg, $pdt, $node_a, $node_from_b_to_l_parent, $edge_attributes);
			}
		}
	}
}