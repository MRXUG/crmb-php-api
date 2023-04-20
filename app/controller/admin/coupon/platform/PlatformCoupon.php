<?php

namespace app\controller\admin\coupon\platform;

use app\common\dao\store\product\ProductDao;
use app\common\repositories\platform\PlatformCouponRepository;
use crmeb\basic\BaseController;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\Request;

class PlatformCoupon extends BaseController
{
    private PlatformCouponRepository $repository;

    public function __construct(App $app, PlatformCouponRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     *
     * @param Request $request
     * @return void
     */
    public function lst(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $where = $request->param();
        unset($where['page'], $where['limit']);

        return app('json')->success($this->repository->platformCouponList($page, $limit, $where));
    }

    /**
     * 创建时预估商品列表
     *
     * @return void
     */
    public function createEstimateGoods (Request $request)
    {
        $discount_num = (int) $request->post('discount_num'); # 面值
        $threshold = (int) $request->post('threshold'); # 门槛
        $scope_id_arr = (array) $request->post('scope_id_arr'); # 使用范围 id 信息
        $use_type = (int) $request->post('use_type'); # 使用范围 1 全部商户 2 指定商品分类 3 指定商户 4 指定商户分类
        $receive_start_time = (string) $request->post('receive_start_time'); # 领取开始时间
        $receive_end_time = (string) $request->post('receive_end_time'); # 领取结束时间
        # 验证数据
        if (in_array($use_type, [2, 3, 4]) && empty($scope_id_arr)) {
            throw new ValidateException('请正确的选择商品使用范围');
        }
        # 调用商品预估
        $resultCode = $this->repository->productEstimate(
            $discount_num,
            $threshold,
            $use_type,
            $scope_id_arr,
            $receive_start_time,
            $receive_end_time
        );

        return app('json')->success(compact(
            'resultCode'
        ));
    }

    /**
     * 获取预估商品结果 轮询
     *
     * @return void
     */
    public function getEstimateGoodsResult(string $resultCode)
    {
        $data = Cache::get("EstimatePlatformCouponProduct:{$resultCode}") ?? [];
        return $this->json()->success($data);
    }

    /**
     * 选卷
     *
     * @param Request $request
     * @return mixed
     * @throws null
     */
    public function selectCoupon(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        return app('json')->success($this->repository->selectCoupon($page, $limit));
    }

    /**
     * 选择优惠券数据
     *
     * @param int $discountNum
     * @return mixed
     */
    public function getCouponOne(int $discountNum)
    {
        return $this->json()->success($this->repository->selectCouponOne($discountNum));
    }

    /**
     * 商户明细
     *
     * @param int $amount
     * @param Request $request
     * @return void
     */
    public function merDetails(int $amount, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        return app('json')->success($this->repository->platformCouponMerDetails($amount, $page, $limit));
    }

    /**
     * 创建
     *
     * @param Request $request
     * @return void
     */
    public function create(Request $request)
    {
        $this->repository->save($request->post());
        return app('json')->success();
    }

    /**
     * 修改
     *
     * @param int $id
     * @param Request $request
     * @return mixed
     */
    public function update(int $id, Request $request) {
        $this->repository->save($request->post(), $id);
        return app('json')->success();
    }

    /**
     * 修改优惠券状态
     *
     * @param int $id
     * @param Request $request
     * @return mixed
     */
    public function updateStatus(int $id, Request $request)
    {
        $status = $request->post('status');
        $this->repository->updateStatus($id, $status);
        return $this->json()->success();
    }

    /**
     * 获取优惠券个数
     *
     * @return mixed
     */
    public function getCouponStatusCount()
    {
        return $this->json()->success($this->repository->getStatusCount());
    }

    /**
     * 获取编辑优惠券商品基本信息
     *
     * @param int $id
     * @return mixed
     */
    public function getEditCouponProductInfo(int $id)
    {
        return $this->json()->success($this->repository->getEditCouponProductInfo($id));
    }

    /**
     * 获取编辑优惠券商品列表
     *
     * @param int $id
     * @param Request $request
     * @return void
     */
    public function getEditCouponProductList(int $id, Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        $params = $request->param();
        unset($params['page'], $params['limit']);

        return $this->json()->success($this->repository->getEditCouponProductList($id, $page, $limit, $params));
    }

    /**
     * 修改商品信息
     *
     * @param int $productId
     * @param Request $request
     * @return void
     * @throws null
     */
    public function updateProduct(int $productId, Request $request)
    {
        $params = $request->post();
        if (empty($params)) throw new ValidateException('没有需要处理的数据');
        foreach ($params as $k => $v) if (!in_array($k, ['is_used', 'sort'])) throw new ValidateException('参数错误');
        /** @var ProductDao $productDao */
        $productDao = app()->make(ProductDao::class);
        $productDao->update($productId, $params);
        return $this->json()->success();
    }

    /**
     * 范围计数
     *
     * @param Request $request
     * @return void
     */
    public function scopeCount(Request $request)
    {
        return $this->json()->success($this->repository->scopeCount($request->post('scope', [])));
    }

    public function getEditPlatformCouponData(int $id)
    {
        return $this->json()->success($this->repository->getPlatformCouponOne($id));
    }

    public function platformCouponStatusUpdate(int $id, Request $request)
    {
        $this->repository->platformCouponStatusUpdate($id, $request->param());
        return $this->json()->success();
    }
}
