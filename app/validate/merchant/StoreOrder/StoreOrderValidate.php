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
        'order_scenario|下单场景' => 'integer', // -1 全部 对应营销场景 0广告初始化，1领取回流券弹窗 2.商品详情页优惠弹窗 4.营销裂变优惠
        // 50. 多级回流-一级 51.多级回流-二级 52.多级回流-三级 6.支付失败优惠
        'saleStatus|售后状态' => 'in:1,2,3,4',
        'delivery_id|快递单号' => 'chsDash',
        'pay_type|支付类型' => 'in:0,1,2,3,4,5,6',
        'sku_code|规格编码'=> 'chsDash',
    ];

}