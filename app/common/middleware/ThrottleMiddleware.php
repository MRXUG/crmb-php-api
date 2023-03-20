<?php

namespace app\common\middleware;

use think\Container;
use think\middleware\Throttle;
use think\Request;

class ThrottleMiddleware extends Throttle
{

    /**
     * 覆盖缓存key
     *
     * @param Request $request
     * @return string|null
     * @author  wzq
     * @date    2023/3/15 17:31
     */
    protected function getCacheKey(Request $request): ?string
    {
        $key = $this->config['key'];

        if ($key instanceof \Closure) {
            $key = Container::getInstance()->invokeFunction($key, [$this, $request]);
        }

        if ($key === null || $key === false || $this->config['visit_rate'] === null) {
            // 关闭当前限制
            return null;
        }

        if ($key === true) {
            $key = $request->ip() . $request->url();
        } elseif (false !== strpos($key, '__')) {
            $key = str_replace(['__CONTROLLER__', '__ACTION__', '__IP__'], [$request->controller(), $request->action(), $request->ip()], $key);
        }

        return $this->config['prefix'] . ':' . md5( $key . $this->config['driver_name']);
    }
}
