<?php
namespace crmeb\services\ads\action;

use crmeb\services\ads\BaseAdsEvent;

//内容浏览 关键页面浏览
class ViewContent extends BaseAdsEvent {

    public function __construct($appid,$unionid,$clickid)
    {
        parent::__construct($appid,$unionid,$clickid);
    }

    public function requestActionParams(){
        return [
            ['action_type'=>'VIEW_CONTENT','action_time'=>time(),'action_param'=>['object'=>'product']],
            ['action_type'=>'AD_CLICK','action_time'=>time()],
            ['action_type'=>'AD_IMPRESSION','action_time'=>time()]
        ];
    }
    
}