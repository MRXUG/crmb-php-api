<?php
/**
 * @user: BEYOND 2023/3/7 12:00
 */

namespace crmeb\jobs;

use app\common\model\coupon\CouponStocks;
use app\common\repositories\coupon\CouponStocksRepository;
use app\common\repositories\system\merchant\PlatformMerchantRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\MerchantCouponService;
use think\facade\Log;

/**
 * 委托营销-异步实现批次委托
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter9_5_1.shtml
 */
class CouponEntrustJob implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info('委托营销-异步实现批次委托,' . json_encode(compact('data')));

        $stockId = $data['stock_id'] ?? '';
        if ($stockId) {
            // 委托指定批次
            $this->entrustByStockId($stockId, $data);
        } elseif($mchId = $data['mch_id'] ?? '') {
            // 委托当前所有进行中的批次
            $this->entrustByMchId($mchId);
        }

    }

    /**
     * 新建商户时进行委托
     *
     * @param $mchId
     *
     * @return void
     */
    private function entrustByMchId($mchId)
    {
        // 获取所有当前进行中的批次
        /**
         * @var CouponStocksRepository $couponStocksRepository
         */
        $couponStocksRepository = app()->make(CouponStocksRepository::class);
        $where = [
            [ 'is_del', '=', 0],
            ['stock_id', '<>', ''],
        ];
        $stockIdList = $couponStocksRepository
            ->selectPageWhere($where, [], 1, 1000, 'stock_id')
            ->whereIn('status', CouponStocks::VALID_STATUS)
            ->column('stock_id');

        foreach ($stockIdList as $stockId) {
            try {
                $params = [
                    'stock_id' => $stockId,
                    'mch_id' => $mchId,
                ];
                $data = [
                    'stock_id' => $stockId,
                ];
                MerchantCouponService::create(MerchantCouponService::ENTRUST_COUPON, $data, $merchantConfig)->coupon()->entrust($params);
            } catch (\Exception $e) {
                Log::error('新建商户时进行委托,' . $e->getMessage() . ',' . json_encode($data, JSON_UNESCAPED_UNICODE));
                // TODO 发送预警消息,记录异常商户
            }
        }

        // 设置回调地址
        MerchantCouponService::create(MerchantCouponService::CALLBACK_COUPON, ['mch_id' => $mchId], $merchantConfig)->coupon()->setCallback($mchId);
    }

    private function entrustByStockId($stockId, $data)
    {
        /**
         * @var PlatformMerchantRepository $platformMerchantRepository
         */
        $platformMerchantRepository = app()->make(PlatformMerchantRepository::class);
        $mchIds = $platformMerchantRepository->lst(['is_del' => 0], 'merchant_id')->column('merchant_id');

        Log::info('异步实现商家券批次委托' . json_encode(compact('data', 'mchIds'), JSON_UNESCAPED_UNICODE));

        try {
            MerchantCouponService::create(MerchantCouponService::ENTRUST_COUPON, $data, $merchantConfig)->coupon()->platformEntrust($mchIds, $stockId);
        } catch (\Exception $e) {
            Log::error('商家券批次委托,' . $e->getMessage() . ',' . json_encode($data, JSON_UNESCAPED_UNICODE));
            // TODO 发送预警消息,记录异常商户
        }
    }

    public function failed($data)
    {
        // TODO 商家券委托失败
    }
}