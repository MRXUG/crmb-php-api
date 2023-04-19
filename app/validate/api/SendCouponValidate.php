<?php
/**
 * @user: BEYOND 2023/3/5 19:49
 */

namespace app\validate\api;

use app\common\dao\platform\PlatformCouponDao;
use app\common\repositories\coupon\CouponStocksUserRepository;
use think\Validate;

class SendCouponValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'stock_list|批次信息' => 'require|array|min:1|checkSendCoupon',
    ];

    protected function checkSendCoupon($value)
    {
        $error = false;
        foreach ($value as $stock) {
            if (empty($stock['stock_id'])) {
                $error = true;
                break;
            }
        }

        if (count($value) == count($value, COUNT_RECURSIVE) || $error) {
            return '批次信息错误';
        }

        return true;
    }

    /**
     * 校验领券数量
     *
     * @param array $stockIdList
     * @param $uid
     *
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function validateReceiveCoupon(array $stockIdList, $uid)
    {
        /**
         * @var CouponStocksUserRepository $couponStocksUserRepository
         */
        $couponStocksUserRepository = app()->make(CouponStocksUserRepository::class);

        foreach ($stockIdList as $stockId) {
            $couponStocksUserRepository->validateReceiveCoupon($stockId, $uid);
        }
    }


    /**
     * 校验平台领券数量
     *
     * @param array $stockIdList
     * @param $uid
     *
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function validateReceivePlatformCoupon(array $stockIdList, $uid)
    {
        $platformCouponDao = app()->make(PlatformCouponDao::class);

        foreach ($stockIdList as $k=>$v){
//            $platformCouponDao->
        }
    }

}