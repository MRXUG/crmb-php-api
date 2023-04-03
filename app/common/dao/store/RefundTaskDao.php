<?php

namespace app\common\dao\store;

use app\common\dao\BaseDao;
use app\common\model\store\RefundTask;
use think\Model;

class RefundTaskDao extends BaseDao
{

    protected function getModel(): string
    {
        return RefundTask::class;
    }

    /**
     * 更新 退款时间
     *
     * @param int $refundTaskId
     * @return Model
     */
    public function upRefundTime(int $refundTaskId): Model
    {
        return $this->getModelObj()->where('refund_task_id', '=', $refundTaskId)->update([
            'refund_time' => date("Y-m-d H:i:s")
        ]);
    }
}
