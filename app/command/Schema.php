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
 
 
                 $file = fopen("/Users/xinyang/work/wandui/wd-backend/modelTmp/" . Schema::snakeToCamel($item) . ".php", "w");
                 var_dump($file);
 
                 fwrite($file, "<?php" . PHP_EOL . "namespace app\model;" . PHP_EOL
                     . PHP_EOL . "use think\model;" . PHP_EOL
                     . PHP_EOL . "class " . Schema::snakeToCamel($item) . " extends Model"
                     . PHP_EOL . "{" . PHP_EOL);
 
                 $content = "//设置字段信息" . PHP_EOL .
                     "protected $" . "schema = [" . PHP_EOL;
                 fwrite($file, $content);
                 //写入字段
                 foreach ($COLUMNS as $COLUMN) {
                     $content = "'" . $COLUMN["COLUMN_NAME"] . "'" . "       =>"  .
                         "'" . $COLUMN["DATA_TYPE"] . "'" . "," . "//" . $COLUMN["COLUMN_COMMENT"] . PHP_EOL;
                     fwrite($file, $content);
                 }
 
                 $content = PHP_EOL . "];";
                 fwrite($file, $content);
 
                 fwrite($file, PHP_EOL . "}");
                 fclose($file);
             }
         }
        // 指令输出
        $output->writeln('app\command\schema');
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
