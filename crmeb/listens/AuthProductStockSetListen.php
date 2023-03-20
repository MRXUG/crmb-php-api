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


namespace crmeb\listens;


use app\common\dao\applet\WxAppletDao;
use app\common\repositories\applet\WxAppletRepository;
use app\common\repositories\store\product\ProductStockSetRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;


class AuthProductStockSetListen extends TimerService implements ListenerInterface
{

    protected string $name = '自动恢复商品库存:' . __CLASS__;
    /**
     * 自动恢复商品库存
     *
     * @param $event
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/3 9:47
     */
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 60 * 24 , function () {
            try {
                $repository = app()->make(ProductStockSetRepository::class);
                $repository->stockSet();
                $repository->attrValueStockSet();
            } catch (\Exception $e) {

            }
        });
    }
}
