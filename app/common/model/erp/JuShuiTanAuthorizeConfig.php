<?php


namespace app\common\model\erp;


use app\common\model\BaseModel;

class JuShuiTanAuthorizeConfig extends BaseModel
{
    protected $schema = [
        'id' => 'int',//
        'app_name' => 'varchar',//应用名称
        'app_key' => 'varchar',//一个app_key可以对应多个授权
        'app_secret' => 'varchar',//
        'version' => 'int',//固定值2
        'charset' => 'varchar',//utf-8
        'state' => 'varchar',//授权商家信息，mer_id
        'mer_id' => 'int',//商户id
        'base_url' => 'varchar',//
        'access_token' => 'varchar',//
        'refresh_token' => 'varchar',//刷新token，必须在access_token过期前刷新，否则需要重新授权
        'access_token_expire_at' => 'datetime',//access_token过期时间
        'authorize_time' => 'datetime',//上次授权时间
        'create_time' => 'datetime',//
        'update_time' => 'datetime',//

    ];
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