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


namespace app\common\model\store\order;


use app\common\model\BaseModel;
use app\common\model\store\RefundTask;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;
use think\model\relation\HasOne;

class StoreRefundOrder extends BaseModel
{
    /**
     *  状态 0:待审核 -1:审核未通过 1:待退货 2:待收货 3:已退款 4: 退款中 5: 退款失败
     */
    const CHECK_PENDING = 0;
    const UNAPPROVE = -1;
    const PENDING_RETURN = 1;
    const TO_BE_RECEIVED = 2;
    const REFUNDED = 3;
    const REFUNDING = 4;
    const REFUND_FAILED = 5;

    const REFUND_ORDER_STATUS = [
        self::CHECK_PENDING => '待审核' ,
        self::UNAPPROVE => '审核未通过',
        self::PENDING_RETURN => '待退货',
        self::TO_BE_RECEIVED => '待收货',
        self::REFUNDED => '已退款',
        self::REFUNDING => '退款中',
        self::REFUND_FAILED => '退款失败'
    ];


    public static function tablePk(): ?string
    {
        return 'refund_order_id';
    }

    public static function tableName(): string
    {
        return 'store_refund_order';
    }

    public function getPicsAttr($val)
    {
        return $val ? explode(',', $val) : [];
    }

    public function setPicsAttr($val)
    {
        return $val ? implode(',', $val) : '';
    }

    public function refundProduct()
    {
        return $this->hasMany(StoreRefundProduct::class, 'refund_order_id', 'refund_order_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function order()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }

    public function refundTask(): HasOne
    {
        return $this->hasOne(RefundTask::class, 'refund_order_id', 'refund_order_id');
    }

    public function searchDataAttr($query, $value)
    {
        return getModelTime($query, $value);
    }

    public function getAutoRefundTimeAttr()
    {
        $merAgree = systemConfig('mer_refund_order_agree') ?: 7;
        return strtotime('+' . $merAgree . ' day', strtotime($this->status_time));
    }

    public function getCombineRefundParams()
    {
        return [
            'sub_mchid' => $this->merchant->sub_mchid,
            'order_sn' => $this->order->order_sn,
            'refund_order_sn' => $this->refund_order_sn,
            'refund_price' => $this->refund_price,
            'pay_price' => $this->order->pay_price
        ];
    }
}
