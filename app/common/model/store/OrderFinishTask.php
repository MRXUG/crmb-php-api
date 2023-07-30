<?php

namespace app\common\model\store;

use app\common\model\BaseModel;

class OrderFinishTask extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'order_finish_task';
    }
}
