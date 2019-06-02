<?php

namespace PhpPdg\SystemDependence\CallDependence;

use PHPCfg\Op\Stmt\Function_;
use PHPCfg\Operand\Literal;
use PhpPdg\Graph\Graph;
use PhpPdg\Graph\GraphInterface;
use PhpPdg\ProgramDependence\Node\OpNode;
use PhpPdg\SystemDependence\Node\BuiltinFuncNode;
use PhpPdg\SystemDependence\Node\UndefinedFuncNode;
use PhpPdg\SystemDependence\System;
use PHPTypes\State;

// 对于函数调用的处理，
class FunctionCallGenerator implements GeneratorInterface {
	public function addCallDependencesToSystem(System $system, State $state, \SplObjectStorage $pdg_func_lookup) {
		$sdg = $system->sdg;
		// link function calls to their functions
        // 链接函数调用其函数
		foreach ($state->funcCalls as $funcCallPair) {
			list($func_call, $containing_cfg_func) = $funcCallPair;
			$call_node = new OpNode($func_call);
			$system->sdg->addNode($call_node);
			assert(isset($pdg_func_lookup[$containing_cfg_func]));
			$system->sdg->addEdge($pdg_func_lookup[$containing_cfg_func], $call_node, ['type' => 'contains']);

			if ($func_call->name instanceof Literal) {
				$name = strtolower($func_call->name->value);
				if (isset($state->functionLookup[$name]) === true) {
					$this->linkFunctions($sdg, $call_node, $state->functionLookup[$name], $pdg_func_lookup);
				}
				// take monkey-patching into account and also link builtin functions
				if (isset($state->internalTypeInfo->functions[$name]) === true) {
					Graph::ensureNodeAndEdgeAdded($sdg, $call_node, new BuiltinFuncNode($name, null), ['type' => 'call']);
				}
				// if we haven't linked anything yet, this is most likely a vendor function (because we know its name).
				if ($sdg->hasEdges($call_node, null, ['type' => 'call']) === false) {
					Graph::ensureNodeAndEdgeAdded($sdg, $call_node, new UndefinedFuncNode($name, null), ['type' => 'call']);
				}
			}
		}

		foreach ($state->nsFuncCalls as $nsFuncCallPair) {
			list($ns_func_call, $containing_cfg_func) = $nsFuncCallPair;
			$call_node = new OpNode($ns_func_call);
			$system->sdg->addNode($call_node);
			assert(isset($pdg_func_lookup[$containing_cfg_func]));
			$system->sdg->addEdge($pdg_func_lookup[$containing_cfg_func], $call_node, ['type' => 'contains']);

			// should always be the case, as otherwise it would be a normal func call
			assert($ns_func_call->name instanceof Literal);
			assert($ns_func_call->nsName instanceof Literal);

			$nsName = strtolower($ns_func_call->nsName->value);
			$name = strtolower($ns_func_call->name->value);

			if (isset($state->functionLookup[$nsName]) === true) {
				$this->linkFunctions($sdg, $call_node, $state->functionLookup[$nsName], $pdg_func_lookup);
			}
			if (isset($state->functionLookup[$name]) === true) {
				$this->linkFunctions($sdg, $call_node, $state->functionLookup[$name], $pdg_func_lookup);
			}
			// take monkey-patching into account and also link builtin functions
			if (isset($state->internalTypeInfo->functions[$name]) === true) {
				Graph::ensureNodeAndEdgeAdded($sdg, $call_node, new BuiltinFuncNode($name, null), ['type' => 'call']);
			}
			// if we haven't linked anything yet, this is most likely a vendor function (because we know its name).
			if ($sdg->hasEdges($call_node, null, ['type' => 'call']) === false) {
				Graph::ensureNodeAndEdgeAdded($sdg, $call_node, new UndefinedFuncNode($nsName, null), ['type' => 'call']);
				Graph::ensureNodeAndEdgeAdded($sdg, $call_node, new UndefinedFuncNode($name, null), ['type' => 'call']);
			}
		}
	}

	private function linkFunctions(GraphInterface $sdg, $call_node, $cfg_functions, \SplObjectStorage $pdg_func_lookup) {
		/** @var Function_ $cfg_function */
		foreach ($cfg_functions as $cfg_function) {
			$cfg_func = $cfg_function->func;
			assert(isset($pdg_func_lookup[$cfg_func]));
			$sdg->addEdge($call_node, $pdg_func_lookup[$cfg_func], ['type' => 'call']);
		}
	}
}