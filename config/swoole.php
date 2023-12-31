<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


use app\webscoket\Manager;
use Swoole\Table;
use think\swoole\websocket\socketio\Parser;

return [
    'server'     => [
        'host'      => env('SWOOLE_HOST', '0.0.0.0'), // 监听地址
        'port'      => env('SWOOLE_PORT', 1024), // 监听端口
        'mode'      => SWOOLE_PROCESS, // 运行模式 默认为SWOOLE_PROCESS
        'sock_type' => SWOOLE_SOCK_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'options'   => [
            'pid_file'              => runtime_path() . 'swoole.pid',
            'log_file'              => runtime_path() . 'swoole.log',
            'daemonize'             => false,
            // Normally this value should be 1~4 times larger according to your cpu cores.
            'reactor_num'           => swoole_cpu_num(),
            'worker_num'            => swoole_cpu_num(),
            'task_worker_num'       => swoole_cpu_num(),
            'task_enable_coroutine' => false,
            'task_max_request'      => 2000,
            'enable_static_handler' => true,
            'document_root'         => root_path('public'),
            'package_max_length'    => 50 * 1024 * 1024,
            'buffer_output_size'    => 10 * 1024 * 1024,
            'socket_buffer_size'    => 128 * 1024 * 1024,
            'max_request'           => 3000,
            'send_yield'            => true,
            'reload_async'          => true,
        ],
    ],
    'websocket'  => [
        'enable'        => true,
        'handler'       => Manager::class,
        'parser'        => Parser::class,
        'ping_interval' => 25000, //1000 = 1秒
        'ping_timeout'  => 60000, //1000 = 1秒
        'room'          => [
            'type'  => 'table',
            'table' => [
                'room_rows'   => 4096,
                'room_size'   => 2048,
                'client_rows' => 8192,
                'client_size' => 2048,
            ],
            'redis' => [

            ],
        ],
        'listen'        => [],
        'subscribe'     => [],
    ],
    'rpc'        => [
        'server' => [
            'enable'   => false,
            'port'     => 9000,
            'services' => [
            ],
        ],
        'client' => [
        ],
    ],
    'hot_update' => [
        'enable'  => env('APP_DEBUG', false),
        'name'    => ['*.php'],
        'include' => [app_path(),root_path().'crmeb'],
        'exclude' => [],
    ],
    //连接池
    'pool'       => [
        'db'    => [
            'enable'        => true,
            'max_active'    => 300,
            'max_wait_time' => 5,
        ],
        'cache' => [
            'enable'        => true,
            'max_active'    => 1000,
            'max_wait_time' => 5,
        ],
    ],
    'coroutine'  => [
        'enable' => false,
        'flags'  => SWOOLE_HOOK_ALL,
    ],
    'tables' => [
        'user' => [
            'size' => 204800,
            'columns' => [
                ['name' => 'fd', 'type' => Table::TYPE_INT],
                ['name' => 'type', 'type' => Table::TYPE_INT],
                ['name' => 'uid', 'type' => Table::TYPE_INT]
            ]
        ]
    ],
    //每个worker里需要预加载以共用的实例
    'concretes'  => [],
    //重置器
    'resetters'  => [],
    //每次请求前需要清空的实例
    'instances'  => [],
    //每次请求前需要重新执行的服务
    'services'   => [],
    'locks' => ['group_buying'],
];
