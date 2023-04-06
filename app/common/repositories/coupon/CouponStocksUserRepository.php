<?php

namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponStocksUserDao;
use app\common\dao\coupon\StockProductDao;
use app\common\model\coupon\CouponStocks;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\coupon\StoreCouponProductRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\MerchantCouponService;
use think\exception\ValidateException;
use think\facade\Log;


class CouponStocksUserRepository extends BaseRepository
{
    /**
     * StoreCouponIssueUserRepository constructor.
     * @param CouponStocksUserDao $dao
     */
    public function __construct(CouponStocksUserDao $dao)
    {
        $this->dao = $dao;
    }

    public function list($page, $limit, $where, $merId): array
    {
        $query = $this->dao->search($merId, $where);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $k=>$v){
            if (isset($v["stockDetail"]["transaction_minimum"]) && isset($v["stockDetail"]["discount_num"]) && ($v["stockDetail"]["transaction_minimum"] == 0)){
                $list[$k]["stockDetail"]["transaction_minimum"] = $v["stockDetail"]["discount_num"]+0.01;
            }
        }

        return compact('count', 'list');
    }

    /**
     * @param $where
     * @param $fields
     *
     * @return \think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function selectWhere($where, $fields = '*')
    {
        return $this->dao->selectWhere($where, $fields);
    }

    /**
     * @param $where
     *
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getOne($where)
    {
        return $this->dao->getWhere($where);
    }

    /**
     * 商家券回调处理
     *
     * @param $callbackData
     *
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function callback($callbackData)
    {
        Log::info('商家券回调处理: callbackData' . json_encode($callbackData));
        $unionId = $callbackData['unionid'];
        $stockId = $callbackData['stock_id'];
        $couponCode = $callbackData['coupon_code'];

        [$appId, $mchId] = explode( '_', $callbackData['send_req_no']);
        /**
         * @var WechatUserRepository $wechatUserRepository
         */
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $wechatUser = $wechatUserRepository->getWhere(['unionid' => $unionId]);
        if (empty($wechatUser)) {
            // 错误
            throw new ValidateException('用户不存在,unionid:' . $unionId);
        }

        /**
         * @var UserRepository $userRepository
         */
        $userRepository = app()->make(UserRepository::class);
        $user = $userRepository->getUseByWechatUserId(['wechat_user_id' => $wechatUser->wechat_user_id]);
        if(empty($user)) {
            // 错误
            throw new ValidateException('用户不存在,unionid:' . $unionId);
        }

        // 批次校验
        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);
        $stockInfo = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
        if (empty($stockInfo)) {
            // 错误
            throw new ValidateException('批次不存在,stock_id:' . $stockId);
        }

        // 计算券的开始和结束时间
        // 券开始核销时间
        $availableTime = $stockInfo['start_at'];
        // 券停止核销时间
        $unAvailableTime = $stockInfo['end_at'];
        // 领取后N天内有效
        $availableDayAfterReceive = (int)$stockInfo['available_day_after_receive'] ?: 0;
        // 领取第N天后生效
        $waitDaysAfterReceive = (int)$stockInfo['wait_days_after_receive'] ?: 0;
        // 开始
        $startTime = date('Y-m-d H:i:s', strtotime("+$waitDaysAfterReceive day"));
        // 结束
        $delay = $waitDaysAfterReceive + $availableDayAfterReceive;
        $endTime = date('Y-m-d H:i:s', strtotime("+$delay day"));
        $start = $waitDaysAfterReceive == 0 ? $availableTime : ($startTime > $availableTime ? $startTime : $availableTime);
        $end = $availableDayAfterReceive == 0 ? $unAvailableTime : ($endTime < $unAvailableTime ? $endTime : $unAvailableTime);

        // 入库
        $insertData = [
            'uid'         => $user->uid,
            'mch_id'      => $mchId,
            'appid'       => $appId,
            'unionid'     => $unionId,
            'stock_id'    => $stockId,
            'coupon_code' => $couponCode,
            'start_time'  => $start,
            'end_time'    => $end,
            'mer_id'      => $stockInfo['mer_id'], // 建券的mer_id
        ];

        $this->dao->createOrUpdate(['coupon_code' => $couponCode], $insertData);
        // 修改发券数量
        $buildCouponRepository->dao->incField($stockInfo->id, 'sended');
    }

    /**
     * 发券数量校验
     *
     * @param $stockId
     * @param $uid
     *
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function validateReceiveCoupon($stockId, $uid)
    {
        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);
        $stockIdInfo = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
        if (empty($stockIdInfo)) {
            throw new ValidateException('券批次不存在');
        }

        $receivedCouponModel = $receivedCouponModel1 = $this->selectWhere(['stock_id' => $stockId]);
        // 该批次领券总数量
        $totalReceived = $receivedCouponModel->count();
        // 该批次当天领券总数量
        $totalReceivedCurrentDay = $receivedCouponModel->where('create_time', date('Y-m-d H:i:s'))->count();
        // 该批次该用户总领券数量
        $totalReceivedByUser = $receivedCouponModel1->where('uid', $uid)->count();
        $sendNumTotal = $stockIdInfo['max_coupons'] - $totalReceived;
        if ($sendNumTotal < 1) {
            // 超过总的券数量
            throw new ValidateException('优惠券已抢光～');
        }

        $sendNumByUser = $stockIdInfo['max_coupons_per_user'] - $totalReceivedByUser;
        if ($sendNumByUser < 1) {
            // 当前用户领券达到上限
            throw new ValidateException('您的该优惠券使用已达上限，暂不可领');
        }

        $sendNumCurrentDay = $stockIdInfo['max_coupons_by_day'] - $totalReceivedCurrentDay;
        if ($sendNumCurrentDay < 1) {
            // 单天发放上限个数
            throw new ValidateException('优惠券已抢光，明天再来~');
        }

        $tempSendNum = $sendNumTotal > $sendNumByUser ? $sendNumByUser : $sendNumTotal;
        return $tempSendNum > $sendNumCurrentDay ? $sendNumCurrentDay : $tempSendNum;
    }

    public function existsWhere($where)
    {
        return $this->dao->existsWhere($where);
    }

    public function updateWhere($where, $data)
    {
        return $this->dao->updateByWhere($where, $data);
    }

    public function createUpdate($where, $data)
    {
        return $this->dao->createOrUpdate($where, $data);
    }

    /**
     * 核销商家券
     *
     * @param $params
     *
     * @return void
     */
    public function use($params)
    {
        if (!empty($params['coupon_code']) && !empty($params['stock_id'])) {
            try {
                MerchantCouponService::create(MerchantCouponService::USE_COUPON, $params, $merchantConfig)->coupon()->use($params);
                $where = [
                    'coupon_code' => $params['coupon_code'],
                    'written_off' => 0,
                ];
                $exists = $this->existsWhere($where);
                if (!$exists) {
                    Log::error('商家券状态不正确, 核销失败' . json_encode($params));
                    throw new ValidateException('商家券状不正确');
                }

                // 修改券状态
                $data = [
                    'written_off' => 1,
                    'use_time' => date('Y-m-d H:i:s'),
                ];
                $this->updateWhere($where, $data);
            } catch (\Exception $e) {
                Log::error('核销商家券失败,' . $e->getMessage() . ',' . json_encode($params));
                throw new ValidateException('核销商家券失败');
            }
        }
    }

    /**
     * 退券
     *
     * @param $order
     *
     * @return void
     */
    public function return($order)
    {
        // 退券
        if (!empty($order->coupon_code)) {
            $params = [
                'stock_id' => $order->stock_id,
                'coupon_code' => $order->coupon_code,
            ];
            try {
                MerchantCouponService::create(MerchantCouponService::RETURN_COUPON, $params)->coupon()->return($params);
                $where = [
                    'coupon_code' => $params['coupon_code'],
                    'written_off' => 1,
                ];
                // 修改券状态
                $data = [
                    'written_off' => 0,
                    'use_time' => null,
                ];
                $this->updateWhere($where, $data);
            } catch (\Exception $e) {
                $params['group_order_sn'] = $order->group_order_sn;
                Log::error('退券失败,' . json_encode($params));
            }
        }
    }

    /**
     * 提交订单页-商家券推优
     *
     * @param $uid
     * @param $merId
     * @param $productInfo
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function best($uid, $merId, $productInfo, $orderPrice)
    {
        $today = date('Y-m-d H:i:s');
        $productAmount = $productInfo['price'];

        $couponList = $this->dao->getModelObj()
            ->alias('a')
            ->leftJoin('eb_coupon_stocks b', 'a.stock_id = b.stock_id')
            ->field(['a.*', 'b.discount_num'])
            ->where([
                ['a.written_off', '=', 0],
                ['a.is_del', '=', 0],
                ['a.uid', '=', $uid],
                ['a.mer_id', '=', $merId],
                ['a.end_at', '>=', $today],
                ['a.start_at', '<', $today],
            ])
            ->select()->toArray();
        # 删除掉不符合规则的优惠券
        foreach ($couponList as $k => $v) {
            if ($v['discount_num'] >= $orderPrice) unset($couponList[$k]);
        }
        $stockIdList = array_column($couponList, 'stock_id');
        /**
         * @var CouponStocksRepository $couponStockRepository
         */
        $couponStockRepository = app()->make(CouponStocksRepository::class);
        $whereStock = [
//            'status' => CouponStocks::STATUS_ING,
            ['mer_id', '=', $merId],
        ];
        $field = 'type, stock_id, scope, discount_num, stock_name, transaction_minimum';
        $stockListCollect = $couponStockRepository->selectPageWhere($whereStock, $stockIdList, 1, 100, $field);
        $stockList = $stockListCollect->toArray();
        # 优惠券新逻辑限制
        foreach ($stockList as $k => &$item) {
            $item['no_threshold'] = isset($item["transaction_minimum"]) && $item["transaction_minimum"] == 0 ? 1 : 0;
            if (isset($item["transaction_minimum"]) && isset($item["discount_num"]) && ($item["transaction_minimum"] == 0)){
                $item["transaction_minimum"] = $item["discount_num"]+0.01;
            }
            if (isset($item["discount_num"]) && $item["discount_num"] >= $orderPrice){
                unset($stockList[$k]);
            }
        }

        $stockListByStockId = array_column($stockList, null, 'stock_id');

        $stockProduct = app()->make(StockProductDao::class);
        $checkCouponList = [];
        $maxCouponCode = '';
        $maxDiscount = 0;
        foreach ($couponList as $item) {
            $stockId = $item['stock_id'];
            $stockData = $stockListByStockId[$stockId];
            if ($productAmount >  $stockData['discount_num']) {
                $discountNum = $stockData['discount_num'];
                $couponCode = $item['coupon_code'];
                if ($discountNum > $maxDiscount) {
                    $maxCouponCode = $couponCode;
                    $maxDiscount = $discountNum;
                }

                //查询优惠券是否绑定指定商品
                $couponInfo = $stockProduct->getStockIdInfo($item["stock_id"]);
                if ($couponInfo && $couponInfo["product_id"] != $productInfo["goods_id"]){
                    continue;
                }

                if ($stockData['transaction_minimum'] == 0){
                    $stockData['transaction_minimum'] = $discountNum+0.01;
                }
                $checkCouponList[] = [
                    'stock_id'            => $stockId,
                    'coupon_code'         => $couponCode,
                    'discount_num'        => $discountNum,
                    'scope'               => $stockData['scope'],
                    'type'                => $stockData['type'],
                    'checked'             => false,
                    'stock_name'          => $stockData['stock_name'],
                    'transaction_minimum' => $stockData['transaction_minimum'],
                    'no_threshold'        => $stockData['no_threshold'],
                    'start_at'            => $item['start_at'],
                    'end_at'              => $item['end_at'],
                    'mer_id'              => $merId,
                ];
            }
        }

        foreach ($checkCouponList as &$item) {
            if ($item['coupon_code'] == $maxCouponCode) {
                $item['checked'] = true;
            }
        }

        array_multisort(array_column($checkCouponList, 'discount_num'), SORT_DESC, $checkCouponList);

        return $checkCouponList;
    }

    public function getValue($where, $filed)
    {
        return $this->dao->getValue($where, $filed);
    }
}
