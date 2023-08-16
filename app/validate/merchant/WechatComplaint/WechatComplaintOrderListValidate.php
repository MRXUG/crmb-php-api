<?php


namespace app\validate\merchant\WechatComplaint;


use think\Validate;

class WechatComplaintOrderListValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'complaint_id|投诉单号' => 'chsDash',
        'transaction_id|微信支付单号' => 'chsDash', //微信订单号
        'out_trade_no|订单编号' => 'chsDash',
        'problem_type|投诉类型' => 'in:0,1,2,3', // 0 全部, 1 申请退款 REFUND, 2 SERVICE_NOT_WORK, 3 OTHERS
        'complaint_state|投诉状态' => 'in:0,1,2,3', // 0 全部 1 PENDING 2.PROCESSING 3.PROCESSED
        'timeout_type|超时状态' => 'in:0,1,2', //0 全部，1 未超时: 待处理距投诉时间小于24小时，处理中距投诉时间小于72小时,2 已超时 所有待处理距投诉时间大于24小时，处理中距投诉时间大于72小时
        'begin_time|投诉开始时间' => 'date|dateFormat:Y-m-d H:i:s',
        'end_time|投诉结束时间' => 'date|dateFormat:Y-m-d H:i:s',
        'page' => 'require|integer',
        'limit' => 'require|integer',
    ];
}
