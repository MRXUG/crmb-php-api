<?php
/**
 * @user: BEYOND 2023/3/3 11:22
 */

namespace app\common\model\coupon;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;
use think\model\relation\HasMany;

class CouponStocks extends BaseModel
{

    protected $schema = [
        'applet_appid'                => 'varchar', //建券，小程序appid
        'available_day_after_receive' => 'smallint', //生效后N天内有效
        'background_color'            => 'varchar', //券背景颜色
        'coupon_code_mode'            => 'varchar', //商家券核销方式
        'coupon_image_cos_url'        => 'text', //券详情cos地址
        'coupon_image_url'            => 'text', //券详情图片
        'create_admin_id'             => 'int', //发布admin_id
        'create_time'                 => 'timestamp', //
        'date_range'                  => 'json', //使用有效期，时间段
        'discount_num'                => 'int', //券面额:88折对应数字88；5折对应数字50
        'end_at'                      => 'timestamp', //券失效时间
        'entrance_words'              => 'varchar', //入口文案
        'goods_name'                  => 'varchar', //商品适用范围
        'guiding_words'               => 'varchar', //引导文案
        'id'                          => 'bigint', //id
        'is_authorization'            => 'tinyint', //是否授权于小程序.1=是,2=否
        'is_del'                      => 'tinyint', //删除状态：0=未删除，1=已删除
        'is_limit'                    => 'tinyint', //是否限量，1=是0=否
        'is_overlay_to_stock'         => 'tinyint', //是否可叠加使用:0否，1是
        'is_public'                   => 'tinyint', //是否在商城店铺展示改券,1=是,0=否
        'is_user_limit'               => 'tinyint', //每人是否限量1=是0=否
        'max_coupons'                 => 'int', //券最大发放数量(发放总量)
        'max_coupons_by_day'          => 'int', //单天发放上限个数
        'max_coupons_per_user'        => 'int', //用户最大可领个数
        'mch_id'                      => 'varchar', //建券，商户号
        'mer_id'                      => 'int', //建券商户主键
        'merchant_logo_cos_url'       => 'text', //商户logocos地址
        'merchant_logo_url'           => 'text', //商户logo
        'merchant_name'               => 'varchar', //商户名称
        'pre_admin_id'                => 'int', //建券admin_id
        'scope'                       => 'tinyint', //分类:1=店铺全部商品，2=指定商品
        'sended'                      => 'int', //已发出的券总数
        'start_at'                    => 'timestamp', //券生效时间
        'status'                      => 'tinyint', //券状态：0待发布，1活动未开始，2进行中，3已结束，4已取消
        'stock_id'                    => 'varchar', //商家券批次号
        'stock_name'                  => 'varchar', //商家券批次名称
        'stock_type'                  => 'varchar', //券类型:NORMAL：固定面额满减券批次,DISCOUNT：折扣券批次
        'total_count'                 => 'int', //去重后上传code总数
        'transaction_minimum'         => 'int', //用券消费门槛
        'type'                        => 'tinyint', //分类:1=商城优惠券，2=回流优惠券
        'type_date'                   => 'tinyint', //使用有效期,1==立即生效，2=时间段，2=N天后
        'wait_days_after_receive'     => 'smallint', //领取后N天开始生效

    ];

    /**
     * 批次最大可发放个数限制|单天发放上限个数
     */
    const MAX_COUPONS = 1000000000;

    /**
     * 用户可领个数，每个用户最多100张券
     */
    const MAX_COUPONS_PER_USER = 100;

    // 批次状态：0待发布，1活动未开始，2进行中，3已结束，4已取消
    const STATUS_DEFAULT = 0;
    const STATUS_NOT     = 1;
    const STATUS_ING     = 2;
    const STATUS_END     = 3;
    const STATUS_CANCEL  = 4;

    // 有效状态
    const VALID_STATUS = [self::STATUS_NOT, self::STATUS_ING];

    /**
     * 批次类型
     *  NORMAL：固定面额满减券批次
     *  DISCOUNT：折扣券批次
     *  EXCHANGE：换购券批次（暂无该场景）
     */
    const STOCK_TYPE_REDUCE   = 'NORMAL';
    const STOCK_TYPE_DISCOUNT = 'DISCOUNT';
    const STOCK_TYPE_EXCHANGE = 'EXCHANGE';

    /**
     * 批次是否公开
     */
    const PUBLIC_YES = 1;
    const PUBLIC_NO  = 0;

    /*
     * 商家券核销方式：请求微服务创建商家券需要传
     */
    const WECHATPAY_MODE  = 'WECHATPAY_MODE';
    const MERCHANT_UPLOAD = 'MERCHANT_UPLOAD';

    /**
     * 商家券，核销方式-线上小程序核销
     */
    const COUPON_USE_ONLINE = 'MINI_PROGRAMS';

    /*
     * 是否全部商品通用:1店铺可用，2部分可用
     */
    const SCOPE_NO  = 2;
    const SCOPE_YES = 1;

    /**
     * 分类:1=商城优惠券，2=回流优惠券
     */
    const TYPE_DISCOUNT = 1;
    const TYPE_REFLUX   = 2;

    const COUPON_TYPE_NAME = [
        self::TYPE_DISCOUNT => '商城优惠券',
        self::TYPE_REFLUX   => '回流优惠券',
    ];

    /**
     * 批次状态
     */
    const TO_BE_RELEASED = 0;
    const NOT_STARTED    = 1;
    const IN_PROGRESS    = 2;
    const HAVE_ENDED     = 3;
    const CANCELLED      = 4;

    const COUPON_STATUS_NAME = [
        self::TO_BE_RELEASED => '待发布',
        self::NOT_STARTED    => '活动未开始',
        self::IN_PROGRESS    => '进行中',
        self::HAVE_ENDED     => '已结束',
        self::CANCELLED      => '已取消',
    ];

    /**
     * 是否核销：0=未核销，1=已核销
     */
    const WRITTEN_OFF_YES = 1;
    const WRITTEN_OFF_NO  = 0;

    // 是否限量
    const IS_LIMIT_YES = 1;
    const IS_LIMIT_NO  = 0;

    // 每人是否限量
    const IS_USER_LIMIT_YES = 1;
    const IS_USER_LIMIT_NO  = 0;

    // 使用有效期,1=立即生效，2=时间段，2=N天后
    const DATE_NOW   = 1;
    const DATE_RANGE = 2;
    const DATE_N     = 3;
    const DATE_H     = 4;

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'coupon_stocks';
    }

    /**
     * @return \think\model\relation\HasMany
     */
    public function product()
    {
        return $this->hasMany(StockProduct::class, 'coupon_stocks_id', 'id')->where('is_del', 0);
    }
    public function couponStocksUser(): HasMany
    {
        return $this->hasMany(CouponStocksUser::class, 'stock_id', 'stock_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

}
