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

namespace app\common\model\store\product;

use app\common\dao\store\StoreSeckillActiveDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponProduct;
use app\common\model\store\Guarantee;
use app\common\model\store\GuaranteeTemplate;
use app\common\model\store\GuaranteeValue;
use app\common\model\store\parameter\ParameterValue;
use app\common\model\store\shipping\PostageTemplateModel;
use app\common\model\store\shipping\ShippingTemplate;
use app\common\model\store\StoreBrand;
use app\common\model\store\StoreCategory;
use app\common\model\store\StoreSeckillActive;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\store\StoreCategoryRepository;
use crmeb\services\VicWordService;
use Darabonba\GatewaySpi\Models\InterceptorContext\request;
use think\db\BaseQuery;
use think\facade\Db;
use think\model\concern\SoftDelete;

class Product extends BaseModel
{
    use SoftDelete;

    protected $deleteTime = 'is_del';
    protected $defaultSoftDelete = 0;

    protected $schema = [
        'bar_code'              => 'varchar', //产品条码（一维码）/废弃
        'brand_id'              => 'int', //品牌 id/废弃
        'browse'                => 'int', //浏览量
        'care_count'            => 'int', //收藏数
        'cate_id'               => 'int', //分类id/废弃
        'code_path'             => 'varchar', //产品二维码地址(用户小程序海报)
        'cost'                  => 'decimal', //成本价
        'create_time'           => 'timestamp', //添加时间
        'delivery_free'         => 'int', //全国包邮
        'delivery_way'          => 'varchar', //1.仅到店自提2快递计价配送3全国包邮
        'extend'                => 'varchar', //扩展信息
        'extension_type'        => 'tinyint', //佣金比例 0.系统，1.自定义
        'ficti'                 => 'mediumint', //虚拟销量
        'give_coupon_ids'       => 'varchar', //赠送优惠券
        'goods_desc'            => 'text', //商品详情
        'guarantee'             => 'tinyint', //购物保障：0-不展示，1-展示
        'guarantee_template_id' => 'int', //保障服务模板
        'image'                 => 'varchar', //商品图片
        'integral_price_total'  => 'decimal', //使用积分抵扣金额总数
        'integral_rate'         => 'int', //积分抵扣比例
        'integral_total'        => 'int', //使用积分抵扣总数
        'is_benefit'            => 'tinyint', //促销单品
        'is_best'               => 'tinyint', //是否精品
        'is_del'                => 'tinyint', //是否删除
        'is_gift_bag'           => 'tinyint', //是否为礼包
        'is_good'               => 'tinyint', //是否优品推荐
        'is_hot'                => 'tinyint', //是否热卖
        'is_new'                => 'tinyint', //是否新品
        'is_show'               => 'tinyint', //商户 状态（0：未上架，1：上架）
        'is_used'               => 'int', //显示/隐藏
        'keyword'               => 'varchar', //关键字/废弃
        'labels'                => 'varchar', //标签id
        'mer_id'                => 'int', //商户Id
        'mer_status'            => 'tinyint', //商铺状态是否 1.正常 0. 非正常
        'mer_svip_status'       => 'tinyint', //商户会员状态
        'old_product_id'        => 'int', //原商品ID
        'once_max_count'        => 'int', //订单单次购买数量最大限制
        'once_min_count'        => 'int', //单次购买最低限购
        'ot_price'              => 'decimal', //原价
        'param_temp_id'         => 'varchar', //参数模板ID
        'pay_limit'             => 'tinyint', //购买总数限制 0:不限购，1单次限购 2 长期限购
        'price'                 => 'decimal', //最低价格
        'product_id'            => 'int', //商品id
        'product_type'          => 'tinyint', //0.普通商品 1.秒杀商品,2.预售商品，3.助力商品，4.拼团商品
        'rank'                  => 'smallint', //总后台排序
        'rate'                  => 'decimal', //评价分数
        'refusal'               => 'varchar', //审核拒绝理由
        'reply_count'           => 'int', //评论数
        'sales'                 => 'mediumint', //销量
        'sell_point'            => 'varchar', //商品卖点
        'short_title'           => 'varchar', //商品短标题
        'slider_image'          => 'varchar', //轮播图
        'sort'                  => 'smallint', //排序
        'spec_type'             => 'tinyint', //规格 0单 1多 /单规格移除
        'status'                => 'tinyint', //管理员 状态（0：审核中，1：审核通过 -1: 未通过 -2: 下架）
        'stock'                 => 'int', //总库存/废弃
        'store_info'            => 'varchar', //商品简介 / 修改为内部备注作用
        'store_name'            => 'varchar', //商品名称
        'svip_price'            => 'decimal', //会员价
        'svip_price_type'       => 'tinyint', //0不参加，1默认比例，2自定义
        'temp_id'               => 'int', //运费模板ID
        'postage_template_id'   => 'int', //新运费模板ID
        'type'                  => 'tinyint', //0.实体商品，1.虚拟商品
        'unit_name'             => 'varchar', //单位名/废弃
        'video_link'            => 'varchar', //主图视频链接/废弃

    ];
    
    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tablePk(): string
    {
        return 'product_id';
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tableName(): string
    {
        return 'store_product';
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 属性
     * -----------------------------------------------------------------------------------------------------------------
    */
    public function getSliderImageAttr($value)
    {
        return $value ? explode(',',$value) : [];
    }
    public function getGoodsDescAttr($value)
    {
        return $value ? explode(',',$value) : [];
    }
    public function getGiveCouponIdsAttr($value)
    {
        return $value ? explode(',',$value) : [];
    }
    public function getMaxExtensionAttr($value)
    {
        if($this->extension_type){
            $org_extension =  ($this->attrValue()->order('extension_two DESC')->value('extension_one'));
        } else {
            $org_extension = bcmul(($this->attrValue()->order('price DESC')->value('price')) , systemConfig('extension_one_rate'),2);
        }
        $spreadUser = (request()->hasMacro('isLogin') && request()->isLogin() &&  request()->userType() == 1  ) ? request()->userInfo() : null;
        if ($spreadUser && $spreadUser->brokerage_level > 0 && $spreadUser->brokerage && $spreadUser->brokerage->extension_one_rate > 0) {
            $org_extension = bcmul($org_extension, 1 + $spreadUser->brokerage->extension_one_rate, 2);
        }
        return $org_extension;
    }
    public function getMinExtensionAttr($value)
    {
        if($this->extension_type){
            $org_extension = ($this->attrValue()->order('extension_two ASC')->value('extension_two'));
        } else {
            $org_extension = bcmul(($this->attrValue()->order('price ASC')->value('price')) , systemConfig('extension_one_rate'),2);
        }
        $spreadUser = (request()->hasMacro('isLogin') && request()->isLogin() &&  request()->userType() == 1 ) ? request()->userInfo() : null;
        if ($spreadUser && $spreadUser->brokerage_level > 0 && $spreadUser->brokerage && $spreadUser->brokerage->extension_one_rate > 0) {
            $org_extension = bcmul($org_extension, 1 + $spreadUser->brokerage->extension_one_rate, 2);
        }
        return $org_extension;
    }

    public function check()
    {
        if(!$this || !$this->is_show || !$this->is_used || !$this->status || $this->is_del || !$this->mer_status) return false;
        return true;
    }

    /**
     * TODO 秒杀商品结束时间
     * @return false|int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getEndTimeAttr()
    {
        if($this->product_type !== 1) return true;
        $day = date('Y-m-d',time());
        $_day = strtotime($day);
        $end_day = strtotime($this->seckillActive['end_day']);
        if($end_day >= $_day)
            return strtotime($day.$this->seckillActive['end_time'].':00:00');
        if($end_day < strtotime($day))
            return strtotime(date('Y-m-d',$end_day).$this->seckillActive['end_time'].':00:00');
    }

    /**
     * TODO 秒杀商品状态
     * @return array|int
     * @author Qinii
     * @day 2020-08-19
     */
    public function getSeckillStatusAttr()
    {
        if($this->product_type !== 1) return true;
        $day = strtotime(date('Y-m-d',time()));
        $_h = date('H',time());
        $start_day = strtotime($this->seckillActive['start_day']);
        $end_day = strtotime($this->seckillActive['end_day']);
        if(!$this->seckillActive) return '';
        if($this->seckillActive['status'] !== -1){
            //还未开始
            if($start_day > time() || $this->is_show !== 1)return 0;
            //已结束
            if($end_day < $day) return -1;
            //开始 - 结束
            if($start_day <= $day && $day <= $end_day){
                //未开始
                if($this->seckillActive['start_time'] > $_h) return 0;
                //已结束
                if($this->seckillActive['end_time'] <= $_h) return -1;
                //进行中
                if($this->seckillActive['start_time'] <= $_h && $this->seckillActive['end_time'] > $_h) return 1;
            }
        }
        //已结束
        return -1;

    }

    public function getImageAttr($value)
    {
        if (is_int(strpos($value, 'http'))){
            return $value;
        }else{
            return rtrim(systemConfig('site_url'),'/') .$value;
        }
    }

    public function getTopReplyAttr()
    {
        $res = ProductReply::where('product_id',$this->product_id)->where('is_del',0)->with(['orderProduct'])->field('reply_id,uid,nickname,merchant_reply_content,avatar,order_product_id,product_id,product_score,service_score,postage_score,comment,pics,rate,create_time')
            ->order('sort DESC,create_time DESC')->limit(1)->find();
        if(!$res) return null;
        if ($res['orderProduct'])
            $res['sku'] = $res['orderProduct']['cart_info']['productAttr']['sku'];
        unset($res['orderProduct']);
        if (strlen($res['nickname']) > 1) {
            $str = mb_substr($res['nickname'],0,1) . '*';
            if (strlen($res['nickname']) > 2) {
                $str .= mb_substr($res['nickname'], -1,1);
            }
            $res['nickname'] = $str;
        }

        return $res;
    }

    public function getUsStatusAttr()
    {
        return ($this->status == 1) ? ($this->is_used == 1 ? ( $this->is_show ? 1 : 0 ) : -1) : -1;
    }

    public function getGuaranteeTemplateAttr()
    {
        $gua = GuaranteeTemplate::where('guarantee_template_id',$this->guarantee_template_id)->where('status',1)->where('is_del',0)->find();
        if(!$gua) return [];
        $guarantee_id = GuaranteeValue::where('guarantee_template_id',$this->guarantee_template_id)->column('guarantee_id');
        return Guarantee::where('guarantee_id','in',$guarantee_id)->where('status',1)->where('is_del',0)->select();
    }

    public function getMaxIntegralAttr()
    {
        if(systemConfig('integral_status') && merchantConfig($this->mer_id,'mer_integral_status')){
            $price = ($this->attrValue()->order('price DESC')->value('price'));
            $rate = ($this->integral_rate < 0) ? merchantConfig($this->mer_id,'mer_integral_rate') : $this->integral_rate;
            $rate = $rate < 0 ? $rate / 100 : 0;
            return bcmul($price ,$rate,2);
        }
        return '0';
    }

    public function getHotRankingAttr()
    {
        if ($this->product_type == 0) {
            $where = [
                'is_show'       => 1,
                'status'        => 1,
                'is_used'       => 1,
                'product_type'  => 0,
                'mer_status'    => 1,
                'is_gift_bag'   => 0,
                'cate_id' => $this->cate_id
            ];
            self::where($where)->order('sales DESC');
        }
    }

    /**
     * TODO 商品参数
     * @author Qinii
     * @day 2022/11/24
     */
    public function getParamsAttr()
    {
        if(in_array($this->product_type,[0,2])) {
            $product_id = $this->product_id;
        } else {
            $product_id = $this->old_product_id;
        }
        return ParameterValue::where('product_id',$product_id)->order('parameter_value_id ASC')->select();
    }

    public function getParamTempIdAttr($value)
    {
        return $value ? explode(',',$value) : $value;
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *  关联模型
     * -----------------------------------------------------------------------------------------------------------------
    */
    public function merCateId()
    {
        return $this->hasMany(ProductCate::class,'product_id','product_id')->field('product_id,mer_cate_id');
    }
    public function attr()
    {
        return $this->hasMany(ProductAttr::class,'product_id','product_id');
    }
    public function attrValue()
    {
        return $this->hasMany(ProductAttrValue::class,'product_id','product_id');
    }
    public function oldAttrValue()
    {
        return $this->hasMany(ProductAttrValue::class,'product_id','old_product_id');
    }
    public function content()
    {
        return $this->hasOne(ProductContent::class,'product_id','product_id');
    }
    protected function temp()
    {//老运费模板 可废弃
        return $this->hasOne(ShippingTemplate::class,'shipping_template_id','temp_id');
    }

    //新运费模板
    protected function postageTemplate()
    {
        return $this->hasOne(PostageTemplateModel::class,'id','postage_template_id');
    }
    public function storeCategory()
    {
        return $this->hasOne(StoreCategory::class,'store_category_id','cate_id')->field('store_category_id,cate_name');
    }
    public function merchant()
    {
        return $this->hasOne(Merchant::class,'mer_id','mer_id')->field('is_trader,type_id,mer_id,mer_name,mer_avatar,product_score,service_score,postage_score,service_phone,care_count');
    }
    public function reply()
    {
        return $this->hasMany(ProductReply::class,'product_id','product_id')->order('create_time DESC');
    }
    public function brand()
    {
        return $this->hasOne(StoreBrand::class,'brand_id','brand_id')->field('brand_id,brand_name');
    }
    public function seckillActive()
    {
        return $this->hasOne(StoreSeckillActive::class,'product_id','product_id');
    }
    public function issetCoupon()
    {
        return $this->hasOne(StoreCouponProduct::class, 'product_id', 'product_id')->alias('A')
            ->rightJoin('StoreCoupon B', 'A.coupon_id = B.coupon_id')->where(function (BaseQuery $query) {
                $query->where('B.is_limited', 0)->whereOr(function (BaseQuery $query) {
                    $query->where('B.is_limited', 1)->where('B.remain_count', '>', 0);
                });
            })->where(function (BaseQuery $query) {
                $query->where('B.is_timeout', 0)->whereOr(function (BaseQuery $query) {
                    $time = date('Y-m-d H:i:s');
                    $query->where('B.is_timeout', 1)->where('B.start_time', '<', $time)->where('B.end_time', '>', $time);
                });
            })->field('A.product_id,B.*')->where('status', 1)->where('type', 1)->where('send_type', 0)->where('is_del', 0)
            ->order('sort DESC,coupon_id DESC')->hidden(['is_del', 'status']);
    }
    public function assist()
    {
        return $this->hasOne(ProductAssist::class,'product_id','product_id');
    }
    public function productGroup()
    {
        return $this->hasOne(ProductGroup::class,'product_id','product_id');
    }
    public function guarantee()
    {
        return $this->hasOne(GuaranteeTemplate::class,'guarantee_template_id','guarantee_template_id')->where('status',1)->where('is_del',0);
    }


    /**
     * TODO 是否是会员
     * @return bool
     * @author Qinii
     * @day 2023/1/4
     */
    public function getIsVipAttr()
    {
        if (request()->hasMacro('isLogin') && request()->isLogin()) {
            if (request()->userType() == 1) {
                $userInfo = request()->userInfo();
                return $userInfo->is_svip ? true : false;
            } else {
                return true;
            }
        }
        return  false;
    }
    /**
     * TODO 是否展示会员价
     * @return bool
     * @author Qinii
     * @day 2023/1/4
     */
    public function getShowSvipPriceAttr()
    {
        if ($this->mer_svip_status != 0 && (systemConfig('svip_show_price') != 1 || $this->is_vip) && $this->svip_price_type > 0 ) {
            return true;
        }
        return false;
    }


    /**
     * TODO 是否显示会员价等信息
     * @return array
     * @author Qinii
     * @day 2022/11/24
     */
    public function getShowSvipInfoAttr()
    {
        $res = [
            'show_svip' => true, //是否展示会员入口
            'is_svip' => false,  //当前用户是否是会员
            'show_svip_price' => false, //是否展示会员价
            'save_money' => 0, //当前商品会员优化多少钱
        ];
        if ($this->product_type == 0) {
            if (!systemConfig('svip_switch_status')) {
                $res['show_svip'] = false;
            } else {
                $res['is_svip'] = $this->is_vip;
                if ($this->show_svip_price) {
                    $res['show_svip_price'] = true;
                    $res['save_money'] = bcsub($this->price, $this->svip_price, 2);
                }
            }
        }
        return  $res;
    }

    /**
     * TODO 获取会员价
     * @return int|string
     * @author Qinii
     * @day 2023/1/4
     */
    public function getSvipPriceAttr()
    {
        if ($this->product_type == 0 && $this->mer_svip_status != 0 && $this->show_svip_price) {
            //默认比例
            if ($this->svip_price_type == 1) {
                $rate = merchantConfig($this->mer_id,'svip_store_rate');
                $svip_store_rate = $rate > 0 ? bcdiv($rate,100,2) : 0;
                $price = $this->attrValue()->order('price ASC')->value('price');
                return bcmul($price,$svip_store_rate,2);
            }
            //自定义
            if ($this->svip_price_type == 2) {
                return $this->getData('svip_price');
            }
        }
        return 0;
    }


    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 搜索器
     * -----------------------------------------------------------------------------------------------------------------
     */
    public function searchMerCateIdAttr($query, $value)
    {
        $cate_ids = (StoreCategory::where('path','like','%/'.$value.'/%'))->column('store_category_id');
        $cate_ids[] = intval($value);
        $product_id = ProductCate::whereIn('mer_cate_id',$cate_ids)->column('product_id');
        $query->whereIn('Product.product_id',$product_id);
    }
    public function searchKeywordAttr($query, $value)
    {
        if (!$value) return;
        if (is_numeric($value)) {
            $query->whereLike("Product.store_name|Product.keyword|bar_code|Product.product_id", "%{$value}%");
        } else {
            $word = app()->make(VicWordService::class)->getWord($value);
            $query->where(function ($query) use ($word, $value) {
                foreach ($word as $item) {
                    $query->whereOr('Product.store_name|Product.keyword', 'LIKE', "%$item%");
                }
                $query->order(Db::raw('REPLACE(Product.store_name,\'' . $value . '\',\'\')'));
            });
        }
    }
    public function searchStatusAttr($query, $value)
    {
        if($value === -1){
            $query->where('Product.status', 'in',[-1,-2]);
        }else {
            $query->where('Product.status',$value);
        }
    }
    public function searchCateIdAttr($query, $value)
    {
        $query->where('cate_id',$value);
    }
    public function searchCateIdsAttr($query, $value)
    {
        $query->whereIn('cate_id',$value);
    }
    public function searchIsShowAttr($query, $value)
    {
        $query->where('is_show',$value);
    }
    public function searchPidAttr($query, $value)
    {
        $cateId = app()->make(StoreCategoryRepository::class)->allChildren(intval($value));
        $query->whereIn('cate_id', $cateId);
    }
    public function searchStockAttr($query, $value)
    {
        $value ? $query->where('stock','<=', $value) :  $query->where('stock', $value);
    }
    public function searchIsNewAttr($query, $value)
    {
        $query->where('is_new',$value);
    }
    public function searchPriceAttr($query, $value)
    {
        if(empty($value[0]) && !empty($value[1]))
            $query->where('price','<',$value[1]);
        if(!empty($value[0]) && empty($value[1]))
            $query->where('price','>',$value[0]);
        if(!empty($value[0]) && !empty($value[1]))
            $query->whereBetween('price',[$value[0],$value[1]]);
    }
    public function searchBrandIdAttr($query, $value)
    {
        $query->whereIn('brand_id',$value);
    }
    public function searchIsGiftBagAttr($query, $value)
    {
        $query->where('is_gift_bag',$value);
    }
    public function searchIsGoodAttr($query, $value)
    {
        $query->where('is_good',$value);
    }
    public function searchIsUsedAttr($query, $value)
    {
        $query->where('is_used',$value);
    }
    public function searchProductTypeAttr($query, $value)
    {
        $query->where('Product.product_type',$value);
    }
    public function searchSeckillStatusAttr($query, $value)
    {
        $product_id = (new StoreSeckillActiveDao())->getStatus($value)->column('product_id');
        $query->whereIn('Product.product_id',$product_id);
    }
    public function searchStoreNameAttr($query, $value)
    {
        $query->where('Product.store_name','like','%'.$value.'%');
    }
    public function searchMerStatusAttr($query, $value)
    {
        $query->where('mer_status',$value);
    }
    public function searchProductIdAttr($query, $value)
    {
        $query->where('Product.product_id',$value);
    }
    public function searchPriceOnAttr($query, $value)
    {
        $query->where('price','>=',$value);
    }
    public function searchPriceOffAttr($query, $value)
    {
        $query->where('price','<=',$value);
    }
    public function searchisFictiAttr($query, $value)
    {
        $query->where('type',$value);
    }
    public function searchGuaranteeTemplateIdAttr($query, $value)
    {
        $query->whereIn('guarantee_template_id',$value);
    }
    public function searchTempIdAttr($query, $value)
    {
        $query->whereIn('Product.postage_temp_id',$value);
    }
}
