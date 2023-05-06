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

namespace crmeb\services;


use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\OrderFlow;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use crmeb\services\easywechat\applet\ProgramAppletProvider;
use crmeb\services\easywechat\broadcast\Client;
use crmeb\services\easywechat\broadcast\ServiceProvider;
use crmeb\services\easywechat\subscribe\ProgramProvider;
use Doctrine\Common\Cache\RedisCache;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Material\Temporary;
use EasyWeChat\MiniProgram\MiniProgram;
use EasyWeChat\Payment\Order;
use EasyWeChat\Payment\Payment;
use EasyWeChat\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Route;

/**
 * Class MiniProgramService
 * @package crmeb\services
 * @author xaboy
 * @day 2020-05-11
 */
class MiniProgramService
{
    /**
     * @var MiniProgram
     */
    protected $service;

    protected $config;

    /**
     * MiniProgramService constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->service = new Application($config);
        $this->service->register(new ServiceProvider());
        $this->service->register(new ProgramProvider());
        $this->service->register(new \crmeb\services\easywechat\certficates\ServiceProvider);
        $this->service->register(new \crmeb\services\easywechat\combinePay\ServiceProvider);
        $this->service->register(new \crmeb\services\easywechat\msgseccheck\ServiceProvider);
        $this->service->register(new ProgramAppletProvider());
    }

    /**
     * @return Client
     * @author xaboy
     * @day 2020/7/29
     */
    public function miniBroadcast()
    {
        return $this->service->miniBroadcast;
    }

    /**
     * @return array[]
     * @author xaboy
     * @day 2020/6/18
     */
    public static function getConfig($merId = 0, $appid = '')
    {
        // 为了跨小程序登录，直接拿appid查得同商户的secret，不一定是当前商户的
        list($appid, $appSecret) = getAppidAndAppSecret($appid);
        $wechat = [
            'routine_appId'     => $appid,
            'routine_appsecret' => $appSecret,
            'site_url'          => systemConfig('site_url')
        ];

        $payment = merchantConfig($merId, [
            'pay_routine_mchid',
            'pay_routine_key',
            'pay_routine_v3_key',
            'pay_routine_client_cert',
            'pay_routine_client_key',
        ]);

        $systemData = systemConfig([
            'pay_weixin_open',
            'wechat_service_merid',
            'wechat_service_key',
            'wechat_service_v3key',
            'wechat_service_client_cert',
            'wechat_service_client_key',
            'wechat_service_serial_no',
        ]);
        $payment = array_merge($payment, $systemData);

        $config = [
            'app_id'          => $wechat['routine_appId'],
            'secret'          => $wechat['routine_appsecret'],
            'mini_program'    => [
                'app_id'  => $wechat['routine_appId'],
                'secret'  => $wechat['routine_appsecret'],
                'token'   => '',
                'aes_key' => '',
            ],
            'payment'         => [
                'app_id'                  => $wechat['routine_appId'],
                'merchant_id'             => trim($payment['pay_routine_mchid']),
                'key'                     => trim($payment['pay_routine_key']),
                'v3_key'                  => trim($payment['pay_routine_v3_key']),
                'cert_path'               => (app()->getRootPath() . 'resources/certs/' . $payment['pay_routine_client_cert']),
                'key_path'                => (app()->getRootPath() . 'resources/certs/' . $payment['pay_routine_client_key']),
                'notify_url'              => $wechat['site_url'] . Route::buildUrl('routineNotify', ['merId' => $merId])->build(),
                'pay_routine_client_key'  => $payment['pay_routine_client_key'],
                'pay_routine_client_cert' => $payment['pay_routine_client_cert'],
            ],
            'service_payment' => [
                'merchant_id'            => trim($payment['wechat_service_merid']),
                'key'                    => trim($payment['wechat_service_key']),
                'type'                   => 'routine',
                'cert_path'              => (app()->getRootPath() . 'public' . $payment['wechat_service_client_cert']),
                'key_path'               => (app()->getRootPath() . 'public' . $payment['wechat_service_client_key']),
                'pay_weixin_client_cert' => $payment['wechat_service_client_cert'],
                'pay_weixin_client_key'  => $payment['wechat_service_client_key'],
                'serial_no'              => trim($payment['wechat_service_serial_no']),
                'apiv3_key'              => trim($payment['wechat_service_v3key']),
            ],
        ];

        $config['is_v3'] = !empty($config['payment']['v3_key']);

        // todo 去除调试
        if($merId != 0){
            \think\facade\Log::info("MiniProgramServiceConfig" . json_encode($config));
        }

        $cacheDriver = new RedisCache();
        $redis = new \Redis();
        $redis->connect(env('redis.redis_hostname'), env('redis.port'));
        if (!empty(env('redis.redis_password'))) {
            $redis->auth(env('redis.redis_password'));
        }
        $redis->select(env('redis.select'));
        $cacheDriver->setRedis($redis);
        $config['cache'] = $cacheDriver;
        return $config;
    }

