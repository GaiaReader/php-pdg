<?php

namespace PhpPdg\SystemDependence;

use PhpPdg\CfgBridge\System as CfgSystem;
use PhpPdg\Graph\FactoryInterface as GraphFactoryInterface;
use PhpPdg\ProgramDependence\FactoryInterface as PdgFactoryInterface;
use PhpPdg\SystemDependence\CallDependence\CombiningGenerator;
use PhpPdg\SystemDependence\CallDependence\FunctionCallGenerator;
use PhpPdg\SystemDependence\CallDependence\GeneratorInterface as CallDependenceGeneratorInterface;
use PhpPdg\SystemDependence\CallDependence\MethodCallGenerator;
use PhpPdg\SystemDependence\CallDependence\MethodResolver;
use PhpPdg\SystemDependence\CallDependence\OperandClassResolver;
use PhpPdg\SystemDependence\CallDependence\OverloadingCallGenerator;
use PhpPdg\SystemDependence\Node\FuncNode;
use PHPTypes\State;
use PHPTypes\TypeReconstructor;
use PhpPdg\Graph\Factory as GraphFactory;
use PhpPdg\ProgramDependence\Factory as PdgFactory;

class Factory implements FactoryInterface {
	/** @var GraphFactoryInterface  */
	private $graph_factory;
	/** @var PdgFactoryInterface  */
	private $pdg_factory;
	/** @var CallDependenceGeneratorInterface  */
	private $call_dependence_generator;
	/** @var  TypeReconstructor */
	private $type_reconstructor;

	public function __construct(GraphFactoryInterface $graph_factory, PdgFactoryInterface $pdg_factory, CallDependenceGeneratorInterface $call_dependence_generator) {
		$this->graph_factory = $graph_factory;
		$this->pdg_factory = $pdg_factory;
		$this->call_dependence_generator = $call_dependence_generator;
		$this->type_reconstructor = new TypeReconstructor();
	}

	public static function createDefault() {
		$graph_factory = new GraphFactory();// 图工厂，没啥东西
		$operand_class_resolver = new OperandClassResolver(); // Op解析
		$method_resolver = new MethodResolver(); // 方法解析
		return new self($graph_factory, PdgFactory::createDefault($graph_factory), new CombiningGenerator([
			new FunctionCallGenerator(), // 函数调用生成器
			new MethodCallGenerator($operand_class_resolver, $method_resolver), // 方法调用生成器
			new OverloadingCallGenerator($operand_class_resolver, $method_resolver), // 过载？？ help
		]));
	}

	public function create(CfgSystem $cfg_system) {
		$sdg = $this->graph_factory->create();
		$system = new System($sdg);

		// pdg函数查找
		/** @var FuncNode[]|\SplObjectStorage $pdg_func_lookup */
		$pdg_func_lookup = new \SplObjectStorage();
		$cfg_scripts = [];
		/** @var \SplFileInfo $fileinfo */
		foreach ($cfg_system->getFilenames() as $filename) {
			$cfg_scripts[] = $cfg_script = $cfg_system->getScript($filename);

			$pdg_func = $this->pdg_factory->create($cfg_script->main, $filename);
			$system->scripts[$filename] = $pdg_func;
			$func_node = new FuncNode($pdg_func);
			$system->sdg->addNode($func_node);
			$pdg_func_lookup[$cfg_script->main] = $func_node;

			foreach ($cfg_script->functions as $cfg_func) {
				$pdg_func = $this->pdg_factory->create($cfg_func, $filename);
				$scoped_name = $cfg_func->getScopedName();
				if ($cfg_func->class !== null) {
					$system->methods[$scoped_name] = $pdg_func;
				} else if (strpos($cfg_func->name, '{anonymous}#') === 0) {
					$system->closures[$scoped_name] = $pdg_func;
				} else {
					$system->functions[$scoped_name] = $pdg_func;
				}
				$func_node = new FuncNode($pdg_func);
				$system->sdg->addNode($func_node);
				$pdg_func_lookup[$cfg_func] = $func_node;
			}
		}

		$state = new State($cfg_scripts);
		$this->type_reconstructor->resolve($state);
		$this->call_dependence_generator->addCallDependencesToSystem($system, $state, $pdg_func_lookup);
		return $system;
	}
}