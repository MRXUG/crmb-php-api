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
}