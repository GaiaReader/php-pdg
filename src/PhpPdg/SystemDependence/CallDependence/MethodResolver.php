<?php

namespace PhpPdg\SystemDependence\CallDependence;

use PHPCfg\Op\Stmt\ClassMethod;
use PhpPdg\Graph\Node\NodeInterface;
use PhpPdg\SystemDependence\Node\BuiltinFuncNode;
use PhpPdg\SystemDependence\Node\FuncNode;
use PHPTypes\InternalArgInfo;
use PHPTypes\State;

class MethodResolver implements MethodResolverInterface {
	/**
	 * @param State $state
	 * @param string $classname
	 * @param string $methodname
	 * @param \SplObjectStorage $pdg_func_lookup
	 * @return NodeInterface[]
	 */
	public function resolveMethod(State $state, $classname, $methodname, \SplObjectStorage $pdg_func_lookup) {
		$nodes = [];

		// try resolve in system
		if (isset($state->classLookup[$classname]) === true) {
			foreach ($state->classLookup[$classname] as $class) {
				if (isset($state->methodLookup[$class][$methodname]) === true) {
					/** @var ClassMethod $method */
					foreach ($state->methodLookup[$class][$methodname] as $method) {
						$func = $method->getFunc();
						assert(isset($pdg_func_lookup[$func]));
						/** @var FuncNode $funcnode */
						$funcnode = $pdg_func_lookup[$func];
						$nodes[$funcnode->getHash()] = $funcnode;
					}
				} else if ($class->extends !== null) {
					foreach ($this->resolveMethod($state, strtolower($class->extends->value), $methodname, $pdg_func_lookup) as $node) {
						$nodes[$node->getHash()] = $node;
					}
				}
			}
		}

		$node = $this->resolveBuiltinMethod($state->internalTypeInfo, $classname, $methodname);
		if ($node !== null) {
			$nodes[$node->getHash()] = $node;
		}

		return $nodes;
	}

	/**
	 * @param InternalArgInfo $internalArgInfo
	 * @param string $classname
	 * @param string $methodname
	 * @return null|BuiltinFuncNode
	 */
	private function resolveBuiltinMethod(InternalArgInfo $internalArgInfo, $classname, $methodname) {
		$node = null;
		if (isset($internalArgInfo->methods[$classname][$methodname]) === true) {
		    // 惊现bug，classname 和 methodname参数传错了
			$node = new BuiltinFuncNode($methodname, $classname);
		}
		if (isset($internalArgInfo->classExtends[$classname]) === true) {
			$node = $this->resolveBuiltinMethod($internalArgInfo, $internalArgInfo->classExtends[$classname], $methodname);
		}
		return $node;
	}
}