<?php

namespace app\controller\api\coupon;

use app\common\dao\platform\PlatformCouponDao;
use app\common\model\platform\PlatformCouponReceive;
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

    /**
     * 优惠券领取列表
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 16:58
     */
    public function receiveList2()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['stock_name', 'nickname','written_off', 'coupon_user_id', 'stock_id', 'uid']);
        $params['time'] = date('Y-m-d H:i:s');
        return app('json')->success($this->userRepository->list2($page, $limit, $params, 0));
    }

    //获取用户弹窗（平台券）
    public function getPlatformCoupon(){
        $uid = $this->request->uid();
        $type = $this->request->param('type',0);

        if ($uid == 0)return app('json')->fail('用户信息错误');
        if ($type == 0)return app('json')->fail('弹窗类型错误');



        $couponConfigRepository = app()->make(CouponConfigRepository::class);

        //查询发券风险设置
        $couponConfig = $couponConfigRepository->getCouponConfig();

        //查询用户是那种类型
        $userType = $couponConfigRepository->getUserType($uid);

        // if ($userType == 0)return app('json')->fail('用户信息错误');

        //用户是否可以发券(返回的是可发券数量)
        $userIssueCoupons = $couponConfigRepository->userSuitablePlatformCoupon($uid,$type,$couponConfig);

        if ($userIssueCoupons == 0)return app('json')->fail('无券');

        $date = date('Y-m-d H:i:s');
        // 查询平台优惠券
        $platformCouponDao = app()->make(PlatformCouponDao::class);
        $list = $platformCouponDao->getPopupsPlatformCoupon([
            ['crowd','in',[1,$userType]],
            ['receive_start_time','<',$date],
            ['receive_end_time','>',$date],
            ['status','=',1],
        ],$userIssueCoupons,$uid,$type);

        return app('json')->success(['closeClickToSendCoupons'=>$couponConfig['closeClickToSendCoupons'],'list'=>$list,'platformCouponNum'=>$userIssueCoupons,'couponConfig'=>$couponConfig]);

    }

    //用户平台券转商家券记录次数
    public function userReceivePlatformCoupon()
    {
        $platform_coupon_id = $this->request->param('platform_coupon_id',0);
        $uid = $this->request->uid();

        if (!$platform_coupon_id)return app('json')->fail('无券');

        $res = PlatformCouponReceive::getDB()->where([['user_id','=',$uid],['platform_coupon_id','=',$platform_coupon_id]])->inc('transform_num',1)->update();
        return app('json')->success('保存成功');
    }
}
