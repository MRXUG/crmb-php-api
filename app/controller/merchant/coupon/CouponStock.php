<?php

namespace app\controller\merchant\coupon;

use app\common\model\store\product\ProductAttrValue;
use app\common\model\store\product\ProductSku;
use app\common\repositories\coupon\CouponStocksRepository;
use app\common\repositories\coupon\CouponStocksUserRepository;
use app\common\repositories\coupon\StockProductRepository;
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
    /**
     * @var StockProductRepository
     */
    private $productRepository;

    public function __construct(
        App $app,
        CouponStocksRepository $repository,
        CouponStocksUserRepository $userRepository,
        StockProductRepository $productRepository
    ) {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->productRepository = $productRepository;
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
        $params = $this->request->params(['status', 'stock_name', 'type', 'stock_id', 'mch_id']);
        $params['mer_id'] = $this->request->merId();

        return app('json')->success($this->repository->list($page, $limit, $params, 0));
    }

    public function getMinAmountSku($id)
    {
        $minPrice = ProductAttrValue::getDB()->where('product_id', $id)->order('price', 'asc')->find();

        return app('json')->success([
            $minPrice
        ]);
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
        $params =
            $this->request->params(['stock_name', 'nickname', 'written_off', 'coupon_user_id', 'stock_id', 'mch_id','coupon_code']);
        $params['mer_id'] = $this->request->merId();

        $params['status'] = $this->request->param("status","");

        return app('json')->success($this->userRepository->list($page, $limit, $params, $params['mer_id']));
    }

    /**
     * 优惠券详情
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 20:15
     */
    public function show($id)
    {
        return app('json')->success($this->repository->show($id));
    }

    /**
     * 优惠券失效
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 21:21
     */
    public function failure($id)
    {
        $this->repository->failure($id);
        return app('json')->success('已失效');
    }

    /**
     * 优惠券取消
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 21:22
     */
    public function cancelled($id)
    {
        $this->repository->cancelled($id);
        return app('json')->success('已取消');
    }

    /**
     * 删除优惠券
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/9 16:28
     */
    public function delete($id)
    {
        $this->repository->delete($id);
        return app('json')->success('已删除');
    }
}
