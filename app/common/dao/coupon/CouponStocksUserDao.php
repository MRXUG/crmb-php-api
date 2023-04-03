<?php


namespace app\common\dao\coupon;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\coupon\CouponStocks;
use app\common\model\coupon\CouponStocksUser;
use app\common\model\user\User;
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
        $query->with(['stockDetail',"userDetail"]);
        $query->hasWhere('stockDetail', function ($query) use ($where) {
            if (isset($where['stock_name']) && $where['stock_name'] != '') {
                $query->where('stock_name', 'LIKE', "%{$where['stock_name']}%");
            } elseif(isset($where['stock_id']) && $where['stock_id'] != '') {
                $query->where('stock_id', (int)$where['stock_id']);
            } else {
                $query->where(true);
            }
        });

        $query->hasWhere("userDetail",function ($query)use ($where){
            if (isset($where['nickname']) && $where['nickname'] != '') {
                $query->where('nickname', 'LIKE', "%{$where['nickname']}%");
            }else{
                $query->where(true);
            }
        });


        if(isset($where['status'])) {
            if ($where['status'] === 1) $query->where('written_off', 1);
            if ($where['status'] === 0) $query->where('written_off', 0);
            if ($where['status'] === 2) {
                $query->where('CouponStocksUser.end_at', '<',date("Y-m-d H:i:s"));
            }
       }

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
                $query->where('CouponStocksUser.start_at', '<=', $where['time'])
                    ->where('CouponStocksUser.end_at', '>', $where['time']);
            })->when(isset($where['coupon_code']), function ($query) use ($where) {
                $query->where('CouponStocksUser.coupon_code', '=', $where['coupon_code']);
            })->when(isset($where['stock_id']), function ($query) use ($where) {
                $query->where('CouponStocksUser.stock_id', '=', $where['stock_id']);
            })
            ->where('CouponStocksUser.is_del', WxAppletModel::IS_DEL_NO);

        return $query->order('sss DESC');
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
