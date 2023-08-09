<?php


namespace crmeb\services\easywechat\merchantComplaint;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class ServiceProvider.
 *
 * @author ClouderSky <clouder.flow@gmail.com>
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}.
     */
    public function register(Container $pimple)
    {
        $pimple['merchantComplaint'] = function ($pimple) {
            return new MerchantComplaintClient($pimple['access_token'], $pimple);
        };
    }
}
