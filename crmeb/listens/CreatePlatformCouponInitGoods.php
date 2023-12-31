<?php

namespace crmeb\listens;

use app\common\dao\platform\PlatformCouponDao;
use app\common\model\platform\PlatformCoupon;
use app\common\model\platform\PlatformCouponProduct;
use app\common\model\platform\PlatformCouponUseScope;
use app\common\model\store\product\Product;
use crmeb\interfaces\JobInterface;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;
use Exception;
use ValueError;
use Throwable;

class CreatePlatformCouponInitGoods implements JobInterface
{

    /**
     * @param Job $job
     * @param array{platform_coupon_id: int} $data
     * @throws null
     * @return void
     */
    public function fire($job, $data)
    {
        try {
            Db::transaction(function () use ($data) {
                # 查询出需要初始化的平台优惠券
                /** @var PlatformCoupon $platformCoupon */
                $platformCoupon = PlatformCoupon::getInstance()->where([
                    ['platform_coupon_id', '=', $data['platform_coupon_id']],
                    ['is_init', '=', 0]
                ])->find();
                if (!$platformCoupon) return;
                # 获取商品id
                /** @var PlatformCouponDao $platformCouponDao */
                $platformCouponDao = app()->make(PlatformCouponDao::class);

                $platform_coupon_id = $platformCoupon->getAttr('platform_coupon_id');
                $use_type = $platformCoupon->getAttr('use_type');

                $newDate = date("Y-m-d H:i:s");
                # 获取到范围的商品id
                $productIdArr = $this->getProductIdList($platformCouponDao->getProductIdFromDenomination(
                    $platformCoupon->getAttr('discount_num'),
                    $platformCoupon->getAttr('receive_start_time'),
                    $platformCoupon->getAttr('receive_end_time'),
                ), $use_type, $platform_coupon_id, $platformCoupon->getAttr('threshold'));
                # 删除已存在
                PlatformCouponProduct::getInstance()->where('platform_coupon_id', $platform_coupon_id)->delete();
                # 将商品id写入
                foreach (array_chunk($productIdArr, 50) as $item) {
                    $arr = [];
                    foreach ($item as $v) $arr[] = [
                        'product_id' => $v,
                        'platform_coupon_id' => $platform_coupon_id,
                        'use_type' => $use_type,
                        'create_time' => $newDate,
                        'update_time' => $newDate
                    ];
                    PlatformCouponProduct::getInstance()->insertAll($arr);
                }
                $platformCoupon->setAttr('is_init', 1);
                $platformCoupon->save();
            });
        } catch (Exception|ValueError|Throwable $e) {
            Log::error($e->getMessage() . $e->getTraceAsString());
        }
        # 结束任务
        $job->delete();
    }

    private function getProductIdList(array $productIdArr, int $useType, int $platformCouponId, int $threshold): array
    {
        if (empty($productIdArr)) return [];
        # 获取涉及的id
        $scopeArr = [];

        if (in_array($useType, [2, 3, 4])) {
            $scopeArr = PlatformCouponUseScope::getInstance()->where([
                ['platform_coupon_id', '=', $platformCouponId],
                ['scope_type', '=', $useType]
            ])->column('scope_id');

            if (empty($scopeArr)) return [];
        }

        $newProductIdArr = [];
        foreach (array_chunk($productIdArr, 50) as $item) {
            $model = Product::getInstance()
                ->alias('a')
                ->whereIn('a.product_id', $item)
                ->whereRaw("(select max(price) from eb_store_product_attr_value where product_id = a.product_id) > {$threshold}");
            switch ($useType) {
                case 2:
                    $model->whereIn('a.cate_id', $scopeArr);
                    break;
                case 3:
                    $model->whereIn('a.mer_id', $scopeArr);
                    break;
                case 4:
                    $whereInStr = implode(',', $scopeArr);
                    $model->whereIn('a.mer_id', Db::raw(<<<SQL
                        select mer_id
                        from eb_merchant a
                            left join eb_merchant_category b on a.category_id = b.merchant_category_id
                            where b.merchant_category_id in ($whereInStr)
                    SQL));
                    break;
            }
            array_push($newProductIdArr, ...($model->column('a.product_id') ?? []));
        }

        return $newProductIdArr;
    }

    public function failed($data)
    {
    }
}
