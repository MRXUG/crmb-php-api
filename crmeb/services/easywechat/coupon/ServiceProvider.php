<?php
/**
 * @user: BEYOND 2023/3/2 10:00
 */

namespace crmeb\services\easywechat\coupon;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{

    /**
     * @param Container $pimple
     *
     * @return void
     */
    public function register(Container $pimple)
    {
        $pimple['coupon'] = function ($pimple) {
            return new Client($pimple['access_token'], $pimple);
        };
    }
}