    public function isV3()
    {
        return $this->config['is_v3'] ?? false;
    }


    /**
     * @return MiniProgramService
     * @author xaboy
     * @day 2020/6/2
     */
    public static function create($merId = 0, $appid = '')
    {
        return new self(self::getConfig($merId, $appid));
    }

    /**
     * @param $merId
     * @param string $appid
     * @return MiniProgramService
     * @author ziyu
     * @day 2023/2/28
     */
    public static function payCreate($merId, $appid = '')
    {
        return new self(self::getConfig($merId, $appid));
    }
    /**
     * 支付
     * @return Payment
     */
    public function paymentService()
    {
        return $this->service->payment;
    }

    /**
     * 小程序接口
     * @return MiniProgram
     */
    public function miniProgram()
    {
        return $this->service->mini_program;
    }

    /**
     * @return \EasyWeChat\Material\Material|mixed
     * @author xaboy
     * @day 2020/7/29
     */
    public function material()
    {
        return $this->service->mini_program->material_temporary;
    }

    /**
     * @param $sessionKey
     * @param $iv
     * @param $encryptData
     * @return mixed
     * @author xaboy
     * @day 2020/6/18
     */
    public function encryptor($sessionKey, $iv, $encryptData)
    {
        return $this->miniProgram()->encryptor->decryptData($sessionKey, $iv, $encryptData);
    }

    /**
     * 上传临时素材接口
     * @return Temporary
     */
    public function materialTemporaryService()
    {
        return $this->miniProgram()->material_temporary;
    }

    /**
     * 客服消息接口
     */
    public function staffService()
    {
        return $this->miniProgram()->staff;
    }

    /**
     * @param $code
     * @return mixed
     * @author xaboy
     * @day 2020/6/18
     */
    public function getUserInfo($code)
    {
        $userInfo = $this->miniProgram()->sns->getSessionKey($code);
        return $userInfo;
    }

    /**
     * @return \EasyWeChat\MiniProgram\QRCode\QRCode
     * @author xaboy
     * @day 2020/6/18
     */
    public function qrcodeService()
    {
        return $this->miniProgram()->qrcode;
    }

