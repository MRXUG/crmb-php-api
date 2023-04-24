<?php

namespace crmeb\utils\platformCoupon;

use app\common\dao\platform\PlatformCouponDao;
use app\common\model\platform\PlatformCoupon;
use app\common\model\platform\PlatformCouponProduct;
use app\common\model\platform\PlatformCouponUseScope;
use app\common\model\store\product\Product;
use crmeb\jobs\RefreshPlatformCoupon;
use think\Collection;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class RefreshPlatformCouponProduct
{
    private string $nowDate;

    public static function runQueue(): void
    {
        Queue::push(RefreshPlatformCoupon::class, []);
    }

    public static function run (): void
    {
        (new self)->handle();
    }

    /**
     * @return void
     * @throws null
     */
    public function handle (): void
    {
        $this->nowDate = date("Y-m-d H:i:s");
        # 查询出需要初始化的平台优惠券
        $platformCouponList = PlatformCoupon::getInstance()->where([
            ['receive_end_time', '>', $this->nowDate],
            ['status', 'in', [0, 1]],
            ['is_cancel', '=', 0],
            ['is_del', '=', 0],
        ])->select();
        # 判断是否需要执行
        if (empty($platformCouponList)) return;
        try {
            Db::transaction(function () use ($platformCouponList){
                $this->handleProductIdList($platformCouponList);
            });
        } catch (\Exception|\ValueError|\Throwable $e) {
            Log::error('刷新平台优惠券商品出错 ' . $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 获取商品id列表
     *
     * @param PlatformCoupon[]|Collection $platformCouponList
     * @return void
     */
    private function handleProductIdList($platformCouponList): void
    {
        foreach ($platformCouponList as $platformCoupon) {
            # 获取商品id
            /** @var PlatformCouponDao $platformCouponDao */
            $platformCouponDao = app()->make(PlatformCouponDao::class);

            $platform_coupon_id = $platformCoupon->getAttr('platform_coupon_id');
            $use_type = $platformCoupon->getAttr('use_type');
            # 获取到范围的商品id
            $productIdArr = $this->getOnePlatformCouponProductIdList($platformCouponDao->getProductIdFromDenomination(
                $platformCoupon->getAttr('discount_num'),
                $platformCoupon->getAttr('receive_start_time'),
                $platformCoupon->getAttr('receive_end_time'),
            ), $use_type, $platform_coupon_id, $platformCoupon->getAttr('threshold'));
            # 获得变动项目
            $this->runChange($this->checkChange($productIdArr, $platformCoupon), $platformCoupon);
        }
    }

    public function runChange(array $change, PlatformCoupon $coupon): void
    {
        $newDate = date("Y-m-d H:i:s");
        PlatformCouponProduct::getInstance()->whereIn('id', $change['del'])->delete();
        foreach (array_chunk($change['add'], 50) as $item) {
            $arr = [];
            foreach ($item as $v) $arr[] = [
                'product_id' => $v,
                'platform_coupon_id' => $coupon->getAttr('platform_coupon_id'),
                'use_type' => $coupon->getAttr('use_type'),
                'create_time' => $newDate,
                'update_time' => $newDate
            ];
            PlatformCouponProduct::getInstance()->insertAll($arr);
        }
    }

    /**
     * 检查变动
     *
     * @param array $productIdArr
     * @param PlatformCoupon $coupon
     * @return array
     */
    private function checkChange(array &$productIdArr, PlatformCoupon $coupon): array
    {
        $productDataList = PlatformCouponProduct::getInstance()->where([
            ['platform_coupon_id', '=', $coupon->getAttr('platform_coupon_id')],
            ['use_type', '=', $coupon->getAttr('use_type')]
        ])->column('product_id', 'id');

        $add = [];
        $del = [];

        foreach ($productIdArr as $num) {
            if (!in_array($num, $productDataList)) {
                $add[] = $num; //将相对多余的元素添加到$add数组中
            }
        }

        foreach ($productDataList as $k =>  $num) {
            if (!in_array($num, $productIdArr)) {
                $del[] = $k; //将相对多余的元素添加到$del数组中
            }
        }

        return [
            'add' => $add,
            'del' => $del
        ];
    }

    private function getOnePlatformCouponProductIdList(array $productIdArr, int $useType, int $platformCouponId, int $threshold): array
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
}
