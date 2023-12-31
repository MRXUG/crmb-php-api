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


namespace crmeb\services\easywechat\pay;


use crmeb\services\easywechat\BaseClient;
use think\exception\ValidateException;

class Client extends BaseClient
{

    public function handleNotify($callback)
    {
        $request = request();
        $success = $request->post('event_type') === 'TRANSACTION.SUCCESS';
        $data = $this->decrypt($request->post('resource', []), 1);

        $handleResult = call_user_func_array($callback, [json_decode($data, true), $success]);
        if (is_bool($handleResult) && $handleResult) {
            $response = [
                'code' => 'SUCCESS',
                'message' => 'OK',
            ];
        } else {
            $response = [
                'code' => 'FAIL',
                'message' => $handleResult,
            ];
        }

        return $response;
    }

    public function pay($type, array $order)
    {
        $params = [
            'appid' => $this->app['config']['app_id'],
            'mchid' => $this->app['config']['service_payment']['merchant_id'],
            'description' => $order['body'],
            'out_trade_no' => $order['out_trade_no'],
            'attach' => $order['attach'],
            'notify_url' => $this->app['config']['service_payment']['notify_url'],
            'amount' => [
                'total' => intval($order['total_fee'] * 100), //单位 分
                'currency' => 'CNY'
            ],
            'scene_info' => [
                'device_id' => 'shop system',
                'payer_client_ip' => request()->ip(),
            ],
        ];

        if ($type === 'h5') {
            $params['scene_info']['h5_info'] = [
                'type' => $order['h5_type'] ?? 'Wap'
            ];
        }

        if (isset($order['openid'])) {
            $params['payer'] = [
                'openid' => $order['openid']
            ];
        }
        $params['settle_info'] = ['profit_sharing'=>true];

        $content = json_encode($params, JSON_UNESCAPED_UNICODE);

        $res = $this->request('/v3/pay/transactions/' . $type, 'POST', ['sign_body' => $content]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function payApp(array $options)
    {
        $res = $this->pay('app', $options);
        return $this->configForAppPayment($res['prepay_id']);
    }

    /**
     * @param string $type 场景类型，枚举值： iOS：IOS移动应用； Android：安卓移动应用； Wap：WAP网站应用
     */
    public function payH5(array $options, $type = 'Wap')
    {
        $options['h5_type'] = $type;
        return $this->pay('h5', $options);
    }

    public function payJs($openId, array $options)
    {
        $options['openid'] = $openId;
        $res = $this->pay('jsapi', $options);
        return $this->configForJSSDKPayment($res['prepay_id']);
    }

    public function payNative(array $options)
    {
        return $this->pay('native', $options);
    }

    /**
     * 订单退款
     * @param string $order_sn
     * @param array $options
     * @return mixed
     */
    public function payOrderRefund(string $order_sn, array $options)
    {
        $params = [
            'out_trade_no' => $order_sn,
            'out_refund_no' => $options['out_refund_no'],
            'amount' => [
                'refund' => intval($options['refund_price'] * 100),
                'total' => intval($options['pay_price'] * 100),
                'currency' => 'CNY'
            ]
        ];
        if (isset($options['reason'])) {
            $params['reason'] = $options['reason'];
        }
        if (isset($options['refund_account'])) {
            $params['refund_account'] = $options['refund_account'];
        }
        $content = json_encode($params);
        $res = $this->request('/v3/refund/domestic/refunds', 'POST', ['sign_body' => $content], true);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function returnAdvance($refund_id, $sub_mchid)
    {
        $res = $this->request('/v3/ecommerce/refunds/' . $refund_id . '/return-advance', 'POST', ['sign_body' => json_encode(compact('sub_mchid'))], true);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function configForPayment($prepayId, $json = true)
    {
        $params = [
            'appId' => $this->app['config']['app_id'],
            'timeStamp' => strval(time()),
            'nonceStr' => uniqid(),
            'package' => "prepay_id=$prepayId",
            'signType' => 'RSA',
        ];
        $message = $params['appId'] . "\n" .
            $params['timeStamp'] . "\n" .
            $params['nonceStr'] . "\n" .
            $params['package'] . "\n";
        openssl_sign($message, $raw_sign, $this->getPrivateKey(), 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);

        $params['paySign'] = $sign;

        return $json ? json_encode($params) : $params;
    }

    /**
     * Generate app payment parameters.
     *
     * @param string $prepayId
     *
     * @return array
     */
    public function configForAppPayment($prepayId)
    {
        $params = [
            'appid' => $this->app['config']['app_id'],
            'partnerid' => $this->app['config']['service_payment']['merchant_id'],
            'prepayid' => $prepayId,
            'noncestr' => uniqid(),
            'timestamp' => time(),
            'package' => 'Sign=WXPay',
        ];
        $message = $params['appid'] . "\n" .
            $params['timestamp'] . "\n" .
            $params['noncestr'] . "\n" .
            $params['prepayid'] . "\n";
        openssl_sign($message, $raw_sign, $this->getPrivateKey(), 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);

        $params['sign'] = $sign;

        return $params;
    }

    public function configForJSSDKPayment($prepayId)
    {
        $config = $this->configForPayment($prepayId, false);

        $config['timestamp'] = $config['timeStamp'];
        unset($config['timeStamp']);

        return $config;
    }

}
