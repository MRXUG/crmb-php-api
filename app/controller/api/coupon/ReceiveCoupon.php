<?php
/**
 * @user: BEYOND 2023/3/10 19:40
 */

namespace app\controller\api\coupon;

use app\common\dao\platform\PlatformCouponDao;
use app\common\dao\platform\PlatformCouponReceiveDao;
use app\common\model\platform\PlatformCouponReceive;
use app\common\repositories\coupon\BuildCouponRepository;
use app\common\repositories\coupon\CouponStocksUserRepository;
use app\common\repositories\platform\PlatformCouponReceiveRepository;
use app\common\repositories\platform\PlatformCouponRepository;
use app\common\repositories\wechat\WechatUserRepository;
use app\controller\api\risk\Risk;
use crmeb\basic\BaseController;
use think\exception\ValidateException;

class ReceiveCoupon extends BaseController
{
    /**
     * 领券时调用
     *
     * @param CouponStocksUserRepository $couponStocksUserRepository
     *
     * @return mixed
     */
    public function receive_bak(CouponStocksUserRepository $couponStocksUserRepository)
    {
        $params = $this->request->post();
        foreach ($params['coupon'] as $item) {
            if ($adId = $item['ad_id'] ?? '') {
                $couponCode = $item['coupon_code'] ?? '';
                if (empty($couponCode)) {
                    throw new ValidateException('券编码不能为空');
                }
                $where = [
                    'coupon_code' => $item['coupon_code'],
                ];
                $data = [
                    'ad_id' => $adId,
                ];

                $couponStocksUserRepository->updateWhere($where, $data);
            }
        }

        return app('json')->success([]);
    }

    public function receive(CouponStocksUserRepository $couponStocksUserRepository)
    {
        $params = $this->request->post();
        $uid = $this->request->uid();
        $user = $this->request->userInfo();
        $wechatUserId = $user->wechat_user_id;

        /**
         * @var WechatUserRepository $wechatUserRepository
         */
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $wechatUser = $wechatUserRepository->getOne(['wechat_user_id' => $wechatUserId], 'wechat_user_id, unionid, routine_openid');

        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);

