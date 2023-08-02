<?php

namespace app\driver\log;

use app\Request;
use Nette\Utils\DateTime;
use think\contract\LogHandlerInterface;

class StdLog implements LogHandlerInterface
{
    protected $config = [
        'time_format' => ' c ',
        'file_size' => 2097152,
        'apart_level' => [],
        'format' => '[%s][%s] %s',
        'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ];

    // 实例化并传入参数
    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 日志写入接口
     * @access public
     * @param array $log 日志信息
     * @param bool $dep 是否写入分割线
     * @return bool
     */
    public function save(array $log = [], bool $dep = true): bool
    {
        $stdErr = fopen("php://stdout", "w");
        $info = [];

        // 日志信息封装
        $time = DateTime::createFromFormat('0.u00 U',
            microtime())->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($this->config['time_format']);

        $request = \think\facade\Request::instance();
        //新增
        foreach ($log as $type => $val) {
            // if ($type == "sql") {
            //     continue;
            // }
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                $message = $this->config['json'] ?
                    ['time' => $time, 'type' => $type, 'request_id' => $_SERVER['x_request_id'] ?? mini_unique_id(), 'msg' => $msg] :
                    sprintf($this->config['format'], $time, $type, $msg);
                $info[$type] = $message;

            }
            fwrite($stdErr, json_encode($info, $this->config['json_options']) . PHP_EOL);
        }
        fclose($stdErr);
        return true;
    }
}