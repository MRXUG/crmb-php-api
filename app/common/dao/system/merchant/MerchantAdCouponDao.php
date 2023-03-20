<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/6 18:23
 */

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use think\db\exception\DbException;
use app\common\model\system\merchant\MerchantAdCoupon;

class MerchantAdCouponDao extends BaseDao
{
    /**
     * @return BaseModel
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantAdCoupon::class;
    }

    /**
     * @throws DbException
     */
    public function dels($where) : int
    {
        return ($this->getModel())::getDB()->where($where)->delete();
    }
}