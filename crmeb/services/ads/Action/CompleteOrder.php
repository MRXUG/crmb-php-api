<?php
namespace crmeb\services\ads\Action;

use crmeb\services\ads\BaseAdsEvent;

//下单
class CompleteOrder extends BaseAdsEvent {

    protected $value = 0;
    
    public function __construct($appid,$unionid,$clickid,$value)
    {
        parent::__construct($appid,$unionid,$clickid);
        $this->value= $value;
    }

    public function requestActionParams(){
        if(!$this->timestamp){
            $this->timestamp = time();
        }
        return [
            ['action_type'=>'COMPLETE_ORDER','action_time'=>$this->timestamp,'action_param'=>['value'=>intval($this->value*100)]],
        ];
    }
    
}