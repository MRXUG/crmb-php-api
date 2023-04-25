<?php
/**
 * @user: BEYOND 2023/3/2 10:00
 */

namespace crmeb\services\easywechat\coupon;

use app\common\model\coupon\CouponStocks;
use app\common\model\platform\PlatformCoupon;
use app\common\repositories\coupon\BuildCouponRepository;
use app\common\repositories\coupon\CouponStocksRepository;
use app\common\repositories\coupon\CouponStocksUserRepository;
use crmeb\exceptions\WechatException;
use crmeb\services\easywechat\BaseClient;
use think\facade\Log;
use think\helper\Str;

/**
 * 商家券
 */
class Client extends BaseClient
{
    // 商家券回调地址
    const NOTIFY_URL = '%s/api/notice/receive-coupon-notify?key=%s';

    /**
     * @param $error
     *
     * @return string
     */
    private function wechatError(array $error)
    {
        return sprintf('商家券,错误码:%s,错误信息:%s', $error['code'], $error['message']);
    }

    /**
     * 批量委托
     *
     * @param array $mchIds
     * @param $stockId
     *
     * @return void
     */
    public function platformEntrust(array $mchIds, $stockId)
    {
        Log::info('商家券委托,' . json_encode(compact('mchIds', 'stockId')));
        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);
        $stockData = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
        $currentMchId = $stockData['mch_id']; // 建券使用的商户除外

