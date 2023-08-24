<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\common\model\system\merchant;


use app\common\model\BaseModel;
use app\common\model\system\auth\Role;
use think\db\BaseQuery;

class MerchantAdmin extends BaseModel
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'merchant_admin_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_admin';
    }

    /**
     * @param $value
     * @return array
     * @author xaboy
     * @day 2020-03-30
     */
    public function getRolesAttr($value)
    {
        return $value ? array_map('intval', explode(',', $value)) : $value;
    }

    /**
     * @param $value
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public function setRolesAttr($value)
    {
        return implode(',', $value);
    }

    /**
     * @param bool $isArray
     * @return array|string
     * @author xaboy
     * @day 2020-04-18
     */
    public function roleNames($isArray = false)
    {
        $roleNames = Role::getDB()->whereIn('role_id', $this->roles)->column('role_name');
        return $isArray ? $roleNames : implode(',', $roleNames);
    }

    public function merchantRelation(){
        /**
         * roles,is_del,status,level 可以删除
         *
         */
        return $this->hasMany(MerchantAdminRelationModel::class, 'merchant_admin_id', 'merchant_admin_id');
    }

}
