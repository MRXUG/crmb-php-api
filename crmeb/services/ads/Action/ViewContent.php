<?php
namespace crmeb\services\ads\Action;

use crmeb\services\ads\BaseAdsEvent;

//内容浏览 关键页面浏览
class ViewContent extends BaseAdsEvent {

    public function __construct($appid,$unionid,$clickid)
    {
        parent::__construct($appid,$unionid,$clickid);
    }

    public function requestActionParams(){
        if(!$this->timestamp){
            $this->timestamp = time();
        }
        return [
            ['action_type'=>'VIEW_CONTENT','action_time'=>$this->timestamp,'action_param'=>['object'=>'product']],
            ['action_type'=>'AD_CLICK','action_time'=>$this->timestamp],
            ['action_type'=>'AD_IMPRESSION','action_time'=>$this->timestamp]
        ];
    }
    
}