    /**
     * 生成支付订单对象
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return Order
     */
    protected function paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        $total_fee = bcmul($total_fee, 100, 0);
        $order = array_merge(compact('openid', 'out_trade_no', 'total_fee', 'attach', 'body', 'detail', 'trade_type'), $options);
        if ($order['detail'] == '') unset($order['detail']);
        return new Order($order);
    }

    /**
     * 获得下单ID
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return mixed
     */
    public function paymentPrepare($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        $order = $this->paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type, $options);
        $result = $this->paymentService()->prepare($order);
        if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
            return $result->prepay_id;
        } else {
            if ($result->return_code == 'FAIL') {
                throw new ValidateException('微信支付错误返回：' . $result->return_msg);
            } else if (isset($result->err_code)) {
                throw new ValidateException('微信支付错误返回：' . $result->err_code_des);
            } else {
                throw new ValidateException('没有获取微信支付的预支付ID，请重新发起支付!');
            }
        }
    }

    /**
     * 获得jsSdk支付参数
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return array|string
     */
    public function jsPay($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        return $this->paymentService()->configForJSSDKPayment($this->paymentPrepare($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type, $options));
    }

    /**
     * 使用商户订单号退款
     * @param $orderNo
     * @param $refundNo
     * @param $totalFee
     * @param null $refundFee
     * @param null $opUserId
     * @param string $refundReason
     * @param string $type
     * @param string $refundAccount
     * @return Collection|ResponseInterface
     */
    public function refund($orderNo, $refundNo, $totalFee, $refundFee = null, $opUserId = null, $refundReason = '', $type = 'out_trade_no', $refundAccount = 'REFUND_SOURCE_UNSETTLED_FUNDS')
    {
        if (empty($this->config['payment']['pay_routine_client_key']) || empty($this->config['payment']['pay_routine_client_cert'])) {
            throw new \Exception('请配置微信支付证书');
        }
        $totalFee = floatval($totalFee);
        $refundFee = floatval($refundFee);
        return $this->paymentService()->refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId, $type, $refundAccount, $refundReason);
    }

    /**
     * 发送订阅消息
     * @param string $touser 接收者（用户）的 openid
     * @param string $templateId 所需下发的订阅模板id
     * @param array $data 模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
     * @param string $link 击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
     * @return \EasyWeChat\Support\Collection|null
     * @throws \EasyWeChat\Core\Exceptions\HttpException
     * @throws \EasyWeChat\Core\Exceptions\InvalidArgumentException
     */
    public function sendSubscribeTemlate(string $touser, string $templateId, array $data, string $link = '')
    {
        return $this->miniprogram()->now_notice->to($touser)->template($templateId)->andData($data)->withUrl($link)->send();
    }


    /**
     * @param $orderNo
     * @param array $opt
     * @param object $order
     *
     * @return bool
     * @throws \Exception
     * @author xaboy
     * @day 2020/6/18
     */
    public function payOrderRefund($orderNo, array $opt, $order = null)
    {
        if (!isset($opt['pay_price'])) throw new ValidateException('缺少pay_price');
        $totalFee = floatval(bcmul($opt['pay_price'], 100, 0));
        $refundFee = isset($opt['refund_price']) ? floatval(bcmul($opt['refund_price'], 100, 0)) : null;
        $refundReason = isset($opt['desc']) ? $opt['desc'] : '';
        $refundNo = isset($opt['refund_id']) ? $opt['refund_id'] : $orderNo;
        $opUserId = isset($opt['op_user_id']) ? $opt['op_user_id'] : null;
        $type = isset($opt['type']) ? $opt['type'] : 'out_trade_no';
        $refundAccount = isset($opt['refund_account']) ? $opt['refund_account'] : 'REFUND_SOURCE_UNSETTLED_FUNDS';

        $info = [];
        if (!is_null($order)) {
            /** @var DeliveryProfitSharingStatusRepository $make */
            $make = app()->make(DeliveryProfitSharingStatusRepository::class);
            $info = $make->getProfitSharingStatus($order->order->order_id);
            // 检查是否分过帐
            if (!empty($info)) {
                $this->checkOrderProfitSharingStatus($order, $info);
            }
        }

        try {
            $res = ($this->refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId, $refundReason, $type, $refundAccount));
            if ($res->return_code == 'FAIL') throw new ValidateException('退款失败:' . $res->return_msg);
            if (isset($res->err_code)) throw new ValidateException('退款失败:' . $res->err_code_des);

            Db::transaction(function () use ($refundFee, $orderNo, $order, $info) {
                // 记录退款订单流水
                if ($info) {
                    $sharingStatus = in_array($info['profit_sharing_status'],[DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING,DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS]);
                    $unfreezeStatus = in_array($info['unfreeze_status'],[DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING,DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_SUCCESS]);
                    if ($unfreezeStatus || $sharingStatus) {
                        app()->make(OrderFlowRepository::class)->refundOrderFlowWrite([
                            'amount' => '-' . $refundFee,
                            'type' => OrderFlow::FLOW_TYPE_OUT,
                            'create_time' => date('Y-m-d H:i:s'),
                            'mer_id' => $order->order->mer_id,
                            'mch_id' => 0,
                            'order_sn' => $order->order->order_sn,
                            'remark' => OrderFlow::SALE_AFTER_REFUND_CN
                        ]);
                    }
                }

                app()
                    ->make(MerchantGoodsPaymentRepository::class)
                    ->updateWhenOrderCancelOrRefund($order->order->order_id);

                // 如果没有分账需要把这条记录标识不需要分账
                app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                    'order_id' => $order->order->order_id,
                    'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_DEFAULT
                ], [
                    'is_del' => DeliveryProfitSharingStatus::DELETE_TRUE
                ]);
            });
        } catch (\Exception $e) {
            throw new ValidateException($e->getMessage());
        }
        return true;
    }

    /**
     * 检查订单分账状态
     *
     * @param $order
     * @param $info
     *
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/14 16:45
     */
    protected function checkOrderProfitSharingStatus($order, $info)
    {
        try {
            // 分帐回退
            $this->profitSharingReturn($order, $info);
            // 如果订单没有解冻需要先解冻订单
            $this->unFreezeOrder($info, $order);
            // 避免完结分账还没走完就发起退款导致失败
            sleep(900);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 请求分账回退
     *
     * @param $order
     *
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 15:27
     */
    protected function profitSharingReturn($order, $info)
    {
        try {
            if (!$info || !in_array($info['profit_sharing_status'], [DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS,DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING])) {
               return true;
            }

            // 发起分账回退
            $data = app()
                ->make(DeliveryProfitSharingLogsRepository::class)
                ->getProfitSharingOrderByOrderId($order->order->order_id);
            if (!$data) {
                return true;
            }

            // 分账回退
            $this->requestProfitSharingReturn($order, $data, $info);
        } catch (ValidateException $exception) {
           throw  $exception;
        }
    }

    /**
     * 请求分账回退
     *
     * @param $order
     * @param $data
     * @param $info
     *
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/13 17:01
     */
    protected function requestProfitSharingReturn($order, $data, $info)
    {
        $params = [
            'out_order_no' => $data->out_order_no,
            'out_return_no' => $data->out_order_no,
            'return_mchid' => $info['mch_id'],
            'amount' => $info['amount'],
            'description' => '用户退款'
        ];
        $res = WechatService::getMerPayObj($order->order->mer_id, $order->order->appid)
            ->profitSharing()
            ->profitSharingReturn($params);

        $update = [
            'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING,
            'return_amount' => $info['amount'],
        ];

        if ($res && $res['result'] == 'PROCESSING') {
            $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
        } elseif ($res && $res['result'] == 'FAILED') {
            throw new ValidateException('分账回退失败');
        }

        Db::transaction(function () use ($order, $info, $res, $params, $update) {
            // 更新分账状态为回退
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere(['order_id' => $order->order->order_id], $update);
            // // 记录收入
            // if ($update['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS) {
            //     app()->make(OrderFlowRepository::class)->create([
            //         'amount' => '+' . $info['amount'],
            //         'type' => OrderFlow::FLOW_TYPE_IN,
            //         'create_time' => date('Y-m-d H:i:s'),
            //         'mer_id' => $order->order->mer_id,
            //         'mch_id' => $info['mch_id'],
            //         'order_sn' => $order->order->order_sn,
            //         'remark' => OrderFlow::PROFIT_SHARING_RETURN_CN
            //     ]);
            // }

            // 记录分账回退日志
            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type' => DeliveryProfitSharingLogs::RETURN_ORDERS_TYPE,
                'out_order_no' => $params['out_order_no'],
                'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id' => $order['order_id'],
                'transaction_id' => $order->order->transaction_id,
                'out_return_no' => $params['out_return_no']
            ]);
        });
    }

    /**
     * 解冻订单
     *
     * @param $info
     * @param $order
     *
     * @return bool
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/13 16:56
     */
    protected function unFreezeOrder($info, $order)
    {
        if (in_array($info['unfreeze_status'], [
            DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING,
            DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_SUCCESS
        ])) {
            return true;
        }
        /** @var DeliveryProfitSharingLogsRepository $logRes */
        $logRes = current(app()->make(DeliveryProfitSharingLogsRepository::class));
        $log = $logRes->getProfitSharingOrder('order_id', [$order->order->order_id]);
        // 请求解冻
        $res = [];
        $update = [
            'unfreeze_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING,
            'profit_sharing_error' => ''
        ];

        $params = [
            'transaction_id' => $log['transaction_id'],
            'out_order_no' => json_decode($log['response'], true)['order_id'],
            'description' => '解冻全部剩余资金'
        ];
        try {
            $res = WechatService::getMerPayObj($order->order->mer_id, $order->order->appid)
                ->profitSharing()
                ->profitSharingUnfreeze($params);
        } catch (\Exception $exception) {
            $update = [
                'unfreeze_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL,
                'profit_sharing_error' => $exception->getMessage()
            ];
        }

        Db::transaction(function () use ($params, $res, $update, $log) {
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                'order_id' => $log['order_id']
            ], $update);

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type' => DeliveryProfitSharingLogs::UNFREEZE_TYPE,
                'out_order_no' => $params['out_order_no'],
                'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id' => $log['order_id'],
                'transaction_id' => $log['transaction_id']
            ]);
        });
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \EasyWeChat\Core\Exceptions\FaultException
     * @author xaboy
     * @day 2020/6/18
     */
    public function handleNotify()
    {
        $this->service->payment = new PaymentService($this->service->merchant);
        return $this->service->payment->handleNotify(function ($notify, $successful) {
            Log::info('小程序支付回调' . var_export($notify, 1));
            if (!$successful) return;
            try {
                event('pay_success_' . $notify['attach'], ['order_sn' => $notify['out_trade_no'], 'data' => $notify, 'is_combine' => 1]);
            } catch (\Exception $e) {
                Log::info('小程序支付回调失败:' . $e->getMessage());
                return false;
            }
            return true;
        });
    }

    /**
     * @return easywechat\combinePay\Client
     */
    public function combinePay()
    {
        return $this->service->combinePay;
    }

    public function handleCombinePayNotify($type)
    {
        $this->service->combinePay->handleNotify(function ($notify, $successful) use ($type) {
            Log::info('微信支付成功回调' . var_export($notify, 1));
            if (!$successful) return false;
            try {
                event('pay_success_' . $type, ['order_sn' => $notify['combine_out_trade_no'], 'data' => $notify, 'is_combine' => 1]);
            } catch (\Exception $e) {
                Log::info('微信支付回调失败:' . $e->getMessage());
                return false;
            }
            return true;
        });
    }


    /**
     * 获取模版标题的关键词列表
     * @param string $tid
     * @return mixed
     */
    public function getSubscribeTemplateKeyWords(string $tid)
    {
//        try {
            $res = $this->miniprogram()->now_notice->getPublicTemplateKeywords($tid);
            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['data'])) {
                return $res['data'];
            } else {
                throw new ValidateException($res['errmsg']);
            }
