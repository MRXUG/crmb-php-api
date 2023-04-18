<?php

namespace app\common\dao\platform;

use app\common\dao\BaseDao;
use app\common\dao\coupon\CouponStocksDao;
use app\common\model\platform\PlatformCoupon;
use app\common\repositories\platform\PlatformCouponRepository;

class PlatformCouponDao extends BaseDao
{

    protected function getModel(): string
    {
        return PlatformCoupon::class;
    }

    /**
     * 根据优惠券面额获取支持的商品id
     *
     * @param int $discount_num
     * @param string $startTime
     * @param string $endTime
     * @return int[]
     */
    public function getProductIdFromDenomination(int $discount_num, string $startTime, string $endTime): array
    {
        /** @var CouponStocksDao $couponDao */
        $couponDao = app()->make(CouponStocksDao::class);
        /** @var PlatformCouponRepository $platformCouponRepository */
        $platformCouponRepository = app()->make(PlatformCouponRepository::class);

        $field = [
            'group_concat(`id` order by `id` desc) as `coupon_id_arr`', # 优惠券id组
            'group_concat(`scope` order by `id` desc) as `scope_arr`', # 优惠券id组
            'group_concat(`mer_id` order by `id` desc) as `mer_id_arr`', # 优惠券id组
        ];

        $where = [
            ['is_del', '=', 0],
            ['type', '=', 1],
            ['status', 'in', [1, 2]],
            ['end_at', '>', $endTime],
            ['start_at', '<', $startTime],
            ['discount_num', '=', $discount_num]
        ];

        $model = $couponDao->getModelObj()
            ->where($where)
            ->group('discount_num')
            ->field($field)
            ->find();

        if (! $model) return [];

        $coupon_id_arr = explode(',', $model['coupon_id_arr']);
        $scope_arr = explode(',', $model['scope_arr']);
        $mer_id_arr = explode(',', $model['mer_id_arr']);
        return $platformCouponRepository->getProductId($coupon_id_arr, $scope_arr, $mer_id_arr);
    }

    public function getPopupsPlatformCoupon($where=[] ,$limit = 1){
       return $this->getModel()::getDB()->where($where)->order("discount_num desc")->limit($limit)->select();
    }
}
