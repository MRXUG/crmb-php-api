<?php


namespace app\common\model\system\merchant;


use app\common\model\BaseModel;

class MerchantAdminRelationModel extends BaseModel
{

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
        return array_map('intval', explode(',', $value));
    }
    
    public function setRolesAttr($value)
    {
        return implode(',', $value);
    }
}