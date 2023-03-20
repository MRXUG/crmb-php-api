<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/2 11:48
 */

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;

class PlatformMerchant extends BaseModel
{
    /**
     * 是否删除，1=是，0=否
     */
    const DELETED_YES = 1;
    const DELETED_NO = 0;

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'platform_merchant';
    }
}