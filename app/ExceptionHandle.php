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


namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\db\exception\PDOException;
use think\Exception;
use think\exception\ErrorException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Log;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制
        $this->report($e);
        if (!$this->isIgnoreReport($e)) {
            $this->logging($e, $request);
        }
        // 其他错误交给系统处理
        if ($e instanceof ValidateException) {
            $this->logging($e, $request, false);
            return app('json')->fail($e->getMessage());
        } else {
            if ($e instanceof DataNotFoundException) {
                $this->logging($e, $request, false);
                return app('json')->fail(isDebug() ? $e->getMessage() : '数据不存在');
            } else {
                if ($e instanceof ModelNotFoundException) {
                    $this->logging($e, $request, false);
                    return app('json')->fail(isDebug() ? $e->getMessage() : '数据不存在');
                } else {
                    if ($e instanceof PDOException) {
                        return app('json')->fail(isDebug() ? $e->getMessage() : '数据库操作失败',
                            isDebug() ? $e->getData() : []);
                    } else {
                        if ($e instanceof ErrorException) {
                            return app('json')->fail(isDebug() ? $e->getMessage() : '系统错误',
                                isDebug() ? $e->getData() : []);
                        } else {
                            if ($e instanceof \PDOException) {
                                return app('json')->fail(isDebug() ? $e->getMessage() : '数据库连接失败');
                            } else {
                                if ($e instanceof \EasyWeChat\Core\Exceptions\HttpException) {
                                    return app('json')->fail($e->getMessage());
                                }
                            }
                        }
                    }
                }
            }
        }

        return parent::render($request, $e);
    }

    public function logging($e, \think\Request $request,$robot = true)
    {
        $serverInfo = $request->server();
        $outData = [
            'REQUEST_METHOD' => $serverInfo['REQUEST_METHOD'] ?? "unknown",
            'REQUEST_URI' => $serverInfo['REQUEST_URI'] ?? "unknown",
            'PATH_INFO' => $serverInfo['PATH_INFO'] ?? "unknown",
            'REQUEST_TIME' => $serverInfo['REQUEST_TIME'] ?? "unknown",
            'SERVER_PROTOCOL' => $serverInfo['SERVER_PROTOCOL'] ?? "unknown",
            'SERVER_PORT' => $serverInfo['SERVER_PORT'] ?? "unknown",
            'REMOTE_PORT' => $serverInfo['REMOTE_PORT'] ?? "unknown",
            'REMOTE_ADDR' => $serverInfo['REMOTE_ADDR'] ?? "unknown",
            'MASTER_TIME' => $serverInfo['MASTER_TIME'] ?? "unknown",
            'HTTP_HOST' => $serverInfo['HTTP_HOST'] ?? "unknown",
            'HTTP_ORIGIN' => $serverInfo['HTTP_ORIGIN'] ?? "unknown",
            'HTTP_REFERER' => $serverInfo['HTTP_REFERER'] ?? "unknown",
            'HTTP_X_FORWARDED_FOR' => $serverInfo['HTTP_X_FORWARDED_FOR'] ?? "unknown",
            'HTTP_X_FORWARDED_HOST' => $serverInfo['HTTP_X_FORWARDED_HOST'] ?? "unknown",
            'HTTP_X_FORWARDED_PORT' => $serverInfo['HTTP_X_FORWARDED_PORT'] ?? "unknown",
            'HTTP_X_FORWARDED_SERVER' => $serverInfo['HTTP_X_FORWARDED_SERVER'] ?? "unknown",
            'HTTP_X_REAL_IP' => $serverInfo['HTTP_X_REAL_IP'] ?? "unknown",
            'HTTP_X_TOKEN' => $serverInfo['HTTP_X_TOKEN'] ?? "unknown",
            'HTTP_APPID' => $serverInfo['HTTP_APPID'] ?? "unknown",
        ];
        Log::error("system_error_exception:" . json_encode([
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => (method_exists($e, "getData")) ? $e->getData() : "",
                'request' => [
                    'url' => $request->url(),
                    'mvc' => $request->controller() . '.' . $request->action() . '@' . $request->method(),
                    'server' => $outData,
                ]
            ]));
        if ($robot){
            sendMessageToWorkBot([
                'msg' => $e->getMessage(),
                '`file`' => $e->getFile(),
                '`line`' => $e->getLine(),
                '`data`' => (method_exists($e, "getData")) ? $e->getData() : "",
                '`request`' => [
                    'url' => '`' . $request->url() . '`',
                    'mvc' => '`' . $request->controller() . '.' . $request->action() . '@' . $request->method() . '`',
                    'server' => $outData,
                ]
            ]);
        }

    }
}
