<?php


namespace app\validate\merchant;


use think\Validate;

class DashboardValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'start_date|开始时间' => 'require|date|dateFormat:Y-m-d',
        'end_date|结束时间' => 'require|date|dateFormat:Y-m-d|before:now|after:start_date',
    ];
}
