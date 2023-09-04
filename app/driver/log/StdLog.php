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

        /** @var Request $request */
        $request = \think\facade\Request::instance();
        $requestParams = [
            'url'=>$request->url(),
            'host'=>$request->host(),
            'env' => env('app_name', 'unknown'). '@' . env('app_server.run_server', 'unknown'),
            'params'=>$request->param(),
            'mvc'=>$request->controller() . '.' . $request->action() . '@' . $request->method()
        ];
        if($request->url() == 'swoole' || !$request->controller()){
            // log more
            $requestParams['argv'] = $_SERVER['argv'] ?? [];
            $requestParams['trace'] = debug_backtrace();
        }
        //新增
        foreach ($log as $type => $val) {
            // if ($type == "sql") {
            //     continue;
            // }
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                $outData = [
                    'time'=>$time,
                    'type'=>$type,
                    'request_id'=>$_SERVER['x_request_id'] ?? mini_unique_id(),
                    'request'=>$requestParams,
                    'msg'=>$msg
                ];
                $message = $this->config['json'] ? $outData  : sprintf($this->config['format'], $time, $type, $msg);
                fwrite($stdErr, json_encode($message, $this->config['json_options']) . PHP_EOL);
            }
            
        }
        fclose($stdErr);
        return true;
    }
}