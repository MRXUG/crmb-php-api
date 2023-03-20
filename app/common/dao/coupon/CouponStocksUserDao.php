<?php


namespace app\common\dao\coupon;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\coupon\CouponStocks;
use app\common\model\coupon\CouponStocksUser;
use think\db\Query;

class CouponStocksUserDao extends BaseDao
{
    protected function getModel(): string
    {
        return CouponStocksUser::class;
    }


    public function search(?int $mchId, array $where)
    {
        $query = ($this->getModel()::getDB());
        $query->with(['stockDetail']);
        $query->hasWhere('stockDetail', function ($query) use ($where) {
            if (isset($where['stock_name']) && $where['stock_name'] != '') {
                $query->where('stock_name', 'LIKE', "%{$where['stock_name']}%");
            } elseif (isset($where['nickname']) && $where['nickname'] != '') {
                $query->where('nickname', 'LIKE', "%{$where['nickname']}%");
            }elseif(isset($where['stock_id']) && $where['stock_id'] != '') {
                $query->where('stock_id', (int)$where['stock_id']);
            } else {
                $query->where(true);
            }
        });

        $query->when(isset($where['written_off']) && $where['written_off'] !== '', function ($query) use ($where) {
            $query->where('written_off', (int)$where['written_off']);
        })
//            ->when(isset($where['stock_id']) && $where['stock_id'] !== '', function ($query) use ($where) {
//                $query->where('stockDetail.stock_id', (int)$where['stock_id']);
//            })
            ->when(isset($where['coupon_user_id']) && $where['coupon_user_id'] !== '', function ($query) use ($where) {
                $query->where('coupon_user_id', (int)$where['coupon_user_id']);
            })
            ->when($mchId > 0, function ($query) use ($mchId) {
                $query->where('stockDetail.mch_id', $mchId);
            })
            ->when(isset($where['uid']) && $where['uid'] > 0, function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] > 0, function ($query) use ($where) {
                $query->where('CouponStocks.mer_id', (int)$where['mer_id']);
            })
            ->when(isset($where['time']), function ($query) use ($where) {
                $query->where('start_at', '<=', $where['time'])
                    ->where('end_at', '>', $where['time']);
            })
            ->where('CouponStocksUser.is_del', WxAppletModel::IS_DEL_NO);

        return $query->order('coupon_user_id DESC');
    }

    /**
     * 核销数据
     *
     * @param $stockId
     * @param $writtenOff
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/6 18:07
     */
    public function couponWrittenOffNum($stockId, $writtenOff)
    {
        return ($this->getModel()::getDB())
            ->where([
                'stock_id'    => $stockId,
                'written_off' => $writtenOff,
            ]);
    }

}
