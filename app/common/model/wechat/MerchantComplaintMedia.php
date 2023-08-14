<?php


namespace app\common\model\wechat;


use app\common\model\BaseModel;

class MerchantComplaintMedia extends BaseModel
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
        return 'wechat_merchant_complaint_media';
    }

    protected $schema = [
        'id' => 'int',//
        'media_id' => 'varchar',//
        'mime_type' => 'varchar',//
        'filesize' => 'int',//byte
        'mer_id' => 'int',//
        'admin_id' => 'int',//管理员id
        'user_type' => 'tinyint',//管理员类型
        'complaint_id' => 'varchar',//
        'create_time' => 'datetime',//
        'update_time' => 'datetime',//

    ];
}