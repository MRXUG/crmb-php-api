<?php


namespace app\validate\merchant\WechatComplaint;


use think\Validate;

class WechatComplaintResponseValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'response_content|回复文本' => 'require|max:200',
        'response_images|图片' => 'array',
    ];

    protected function checkTime($value)
    {
        $start = strtotime($value[0]);
        $end = strtotime($value[1]);
        if ($end < $start) return '请选择正确的直播时间';
        if ($start < strtotime('+ 15 minutes')) return '开播时间必须大于当前时间15分钟';
        if ($start >= strtotime('+ 6 month')) return '开播时间不能在6个月后';
        if (($end - $start) < (60 * 30)) return '直播时间不得小于30分钟';
        if (($end - $start) > (60 * 60 * 24)) return '直播时间不得超过24小时';
        return true;
    }
}
