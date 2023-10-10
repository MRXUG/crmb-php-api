<?php


namespace app\common\model\erp;


use app\common\model\BaseModel;

class JuShuiTanAuthorizeConfig extends BaseModel
{

    /**
     * @inheritDoc
     */
    public static function tablePk(): ?string
    {
        return "id";
    }

    /**
     * @inheritDoc
     */
    public static function tableName(): string
    {
        return "jushuitan_authorize_config";
    }
}