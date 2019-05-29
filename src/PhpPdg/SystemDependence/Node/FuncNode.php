<?php

namespace PhpPdg\SystemDependence\Node;

use PhpPdg\Graph\Node\NodeInterface;
use PhpPdg\ProgramDependence\Func;

// Func节点，就存储Func而已
class FuncNode implements NodeInterface {
	/** @var Func  */
	private $func;

	/**
	 * FuncNode constructor.
	 * @param Func $func
	 */
	public function __construct(Func $func) {
		$this->func = $func;
	}

	/**
	 * @return Func
	 */
	public function getFunc() {
		return $this->func;
	}

	public function toString() {
		return 'Func[' . $this->func->getId() . ']';
	}

	public function getHash() {
		return 'FUNC-' . $this->func->getId() . '-' . spl_object_hash($this->func);
	}
}