<?php


namespace app\common\model\wechat;


use app\common\model\BaseModel;

class MerchantComplaintOrderLog extends BaseModel
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
        return 'wechat_merchant_complaint_order_log';
    }

}