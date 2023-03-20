<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;

class MerchantGoodsPayment extends BaseModel
{

    /**
     * 结算状态：1-待结算，2-部分结算，3-全部结算，4-售后退款
     */
    const SETTLE_STATUS_WAITING_SETTLE = 1;
    const SETTLE_STATUS_PART = 2;
    const SETTLE_STATUS_ALL = 3;
    const SETTLE_STATUS_AFTER_SALE_REFUND = 4;

    const SETTLE_STATUS_TEXT = [
        self::SETTLE_STATUS_WAITING_SETTLE    => '待结算',
        self::SETTLE_STATUS_PART              => '部分结算',
        self::SETTLE_STATUS_ALL               => '全部结算',
        self::SETTLE_STATUS_AFTER_SALE_REFUND => '售后退款',
    ];

    public static function getSettlementStatusText(int $status): string
    {
        return self::SETTLE_STATUS_TEXT[$status] ?? '';
    }

    /**
     * 服务费状态：0-无,1-待到账，2-临时到账，3-已到账，4-已失效
     */
    const SERVICE_FEE_STATUS_NONE = 0;
    const SERVICE_FEE_STATUS_WAITING_RECEIVE = 1;
    const SERVICE_FEE_STATUS_TEMPORARY = 2;
    const SERVICE_FEE_STATUS_RECEIVED = 3;
    const SERVICE_FEE_STATUS_INVALID = 4;

    const SERVICE_FEE_STATUS_TEXTS = [
        self::SERVICE_FEE_STATUS_NONE            => '无',
        self::SERVICE_FEE_STATUS_WAITING_RECEIVE => '待到账',
        self::SERVICE_FEE_STATUS_TEMPORARY       => '临时到账',
        self::SERVICE_FEE_STATUS_RECEIVED        => '已到账',
        self::SERVICE_FEE_STATUS_INVALID         => '已失效',
    ];

    /**
     * @param  int  $status
     * @return string
     */
    public static function getServiceFeeStatusText(int $status): string
    {
        return self::SERVICE_FEE_STATUS_TEXTS[$status] ?? '无';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'payment_id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_goods_payment';
    }

    public function storeOrder()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }

    public function paymentMerchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }
}