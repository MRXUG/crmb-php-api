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


namespace app\common\repositories\coupon;


use app\common\dao\coupon\CouponStocksDao;
use app\common\dao\coupon\CouponStocksUserDao;
use app\common\dao\store\product\ProductDao;
use app\common\model\coupon\CouponStocks;
use app\common\model\store\product\ProductAttrValue;
use app\common\repositories\BaseRepository;
use crmeb\jobs\ChangeBatchStatusJob;
use crmeb\jobs\SendSvipCouponJob;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Queue;
use think\Model;


class CouponStocksRepository extends BaseRepository
{
    /**
     * 不限量优惠价数量
     */
    const MAX_COUPONS = 100000;
    /**
     * @var CouponStocksUserDao
     */
    private $userDao;

    /**
     * StoreCouponIssueUserRepository constructor.
     * @param CouponStocksDao $dao
     */
    public function __construct(CouponStocksDao $dao, CouponStocksUserDao $userDao)
    {
        $this->dao = $dao;
        $this->userDao = $userDao;
    }

    /**
     * 优惠券列表
     *
     * @param $page
     * @param $limit
     * @param $where
     * @param $mchId
     *
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/6 18:23
     */
    public function list($page, $limit, $where, $mchId): array
    {
        $query = $this->dao->search($mchId, $where)->where('start_at', '<', date("Y-m-d H:i:s"))->where('end_at', '>', date("Y-m-d H:i:s"))->where("is_del",0);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as &$item) {
            $item['type_name'] = CouponStocks::COUPON_TYPE_NAME[$item['type']];
            $item['status_name'] = CouponStocks::COUPON_STATUS_NAME[$item['status']];
            $item['discount_copywriter'] = $this->discountCopywriter($item);
            $item['written_off_num'] =
                $this->userDao->couponWrittenOffNum($item['stock_id'], CouponStocks::WRITTEN_OFF_YES)->count();
            $item['max_coupons'] = $item['is_limit'] == CouponStocks::IS_LIMIT_NO ? '不限量' : $item['max_coupons'];
            $item['sended'] = count($item['couponStocksUser']);
        }

