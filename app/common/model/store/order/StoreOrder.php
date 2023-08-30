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


use app\common\model\applet\WxAppletModel;
use app\common\model\BaseModel;
use app\common\model\community\Community;
use app\common\model\store\product\ProductGroupUser;
use app\common\model\store\service\StoreService;
use app\common\model\store\shipping\Express;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantGoodsPayment;
use app\common\model\system\merchant\MerchantAd;
use app\common\model\user\User;
use app\common\repositories\store\MerchantTakeRepository;
use crmeb\jobs\ElasticSearch\OrderInsertJob;
use crmeb\jobs\ElasticSearch\OrderUpdateJob;
use think\facade\Queue;

class StoreOrder extends BaseModel
{

    /**
     * 订单状态（0：待发货；1：待收货；2：待评价；3：已完成； 9: 拼团中 10:  待付尾款 11:尾款超时未付 -1：已退款）
     */
    const ORDER_STATUS_BE_SHIPPED = 0;
    const ORDER_STATUS_BE_RECEIVE = 1;
    const ORDER_STATUS_REPLY = 2;
    const ORDER_STATUS_SUCCESS = 3;
    const ORDER_STATUS_REFUNDING = 4;
    const ORDER_STATUS_REFUND_ERROR = 5;
    const ORDER_STATUS_SPELL = 9;
    const ORDER_STATUS_TAIL = 10;
    const ORDER_STATUS_TAIL_FAIL = 11;
    const ORDER_STATUS_REFUND = -1;

    const ORDER_STATUS = [
        self::ORDER_STATUS_BE_SHIPPED => '待发货',
        self::ORDER_STATUS_BE_RECEIVE => '待收货',
        self::ORDER_STATUS_REPLY => '待评价',
        self::ORDER_STATUS_SUCCESS => '已完成',
        self::ORDER_STATUS_REFUNDING => '退款中',
        self::ORDER_STATUS_REFUND_ERROR => '退款失败',
        self::ORDER_STATUS_SPELL => '拼团中',
        self::ORDER_STATUS_TAIL => '待付尾款',
        self::ORDER_STATUS_TAIL_FAIL => '尾款超时未付',
        self::ORDER_STATUS_REFUND => '已退款',
    ];

    /**
     *
     * 下单场景 order_scenario
     * 0. 默认 其他
     * 1. 营销流量-回流优惠券
     * 2. 营销流量-扩展链接(视频号)
     * 3. 二次动画挽留
     * 4. 首次支付失败优惠
     * 5. 二次支付失败优惠
     * 6. 顺手买一件
     *
     *
     *
     */

    // 更具前端订单状态判断逻辑
    const STATUS_MAP = [
        self::ORDER_STATUS_BE_SHIPPED => "待提货",
        self::ORDER_STATUS_BE_RECEIVE=> "待提货",
        self::ORDER_STATUS_REPLY=> "待评价",
        self::ORDER_STATUS_SUCCESS=> "已完成",
        self::ORDER_STATUS_REFUND=> "已退款",
        self::ORDER_STATUS_SPELL=> "未成团"
    ];

    /**
     * 对平台而言的订单流量来源：1-回流流量（默认），2-自然流量，3-广告流量
     */
    const PLATFORM_SOURCE_BACK_FLOW = 1;
    const PLATFORM_SOURCE_NATURE = 2;
    const PLATFORM_SOURCE_AD = 3;

    const PLATFORM_SOURCE_TEXT = [
        self::PLATFORM_SOURCE_BACK_FLOW => '回流流量',
        self::PLATFORM_SOURCE_NATURE => '自然流量',
        self::PLATFORM_SOURCE_AD => '广告流量',
    ];

    /**
     * 对商户而言的订单流量来源：1-回流流量（未回传），2-回流流量（已回传），3-自然流量，4-广告流量'
     */
    const MERCHANT_SOURCE_BACK_NOT_TRANSMIT = 1;
    const MERCHANT_SOURCE_BACK_TRANSMITTED = 2;
    const MERCHANT_SOURCE_NATURE = 3;
    const MERCHANT_SOURCE_AD = 4;
    const MERCHANT_SOURCE_BACK_FLOW = 5;

    const MERCHANT_SOURCE_TEXT = [
        self::MERCHANT_SOURCE_BACK_NOT_TRANSMIT => '回流流量（未回传）',
        self::MERCHANT_SOURCE_BACK_TRANSMITTED  => '回流流量（已回传）',
        self::MERCHANT_SOURCE_NATURE                 => '自然流量',
        self::MERCHANT_SOURCE_AD                     => '广告流量',
        self::MERCHANT_SOURCE_BACK_FLOW         => '回流流量',
    ];

    public static function getMerchantSourceText($source)
    {
        return self::MERCHANT_SOURCE_TEXT[$source] ?? '';
    }

