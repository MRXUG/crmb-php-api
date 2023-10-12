<?php


namespace crmeb\jobs\Erp\JushuiTan;


use app\common\model\erp\JuShuiTan\OrderInJuShuiTan;
use app\common\model\erp\JuShuiTanAuthorizeConfig;
use app\common\model\store\order\StoreOrder;
use crmeb\interfaces\JobInterface;
use crmeb\services\erp\JuShuiTan\Api\ApiRequest;

class OrderPutJob implements JobInterface
{

    public function fire($job, $data)
    {
        $this->deal();
    }

    public static function deal(){
        $configs = JuShuiTanAuthorizeConfig::getInstance()->select()->toArray();
        $addressRegex = '/(.*?(省|自治区|行政区|市))+(.*?(自治州|地区|行政单位|盟|市辖区|市|县))+(.*?(县|区|市|旗|海域|岛))/';
        foreach ($configs as $config){
            $merId = $config["mer_id"];
            $max = 50;
            $beginDate = date('Y-m-d H:i:s', strtotime("-1 day"));
            $orderInfo = StoreOrder::getInstance()
                ->where("mer_id", $merId)
                ->where('update_time','>', $beginDate)
                ->order('order_id ASC')
                ->with(['orderProduct'])
                ->select()->toArray();

            $put = [];
            $JuShuiTanOrderModel = new OrderInJuShuiTan();
            foreach (array_chunk($orderInfo, $max) as $orders){
                foreach ($orders as $order){
                    preg_match($addressRegex, $order['user_address'], $detailAddress);
                    $erpOrder = [
                        'shop_id' => $config['shop_id'],
                        'outer_so_id' => $order['pay_order_sn'],
                        'buyer_paid_amount' => $order['pay_price'],
                        'seller_income_amount' => $order['pay_price'],
                        'so_id' => $order['order_id'],
                        'order_date' => $order['create_time'],
                        'shop_status' => $JuShuiTanOrderModel->getShopStatus($order),

                        'shop_buyer_id' => $order['uid'],
                        'receiver_state' => $detailAddress[1] ?? "**",
                        'receiver_city' => $detailAddress[3] ?? "**",
                        'receiver_district' => $detailAddress[5] ?? "**",
                        'receiver_address' => $order['user_address'],
                        'receiver_name' => $order['real_name'],
                        'receiver_phone' => $order['user_phone'],
                        'buyer_message' => $order['mark'], //买家备注

                        'pay_amount' => $order['pay_price'],//应付金额
                        'freight' => $order['pay_postage'], //运费
                        'remark' => $order['remark'], //卖家备注
                        'is_cod' => false, //是否货到付款
                        'shop_modified' => $order['update_time'],
                        'l_id' => $order['delivery_id'],
                        'logistics_company' => $order['delivery_name'],
                        'question_desc' => $order['admin_mark'], //订单异常描述
                    ];
                   $refundStatus = $JuShuiTanOrderModel->getRefundStatus($order);
                    foreach ($order['orderProduct'] as $p){
                        $item = [
                            'sku_id' => $p['sku_id'],
                            'shop_sku_id' => $p['sku_id'],
                            'pic' => $p['cart_info']['product']['image'],
                            'properties_value' => $p['cart_info']['productAttr']['sku'],
                            'amount' => $p['cart_info']['productAttr']['price'],
                            'base_price' => $p['cart_info']['productAttr']['price'],
                            'qty' => $p['product_num'],
                            'name' => $p['cart_info']['product']['store_name'],
                            'outer_oi_id' => $p['order_product_id'],
                        ];
                        if($refundStatus != ""){
                            $item['refund_status'] = $refundStatus;
                        }
                        $erpOrder['item'][] = $item;
                    }
                    if($erpOrder['shop_status'] != "WAIT_BUYER_PAY"){
                        $erpOrder['pay'] = [
                            'outer_pay_id' => $order['transaction_id'],
                            'pay_date' => $order['pay_time'],
                            'payment' => StoreOrder::getPayment($order['pay_type']),
                            'seller_account' => $order['transaction_id'],
                            'buyer_account' => $order['transaction_id'],
                            'amount' => $order['pay_price'],
                        ];
                    }
                    //invoice
                    $put[] = $erpOrder;
                }
            }
            echo json_encode($put);

            $request= new ApiRequest([]);
            $request->request(ApiRequest::UPLOAD_ORDERS, $put);

        }
    }

    public function failed($data)
    {

    }
}