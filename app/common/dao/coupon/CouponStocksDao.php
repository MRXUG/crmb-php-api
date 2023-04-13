<?php
/**
 * @user: BEYOND 2023/3/6 12:00
 */

namespace app\common\dao\coupon;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\coupon\CouponStocks;
use app\common\model\coupon\StockProduct;
use app\common\model\store\product\Product;
use think\db\BaseQuery;

class CouponStocksDao extends BaseDao
{
    protected function getModel(): string
    {
        return CouponStocks::class;
    }


    /**
     * 优惠券批次列表
     *
     * @param int|null $mchId
     * @param array $where
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/6 14:38
     */
    public function search($mchId, array $where)
    {
        $query = ($this->getModel()::getDB())
            ->with(['couponStocksUser','merchant']);

        $query->hasWhere("merchant",function ($query)use ($where){

            if (isset($where['mer_name']) && $where['mer_name'] != ''){
                $query->where('mer_name', 'LIKE', "%{$where['mer_name']}%");
            }else{
                $query->where(true);
            }
        });


        $query->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('CouponStocks.status', (int)$where['status']);
        })
            ->when(isset($where['stock_name']) && $where['stock_name'] !== '', function ($query) use ($where) {
                $query->whereLike('CouponStocks.stock_name', "%{$where['stock_name']}%");
            })
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $query->whereLike('CouponStocks.type', $where['type']);
            })
            ->when(isset($where['stock_id']) && $where['stock_id'] !== '', function ($query) use ($where) {
                $query->whereLike('CouponStocks.stock_id', $where['stock_id']);
            })
            ->when($mchId > 0, function ($query) use ($mchId) {
                $query->where('CouponStocks.mch_id', $mchId);
            })
            ->when(isset($where['is_public']) && $where['is_public'] !== '', function ($query) use ($where) {
                $query->where('CouponStocks.is_public', (int)$where['is_public']);
            })
            ->when(isset($where['scope']) && $where['scope'] !== '', function ($query) use ($where) {
                $query->where('CouponStocks.scope', (int)$where['scope']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] > 0, function ($query) use ($where) {
                $query->where('CouponStocks.mer_id', (int)$where['mer_id']);
            })

            ->when(isset($where['time']) && $where['time'], function ($query) use ($where) {
                $query->where('CouponStocks.start_at', '<', $where['time'])->where('end_at', '>', $where['time']);
            })
            ->where('CouponStocks.is_del', WxAppletModel::IS_DEL_NO);
        return $query->order('CouponStocks.id DESC');
    }

    public function joinStockProduct($where)
    {
        return CouponStocks::alias('CouponStocks')->hasWhere('goodsList', function (BaseQuery $query) use ($where) {
            $query->where('CouponStocks.id', $where['id']);
        });
    }

    public function info($wherce)
    {
        return ($this->getModel()::getDB())->where($wherce)->find();
    }

    /**
     * 优惠券详情
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 20:12
     */
    public function show($id)
    {
        return ($this->getModel()::getDB())
            ->find($id)
            ->with(['product'=> function ($query) {
                $query->with('productDetail');
            },
            ])
            ->where('id', $id)
            ->where('is_del', 0);
    }

    public function selectPageWhere($where = [], $stockIds = [], $page = 1, $limit = 10, $field = ['*'])
    {
        /**
         * @var BaseQuery $query
         */
        $query = $this->getModel()::getDB();
        return $query
            ->when($where, function ($query) use ($where) {
                $query->where($where);
            })
            ->when($stockIds, function ($query) use ($stockIds) {
                $query->whereIn('stock_id', $stockIds);
            })
            ->field($field)
            ->with(['product'])
            ->where('is_del', 0)
            ->page($page, $limit)
            ->select();
    }

    /**
     * 根据商品id获取优惠券列表
     *
     * @param int $productId
     * @return array
     * @throws null
     */
    public function getCouponListFromProductId(int $productId): array
    {
        $newDate = date("Y-m-d H:i:s");
        $mer_id = Product::getInstance()->where('product_id', $productId)->value('mer_id');
        # 以获取优惠券id的方式获取优惠券数据
        # 先获取匹配的商户优惠券
        $where = [
            ['a.scope', '=', 1],
            ['a.is_del', '=', 0],
            ['a.end_at', '>', $newDate],
            ['a.status', 'in', [1, 2]]
        ];

        $couponIds = CouponStocks::getInstance()->alias('a')
            ->where(array_merge($where, [['a.mer_id', '=', $mer_id]]))
            ->column('a.id');

        array_push($couponIds, ...StockProduct::getInstance()
            ->alias('b')
            ->leftJoin('eb_coupon_stocks a', 'b.coupon_stocks_id = a.id')
            ->where($where)
            ->where('b.product_id', $productId)
            ->column('b.coupon_stocks_id'));

        $couponIds = array_unique($couponIds);

        return CouponStocks::getInstance()->whereIn('id', $couponIds)
            ->field([
                'id',
                'discount_num',
                'transaction_minimum',
                'status'
            ])
            ->select()
            ->toArray();
    }

}
