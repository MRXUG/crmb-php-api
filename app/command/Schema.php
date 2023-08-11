<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class Schema extends Command
{
    static $schema = "ncrmeb";

    protected function configure()
    {
        // 指令配置
        $this->setName('schema')
            ->setDescription('the app\command\schema command');
    }

    protected function execute(Input $input, Output $output)
    {
         //获取表名
         $tables = Db::query("SELECT TABLE_NAME  as 'name' from information_schema.tables WHERE TABLE_SCHEMA = :dataBase and TABLE_NAME='eb_merchant'", ["dataBase" => Schema::$schema]);
 
         foreach ($tables as $key => $value) {
 
             foreach ($value as $item) {
 
                 //获取字段名称和备注
                 $COLUMNS = Db::query("select COLUMN_NAME,DATA_TYPE,COLUMN_COMMENT from information_schema.COLUMNS where table_name = :item and table_schema = :dataBase", ["item" => $item, "dataBase" => Schema::$schema]);
 
                 $content = "//设置字段信息" . PHP_EOL .
                     "protected $" . "schema = [" . PHP_EOL;
                 //写入字段
                 foreach ($COLUMNS as $COLUMN) {
                     $content .= "'" . $COLUMN["COLUMN_NAME"] . "'" . "       =>"  .
                         "'" . $COLUMN["DATA_TYPE"] . "'" . "," . "//" . $COLUMN["COLUMN_COMMENT"] . PHP_EOL;

                 }
 
                 $content .= PHP_EOL . "];";
                 $output->writeln($content.PHP_EOL);
             }
         }
        // 指令输出
        
    }

    public static function snakeToCamel($str, $capitalized = true)
    {
        $result = str_replace('_', '', ucwords($str, '_'));
        if (!$capitalized) {
            $result = lcfirst($result);
        }
        return $result;
    }

}
