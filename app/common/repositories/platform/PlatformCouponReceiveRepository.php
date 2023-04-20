<?php

namespace app\common\repositories\platform;

use app\common\dao\coupon\CouponStocksDao;
use app\common\dao\platform\PlatformCouponDao;
use app\common\dao\platform\PlatformCouponPositionDao;
use app\common\dao\platform\PlatformCouponReceiveDao;
use app\common\dao\platform\PlatformCouponUseScopeDao;
use app\common\model\coupon\CouponStocks;
use app\common\model\platform\PlatformCoupon;
use app\common\model\platform\PlatformCouponProduct;
use app\common\model\platform\PlatformCouponReceive;
use app\common\model\store\product\Product;
use app\common\repositories\BaseRepository;
use crmeb\jobs\EstimatePlatformCouponProduct;
use crmeb\listens\CreatePlatformCouponInitGoods;
use crmeb\services\MerchantCouponService;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use Exception;
use ValueError;
use Throwable;

class PlatformCouponReceiveRepository extends BaseRepository
{
    public function __construct(
        PlatformCouponReceiveDao $dao
    ) {
        $this->dao = $dao;
    }


    public function createUpdate($where, $data)
    {
        return $this->dao->createOrUpdate($where, $data);
    }

    public function getList(int $userId, int $page = 1, int $limit = 10): array
    {
        $modelFn = fn () => PlatformCouponReceive::getInstance()
            ->alias('a')
            ->field([
                'a.*',
                'b.threshold'
            ])
            ->leftJoin('eb_platform_coupon b', 'a.platform_coupon_id = b.platform_coupon_id')
            ->where([
                ['a.user_id', '=', $userId],
                ['a.status', '=', 0]
            ]);

        return [
            'list' => $modelFn()->order('a.id', 'desc')->page($page, $limit)->select(),
            'count' => $modelFn()->count('a.id')
        ];
    }
}
