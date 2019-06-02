<?php

use PhpPdg\Graph\GraphvizConverter;
use PhpPdg\Graph\Printer\GraphvizPrinter;


/**
 * Class GraphUtils
 * 处理图可视化的类
 */
class GraphUtils{
    private static $_instance = NULL;

    /*
     * 私有化构造函数
     */
    private function __construct(){}


    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /*
     * 防止用户克隆实例
     */
    public function __clone(){
        die('Clone is not allowed.' . E_USER_ERROR);
    }

    public function graphviz($graph, $filename){
        $startTime = microtime(true);

        // 图转换器，负责将图转换为graphviz格式的dot格式
        $graphviz_converter = new PhpPdg\Graph\GraphvizConverter();
        $graphviz_printer = new PhpPdg\Graph\Printer\GraphvizPrinter($graphviz_converter);
        file_put_contents("out/1.dot",$graphviz_printer->printGraph($graph)) ;
        system("dot -Tsvg -o out/$filename.svg out/1.dot");

        $endTime = microtime(true);
        echo "运行了".($endTime-$startTime) ." s \n";
    }

}