    /**
     * 广告渠道：1-腾讯广告
     */
    const TENCENT_AD = 1;
    const TRILL_AD = 2;
    const AD_CHANNEL = [
        self::TENCENT_AD => '腾讯广告',
        self::TRILL_AD => '抖音广告'
    ];
    public static function tablePk(): ?string
    {
        return 'order_id';
    }

    public static function tableName(): string
    {
        return 'store_order';
    }

    public function orderProduct()
    {
        return $this->hasMany(StoreOrderProduct::class, 'order_id', 'order_id');
    }

    public function refundProduct()
    {
        return $this->orderProduct()->where('refund_num', '>', 0);
    }

    public function refundOrder()
    {
        return $this->hasMany(StoreRefundOrder::class,'order_id','order_id');
    }

    public function orderStatus()
    {
        return $this->hasMany(StoreOrderStatus::class,'order_id','order_id')->order('change_time DESC');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }
    public function receipt()
    {
        return $this->hasOne(StoreOrderReceipt::class, 'order_id', 'order_id');
    }

    public function spread()
    {
        return $this->hasOne(User::class, 'uid', 'spread_uid');
    }

    public function TopSpread()
    {
        return $this->hasOne(User::class, 'uid', 'top_uid');
    }

    public function groupOrder()
    {
        return $this->hasOne(StoreGroupOrder::class, 'group_order_id', 'group_order_id');
    }

    public function verifyService()
    {
        return $this->hasOne(StoreService::class, 'service_id', 'verify_service_id');
    }

    public function applet()
    {
        return $this->hasOne(WxAppletModel::class, 'original_appid', 'appid');
    }

    public function getTakeAttr()
    {
        return app()->make(MerchantTakeRepository::class)->get($this->mer_id);
    }

    public function searchDataAttr($query, $value)
    {
        return getModelTime($query, $value);
    }

    public function presellOrder()
    {
        return $this->hasOne(PresellOrder::class, 'order_id', 'order_id');
    }

    public function finalOrder()
    {
        return $this->hasOne(PresellOrder::class,'order_id','order_id');
    }

    public function groupUser()
    {
        return $this->hasOne(ProductGroupUser::class,'order_id','order_id');
    }

    public function profitsharing()
    {
        return $this->hasMany(StoreOrderProfitsharing::class, 'order_id', 'order_id');
    }

    public function firstProfitsharing()
    {
        return $this->hasOne(StoreOrderProfitsharing::class, 'order_id', 'order_id')->where('type', 'order');
    }

    public function presellProfitsharing()
    {
        return $this->hasOne(StoreOrderProfitsharing::class, 'order_id', 'order_id')->where('type', 'presell');
    }

    // 核销订单的自订单列表
    public function takeOrderList()
    {
        return $this->hasMany(self::class,'main_id','order_id');
    }

    public function searchMerIdAttr($query, $value)
    {
        return $query->where('mer_id', $value);
    }

    public function getRefundStatusAttr()
    {
        $day = (int)systemConfig('sys_refund_timer') ?: 15;
        return ($this->verify_time ? strtotime($this->verify_time) > strtotime('-' . $day . ' day') : true);
    }

    public function getOrderExtendAttr($val)
    {
        return $val ? json_decode($val, true) : [];
    }

    public function getRefundExtensionOneAttr()
    {
        if ( $this->refundOrder ){
            return $this->refundOrder()->where('status',3)->sum('extension_one');
        }
        return 0;
    }

    public function getRefundExtensionTwoAttr()
    {
       if ( $this->refundOrder ){
           return $this->refundOrder()->where('status',3)->sum('extension_two');
       }
       return 0;
    }

    public function community()
    {
        return $this->hasOne(Community::class, 'order_id', 'order_id')->bind(['community_id']);
    }

    public function storeRefundOrder()
    {
        return $this->hasOne(StoreRefundOrder::class, 'order_id', 'order_id');
    }
    public function merchantAd()
    {
        return $this->hasOne(MerchantAd::class, 'ad_id', 'ad_id');
    }


    public function getRefundPriceAttr()
    {
       return StoreRefundOrder::where('order_id',$this->order_id)->where('status',3)->sum('refund_price');
    }
    // 订单金额流水
    public function flow()
    {
        return $this->hasMany(OrderFlow::class,'order_sn','order_sn');
    }
    // 货款
    public function goodsPayment()
    {
        return $this->hasMany(MerchantGoodsPayment::class,'order_id','order_id');
    }

    public static function onAfterUpdate($order){
        Queue::push(OrderUpdateJob::class, ['orderIds' => $order->order_id, 'updateColumn' => $order->toArray()]);
    }

    public static function onAfterInsert($order){
        Queue::push(OrderInsertJob::class, $order->toArray());
    }


}
