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


    public function search(?int $merId, array $where)
    {
        $query = ($this->getModel()::getDB())->alias("CouponStocksUser");
        $query->with(['stockDetail',"userDetail"]);

        if (isset($where['stock_name']) && $where['stock_name'] != ''){
            $query->hasWhere('stockDetail', function ($query) use ($where) {
                $query->where('stock_name', 'LIKE', "%{$where['stock_name']}%");
            });
        }

        if (isset($where['nickname']) && $where['nickname'] != ''){
            $query->hasWhere("userDetail",function ($query)use ($where){
                $query->where('nickname', 'LIKE', "%{$where['nickname']}%");
            });
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
            ->when($merId > 0, function ($query) use ($merId) {
                $query->where('CouponStocksUser.mer_id', $merId); //建券商户id
            })
            ->when(isset($where['uid']) && $where['uid'] > 0, function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['mch_id']) && $where['mch_id'] > 0, function ($query) use ($where) {
                $query->where('CouponStocksUser.mch_id', (int)$where['mch_id']); //发券商户号
            })
            ->when(isset($where['time']) && $where['time'], function ($query) use ($where) {
                $query->where('CouponStocksUser.start_at', '<=', $where['time'])
                    ->where('CouponStocksUser.end_at', '>', $where['time']);
            })->when(isset($where['coupon_code']) && $where['coupon_code'], function ($query) use ($where) {
                $query->where('CouponStocksUser.coupon_code', '=', $where['coupon_code']);
            })->when(isset($where['stock_id']) && $where['stock_id'], function ($query) use ($where) {
                $query->where('CouponStocksUser.stock_id', '=', $where['stock_id']);
            })->when(isset($where['status']) && $where['status'] != "", function ($query) use ($where) {
                if (intval($where['status']) === 1) $query->where('written_off', $where['status']);
                if (intval($where['status']) === 0) $query->where('written_off', $where['status']);
                if (intval($where['status']) === 2) {
                    $query->where('CouponStocksUser.end_at', '<',date("Y-m-d H:i:s"));
                }
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
        return $this->getModelObj()
            ->where([
                'stock_id'    => $stockId,
                'written_off' => $writtenOff,
            ]);
    }

    public function userReceivedCoupon($stockId, $uid)
    {
        return $this->getModelObj()
            ->with("stockDetail")
            ->where([
                'stock_id'    => $stockId,
                'uid'         => $uid
            ]);
    }

}
