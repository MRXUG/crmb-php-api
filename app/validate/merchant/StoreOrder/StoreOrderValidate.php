<?php


namespace app\validate\merchant\StoreOrder;


use think\Validate;

class StoreOrderValidate extends Validate
{

    protected $failException = true;

    protected $rule = [
        'received_name|收货人姓名' => 'chsDash',
        'received_phone|收货人手机号' => 'mobile',
        'buyer_name|订单编号' => 'chsDash',
        'buyer_phone|购买者手机号' => 'mobile',
        'search_Pid|产品id' => 'integer',
        'logistics_anomaly|物流状态' => 'chsDash', // 暂未使用
        'order_scenario|下单场景' => 'in|-1,0,1,2,3,4,5,6,7',
        'saleStatus|售后状态' => 'in:1,2,3,4',
    ];

}