        return compact('count', 'list');
    }


    /**
     * 组装优惠文案
     *
     * @param $data
     *
     * @return string
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/6 17:12
     */
    public function discountCopywriter($data): string
    {
        $copywriter = '';
        // 用券消费门槛
        if ($data['transaction_minimum'] == 0) {
            $copywriter = '无门槛使用';
        } else {
            // 券类型
            if ($data['stock_type'] == CouponStocks::STOCK_TYPE_REDUCE) {
                $copywriter = '满' . $data['transaction_minimum'] . '减' . $data['discount_num'];
            }
            if ($data['stock_type'] == CouponStocks::STOCK_TYPE_DISCOUNT) {
                $copywriter = '满' . $data['transaction_minimum'] . '打' . $data['discount_num'] . '折';
            }
        }
        // 1=店铺全部商品，2=指定商品
        if ($data['scope'] == CouponStocks::SCOPE_YES) {
            $copywriter = $copywriter . '，全店使用';
        } else {
            $copywriter = $copywriter . '，指定商品使用';
        }
        // 用户最大可领个数
        if ($data['is_user_limit'] == CouponStocks::IS_USER_LIMIT_NO){
            $copywriter = $copywriter . '，每人不限量';
        } else {
            $copywriter = $copywriter . '，每人限量' . $data['max_coupons_per_user'] . '次';
        }

        return $copywriter;
    }

    /**
     * 变更批次状态
     *
     * @param $id
     * @param string $event
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 15:08
     */
    public function changeStatus($id, string $event = '')
    {
        $query = $this->dao->info(['id' => $id]);
        if ($query) {
            $startAt = $query->start_at;
            $endAt = $query->end_at;

            // 活动未开始 not_started、已失效 failure、已取消 cancelled
            if ($event != '') {
                $statusMsg = '';
                if ($event == 'not_started') {
                    $statusMsg = '活动未开始';
                } elseif($event == 'failure') {
                    $statusMsg = '已失效';
                }elseif($event == 'cancelled') {
                    $statusMsg = '已取消';
                }
                Log::info('活动'.$id.'变更状态到'.$statusMsg.'未开始，异步任务创建');
                Queue::push(ChangeBatchStatusJob::class, [
                    'coupon_stocks_id' => $id,
                    'event'    => $event,
                ]);
            }

            // 进行中
            if (!empty($startAt)) {
                Log::info('活动'.$id.'变更状态到进行中，异步任务创建');
                $next = strtotime($startAt);
                $delay = $next - time();
                if ($delay > 0) {
                    Queue::later($delay, ChangeBatchStatusJob::class, [
                        'coupon_stocks_id' => $id,
                        'event'    => 'in_progress',
                    ]);
                }

            }
            // 已结束
            if (!empty($endAt)) {
                Log::info('活动'.$id.'变更状态到已结束，异步任务创建');
                $next = strtotime($endAt);
                $delay = $next - time();
                if ($delay > 0) {
                    Queue::later($delay, ChangeBatchStatusJob::class, [
                        'coupon_stocks_id' => $id,
                        'event'    => 'have_ended',
                    ]);
                }
            }
        }
    }

    /**
     * 优惠券详情
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 20:16
     */
    public function show($id)
    {
        $data = $this->dao->show($id)->find();

        return $data ? $data->toArray() : [];
    }

    /**
     * 优惠券失效
     *
     * @param $id
     *
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 21:14
     */
    public function failure($id): array
    {
        $this->changeStatus($id, 'failure');

        return [];
    }

    /**
     * 优惠券取消
     *
     * @param $id
     *
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 21:15
     */
    public function cancelled($id): array
    {
        $this->changeStatus($id, 'cancelled');

        return [];
    }

    /**
     * 删除优惠券
     *
     * @param $id
     *
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/9 16:27
     */
    public function delete($id): array
    {
        $this->dao->deleteByIsDel($id);

        return [];
    }

    public function selectPageWhere($where, $stockIds = [], $page = 1, $limit =10, $field = ['*'])
    {
        return $this->dao->selectPageWhere($where, $stockIds, $page, $limit, $field);
    }


    public function getValue($where, $filed)
    {
        return $this->dao->getValue($where, $filed);
    }

    /**
     * 获取推荐的优惠券信息
     *
     * @param int $productId 商品id
     * @throws null
     * @return null|array{coupon:Model, minPriceSku: Model, discount_num: string, price: string, sub: float}
     */
    public function getRecommendCoupon(int $productId): ?array
    {
        // 获取最小的商品sku
        $minPriceSku = ProductAttrValue::getDB()->where('product_id', $productId)->order('price', 'asc')->find();
        if (!$minPriceSku) return null;
        $coupon = $this->getRecommendCouponFormProductId($productId, true);

        $discount_num = $coupon['discount_num'] ?? 0;
        $price = $minPriceSku['price'] ?? 0;
        $sub = bcsub($price, $discount_num, 2);

        return [
            'coupon' => $coupon,
            'minPriceSku' => $minPriceSku,
            'discount_num' => $discount_num,
            'price' => $price,
            'sub' => max($sub, 0),
        ];
    }


    /**
     * 根据商品id获取推荐的优惠券
     *
     * @param int $productId 商品id
     * @param bool $isFirst 是否获取单个数据
     * @return mixed
     * @throws null
     */
    public function getRecommendCouponFormProductId(int $productId, bool $isFirst)
    {
        // 获取这个商品所属的商户id
        /** @var ProductDao $productRep */
        $productRep = app()->make(ProductDao::class);
        $merId = $productRep->getMerIdFormProductId($productId);
        /** @var StockProductRepository $stockProductRep */
        $stockProductRep = app()->make(StockProductRepository::class);
        return $stockProductRep->productBestOffer($productId, $merId, $isFirst);
    }
}
