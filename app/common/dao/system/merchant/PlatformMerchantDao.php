<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/2 11:49
 */

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\system\merchant\PlatformMerchant;

class PlatformMerchantDao extends BaseDao
{

    protected function getModel(): string
    {
        return PlatformMerchant::class;
    }
}