<?php

namespace PhpPdg\ProgramDependence\ControlDependence\PostDominatorTree;

use PhpPdg\Graph\FactoryInterface;
use PhpPdg\Graph\GraphInterface;
use PhpPdg\Graph\Node\NodeInterface;

class Generator implements GeneratorInterface {
	/** @var FactoryInterface  */
	private $graph_factory;

	public function __construct(FactoryInterface $graph_factory) {
		$this->graph_factory = $graph_factory;
	}

	public function generate(GraphInterface $graph, NodeInterface $stop_node) {
	    // 生成一个hash=>node 的数组
		$nodes_by_hash = [];
		$all_hashes = array_keys($nodes_by_hash);
		foreach ($graph->getNodes() as $node) {
			$hash = $node->getHash();
			$nodes_by_hash[$hash] = $node;
			$all_hashes[] = $hash;
		}
		// 初始化所有节点的后序支配，
		// initialize all node postdominators
		$post_dominators = array_fill_keys($all_hashes, $all_hashes); // n*n
		$stop_node_hash = $stop_node->getHash();
		$post_dominators[$stop_node_hash] = [$stop_node_hash];

		// 迭代地确定后支配者。不纠结这个算法了，结果就是生成了后序支配树
        // 这可能不是最快的方法，但它很简单，现在应该足够了。
		// Iteratively determine post-dominators.
		// This is probably not the fastest way to do this, but it is simple, and should be enough for now.
		do {
			$changes = false;
			foreach ($nodes_by_hash as $hash => $node) {
			    // 节点的后支配者包括所有出边节点的后支配者和节点本身的交集。
				// A node's post-dominators consist of the intersection of the post dominators of all outgoing edge nodes, and the node itself.
				$new_post_dominators = null;
				foreach ($graph->getEdges($node) as $edge) {
					$to_node_post_dominator_hashes = $post_dominators[$edge->getToNode()->getHash()];
					$new_post_dominators = $new_post_dominators === null ? $to_node_post_dominator_hashes : array_intersect($new_post_dominators, $to_node_post_dominator_hashes);
				}
				$new_post_dominators = array_unique(array_merge((array) $new_post_dominators, [$hash]));

				// If changes, store new post dominators and ensure we do another iteration
				if (count($new_post_dominators) !== count($post_dominators[$hash])) {
					$post_dominators[$hash] = $new_post_dominators;
					$changes = true;
				}
			}
		} while ($changes === true);

		// remove non-strict post dominators and compute post-dominations - this allows easy adding of immediate dominators
		$post_dominations = [];
		foreach ($post_dominators as $hash => $post_dominator_hashes) {
			foreach ($post_dominator_hashes as $index => $post_dominator_hash) {
				if ($hash === $post_dominator_hash) {
					unset($post_dominators[$hash][$index]);
				} else {
					$post_dominations[$post_dominator_hash][] = $hash;
				}
			}
		}

		$pdt_graph = $this->graph_factory->create();
		foreach ($nodes_by_hash as $node) {
			$pdt_graph->addNode($node);
		}
		foreach ($post_dominators as $node_hash => $post_dominator_hashes) {
			foreach ($post_dominator_hashes as $post_dominator_hash) {
				// if this is an immediate post-dominator, then there are no nodes that both dominate $node_hash and are dominated by $post_dominator_hash
				if (empty(array_diff(array_intersect($post_dominations[$post_dominator_hash], $post_dominator_hashes), [$node_hash, $post_dominator_hash])) === true) {
					$pdt_graph->addEdge($nodes_by_hash[$node_hash], $nodes_by_hash[$post_dominator_hash]);
				}
			}
		}

		return $pdt_graph;
	}
}