<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace crmeb\listens\pay;


use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

class OrderPaySuccessListen implements ListenerInterface
{

    public function handle($data): void
    {
        /**
         * {
            "order_sn": "wx1678514540116975741_901643de",
            "data": {
                "appid": "wxa1a20dd8329ac0bc",
                "attach": "order",
                "bank_type": "CMB_DEBIT",
                "cash_fee": "2",
                "fee_type": "CNY",
                "is_subscribe": "N",
                "mch_id": "1638941761",
                "nonce_str": "640c196c24db9",
                "openid": "orriu4jjzKvinujP81byIeuTsSLw",
                "out_trade_no": "wx1678514540116975741_901643de",
                "result_code": "SUCCESS",
                "return_code": "SUCCESS",
                "sign": "3E9C5F35CB60356E1ED33B9A31CD7E75",
                "time_end": "20230311140229",
                "total_fee": "2",
                "trade_type": "JSAPI",
                "transaction_id": "4200001735202303118497488484"
            }
        }
         */
        // $data['order_sn'] 是拚了随机数的sn
        $data['data']['pay_order_sn'] = $data['order_sn'];

        $outTradeNoArr = explode("_", $data['order_sn']);
        $orderSn = $data['order_sn'] = $outTradeNoArr[0];

        $is_combine = $data['is_combine'] ?? 0;
        $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere(['group_order_sn' => $orderSn]);
        if (!$groupOrder || $groupOrder->paid == 1) {
            return;
        }

        $orders = [];
        if ($is_combine) {
            foreach ($data['data']['sub_orders'] as $order) {
                $orders[$order['out_trade_no']] = $order;
            }
        }else{
            $orders[$orderSn] = $data['data'];
        }
        /* @var StoreOrderRepository $repo */
        $repo = app()->make(StoreOrderRepository::class);
        $repo->paySuccess($groupOrder, $is_combine, $orders);
    }
}