//        } catch (\Throwable $e) {
//            throw new ValidateException($e);
//        }
    }

    /**
     * 添加订阅消息模版
     * @param string $tid
     * @param array $kidList
     * @param string $sceneDesc
     * @return mixed
     */
    public function addSubscribeTemplate(string $tid, array $kidList, string $sceneDesc = '')
    {
        try {
            $res = $this->miniprogram()->now_notice->addTemplate($tid, $kidList, $sceneDesc);
            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['priTmplId'])) {
                return $res['priTmplId'];
            } else {
                throw new ValidateException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new ValidateException($e);
        }
    }

    public function getPrivateTemplates()
    {
        try{
            $res = $this->miniprogram()->now_notice->getPrivateTemplates();
            return $res;
            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['priTmplId'])) {
                return $res['priTmplId'];
            } else {
                throw new ValidateException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new ValidateException($e);
        }
    }

    public function msgSecCheck($userInfo,$content,$scene,$type = 0)
    {
        //$media_type 1:音频;2:图片
        //scene 场景枚举值（1 资料；2 评论；3 论坛；4 社交日志）
        if (!in_array($scene,[1,2,3,4])) {
            throw new ValidateException('使用场景类型错误');
        }
        if (!isset($userInfo->wechat->routine_openid)) return ;
        $openid = $userInfo->wechat->routine_openid;
        if ($type) {
            return $this->service->msgSec->mediaSecCheck($content,$scene,$openid,$type);
        } else {
            return $this->service->msgSec->msgSecCheck($content,$scene,$openid);
        }
    }
}
