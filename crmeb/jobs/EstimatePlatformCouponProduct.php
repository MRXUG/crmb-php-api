<?php

namespace crmeb\jobs;

use app\common\dao\platform\PlatformCouponDao;
use app\common\model\store\product\Product;
use crmeb\interfaces\JobInterface;
use think\db\BaseQuery;
use think\facade\Db;
use think\queue\Job;
use think\facade\Cache;

# 预估平台优惠券所使用商品id
class EstimatePlatformCouponProduct implements JobInterface
{
    const EXECUTE_BATCH = 50; # 执行批次

    /**
     * @param Job $job
     * @param array{
     *     discount_num: int,
     *     threshold: int,
     *     use_type: int,
     *     scope_id_arr: int[],
     *     receive_start_time: string,
     *     receive_end_time: string,
     *     jobNumber: string
     * } $data
     * @return void
     */
    public function fire($job, $data)
    {
        /** @var PlatformCouponDao $platformCouponDao */
        $platformCouponDao = app()->make(PlatformCouponDao::class);
        # 获取到范围的商品id
        $productIdArr = $platformCouponDao->getProductIdFromDenomination(
            $data['discount_num'],
            $data['receive_start_time'],
            $data['receive_end_time']
        );
        # 切分数组防止执行数量过多
        $productIdArrChunk = array_chunk($productIdArr, self::EXECUTE_BATCH);
        # 循环查取数据
        $productCount = 0;
        foreach ($productIdArrChunk as $item) {
            $productCount += Product::getInstance()
                ->where('price', '>', $data['threshold'])
                ->when($data['use_type'] == 2, function (BaseQuery $query) use ($data) {
                    $query->whereIn('cate_id', $data['scope_id_arr']);
                })
                ->when($data['use_type'] == 3, function (BaseQuery $query) use ($data) {
                    $query->whereIn('mer_id', $data['scope_id_arr']);
                })
                ->when($data['use_type'] == 4, function (BaseQuery $query) use ($data) {
                    $whereInStr = implode(',', $data['scope_id_arr']);
                    $query->whereIn('mer_id', Db::raw(<<<SQL
                        select mer_id
                        from eb_merchant a
                            left join eb_merchant_category b on a.category_id = b.merchant_category_id
                            where b.merchant_category_id in ($whereInStr)
                    SQL));
                })
                ->whereIn('product_id', $item)
                ->count('product_id') ?? 0;
        }

        Cache::set("EstimatePlatformCouponProduct:{$data['jobNumber']}", [
            'productCount' => $productCount
        ], 30 * 60);

        $job->delete();
    }

    public function failed($data)
    {

    }
}
