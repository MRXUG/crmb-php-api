<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\common\model\system\merchant\MerchantAd;

class MerchantAdDao extends BaseDao
{
    /**
     * @return BaseModel
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantAd::class;
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function getInfo($id)
    {
        $nowUnixTime = time();

        $result = $this->getModelObj()->with(['couponIds' => function (BaseQuery  $query)  {
            $query->with(['couponInfo']);
        }])->find($id);

        $res = [];
        if (method_exists($result, 'toArray')) {
            $res = $result->toArray();

            foreach ($res['couponIds'] as $k => $v) {
                if (!empty($v['couponInfo']) && strtotime($v['couponInfo']['end_at']) <= $nowUnixTime) {
                    unset($res['couponIds'][$k]);
                }
            }

            $res['couponIds'] = array_merge($res['couponIds'], []);
        }

        return $res;
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function getInfo2($id)
    {
        $result = $this->getModelObj()->find($id);
        if (empty($result)) {
            return [];
        } else {
            return $result->toArray();
        }
    }

    public function getDeliveryMethod($id){
        return $this->getModelObj()->where("ad_id","=",$id)->value("deliveryMethod");
    }
}
