<?php


namespace app\common\dao\coupon;

use app\common\dao\BaseDao;
use app\common\model\coupon\CouponConfig;

class CouponConfigDao extends BaseDao
{
    protected function getModel(): string
    {
        return CouponConfig::class;
    }


    //修改
    public function updateCouponConfig($data)
    {
        $couponConfigMode = ($this->getModel()::getDB());
        foreach ($data as $k=>$v){
            if ($couponConfigMode->where(['configKey'=>$k])->count()){
                $couponConfigMode->where(['configKey'=>$k])->update([
                    'configValue'=>$v
                ]);
            }else{
                $couponConfigMode->insert([
                    'configKey'=>$k,
                    'configValue'=>$v,
                ]);
            }
        }
    }

}
