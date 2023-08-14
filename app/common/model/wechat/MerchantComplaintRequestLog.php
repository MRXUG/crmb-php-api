<?php


namespace app\common\model\wechat;


use app\common\model\BaseModel;

class MerchantComplaintRequestLog extends BaseModel
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
        return 'wechat_merchant_complaint_request_log';
    }

    protected $schema = [
        'id' => 'int',//
        'mer_id' => 'int',//
        'param' => 'text',//
        'url' => 'varchar',//
        'request_time' => 'datetime',//
        'input' => 'text',//
        'content' => 'text',//
        'header' => 'text',//
        'verify_status' => 'tinyint',//验证状态 0 验证失败，1验证成功
        'queue_status' => 'tinyint',//队列状态，0 pendding，1 success，2 failed

    ];
}