<?php


namespace app\validate\merchant\StoreOrder;


use think\Validate;

class StoreOrderValidate extends Validate
{

    protected $failException = true;

    protected $rule = [
        //追加字段搜索 不包含全部
        'received_name|收货人姓名' => 'chsDash',
        'received_phone|收货人手机号' => 'chsDash',
        'buyer_name|订单编号' => 'chsDash',
        'buyer_phone|购买者手机号' => 'chsDash',
        'search_Pid|产品id' => 'integer',
        'logistics_anomaly|物流状态' => 'chsDash', // 暂未使用
        'order_scenario|下单场景' => 'in:-1,0,1,2,3,4,5,6,7',
        'saleStatus|售后状态' => 'in:1,2,3,4',
        'delivery_id|快递单号' => 'chsDash',
        'pay_type|支付类型' => 'in:0,1,2,3,4,5,6',
        'sku_code|规格编码'=> 'chsDash',
    ];

}