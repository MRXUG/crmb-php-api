<?php
/**
 * @user: BEYOND 2023/3/11 18:37
 */

namespace app\controller\api\store\order;

use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\basic\BaseController;

class RemovePayFailureOrder extends BaseController
{
    /**
     * 使用支付失败优惠，删除原有订单
     *
     * @param $orderSn
     * @param StoreOrderRepository $storeOrderRepository
     *
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function remove($groupOrderId, StoreOrderRepository $storeOrderRepository)
    {
        $storeOrderRepository->removePayFailureOrder($groupOrderId, $this->request->uid());
        return app('json')->success();
    }

}