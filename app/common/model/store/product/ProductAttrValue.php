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

use app\common\model\BaseModel;

class ProductAttrValue extends BaseModel
{

    protected $schema = [
        'bar_code'      => 'varchar', //产品条码
        'cost'          => 'decimal', //成本价
        'detail'        => 'varchar', //
        'extension_one' => 'decimal', //一级佣金
        'extension_two' => 'decimal', //二级佣金
        'image'         => 'varchar', //图片
        'ot_price'      => 'decimal', //原价
        'price'         => 'decimal', //价格
        'product_id'    => 'int', //商品ID
        'sales'         => 'int', //销量
        'sku'           => 'varchar', //商品属性索引值 (attr_value|attr_value[|....])
        'stock'         => 'int', //属性对应的库存
        'svip_price'    => 'decimal', //会员价
        'type'          => 'tinyint', //活动类型 0=商品
        'unique'        => 'char', //唯一值
        'volume'        => 'decimal', //体积
        'weight'        => 'decimal', //重量
    ];
    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tablePk(): string
    {
        return '';
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tableName(): string
    {
        return 'store_product_attr_value';
    }

    public function getDetailAttr($value)
    {
        return json_decode($value);
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id','product_id');
    }

    public function getSvipPriceAttr()
    {
        if ($this->product->product_type == 0 && $this->product->show_svip_price && $this->product->svip_price_type == 1) {
            $rate = merchantConfig($this->product->mer_id,'svip_store_rate');
            $svip_store_rate = $rate > 0 ? bcdiv($rate,100,2) : 0;
            return bcmul($this->price, $svip_store_rate,2);
        }
        return $this->getData('svip_price');
    }

    public function getBcExtensionOneAttr()
    {
        if(!intval(systemConfig('extension_status')))  return 0;
        if($this->extension_one > 0)  return $this->extension_one;
        return floatval(round(bcmul(systemConfig('extension_one_rate'), $this->price, 3),2));
    }

    public function getBcExtensionTwoAttr()
    {
        if(!intval(systemConfig('extension_status')))  return 0;
        if($this->extension_two > 0)  return $this->extension_two;
        return floatval(round(bcmul(systemConfig('extension_two_rate'), $this->price, 3),2));
    }

    public function productSku()
    {
        return $this->hasOne(ProductSku::class, 'unique', 'unique');
    }

    public function searchUniqueAttr($query,$value)
    {
        $query->where('unique',$value);
    }

    public function searchSkuAttr($query,$value)
    {
        $query->where('sku',$value);
    }

    public function searchProductIdAttr($query,$value)
    {
        $query->where('product_id',$value);
    }


}
