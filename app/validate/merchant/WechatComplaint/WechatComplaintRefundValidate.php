<?php


namespace app\validate\merchant\WechatComplaint;


use think\Validate;

class WechatComplaintRefundValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'action|审批动作' => 'require|in:REJECT,APPROVE',
        'launch_refund_day|预计退款时间' => 'requireIf:action,APPROVE|integer',
        'remark|备注' => 'max:200',
        'reject_reason|拒绝原因' => 'requireIf:action,REJECT|max:200',
        'reject_media_list|举证图片' => 'array|max:4',
    ];
}
