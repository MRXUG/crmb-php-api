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

namespace app\common\model\system\merchant;

use app\common\dao\store\product\ProductDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponUser;
use app\common\model\store\product\Product;
use app\common\model\store\product\Spu;
use app\common\model\system\config\SystemConfigValue;
use app\common\model\system\financial\Financial;
use app\common\model\system\serve\ServeOrder;
use app\common\repositories\store\StoreActivityRepository;

class Merchant extends BaseModel
{

    protected $schema = [
        'business_license'               => 'varchar', //营业执照
        'care_count'                     => 'int', //关注总数
        'category_id'                    => 'int', //商户分类 id
        'commission_rate'                => 'decimal', //提成比例
        'copy_product_num'               => 'int', //剩余复制商品次数
        'create_time'                    => 'datetime', //
        'delivery_balance'               => 'decimal', //配送余额
        'delivery_way'                   => 'varchar', //配送方式
        'export_dump_num'                => 'int', //电子面单剩余次数
        'financial_alipay'               => 'varchar', //支付宝转账信息
        'financial_bank'                 => 'varchar', //银行卡转账信息
        'financial_type'                 => 'tinyint', //默认使用类型
        'financial_wechat'               => 'varchar', //微信转账信息
        'is_audit'                       => 'tinyint', //添加的产品是否审核0不审核1审核
        'is_best'                        => 'tinyint', //是否推荐
        'is_bro_goods'                   => 'tinyint', //是否审核直播商品0不审核1审核
        'is_bro_room'                    => 'tinyint', //是否审核直播间0不审核1审核
        'is_del'                         => 'tinyint', //0未删除1删除
        'is_margin'                      => 'tinyint', //是否有保证金（0无，1有未支付，10已支付，-1 申请退款, -10 拒绝退款）
        'is_trader'                      => 'tinyint', //是否自营
        'lat'                            => 'varchar', //纬度
        'long'                           => 'varchar', //经度
        'margin'                         => 'decimal', //保证金
        'mark'                           => 'varchar', //商户备注
        'mer_address'                    => 'varchar', //商户地址
        'mer_avatar'                     => 'varchar', //商户头像
        'mer_banner'                     => 'varchar', //商户banner图片
        'mer_id'                         => 'int', //商户id
        'mer_info'                       => 'varchar', //店铺简介
        'mer_keyword'                    => 'varchar', //商户关键字
        'mer_money'                      => 'decimal', //商户余额
        'mer_name'                       => 'varchar', //商户名称
        'mer_phone'                      => 'varchar', //商户手机号
        'mer_state'                      => 'tinyint', //商户是否1开启0关闭
        'mini_banner'                    => 'varchar', //商户店店铺街图片
        'ot_margin'                      => 'decimal', //保证金额度
        'postage_score'                  => 'decimal', //物流评分
        'product_score'                  => 'decimal', //商品描述评分
        'real_name'                      => 'varchar', //商户姓名
        'reg_admin_id'                   => 'int', //总后台管理员ID
        'sales'                          => 'int', //销量
        'service_phone'                  => 'varchar', //店铺电话
        'service_score'                  => 'decimal', //服务评分
        'sort'                           => 'int', //
        'status'                         => 'tinyint', //商户是否禁用0锁定,1正常
        'sub_mchid'                      => 'varchar', //微信支付分配的分账号
        'type_id'                        => 'int', //店铺类型 id
        'update_time'                    => 'datetime', //
        'wechat_complaint_notify_status' => 'tinyint', //微信投诉回调状态，0关闭，1开启
        'wechat_complaint_notify_url'    => 'varchar', //微信商户号投诉回调地址，跟商户号id绑定，如果id更新需要更新

    ];

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'mer_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant';
    }

    public function getDeliveryWayAttr($value)
    {
        if (!$value) {
            return [];
        }

        return explode(',', $value);
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'mer_id', 'mer_id');
    }

    public function config()
    {
        return $this->hasMany(SystemConfigValue::class, 'mer_id', 'mer_id');
    }

    public function showProduct()
    {
        return $this->hasMany(Product::class, 'mer_id', 'mer_id')
            ->where((new ProductDao())->productShow())
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good')
            ->order('is_good DESC,sort DESC');
    }

    /**
     * TODO 商户列表下的推荐
     * @return \think\Collection
     * @author Qinii
     * @day 4/20/22
     */
    public function getAllRecommendAttr()
    {
        $list = Product::where('mer_id', $this['mer_id'])
            ->where((new ProductDao())->productShow())
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,cate_id')
            ->order('sort DESC, create_time DESC')
            ->limit(3)
            ->select()->append(['show_svip_info']);
        if ($list) {
            $data = [];
            $make = app()->make(StoreActivityRepository::class);
            foreach ($list as $item) {
                $spu_id             = Spu::where('product_id', $item->product_id)->where('product_type', 0)->value('spu_id');
                $act                = $make->getActivityBySpu(StoreActivityRepository::ACTIVITY_TYPE_BORDER, $spu_id, $item['cate_id'], $item['mer_id']);
                $item['border_pic'] = $act['pic'] ?? '';
                $data[]             = $item;
            }
            return $data;
        }
        return [];
    }

    public function getCityRecommendAttr()
    {
        $list = Product::where('mer_id', $this['mer_id'])
            ->where((new ProductDao())->productShow())
            ->whereLike('delivery_way', "%1%")
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,cate_id')
            ->order('sort DESC, create_time DESC')
            ->limit(3)
            ->select();
        if ($list) {
            $data = [];
            $make = app()->make(StoreActivityRepository::class);
            foreach ($list as $item) {
                $spu_id             = Spu::where('product_id', $item->product_id)->where('product_type', 0)->value('spu_id');
                $act                = $make->getActivityBySpu(StoreActivityRepository::ACTIVITY_TYPE_BORDER, $spu_id, $item['cate_id'], $item['mer_id']);
                $item['border_pic'] = $act['pic'] ?? '';
                $data[]             = $item;
            }
            return $data;
        }
        return [];
    }

    public function recommend()
    {
        return $this->hasMany(Product::class, 'mer_id', 'mer_id')
            ->where((new ProductDao())->productShow())
            ->where('is_good', 1)
            ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,sales,create_time')
            ->order('is_good DESC,sort DESC,create_time DESC')
            ->limit(3);
    }

    public function coupon()
    {
        $time = date('Y-m-d H:i:s');
        return $this->hasMany(StoreCouponUser::class, 'mer_id', 'mer_id')->where('start_time', '<', $time)->where('end_time', '>', $time)
            ->where('is_fail', 0)->where('status', 0)->order('coupon_price DESC, coupon_user_id ASC')
            ->with(['product' => function ($query) {
                $query->field('coupon_id,product_id');
            }, 'coupon' => function ($query) {
                $query->field('coupon_id,type');
            }]);
    }

    public function getServicesTypeAttr()
    {
        return merchantConfig($this->mer_id, 'services_type');
    }

    public function marginOrder()
    {
        return $this->hasOne(ServeOrder::class, 'mer_id', 'mer_id')->where('type', 10)->order('create_time DESC');
    }

    public function refundMarginOrder()
    {
        return $this->hasOne(Financial::class, 'mer_id', 'mer_id')
            ->where('type', 1)
            ->where('status', -1)
            ->order('create_time DESC')
            ->limit(1);
    }

    public function merchantCategory()
    {
        return $this->hasOne(MerchantCategory::class, 'merchant_category_id', 'category_id');
    }

    public function merchantType()
    {
        return $this->hasOne(MerchantType::class, 'mer_type_id', 'type_id');
    }

    public function typeName()
    {
        return $this->merchantType()->bind(['type_name']);
    }

    public function getMerCommissionRateAttr()
    {
        return $this->commission_rate > 0 ? $this->commission_rate : bcmul($this->merchantCategory->commission_rate, 100, 4);
    }

    public function getOpenReceiptAttr()
    {
        return merchantConfig($this->mer_id, 'mer_open_receipt');
    }

    public function admin()
    {
        return $this->hasOne(MerchantAdmin::class, 'mer_id', 'mer_id')->where('level', 0);
    }

    public function searchKeywordAttr($query, $value)
    {
        $query->whereLike('mer_name|mer_keyword', "%{$value}%");
    }

    public function getFinancialAlipayAttr($value)
    {
        return $value ? json_decode($value) : $value;
    }

    public function getFinancialWechatAttr($value)
    {
        return $value ? json_decode($value) : $value;
    }

    public function getFinancialBankAttr($value)
    {
        return $value ? json_decode($value) : $value;
    }

    public function getMerCertificateAttr()
    {
        return merchantConfig($this->mer_id, 'mer_certificate');
    }

    public function getIssetCertificateAttr()
    {
        return count(merchantConfig($this->mer_id, 'mer_certificate') ?: []) > 0;
    }

    public function searchMerIdsAttr($query, $value)
    {
        $query->whereIn('mer_id', $value);
    }

    /**
     * 关联主体信息
     *
     * @return \think\model\relation\HasMany
     */
    public function relatedBusiness()
    {
        return $this->hasMany(RelatedBusiness::class, 'mer_id', 'mer_id')
            ->order('id');
    }
}
