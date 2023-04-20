<?php

namespace crmeb\services;

use app\common\repositories\applet\WxAppletRepository;
use app\common\repositories\coupon\BuildCouponRepository;
use app\common\repositories\coupon\CouponStocksUserRepository;
use app\common\repositories\system\merchant\PlatformMerchantRepository;
use crmeb\services\easywechat\coupon\Client;
use EasyWeChat\Foundation\Application;
use think\exception\ValidateException;
use think\facade\Log;

/*
 * 商家券
 */
class MerchantCouponService
{
    /**
     * build=建券 send=发券 use=核销 return=退券 entrust=委托 deactivate=使失效 callback=回调
     */
    const BUILD_COUPON = 'build';
    const SEND_COUPON = 'send';
    const USE_COUPON = 'use';
    const RETURN_COUPON = 'return';
    const ENTRUST_COUPON = 'entrust';
    const DEACTIVATE_COUPON = 'deactivate';
    const CALLBACK_COUPON = 'callback';

    /**
     * @var Application
     */
    protected $application;

    protected $config;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->application = new Application($config);
        $this->application->register(new \crmeb\services\easywechat\certficates\ServiceProvider());
        $this->application->register(new \crmeb\services\easywechat\coupon\ServiceProvider);
    }

    /**
     * 处理领券回调
     *
    {
    "id": "a9b16735-fabf-583b-87c5-20e86da23fb6",
    "create_time": "2023-03-08T10:26:18+08:00",
    "resource_type": "encrypt-resource",
    "event_type": "COUPON.SEND",
    "summary": "\u5546\u5bb6\u5238\u9886\u5238\u901a\u77e5",
    "resource": {
    "original_type": "coupon",
    "algorithm": "AEAD_AES_256_GCM",
    "ciphertext": "WDuRUCDpiv4f4tFDX3lmhNUcD37l+C0n+hjooMaReNHLKGknsQar7N+yTo890YmNcoCE+memqBDp9hhJjukJVsoU9BndvE7ma9OYUJdFCrmtZyyGVOCqYIMLt6TKjxvmC16ib\/3PTs8GZBWkNKW3wU3\/NjPIMc8scX7sZU7BBIrgZAm\/LzJdbrsNLMP6iffRcQDHMmaXX4K7XU1rjTDfpxM5mJ5t47K4B9bjSOM4NejLh07+pf57I4c95gZBE\/nWmBgTtI5l00qZVTmyin88CayADnmghlr1nNETRVhBCF2P+5LmAWTHUqnSQQVSh5udehntlrBIKGP2ohSRVW2ixr0pEyZ1vwZMJ\/fjdD3FGeYp\/vTy+PNZDQMJXB\/rDB0HCsQ0rpsl9XOscZxGMOUU9LI\/+yTyxR\/Ib4tdevq0nQk3Q4W+WKZt0YpNw2bDYlkEag==",
    "associated_data": "coupon",
    "nonce": "ecCNx5xNYwQp"
    }
    }
     *
     * @param $rawCallbackData
     *
     * @return string[]|null
     */
    public function handleNotify($rawCallbackData)
    {
        $eventType = $rawCallbackData['event_type'];
        if ($eventType == 'COUPON.SEND') {
            // 领券
            try {
                $pem = $this->coupon()->decrypt($rawCallbackData['resource']);
                $callbackData = json_decode($pem, true);
                /**
                 * @var CouponStocksUserRepository $couponStocksUserRepository
                 */
                $couponStocksUserRepository = app()->make(CouponStocksUserRepository::class);
                $couponStocksUserRepository->callback($callbackData);
                $result = ["code" => "SUCCESS","message" => "成功"];
            } catch (\Exception $e) {
                Log::error('处理领券回调异常,' . $e->getMessage() . ',参数' . json_encode($callbackData));
                $result = ["code" => "fail","message" => "失败"];
            }
            return $result;
        } elseif ($eventType == 'COUPON.USE') {
            // 核销 //TODO

        }

        return null;
    }

    /**
     * @param $type
     * @param array $params
     * @param array $config
     *
     * @return self
     */
    public static function create($type, $params = [], &$config = [])
    {
        $config = self::getConfig($type, $params);
        return new self($config);
    }

    /**
     * 通过商户号创建操作对象
     *
     * @param string $businessNumber
     * @return self
     * @throws null
     */
    public static function createFromBusinessNumber(string $businessNumber, array &$config): self
    {
        /**
         * @var PlatformMerchantRepository $platformMerchantRepository
         */
        $platformMerchantRepository = app()->make(PlatformMerchantRepository::class);

        $config = self::formatMerchantConfig($platformMerchantRepository->formatMerchantByMchId($businessNumber));

        return new self($config);
    }

    /**
     * @param $type string build=建券 send=发券 use=核销 return=退券 entrust=委托 deactivate=使失效
     * @param array $params
     *                  + mch_id
     *                  + stock_id
     *                  + coupon_code
     *
     * @return array
     */
    private static function getConfig($type, array $params = [])
    {
        /**
         * @var PlatformMerchantRepository $platformMerchantRepository
         */
        $platformMerchantRepository = app()->make(PlatformMerchantRepository::class);

        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);

        /**
         * @var CouponStocksUserRepository $couponStocksUserRepository
         */
        $couponStocksUserRepository = app()->make(CouponStocksUserRepository::class);

        $mchId = 0;
        $stockId = $params['stock_id'] ?? '';
        $couponCode = $params['coupon_code'] ?? '';

        switch ($type) {
            case self::BUILD_COUPON:
                // 建券,随机获取一个制券商户
                $buildCouponMchIdList = systemConfig('build_bonds_merchant');
                if (empty($buildCouponMchIdList)) {
                    throw new ValidateException('建券商户不能为空');
                }
                $mchId = $buildCouponMchIdList[array_rand($buildCouponMchIdList)];
                break;
            case self::ENTRUST_COUPON:
                if (empty($stockId)) {
                    throw new ValidateException('委托，批次不能为空');
                }
                // 委托-委托给除制券商户外的所有平台商户
                $data = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
                // 获取该批次建券时使用的商户
                $mchId = $data['mch_id'];
                break;
            case self::SEND_COUPON:
                // 发券-随机获取一个发券商户
                $sendCouponMchIdList = systemConfig('issue_bonds_merchant');
                if (empty($sendCouponMchIdList)) {
                    throw new ValidateException('发券商户不能为空');
                }
                $mchId = $sendCouponMchIdList[array_rand($sendCouponMchIdList)];
                break;
            case self::USE_COUPON:
                // 核销
                if (empty($couponCode)) {
                    throw new ValidateException('核券编码不能为空');
                }
                $data = $couponStocksUserRepository->getOne(['coupon_code' => $couponCode]);
                if (empty($data)) {
                    throw new ValidateException('券不存在');
                }
                $mchId = $data->mch_id;
                break;
            case self::RETURN_COUPON:
                // 退券
                if (empty($couponCode)) {
                    throw new ValidateException('核券编码不能为空');
                }
                $data = $couponStocksUserRepository->getOne(['coupon_code' => $couponCode]);
                $mchId = $data['mch_id'];
                break;
            case self::DEACTIVATE_COUPON:
                // 使失效
                if (empty($couponCode)) {
                    throw new ValidateException('核券编码不能为空');
                }
                $data = $couponStocksUserRepository->getOne(['coupon_code' => $couponCode]);
                $mchId = $data['mch_id'];
                break;
            case self::CALLBACK_COUPON:
                // 回调
                $mchId = $params['mch_id'] ?? '';
                if (empty($mchId)) {
                    Log::error('领券回商户为空,' . json_encode($params));
                }
                break;
        }

        // 商户信息
        return self::formatMerchantConfig($platformMerchantRepository->formatMerchantByMchId($mchId));
    }

    /**
     * 获取appid
     *
     * @return array
     */
    private static function getWxApplet()
    {
        [$appid, $appSecret] = getAppidAndAppSecret();
//        var_dump($appid);die;
        if (empty($appid) || empty($appSecret)) {
            /**
             * @var WxAppletRepository $wxAppletRepository
             */
            $wxAppletRepository = app()->make(WxAppletRepository::class);
            $wxApplet = $wxAppletRepository->healthyApplet();
            if (!is_array($wxApplet) || empty($wxApplet)) {
                throw new ValidateException('当前没有健康的小程序');
            }
            $appid = $wxApplet['original_appid'];
            $appSecret = $wxApplet['original_appsecret'];
        }

        return [$appid, $appSecret];
    }

    /**
     * 格式化商户配置信息
     *
     * @param $payment
     *
     * @return array
     */
    private static function formatMerchantConfig($payment)
    {
        [$appid, $appSecret] = self::getWxApplet();

        $wechat = [
            'routine_appId' => $appid,
            'routine_appsecret' => $appSecret,
        ];

        return [
            'app_id' => $wechat['routine_appId'],
            'secret' => $wechat['routine_appsecret'],
            'mini_program'    => [
                'app_id'  => $wechat['routine_appId'],
                'secret'  => $wechat['routine_appsecret'],
                'token'   => '',
                'aes_key' => '',
            ],
            'payment' => [
                'app_id'                  => $wechat['routine_appId'],
                'merchant_id'             => trim($payment['pay_routine_mchid']),
                'key'                     => trim($payment['pay_routine_key']),
                'v3_key'                  => trim($payment['pay_routine_v3_key']),
                'cert_path'               => app()->getRootPath() . 'resources/certs/' . $payment['pay_routine_client_cert'],
                'key_path'                => app()->getRootPath() . 'resources/certs/' . $payment['pay_routine_client_key'],
                'notify_url'              => '', // $payment['site_url'] . Route::buildUrl('wechatNotify')->build(), //TODO 商家券回调
                'pay_routine_client_key'  => $payment['pay_routine_client_key'],
                'pay_routine_client_cert' => $payment['pay_routine_client_cert'],
            ],
            'service_payment' => [
                'merchant_id'            => trim($payment['pay_routine_mchid']),
                'key'                    => trim($payment['pay_routine_key']),
                'type'                   => 'routine',
                'cert_path'              => app()->getRootPath() . 'resources/certs/' . $payment['pay_routine_client_cert'],
                'key_path'               => app()->getRootPath() . 'resources/certs/' . $payment['pay_routine_client_key'],
                'pay_weixin_client_cert' => $payment['pay_routine_client_cert'],
                'pay_weixin_client_key'  => $payment['pay_routine_client_key'],
                'serial_no'              => trim($payment['pay_routine_serial_no']),
                'apiv3_key'              => trim($payment['pay_routine_v3_key']),
            ],
            'is_v3' => !empty($payment['pay_routine_v3_key']),
        ];
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @return Client
     */
    public function coupon()
    {
        return $this->application->coupon;
    }

    public function decrypt($resource)
    {
        return $this->coupon()->decrypt($resource);
    }


}
