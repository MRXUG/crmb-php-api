<?php


namespace app\validate\Elasticsearch;


use think\Validate;


/**
 * store_order table, now and should total same as eb_store_order in DB.
 * Class UserVisitLogValidate
 * @package app\validate\Elasticsearch
 */
class StoreOrderValidate extends Validate
{
    protected $failException = true;

    public static $tableIndexName = 'eb_store_order';

    protected $rule = [
        'order_id' => 'require|integer', //
        'main_id' => 'require|integer', //
        'group_order_id' => 'integer',    //
        'order_sn' => 'require|chsDash',  //
        'pay_order_sn' => 'chsDash', //

        'appid' => 'chsDash',
        'ad_id' => 'integer',
        'ad_channel_id' => 'integer',
        'uid' => 'integer',
        'spread_uid' => 'integer',

        'top_uid' => 'integer',
        'real_name' => '',
        'user_phone' => '',
        'user_address' => '', //
        'cart_id' => '',

        'total_num' => 'integer', //
        'total_price' => 'float', //
        'total_postage' => 'float', //
        'pay_price' => 'float', //
        'pay_postage' => 'float', //

        'is_selfbuy' => 'integer', //
        'extension_one' => 'float', //
        'extension_two' => 'float', //
        'commission_rate' => 'float|ignore', //
        'integral' => 'integer', //

        'integral_price' => 'float', //
        'give_integral' => 'integer', //
        'coupon_id' => '', //
        'coupon_price' => 'float', //
        'platform_coupon_price' => 'float', //

        'svip_discount' => 'float', //
        'order_type' => 'integer', //
        'platform_source' => 'integer', //
        'merchant_source' => 'integer', //
        'paid' => 'integer', // 支付状态

        'pay_time' => 'date', //
        'pay_type' => 'integer', //
        'create_time' => 'date', //
        'status' => 'integer', //
        'delivery_type' => '', //

        'is_virtual' => 'integer', //
        'delivery_name' => '', //
        'delivery_id' => '', //
        'mark' => '', //
        'remark' => '', //

        'admin_mark' => '', //
        'verify_code' => '', //
        'verify_time' => 'date', //
        'verify_service_id' => 'integer', //
        'transaction_id' => '', //

        'activity_type' => 'integer', //
        'order_extend' => '', //
        'system_commission' => '', // json,text
        'mer_id' => 'integer', //
        'reconciliation_id' => 'integer', //

        'cost' => 'float', //
        'is_del' => 'integer', //
        'is_system_del' => 'integer', //
        'verify_status' => 'integer', //
        'delivery_time' => 'date', //

        'finish_time' => 'date', //
        'stock_id' => '', //
        'coupon_code' => '', //
        'marketing_discount' => 'float', //
        'ad_query' => '', //chsDash varchar

        'update_time' => 'date', //

    ];

    public static function getFloatColumn(){
        return ['total_price', 'total_postage', 'pay_price', 'pay_postage', 'extension_one', 'extension_two', 'integral_price',
            'coupon_price', 'platform_coupon_price', 'svip_discount', 'cost', 'marketing_discount'];
    }
}
