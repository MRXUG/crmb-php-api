<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use think\model\relation\HasMany;

class MerchantAd extends BaseModel
{

    protected $schema = [
        'ad_account_id'               => 'int', //广告账户id（关联账户）
        'ad_channel_id'               => 'int', //广告渠道
        'ad_id'                       => 'int', //主键
        'ad_link_name'                => 'varchar', //广告链接名称
        'consume_coupon_switch'       => 'tinyint', //核销券：1-主动核销
        'coupon_popup_chart'          => 'varchar', //领券弹窗
        'create_time'                 => 'timestamp', //创建时间
        'deliveryMethod'              => 'json', //抖音投放设置
        'discount_fission_switch'     => 'tinyint', //优惠裂变开关：1-开启，2-关闭
        'discount_image'              => 'varchar', //优惠后商品图
        'fission_amount'              => 'decimal', //涨红包金额
        'goods_id'                    => 'int', //商品id
        'is_del'                      => 'tinyint', //是否删除
        'landing_page_type'           => 'tinyint', //落地页类型：1-图文落地页
        'marketing_discount_amount'   => 'decimal', //优惠金额
        'marketing_page_backcolor'    => 'varchar', //营销页背景色
        'marketing_page_bottom_chart' => 'varchar', //营销页底图
        'marketing_page_goods_chart'  => 'varchar', //营销页商品图
        'marketing_page_main_chart'   => 'varchar', //营销页头图
        'marketing_page_popup_chart'  => 'varchar', //营销页弹窗
        'marketing_page_switch'       => 'tinyint', //营销页开关：1-开启，2-关闭
        'mer_id'                      => 'int', //商户id
        'multistep_discount'          => 'varchar', //多级别回流优惠信息
        'multistep_switch'            => 'tinyint', //多级别回流0关闭 1开启
        'page_popover_switch'         => 'tinyint', //商详页弹窗开关：1-开启，2-关闭
        'page_type'                   => 'tinyint', //页面配置：1-集合页，2-单品页
        'pay_failure_discount_amount' => 'decimal', //支付失败优惠金额
        'pay_failure_discount_switch' => 'tinyint', //支付失败优惠开关：1-开启， 2-关闭
        'postback_proportion'         => 'int', //回传比例
        'reflow_coupons_switch'       => 'tinyint', //回流优惠券开关：1-开启，2-关闭
        'update_time'                 => 'timestamp', //更新时间

    ];
    
    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'ad_id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_ad';
    }

    public function couponIds() : HasMany
    {
        return $this->hasMany(MerchantAdCoupon::class, 'ad_id', 'ad_id');
    }

    public function getMultistepDiscountAttr($value)
    {
        return $value ? json_decode($value) : [];
    }
}