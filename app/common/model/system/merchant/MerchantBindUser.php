<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;

class MerchantBindUser extends BaseModel
{
    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'bind_id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_bind_user';
    }

    /**
     * 状态：0-无效，1-有效
     */
    const STATUS_VALID = 1;
    const STATUS_INVALID = 0;
}