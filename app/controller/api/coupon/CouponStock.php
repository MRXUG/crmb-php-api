<?php

namespace app\controller\api\coupon;

use app\common\dao\platform\PlatformCouponDao;
use app\common\repositories\coupon\CouponConfigRepository;
use app\common\repositories\coupon\CouponStocksRepository;
use app\common\repositories\coupon\CouponStocksUserRepository;
use crmeb\basic\BaseController;
use think\App;

class CouponStock extends BaseController
{

    /**
     * @var CouponStocksRepository
     */
    private $repository;
    /**
     * @var CouponStocksUserRepository
     */
    private $userRepository;

    public function __construct(App $app, CouponStocksRepository $repository, CouponStocksUserRepository $userRepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userRepository = $userRepository;
    }

    /**
     * 优惠券列表
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 16:58
     */
    public function list()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['status', 'stock_name','type', 'stock_id']);

        return app('json')->success($this->repository->list($page, $limit, $params, 0));
    }

    /**
     * 优惠券领取列表
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 16:58
     */
    public function receiveList()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['stock_name', 'nickname','written_off', 'coupon_user_id', 'stock_id', 'uid']);
        $params['time'] = date('Y-m-d H:i:s');
        return app('json')->success($this->userRepository->list($page, $limit, $params, 0));
    }

    //获取用户弹窗（平台券）
    public function getPlatformCoupon(){
        $uid = $this->request->param('uid',0);
        $type = $this->request->param('type',0);

        $couponConfigRepository = app()->make(CouponConfigRepository::class);

        //查询用户是那种类型
        $userType = $couponConfigRepository->getUserType($uid);

        //用户是否可以发券(返回的是可发券数量)
        $userIssueCoupons = $couponConfigRepository->userSuitablePlatformCoupon($uid,$type);

        if ($userIssueCoupons == 0)return app('json')->fail('无券');

        $date = date('Y-m-d H:i:s');
        // 查询平台优惠券
        $platformCouponDao = app()->make(PlatformCouponDao::class);
        $list = $platformCouponDao->getPopupsPlatformCoupon([
            ['crowd','in',[1,$userType]],
            ['receive_start_time','>',$date],
            ['receive_end_time','<',$date],
        ],$userIssueCoupons);

        return app('json')->success($list);

    }
}