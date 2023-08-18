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


namespace crmeb\services\easywechat\profitSharing;


use crmeb\services\easywechat\BaseClient;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Log;

class Client extends BaseClient
{
    /**
     * 请求分账API
     */
    const PROFIT_SHARING_ORDERS = '/v3/profitsharing/orders';

    /**
     * 查询分账结果API
     */
    const PROFIT_SHARING_ORDERS_RESULT = '/v3/profitsharing/orders/%s?&transaction_id=%s';

    /**
     * 请求分账回退API
     */
    const PROFIT_SHARING_RETURN_ORDERS = '/v3/profitsharing/return-orders';

    /**
     * 查询分账回退结果API
     */
    const PROFIT_SHARING_RETURN_ORDERS_RESULT = '/v3/profitsharing/return-orders/%s?&out_order_no=%s';

    /**
     * 解冻剩余资金API
     */
    const PROFIT_SHARING_ORDERS_UNFREEZE = '/v3/profitsharing/orders/unfreeze';

    /**
     * 查询剩余待分金额API
     */
    const PROFIT_SHARING_BALANCE = '/v3/profitsharing/transactions/%s/amounts';

    /**
     * 添加分账接收方API
     */
    const PROFIT_SHARING_RECEIVERS_ADD = '/v3/profitsharing/receivers/add';

    /**
     * 查询剩余待分金额API
     */
    const PROFIT_SHARING_TRANSACTIONS_AMOUNT = '/v3/profitsharing/transactions/%s/amounts';

    /**
     * 请求分账
     *
     * @param array $options
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 20:57
     */
    public function profitSharingOrders(array $options)
    {
        $params = [
            'appid' => $this->app['config']['app_id'],
            'transaction_id' => $options['transaction_id'],
            'out_order_no' => $options['out_order_no'],
            'unfreeze_unsplit' => $options['unfreeze_unsplit'],
            'receivers' => $options['receivers'],
        ];

        \think\facade\Log::info("这是打印数据".json_encode($params));
        
        $content = json_encode($params, JSON_UNESCAPED_UNICODE);
        $res = $this->request(self::PROFIT_SHARING_ORDERS, 'POST', ['sign_body' => $content], false);
        return $this->handleRes($res);
    }

    /**
     * 查询分佣结果
     *
     * @param string $outOrderNo
     * @param string $transactionId
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:02
     */
    public function profitSharingResult(string $outOrderNo, string $transactionId)
    {
        $res = $this->request(sprintf(self::PROFIT_SHARING_ORDERS_RESULT, $outOrderNo, $transactionId), 'GET', [], false);
        return $this->handleRes($res);
    }

    /**
     * 查询分账剩余金额
     *
     * @param string $transactionId
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 11:43
     */
    public function transactionsAmounts(string $transactionId)
    {
        $res = $this->request(sprintf(self::PROFIT_SHARING_TRANSACTIONS_AMOUNT, $transactionId), 'GET', [], false);
        return $this->handleRes($res);
    }

    /**
     * 分账回退
     *
     * @param array $options
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:06
     */
    public function profitSharingReturn(array $options)
    {
        $params = [
            'order_id' => $options['order_id'] ?? '',
            'out_order_no' => $options['out_order_no'],
            'out_return_no' => $options['out_return_no'],
            'return_mchid' => (string)$options['return_mchid'],
            'amount' => (int)$options['amount'],
            'description' => $options['description'],
        ];
        
        $content = $this->formatParams($params);
        $res = $this->request(self::PROFIT_SHARING_RETURN_ORDERS, 'POST', ['sign_body' => $content], false);
        return $this->handleRes($res);
    }

    /**
     * 查询分账回退结果
     *
     * @param string $outReturnNo
     * @param string $outOrderNo
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:09
     */
    public function profitSharingReturnResult(string $outReturnNo, string $outOrderNo)
    {
        $res = $this->request(sprintf(self::PROFIT_SHARING_RETURN_ORDERS_RESULT, $outReturnNo, $outOrderNo), 'GET', [], false);
        return $this->handleRes($res);
    }

    /**
     * 解冻资金
     *
     * @param array $options
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:12
     */
    public function profitSharingUnfreeze(array $options)
    {
        $params = [
            'transaction_id' => $options['transaction_id'],
            'out_order_no' => $options['out_order_no'],
            'description' => $options['description'],
        ];
        
        $content = $this->formatParams($params);
        $res = $this->request(self::PROFIT_SHARING_ORDERS_UNFREEZE, 'POST', ['sign_body' => $content], false);
        return $this->handleRes($res);
    }

    /**
     * 查询剩余待分金额
     *
     * @param string $transactionId
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:18
     */
    public function profitSharingBalance(string $transactionId)
    {
        $res = $this->request(sprintf(self::PROFIT_SHARING_BALANCE, $transactionId), 'GET', [], false);
        return $this->handleRes($res);
    }

    /**
     * 添加分账接收方
     *
     * @param $options
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:22
     */
    public function profitSharingReceiversAdd($options)
    {
        $params = [
            'appid' => $this->app['config']['app_id'],
            'type' => $options['type'],
            'account' => (string)$options['account'],
            'name' => $options['name'] ?? '',
            'relation_type' => $options['relation_type'],
            'custom_relation' => $options['custom_relation'] ?? ''
        ];
        
        $content = $this->formatParams($params);
        $res = $this->request(self::PROFIT_SHARING_RECEIVERS_ADD, 'POST', ['sign_body' => $content]);
        return $this->handleRes($res);
    }
    
    /**
     * 加密参数
     *
     * @param $str
     * @param $merPayConfig
     *
     * @return string
     * @throws Exception
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/3 17:22
     */
    private function getEncrypt($str, $merPayConfig)
    {
        //$str是待加密字符串
        $public_key = file_get_contents($merPayConfig['service_payment']['cert_path']);
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码 
            $sign = base64_encode($encrypted);
        } else {
            throw new Exception('encrypt failed');
        }
        
        return $sign;
    } 

    /**
     * 处理返回结果
     *
     * @param $res
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:02
     */
    public function handleRes($res)
    {
        if (isset($res['code'])) {
            $msg = $res['message'] ?? '';
            Log::error('res:'.json_encode($res,JSON_UNESCAPED_UNICODE));
            throw new ValidateException('微信接口报错:' . $msg);
        }
        
        return $res ?? [];
    }

    /**
     * 格式化参数
     *
     * @param array $options
     *
     * @return array|false|string
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 21:25
     */
    public function formatParams(array $options)
    {
        $options = array_filter($options);
        return json_encode($options, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 查询退款单详情 v3
     * @param string $out_refund_no
     * @return mixed
     */
    public function getRefundOrder(string $out_refund_no){
        $res = $this->request('/v3/refund/domestic/refunds/'.$out_refund_no, 'GET', [], true);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }
}