        foreach ($mchIds as $mchId) {
            $params = [
                'mch_id'   => $mchId,
                'stock_id' => $stockId,
            ];

            try {
                $currentMchId != $mchId && $this->entrust($params);
            } catch (\Exception $e) {
                Log::error('批量委托异常,' . $e->getMessage() . ',' . json_encode($params));
                // TODO 预警消息
            }
        }
    }

    /**
     * 委托-建立合作关系
     *
     * @param $params
     *
     * @return mixed
     */
    public function entrust($params)
    {
        $stockId = $params['stock_id'];
        /**
         * @var CouponStocksRepository $couponStocksRepository
         */
        $couponStocksRepository = app()->make(CouponStocksRepository::class);
        // 建券时的商户
        $originMchId = $couponStocksRepository->getValue(['stock_id' => $stockId], 'mch_id');

        $body = [
            'partner' => [
                "merchant_id" => $params['mch_id'],
                "type"  => "MERCHANT",
            ],
            'authorized_data' => [
                "business_type" => "BUSIFAVOR_STOCK", // 商家券
                "stock_id"      => $params['stock_id'],
            ],
        ];

        $jsonBody = json_encode($body);
        $options = [
            'sign_body' => $jsonBody,
            'headers' => [
                'Idempotency-Key' => $this->generateOutRequestNo(0, $originMchId),
            ],
        ];
        $result = $this->request('/v3/marketing/partnerships/build', 'POST', $options);
        Log::info('委托-建立合作关系,' . json_encode(compact('result', 'params'), JSON_UNESCAPED_UNICODE));

        if (!empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return $result;
    }

    /**
     * 查询合作关系列表
     *
     * @return mixed
     */
    public function entrusts($params)
    {
        $body = [
            'partner' => [
                "merchant_id" => $params['mch_id'],
                "type"  => "MERCHANT",
            ],
            'authorized_data' => [
                "business_type" => "BUSIFAVOR_STOCK",
                "stock_id"      => $params['stock_id'],
            ],
            'limit' => '',
            'offset' => '',
        ];
        $jsonBody = json_encode($body);

        return $this->request('/v3/marketing/busifavor/coupons/return', 'GET', ['sign_body' => $jsonBody]);
    }

    /**
     * 退券
     *
     * @param $params
     *
     * @return mixed
     */
    public function return($params)
    {
        $couponData = $this->getOneReceiveCoupon($params['coupon_code']);
        $appId = $couponData['appid'];
        $mchId = $couponData['mch_id'];

        $body = [
            'stock_id'       => $params['stock_id'],
            'coupon_code'    => $params['coupon_code'],
            'return_request_no' => $this->generateOutRequestNo($appId, $mchId),
        ];

        $jsonBody = json_encode($body);
        $result = $this->request('/v3/marketing/busifavor/coupons/return', 'POST', ['sign_body' => $jsonBody]);
        if (!empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return $result;
    }

    /**
     * @param $couponCode
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function getOneReceiveCoupon($couponCode)
    {
        /**
         * @var CouponStocksUserRepository $couponStocksUserRepository
         */
        $couponStocksUserRepository = app()->make(CouponStocksUserRepository::class);

        $model = $couponStocksUserRepository->getOne(['coupon_code' => $couponCode]);
        return method_exists($model, 'toArray') ? $model->toArray() : [];
    }

    /**
     * 核券
     *
     * @param $params
     * @param $merchantConfig
     *
     * @return mixed
     */
    public function use($params)
    {
        $couponData = $this->getOneReceiveCoupon($params['coupon_code']);
        $appId = $couponData['appid'];
        $mchId = $couponData['mch_id'];

        $body = [
            'stock_id'       => $params['stock_id'],
            'coupon_code'    => $params['coupon_code'],
            'use_request_no' => $this->generateOutRequestNo($appId, $mchId),
            'appid'          => $appId, // 领券的appid
            'use_time'       => date(DATE_RFC3339),
        ];

        $jsonBody = json_encode($body);
        $result =  $this->request('/v3/marketing/busifavor/coupons/use', 'POST', ['sign_body' => $jsonBody]);
        if (isset($result['code']) && !empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return $result;
    }

    /**
     * 建券
     *
     * @param array $params
     * @param array $merchantConfig
     *
     * @return mixed
     */
    public function build(&$params, array $merchantConfig)
    {
        $params = $this->formatBuildCoupon($params, $merchantConfig);

        $jsonBody = json_encode($params);
//        echo json_encode($jsonBody, 256);die;
        $result = $this->request('/v3/marketing/busifavor/stocks', 'POST', ['sign_body' => $jsonBody]);
        if (!empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return $result;
    }

    /**
     * 创建平台优惠券
     *
     * @param PlatformCoupon $coupon
     * @param array $merchantConfig
     * @return array
     */
    public function buildPlatformCoupon(PlatformCoupon $coupon, array $merchantConfig): array
    {
        $params = $this->formatBuildPlatformCoupon($coupon, $merchantConfig);

        $result = $this->request('/v3/marketing/busifavor/stocks', 'POST', [
            'sign_body' => json_encode($params)
        ]);
        if (!empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return compact('params', 'result');
    }


    public function expiredCoupon(string $couponCode, string $stockId)
    {
        $deactivate_request_no = 'PC' . Str::random(30);

        return $this->request('/v3/marketing/busifavor/coupons/deactivate', 'POST', [
            'sign_body' => json_encode([
                'coupon_code' => $couponCode,
                'stock_id' => $stockId,
                'deactivate_request_no' => $deactivate_request_no
            ])
        ]);
    }

    /**
     * 生成发券签名
     *
     * @param array $couponList
     * @param array $merchantConfig
     *
     * @return array
     */
    public function generateSign(array $couponList, $merchantConfig)
    {
        $mchId = $merchantConfig['payment']['merchant_id'];
        $appId = $merchantConfig['app_id'];

        // 发券商户信息
        $returnData = [];
        $num = 0;
        foreach ($couponList as $item) {
            $stockId = $item['stock_id'];
            $sendNum = $item['send_num'] ?? 1;

            for ($index = 0; $index < $sendNum; $index++) {
                $outRequestNo = $this->generateOutRequestNo($appId, $mchId);
                $signData["out_request_no$num"] = $outRequestNo;
                $signData["stock_id$num"] = $stockId;

                $returnData[] = [
                    'stock_id' => $stockId,
                    'out_request_no' => $outRequestNo,
                ];
                $num++;
            }
        }
        // 领券商户随机获取
        $signData['send_coupon_merchant'] = $mchId;

        ksort($signData);
        $signDataString = http_build_query($signData);
        $signString = sprintf('%s&key=%s', $signDataString, $merchantConfig['payment']["key"]);
        $sign = strtoupper(hash_hmac("sha256", $signString, $merchantConfig['payment']["key"]));

        $result = [
            'send_coupon_params'   => $returnData,
            "send_coupon_merchant" => $signData['send_coupon_merchant'],
            "sign"                 => $sign,
        ];

//        Log::info('发券签名:', compact('result', 'signDataString', 'signData'));
        return $result;
    }

    /**
     * @param $mchId
     * @param string $gcsId
     *
     * @return string
     */
    private function generateOutRequestNo($appId, $mchId, string $gcsId = '')
    {
        return sprintf('%s_%s_%s%s_%s', $appId, $mchId, date('YmdHis'), rand(10000, 99999), intval($gcsId));
    }

    /**
     * 格式化平台优惠券参数
     *
     * @param PlatformCoupon $coupon
     * @param array $merchantConfig
     * @return array
     */
    private function formatBuildPlatformCoupon(PlatformCoupon $coupon, array $merchantConfig): array
    {
        $appId = $merchantConfig['app_id']; // 随机一个健康的小程序Id
        $mchId = $merchantConfig['payment']['merchant_id']; // 随机获取一个健康商户
        # 防止最小值创建失败
        $getMaxCoupon = $coupon->getAttr('is_limit') == 1 ? $coupon->getAttr('limit_number') : 1000000000;

        $miniAppPath = "/pages/columnGoods/goods_coupon_list/index?coupon_id={$coupon->getAttr('platform_coupon_id')}&type=3";

        return [
            'out_request_no'   => $this->generateOutRequestNo($appId, $mchId),
            'belong_merchant'  => $mchId,
            'goods_name'       => '部分商品可用',
            'stock_name'       => $coupon->getAttr('coupon_name'),
            'stock_type'       => CouponStocks::STOCK_TYPE_REDUCE,
            'coupon_code_mode' => CouponStocks::WECHATPAY_MODE,
            'coupon_use_rule'      => [
                'use_method'            => CouponStocks::COUPON_USE_ONLINE,
                'mini_programs_appid'   => $appId,
                'mini_programs_path'    => $miniAppPath,
                'coupon_available_time' => [
                    'available_begin_time'        => date(DATE_RFC3339, strtotime($coupon->getAttr('receive_start_time'))),
                    'available_end_time'          => date(DATE_RFC3339, strtotime($coupon->getAttr('receive_end_time'))),
                    'available_day_after_receive' => (int) $coupon->getAttr('effective_day_number'),
                ],
                'fixed_normal_coupon' => [
                    'discount_amount'     => (int)(bcmul($coupon->getAttr('discount_num'), 100)), // 参数使用的单位是：元
                    'transaction_minimum' => empty($coupon->getAttr('threshold')) ? (int)(bcmul($coupon->getAttr('discount_num'), 100)) +1 : (int)(bcmul($coupon->getAttr('threshold'),  100)),
                ],
            ],
            'stock_send_rule' => [
                'max_coupons'          => $getMaxCoupon,
                'max_coupons_by_day'   => $getMaxCoupon,
                'max_coupons_per_user' => $coupon->getAttr('is_user_limit') == 1 ? $coupon->getAttr('user_limit_number') : 100,
            ],
            'custom_entrance' => [
                'mini_programs_info' => [
                    'entrance_words'      => '关闭提醒',
                    'guiding_words'       => '投诉商家',
                    'mini_programs_path'  => '/pages/users/feedback/index',
                    'mini_programs_appid' => $appId,
                ],
            ],
            'display_pattern_info' => [
                'merchant_logo_url' => 'https://wxpaylogo.qpic.cn/wxpaylogo/PiajxSqBRaEIPAeia7ImvtsoDnve6H2tq7ibrQJ9CgaKOxtw8Bm6ArKDw/0'
            ],
            'notify_config' => [
                'notify_appid' => $appId, // 用于回调通知时，计算返回操作用户的openid（诸如领券用户），支持小程序or公众号的APPID
            ],
        ];
    }

    /**
     * 格式化建券参数
     *
     * @param array $params
     *
     * @return array
     */
    private function formatBuildCoupon(array $params, $merchantConfig): array
    {
        $appId = $merchantConfig['app_id']; // 随机一个健康的小程序Id
        // 随机获取一个健康商户
        $mchId = $merchantConfig['payment']['merchant_id'];

        $couponData = [
            'out_request_no'   => $this->generateOutRequestNo($appId, $mchId),
            'belong_merchant'  => $mchId,
            'goods_name'       => '店铺内{全场or部分}商品可用',
            'stock_name'       => $params['stock_name'],
            'stock_type'       => CouponStocks::STOCK_TYPE_REDUCE,
            'coupon_code_mode' => CouponStocks::WECHATPAY_MODE,
            'coupon_use_rule'      => [
                'use_method'            => CouponStocks::COUPON_USE_ONLINE,
                'mini_programs_appid'   => $appId,
                'mini_programs_path'    => "/pages/columnGoods/goods_coupon_list/index?type={$params['type']}&mer_id={$params['mer_id']}",//需要携参券类型
                'coupon_available_time' => [
                    'available_begin_time'        => date(DATE_RFC3339, strtotime($params['start_at'])),
                    'available_end_time'          => date(DATE_RFC3339, strtotime($params['end_at'])),
                ],
                'fixed_normal_coupon' => [
                    'discount_amount'     => (int)(bcmul($params['discount_num'], 100)), // 参数使用的单位是：元
                    'transaction_minimum' => empty($params['transaction_minimum']) ? (int)(bcmul($params['discount_num'], 100)) +1 : (int)(bcmul($params['transaction_minimum'],  100)),
                ],
            ],
            'stock_send_rule' => [
                'max_coupons'          => (int)$params['max_coupons'],
                'max_coupons_by_day'   => (int)$params['max_coupons'],
                'max_coupons_per_user' => (int)$params['max_coupons_per_user'],
//                'natural_person_limit' => false,
//                'prevent_api_abuse'    => false,
//                'transferable'         => false,
//                'shareable'            => false,
            ],
//            'display_pattern_info' => [
//                'merchant_logo_url' => $params['merchant_logo_url'],
//                'coupon_image_url'  => $params['coupon_image_url'],
//                'background_color'  => $params['background_color'],
//                'merchant_name'     => $params['merchant_name'],
//            ],
            'custom_entrance' => [
                'mini_programs_info' => [
                    'entrance_words'      => '关闭提醒',
                    'guiding_words'       => '投诉商家',
                    'mini_programs_path'  => '/pages/users/feedback/index', //小程序首页
//                    'mini_programs_path'  => 'pages/columnGoods/goods_coupon_list/index', //小程序首页
                    'mini_programs_appid' => $appId,
                ],
//                'appid' => 'wxee403b4ddd3978bd',  // TODO 公众号appid，提示与商户没有关联
            ],
            'display_pattern_info' => [
                'merchant_logo_url' => 'https://wxpaylogo.qpic.cn/wxpaylogo/PiajxSqBRaEIPAeia7ImvtsoDnve6H2tq7ibrQJ9CgaKOxtw8Bm6ArKDw/0'
            ],
            'notify_config' => [
                'notify_appid' => $appId, // 用于回调通知时，计算返回操作用户的openid（诸如领券用户），支持小程序or公众号的APPID
            ]
        ];

        $couponAvailableTime = &$couponData['coupon_use_rule']['coupon_available_time'];
        $typeDate = $params['type_date'];
        // 使用有效期,1==立即生效，2=时间段，3=N天后 4 领券时间内均可用(与2相同)
        if ($typeDate == CouponStocks::DATE_NOW) {
            // 立即生效 wait_days_after_receive 不传
            $couponAvailableTime['available_day_after_receive'] = $params['available_day_after_receive'];

        } elseif ($params['type_date'] == CouponStocks::DATE_RANGE  || $params['type_date'] == CouponStocks::DATE_H) {
            // 无规律的有效时间，多个无规律时间段，用户自定义字段。
            $dateRange = json_decode($params['date_range'] ?? [], true);
            [$beginTime, $endTime] = $dateRange;
            $irregularyAvaliableTime = [
                [
                    'begin_time' => date(DATE_RFC3339, strtotime($beginTime)),
                    'end_time' => date(DATE_RFC3339, strtotime($endTime)),
                ]
            ];
            $couponAvailableTime['irregulary_avaliable_time'] = $irregularyAvaliableTime;
        } elseif ($params['type_date'] == CouponStocks::DATE_N) {
            // 日期区间内，用户领券后需等待x天开始生效。例如领券后当天开始生效则无需填写
            $couponAvailableTime['available_day_after_receive'] = (int)$params['available_day_after_receive'];
            $couponAvailableTime['wait_days_after_receive'] = (int)$params['wait_days_after_receive'];
        }

        return $couponData;
    }

    public function setCallback($mchId)
    {
        $params = [
            "mchid"      => $mchId,
            "notify_url" => sprintf(self::NOTIFY_URL, env('APP.HOST'), base64_encode($mchId)),
        ];

        $jsonBody = json_encode($params);
        $result = $this->request('/v3/marketing/busifavor/callbacks', 'POST', ['sign_body' => $jsonBody]);
        if (!empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return $result;
    }

    /**
     * 查询回调配置
     *
     * @param $mchId
     *
     * @return mixed
     */
    public function getCallback($mchId)
    {
        $params = [
            "mchid" => $mchId,
        ];

        $jsonBody = json_encode($params);
        $result = $this->request('/v3/marketing/busifavor/callbacks', 'GET', ['sign_body' => $jsonBody]);
        if (!empty($result['code'])) {
            throw new WechatException($this->wechatError($result));
        }
        return $result;
    }
}
