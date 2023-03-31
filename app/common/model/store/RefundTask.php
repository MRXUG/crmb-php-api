<?php

namespace app\common\model\store;

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
        $newTask = clone $this;
        $newTask->setAttr('err_msg',  implode(";", array_merge(explode(";", $newTask->getAttr('err_msg')), $errArr)));
        $newTask->save();
        # 变更退款状态
        StoreRefundOrder::getDB()->where('refund_order_id', $newTask->getAttr('refund_order_id'))->update([
            'status' => 5
        ]);

        return true;
    }
}
