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

namespace app\common\model\user;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrderProduct;
use app\common\model\store\order\StoreOrder;


class Feedback extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'feedback_id';
    }

    public static function tableName(): string
    {
        return 'feedback';
    }

    public function getImagesAttr($val)
    {
        return $val ? explode(",", $val) : [];
    }

    public function setImagesAttr($val)
    {
        return $val ? implode(",", $val) : '';
    }

    public function getUpdateTimeAttr($value)
    {
        if ($value) return date('m月d日',strtotime($value));
        return $value;
    }

    public function user()
    {
        return $this->hasOne(User::class,'uid','uid');
    }

    public function type()
    {
        return $this->hasOne(FeedBackCategory::class,'feedback_category_id','type');
    }
    
    public function orderProduct(){
        return $this->hasOne(StoreOrderProduct::class,'order_id','order_id');
    }
    
    public function orderInfo(){
        return $this->hasOne(StoreOrder::class,'order_id','order_id');
    }
}
