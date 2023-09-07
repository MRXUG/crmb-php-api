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

namespace app\validate\merchant;

use think\Exception;
use think\File;
use think\Validate;

class StoreProductValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        "image|主图" => 'require|max:128',
        "slider_image|轮播图" => 'require|array',
        "goods_desc|商品详情" => 'require|array',
        "store_name|商品名称" => 'require|max:128',
        "spec_type" => "in:0,1",
        "is_show｜是否上架" => "in:0,1",
        "attr|商品规格" => "requireIf:spec_type,1|Array|checkUnique",
        "attrValue|商品属性" => "require|array|productAttrValue",
        'once_min_count|最小限购' => 'min:0',
        'pay_limit|是否限购' => 'require|in:0,1,2|payLimit',
        'guarantee_type|购物保障' => 'in:0,1',
    ];

    protected function payLimit($value,$rule,$data)
    {
        if ($value && ($data['once_max_count'] < $data['once_min_count']))
           return '限购数量不能小于最少购买件数';
        return true;
    }

    protected function productAttrValue($value,$rule,$data)
    {
        $arr = [];
        try{
            foreach ($value as $v){
                $sku = '';
                if(isset($v['detail']) && is_array($v['detail'])){
                    sort($v['detail'],SORT_STRING);
                    $sku = implode(',',$v['detail']);
                }
                if(in_array($sku,$arr)) return '商品SKU重复';
                $arr[] = $sku;
            }
        } catch (\Exception $exception) {
            return '商品属性格式错误';
        }
        return true;
    }

    protected function checkUnique($value)
    {
        $arr = [];
       foreach ($value as $item){
           if(in_array($item['value'],$arr)) return '规格重复';
           $arr[] = $item['value'];
           if (count($item['detail']) != count(array_unique($item['detail']))) return '属性重复';
       }
       return true;
    }
}
