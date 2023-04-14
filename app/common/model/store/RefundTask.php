<?php

namespace app\common\model\store;

use app\common\dao\store\order\StoreOrderDao;
use app\common\model\BaseModel;
use app\common\model\store\order\StoreRefundOrder;
use think\db\exception\DbException;

class RefundTask extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'refund_task_id';
    }

    public static function tableName(): string
    {
        return 'refund_task';
    }

    /**
     * 返回错误集中处理
     *
     * @param array $errArr
     * @return bool
     * @throws null
     */
    public function profitSharingErrHandler(array $errArr): bool
    {
        # 如果没有错误的话那么返回false继续向下执行
        if (empty($errArr)) return false;
        # 解析先前存在的错误
        RefundTask::getDB()->where('refund_task_id', $this->getAttr('refund_task_id'))->update([
            'err_msg' => implode(";", array_merge(explode(";", $this->getAttr('err_msg')), $errArr))
        ]);
        # 变更退款状态
        StoreRefundOrder::getDB()->where('refund_order_id', $this->getAttr('refund_order_id'))->update([
            'status' => 5
        ]);
        /** @var StoreOrderDao $orderDao */
        $orderDao = app()->make(StoreOrderDao::class);

        return true;
    }
}
