<?php
/**
 * @user: BEYOND 2023/3/3 11:17
 */

namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponConfigDao;
use app\common\dao\coupon\CouponStocksDao;
use app\common\model\coupon\CouponStocks;
use app\common\repositories\BaseRepository;
use crmeb\jobs\CouponEntrustJob;
use crmeb\services\MerchantCouponService;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class CouponConfigRepository extends BaseRepository
{

    public function __construct(CouponConfigDao $dao)
    {
        $this->dao = $dao;
    }

    public function updateCouponConfig($data){
        $this->dao->updateCouponConfig($data);
    }


}
