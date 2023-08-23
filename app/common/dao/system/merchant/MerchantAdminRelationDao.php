<?php

namespace app\common\dao\system\merchant;


use app\common\dao\BaseDao;
use app\common\model\system\merchant\MerchantAdmin;
use app\common\model\system\merchant\MerchantAdminRelationModel;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Model;

/**
 * Class MerchantAdminRelationDao
 * @package app\common\dao\system\merchant
 */
class MerchantAdminRelationDao extends BaseDao
{

    protected function getModel(): string
    {
        return MerchantAdminRelationModel::class;
    }
}
