<?php


namespace app\common\model\system\merchant;


use app\common\model\BaseModel;

class MerchantAdminRelationModel extends BaseModel
{
    protected $schema = [
        'id' => 'int',//
        'merchant_admin_id' => 'int',//merchant_admin表
        'roles' => 'varchar',//角色id，system_role目前可以多个
        'mer_id' => 'int',//绑定的商户id
        'create_time' => 'datetime',//
        'update_time' => 'datetime',//
        'is_del' => 'tinyint',//是否删除
        'status' => 'tinyint',//是否有效 1有效 0无效
        'login_count' => 'int',//商户管理员登录次数
        'level' => 'tinyint',//商户管理员等级(管理员添加的为0, 商户添加的为1)
        'last_ip' => 'varchar',//商户管理员最后一次登录IP地址
        'last_time' => 'timestamp',//商户管理员最后一次登录时间

    ];

    /**
     * @inheritDoc
     */
    public static function tablePk(): ?string
    {
        return 'id';
    }

    /**
     * @inheritDoc
     */
    public static function tableName(): string
    {
        return 'merchant_admin_relation';
    }

    public function merchantAdmin(){
        return $this->hasOne(MerchantAdmin::class, 'merchant_admin_id', 'merchant_admin_id');
    }

    public function merchant(){
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function getRolesAttr($value)
    {
        return $value ? array_map('intval', explode(',', $value)) : $value;
    }
    
    public function setRolesAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }
}