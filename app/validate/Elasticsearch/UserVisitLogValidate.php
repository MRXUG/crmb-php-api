<?php


namespace app\validate\Elasticsearch;


use think\Validate;


/**
 * user_visit_log
 * Class UserVisitLogValidate
 * @package app\validate\Elasticsearch
 */
class UserVisitLogValidate extends Validate
{
    protected $failException = true;

    public static $tableIndexName = 'user_visit_log';

    public static $WxAppletUserType = 1;

    protected $rule = [
        'user_type|用户类型' => 'require|integer', // 1:微信小程序 applet，2:微商城, 3:h5, if more, update here
        'uid|用户id' => 'require|integer', // === model\user\User()->tablePk(). db uid.
        'mer_id|当前商户id' => 'integer',    //  === model\system\merchant\Merchant()->tablePk(). db mer_id.
        'product_id|当前商品id' => 'integer',  // === model\store\product\Product()->tablePk(). db product_id.
        'app_id' => 'require|chsDash', // 小程序appid  or others
        'account' => 'chsDash',
        'open_id' => 'chsDash',
        'union_id' => 'chsDash',
        'avatar' => 'url',
        'nick_name' => 'chsDash',
//        'ip' => 'ip',

        'visit_time|访问时间' => 'require|date|dateFormat:Y-m-d H:i:s',
        'visit_page' => 'require', //
        'source_from_type' => 'require|chsDash', // deeplink--广告链接, index--首页/自然流量
        'source_from_link' => 'require', // deeplink ,or 点击内部页面跳转的上一页面 地址
    ];
}
