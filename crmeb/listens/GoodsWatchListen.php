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

use app\common\model\store\product\GoodsWatch;
use app\common\RedisKey;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use think\facade\Cache;

class GoodsWatchListen extends TimerService implements ListenerInterface
{
    protected string $name = "缓存商品围观数据";

    public function handle($params): void
    {
        $this->tick(1000*60 *5, function () {
            $all = GoodsWatch::getDB()->count();
            $limit = 20;
            $count = ceil($all/$limit);
            $page = mt_rand(1,$count);
            $offet = ($page-1)*$limit;
            $data = GoodsWatch::getDB()->limit($offet,$limit)->select();
            if(count($data) >=20){
                Cache::store('redis')->del(RedisKey::GOODS_DETAIL_WATCH);
                Cache::store('redis')->set(RedisKey::GOODS_DETAIL_WATCH,json_encode($data));
            }
        });
    }
}