        foreach ($params['coupon'] as $item) {
            $couponCode = $item['coupon_code'] ?? '';
            $stockId = $item['stock_id'] ?? '';
            $stockInfo = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
            if (empty($couponCode) || empty($stockInfo)) {
                throw new ValidateException('券编码不能为空');
            }

            // 计算券的开始和结束时间
            list($start, $end) = $couponStocksUserRepository->calculateCouponAvailableTime($stockInfo);

            $where = [
                'coupon_code' => $item['coupon_code'],
            ];
            if (!$item["out_request_no"]){
                throw new ValidateException('商户号不存在');
            }
            $out_request_no = explode("_",$item["out_request_no"]);

            if (count($out_request_no) < 2){
                throw new ValidateException('商户号不存在');
            }
            $data = [
                'mer_id'      => $stockInfo['mer_id'] ?? 0,
                'coupon_id'   => $stockInfo['id'] ?? 0,
                'ad_id'       => $item['ad_id'] ?? 0,
                'uid'         => $uid,
                'coupon_code' => $item['coupon_code'],
                'unionid'     => $wechatUser['unionid'],
                'stock_id'    => $stockId,
                'start_at'    => $start,
                'end_at'      => $end,
                'appid'      => $out_request_no[0],
                'mch_id'      => $out_request_no[1],
                'create_time' => date('Y-m-d H:i:s'),
            ];

            $couponStocksUserRepository->createUpdate($where, $data);
            $buildCouponRepository->incField($stockInfo['id'], 'sended');
        }
        //调用黑名单规则
        /**
         * @var Risk $risk
         */
        $risk =  app()->make(Risk::class);
        $risk->checkBlackApi($uid);
        return app('json')->success([]);
    }


    public function isMerCoupon($item,$uid,$wechatUser){
        $buildCouponRepository = app()->make(BuildCouponRepository::class);

        $couponStocksUserRepository  = app()->make(CouponStocksUserRepository::class);
        $couponCode = $item['coupon_code'] ?? '';
        $stockId = $item['stock_id'] ?? '';
        $stockInfo = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
        // 券核销类型
        $typeData = (int)$stockInfo['type_date'] ?: 0;

        if (empty($couponCode) || empty($stockInfo)) {
            throw new ValidateException('券编码不能为空');
        }

        // 计算券的开始和结束时间
        list($startTime, $endTime) = $couponStocksUserRepository->calculateCouponAvailableTime($stockInfo);

        $where = [
            'coupon_code' => $item['coupon_code'],
        ];
        if (!$item["out_request_no"]){
            throw new ValidateException('商户号不存在');
        }
        $out_request_no = explode("_",$item["out_request_no"]);

        if (count($out_request_no) < 2){
            throw new ValidateException('商户号不存在');
        }
        $data = [
            'mer_id'      => $stockInfo['mer_id'] ?? 0,
            'ad_id'       => $item['ad_id'] ?? 0,
            'coupon_id'   => $stockInfo['id'] ?? 0,
            'uid'         => $uid,
            'coupon_code' => $item['coupon_code'],
            'unionid'     => $wechatUser['unionid'],
            'stock_id'    => $stockId,
            'start_at'    => $startTime,
            'end_at'      => $endTime,
            'appid'       => $out_request_no[0],
            'mch_id'      => $out_request_no[1],
            'create_time' => date('Y-m-d H:i:s'),
        ];
        $couponStocksUserRepository->createUpdate($where, $data);
    }


    public function isPlatformCoupon($item,$uid,$type){
        $date = date('Y-m-d').' 00:00:00';

        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $platformCouponDao = app()->make(PlatformCouponDao::class);


        $platformCouponReceiveRepository = app()->make(PlatformCouponReceiveRepository::class);
        $couponCode = $item['coupon_code'] ?? '';
        $stockId = $item['stock_id'] ?? '';
        $stockInfo = $platformCouponDao->getWhere(['stock_id' => $stockId]);
        if (empty($couponCode) || empty($stockInfo)) {
            throw new ValidateException('券编码不能为空');
        }

        $where = [
            'coupon_code' => $item['coupon_code'],
        ];
        if (!$item["out_request_no"]){
            throw new ValidateException('商户号不存在');
        }
        $out_request_no = explode("_",$item["out_request_no"]);

        if (count($out_request_no) < 2){
            throw new ValidateException('商户号不存在');
        }
        $data = [
            'platform_coupon_id'       => $stockInfo['platform_coupon_id'] ?? 0,
            'user_id'         => $uid,
            'coupon_code' => $item['coupon_code'],
            'discount_num'     => $stockInfo['discount_num'],
            'stock_id'    => $stockId,
            'start_use_time'    => $date,
            'end_use_time'      => date('Y-m-d',time() + (($stockInfo['effective_day_number'] - 1)*60*60*24)).' 23:59:59' ,
            'appid'      => $out_request_no[0],
            'mch_id'      => $out_request_no[1],
            'use_type'      =>$type,
            'create_time' => date('Y-m-d H:i:s'),
        ];

        $platformCouponReceiveRepository->createUpdate($where, $data);

        //已领取数量加1
        \app\common\model\platform\PlatformCoupon::getDB()->where(['stock_id' => $stockId])->inc("received",1)->update();
    }


//平台券|商户券领取领取
    public function receivePlatformCoupon()
    {
        $params = $this->request->post();
        $type = $this->request->post('type',0);
        $uid = $this->request->uid();
        $user = $this->request->userInfo();
        $wechatUserId = $user->wechat_user_id;


        /**
         * @var WechatUserRepository $wechatUserRepository
         */
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $wechatUser = $wechatUserRepository->getOne(['wechat_user_id' => $wechatUserId], 'wechat_user_id, unionid, routine_openid');

        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);

        $platformCouponDao = app()->make(PlatformCouponDao::class);

        foreach ($params['coupon'] as $item) {
            $stockId = $item['stock_id'] ?? '';
            $couponCode = $item['coupon_code'] ?? '';

            //平台券
            $stockInfoPlatform = $platformCouponDao->getWhere(['stock_id' => $stockId]);

            //商户券
            $stockInfo = $buildCouponRepository->stockProduct(['stock_id' => $item['stock_id']]);


            if (empty($couponCode) || (empty($stockInfo) && empty($stockInfoPlatform))) {
                throw new ValidateException('券编码不能为空');
            }

            if ($stockInfo){
                $this->isMerCoupon($item,$uid,$wechatUser);
            }

            if ($stockInfoPlatform){
                $this->isPlatformCoupon($item,$uid,$type);
            }

        }
        //调用黑名单规则
        /**
         * @var Risk $risk
         */
        $risk =  app()->make(Risk::class);
        $risk->checkBlackApi($uid);

        return app('json')->success([]);
    }

}