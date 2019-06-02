<?php
use PHPCfg\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpPdg\AstBridge\Slicing\PdgBasedSlicer;
use PhpPdg\AstBridge\System as AstSystem;
use PhpPdg\CfgBridge\System as CfgSystem;
use PhpPdg\CfgBridge\SystemFactory as CfgSystemFactory;
use PhpPdg\Graph\Factory as GraphFactory;
use PhpPdg\Graph\GraphInterface;
use PhpPdg\Graph\GraphvizConverter;
use PhpPdg\Graph\Node\NodeInterface;
use PhpPdg\Graph\Printer\GraphvizPrinter;
use PhpPdg\ProgramDependence\ControlDependence\BlockDependenceGraph\Generator as BlockCdgGenerator;
use PhpPdg\ProgramDependence\ControlDependence\BlockFlowGraph\Generator as BlockCfgGenerator;
use PhpPdg\ProgramDependence\ControlDependence\Generator as ControlDependenceGenerator;
use PhpPdg\ProgramDependence\ControlDependence\PostDominatorTree\Generator as PdgGenerator;
use PhpPdg\ProgramDependence\DataDependence\Generator as DataDependenceGenerator;
use PhpPdg\ProgramDependence\Factory as PdgFactory;
use PhpPdg\SystemDependence\CallDependence\CombiningGenerator;
use PhpPdg\SystemDependence\CallDependence\FunctionCallGenerator;
use PhpPdg\SystemDependence\CallDependence\MethodCallGenerator;
use PhpPdg\SystemDependence\CallDependence\MethodResolver;
use PhpPdg\SystemDependence\CallDependence\OperandClassResolver;
use PhpPdg\SystemDependence\CallDependence\OverloadingCallGenerator;
use PhpPdg\SystemDependence\Factory as SdgSystemFactory;


require_once 'vendor/autoload.php';
require_once 'GraphUtils.php';

$startTime = microtime(true);

$projectPath = realpath('D:\git\GaiaReader\php-pdg\out\mysqli-ok.php');

$astParser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

$filePaths = [];
if (is_dir($projectPath) === true) {
    /** @var \SplFileInfo $fileInfo */
    foreach (new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($projectPath)), "/.*\.php$/i") as $fileInfo) {
        $filePath = $fileInfo->getRealPath();
        $filePaths[] = $filePath;
    }
} else if (is_file($projectPath) === true) {
    $filePaths[] = realpath($projectPath);
} else {
    throw new \RuntimeException("No such file or directory `$projectPath`");
}

// AST system，用于存储Project的所有ast
$astSystem = new AstSystem();
foreach ($filePaths as $filePath) {
    $astSystem->addAst($filePath, $astParser->parse(file_get_contents($filePath)));
}


$cfgSystemFactory = new CfgSystemFactory($astParser);
// cfg system，用于存储Project的所有cfg
// 生成了所有的cfg
$cfgSystem = $cfgSystemFactory->create($astSystem);

// --------------------------------
// 这后面部分肯定是通过遍历CFG产生所谓的PDG

// $sdgSystemFactory = SdgSystemFactory::createDefault();
// 分别用几个visitor进行遍历分析，并将相关数据保存起来
$graph_factory = new GraphFactory();// 图工厂，没啥东西

//$pdg_factory = PdgFactory::createDefault($graph_factory);
$block_cfg_generator = new BlockCfgGenerator($graph_factory);// 控制依赖块生成器
// 它的generate函数，传入参数func,entry node 和 stop node，可以生成cfg？

$block_cdg_generator = new BlockCdgGenerator($graph_factory);// 数据依赖块生成器
// 它的generate函数，传入参数cfg和pdt，可以生成dfg？

$pdt_generator = new PdgGenerator($graph_factory);// 后序支配树，用于判断两个cfg中基本块的支配关系，
// 这个后序支配树的生成在generate方法中，传入的参数有GraphInterface $graph, NodeInterface $stop_node，即给出graph和结束节点，生成这个图的后序支配树。

$control_dependence_generator = new ControlDependenceGenerator($block_cfg_generator, $pdt_generator, $block_cdg_generator);
$data_dependence_generator = new DataDependenceGenerator();
$pdg_factory = new PdgFactory($graph_factory, $control_dependence_generator, $data_dependence_generator);



$function_call_generator = new FunctionCallGenerator();// 函数调用解析

$operand_class_resolver = new OperandClassResolver();  // 操作类解析
$method_resolver = new MethodResolver();               // 方法解析
$methodCallGenerator = new MethodCallGenerator($operand_class_resolver, $method_resolver); // 方法调用生成器
$overloadingCallGenerator = new OverloadingCallGenerator($operand_class_resolver, $method_resolver); // 过载？？ help

$combiningGenerator = new CombiningGenerator([
    $function_call_generator,
    $methodCallGenerator,
    $overloadingCallGenerator,
]);

$sdgSystemFactory = new SdgSystemFactory($graph_factory, $pdg_factory, $combiningGenerator);

// sdg system，系统依赖图
$sdgSystem = $sdgSystemFactory->create($cfgSystem);


GraphUtils::getInstance()->graphviz($sdgSystem->sdg, "sdg");

//foreach ($sdgSystem->sdg->getNodes() as $node){
////    if($node instanceof PhpPdg\ProgramDependence\Node\OpNode){
////        echo get_class($node->op)."\n";
////    }
//    if($node instanceof PhpPdg\SystemDependence\Node\UndefinedFuncNode) {
//        echo get_class($node)."\n";
//
//    }
//}

foreach ($sdgSystem->getFuncs() as $func){

    $pdg = $func->pdg;
    foreach ($pdg->getNodes() as $node){
        if($node instanceof PhpPdg\ProgramDependence\Node\OpNode){
            $op = $node->op;
            echo "\$func class type = ".get_class($op)."\n";
        }
    }

    GraphUtils::getInstance()->graphviz($func->pdg, basename($func->filename).".".$func->name);
}


$endTime = microtime(true);
echo "总共运行时间 = ".($endTime-$startTime) ." s \n";