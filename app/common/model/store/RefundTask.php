<?php

namespace app\common\model\store;

use app\common\model\BaseModel;

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
}
