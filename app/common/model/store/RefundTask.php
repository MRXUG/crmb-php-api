<?php

namespace app\common\model\store;

use app\common\dao\store\order\StoreOrderDao;
use app\common\model\BaseModel;
use app\common\model\store\order\StoreRefundOrder;
use app\common\repositories\store\order\StoreRefundStatusRepository;
use think\db\exception\DbException;
use think\facade\Log;

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
        $msg = implode(";", array_merge(explode(";", $this->getAttr('err_msg') ?? ''), $errArr));
        Log::debug("退款错误获取到参数 {$this->getAttr('refund_task_id')} " . $msg);
        # 解析先前存在的错误
        RefundTask::getInstance()->where('refund_task_id', $this->getAttr('refund_task_id'))->update([
            'err_msg' => $msg
        ]);
        $this->setAttr('err_msg', $msg);
        # 退款失败记录
        /** @var StoreRefundStatusRepository $statusRepository */
        $statusRepository = app()->make(StoreRefundStatusRepository::class);
        $statusRepository->status(
            $this->getAttr('refund_order_id'),
            $statusRepository::REFUND_FAILED,
            "退款失败: {$msg}"
        );
        # 变更退款状态
        StoreRefundOrder::getInstance()->where('refund_order_id', $this->getAttr('refund_order_id'))->update([
            'status' => 5
        ]);
        return true;
    }
}
