<?php


namespace app\validate\merchant\WechatComplaint;


use think\Validate;

class WechatComplaintResponseValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'response_content|回复文本' => 'require|max:200',
        'response_images|图片' => 'array|max:4', //array 内为 media_id
    ];
}
