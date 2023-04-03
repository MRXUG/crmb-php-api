<?php

namespace app\controller\admin\coupon;

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
     * @date    2023/3/7 16:57
     */
    public function list()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['status', 'stock_name', 'type', 'stock_id', 'mch_id']);
        $params['mch_id'] = !empty($params['mch_id']) ? $params['mch_id'] : 0;

        return app('json')->success($this->repository->list($page, $limit, $params, $params['mch_id']));
    }

    /**
     * 优惠券领取列表
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 16:57
     */
    public function receiveList()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['stock_name', 'nickname','written_off', 'coupon_user_id', 'stock_id', 'mch_id','coupon_code']);
        $params['mch_id'] = !empty($params['mch_id']) ? $params['mch_id'] : 0;
        return app('json')->success($this->userRepository->list($page, $limit, $params, $params['mch_id']));
    }
}