<?php
/**
 * @user: BEYOND 2023/3/6 12:00
 */

namespace app\common\dao\coupon;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\coupon\CouponStocks;
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
    public function search(?int $mchId, array $where)
    {
        $query = ($this->getModel()::getDB())
            ->with(['couponStocksUser']);

        $query->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', (int)$where['status']);
        })
            ->when(isset($where['stock_name']) && $where['stock_name'] !== '', function ($query) use ($where) {
                $query->whereLike('stock_name', "%{$where['stock_name']}%");
            })
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $query->whereLike('type', $where['type']);
            })
            ->when(isset($where['stock_id']) && $where['stock_id'] !== '', function ($query) use ($where) {
                $query->whereLike('stock_id', $where['stock_id']);
            })
            ->when($mchId > 0, function ($query) use ($mchId) {
                $query->where('mch_id', $mchId);
            })
            ->when(isset($where['is_public']) && $where['is_public'] !== '', function ($query) use ($where) {
                $query->where('is_public', (int)$where['is_public']);
            })
            ->when(isset($where['scope']) && $where['scope'] !== '', function ($query) use ($where) {
                $query->where('scope', (int)$where['scope']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] > 0, function ($query) use ($where) {
                $query->where('mer_id', (int)$where['mer_id']);
            })
            ->where('is_del', WxAppletModel::IS_DEL_NO);
        return $query->order('id DESC');
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
            ->page($page, $limit)
            ->where('is_del', 0)
            ->select();
    }

}