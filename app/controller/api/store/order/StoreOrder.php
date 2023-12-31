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


namespace app\controller\api\store\order;


use app\common\model\system\merchant\MerchantProfitRecord;
use app\common\repositories\coupon\CouponStocksUserRepository;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\StoreOrderCreateRepository;
use app\common\repositories\store\order\StoreOrderReceiptRepository;
use app\common\repositories\system\merchant\MerchantProfitRecordRepository;
use app\validate\api\UserReceiptValidate;
use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\services\ExpressService;
use crmeb\services\LockService;
use think\App;
use think\facade\Db;
use think\exception\ValidateException;
use app\common\model\store\order\StoreOrder as StoreOrderModel;


/**
 * Class StoreOrder
 * @package app\controller\api\store\order
 * @author xaboy
 * @day 2020/6/10
 */
class StoreOrder extends BaseController
{
    /**
     * @var StoreOrderRepository
     */
    protected $repository;

    /**
     * StoreOrder constructor.
     * @param App $app
     * @param StoreOrderRepository $repository
     */
    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 确认订单、校验订单
     *
     * @param StoreCartRepository $cartRepository
     * @param StoreOrderCreateRepository $orderCreateRepository
     *
     * @return mixed
     */
    public function v2CheckOrder(StoreCartRepository $cartRepository, StoreOrderCreateRepository $orderCreateRepository)
    {
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $couponIds = (array)$this->request->param('use_coupon', []);
        $takes = (array)$this->request->param('takes', []);
        $useIntegral = (bool)$this->request->param('use_integral', false);
        $clipCoupons = (int)$this->request->param('clipCoupons', 1);
        $user = $this->request->userInfo();
        $uid = $user->uid;
        // 营销页优惠
        $marketingDiscount = (array) $this->request->param('marketing_discount', []);

        if ($clipCoupons == 2) {
            $couponIds = [];
        }

        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');

        $orderInfo = $orderCreateRepository->v2CartIdByOrderInfo2($user, $cartId, $takes, $couponIds, $useIntegral, $addressId, false, $marketingDiscount, [], $clipCoupons);
        $orderInfo['auto_check_purchase_protection'] = (int)(systemConfig('auto_check_purchase_protection') ?? 0);
        return app('json')->success($orderInfo);
    }


    /**
     * 查询可用优惠券
     * @return mixed
     */
    public function getUserBeforeOneCoupon(){
        $money = (double) $this->request->param('money', 0);
        if ($money == 0){
            return app('json')->success([]);
        }
        $productId = (int)$this->request->param('productId', 0);
        if ($productId == 0){
            return app('json')->fail('数据无效');
        }
        $merId = (int)$this->request->param('merId', 0);

        if ($productId == 0){
            return app('json')->fail('数据无效');
        }

        $user = $this->request->userInfo();
        $uid = $user->uid;
        $goodsInfo = [
            "price"=>$money,
            "goods_id"=>$productId,
            "origin_amount"=>$money,
        ];
        /** @var CouponStocksUserRepository $couponUser */
        $couponUser = app()->make(CouponStocksUserRepository::class);
        $checkCouponList = $couponUser->best($uid, $merId, $goodsInfo,$money);

        return app('json')->success($checkCouponList);

    }

    /**
     * 提交订单
     *
     * @param StoreCartRepository $cartRepository
     * @param StoreOrderCreateRepository $orderCreateRepository
     *
     * @return mixed
     */
    public function v2CreateOrder(StoreCartRepository $cartRepository, StoreOrderCreateRepository $orderCreateRepository)
    {
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $couponIds = (array)$this->request->param('use_coupon', []);
        $takes = (array)$this->request->param('takes', []);
        $useIntegral = (bool)$this->request->param('use_integral', false);
        $receipt_data = (array)$this->request->param('receipt_data', []);
        $extend = (array)$this->request->param('extend', []);
        $mark = (array)$this->request->param('mark', []);
        $payType = $this->request->param('pay_type');
        $post = (array)$this->request->param('post');
        $clipCoupons = (int)$this->request->param('clipCoupons',1);
        // 营销页优惠
        $marketingDiscount = (array)$this->request->param('marketing_discount', []);
        $ad_type = (int)$this->request->param('ad_type',0);
        $ad_query = $this->request->param('gdt_params','');
        $order_scenario = $this->request->param('order_scenario', 0); //下单场景 具体见model:StoreOrder
        if ($clipCoupons == 2) {
            $couponIds = [];
        }

        if ($ad_query!=''){
            $ad_query['unionid'] = $this->request->unionid();
            $ad_query['appid']  = $this->request->header('appid');
            $ad_query = json_encode($ad_query);
        }

        $isPc = $payType === 'pc';
        if ($isPc) {
            $payType = 'balance';
        }

        if (!in_array($payType, StoreOrderRepository::PAY_TYPE, true))
            return app('json')->fail('请选择正确的支付方式');

        $validate = app()->make(UserReceiptValidate::class);
        foreach ($receipt_data as $receipt) {
            if (!is_array($receipt)) throw new ValidateException('发票信息有误');
            $validate->check($receipt);
        }

        $uid = $this->request->uid();
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');
        // if (!$addressId)
        //     return app('json')->fail('请选择地址');

        $groupOrder = app()->make(LockService::class)->exec('order.create', function () use ($orderCreateRepository, $receipt_data, $mark, $extend, $cartId, $payType, $takes, $couponIds, $useIntegral, $addressId, $post, $marketingDiscount,$clipCoupons,$ad_type,$ad_query, $order_scenario) {
            return $orderCreateRepository->v2CreateOrder2(array_search($payType, StoreOrderRepository::PAY_TYPE),
                $this->request->userInfo(), $cartId, $extend, $mark, $receipt_data, $takes, $couponIds, $useIntegral,
                $addressId, $post, $marketingDiscount, [], $clipCoupons,$ad_type,$ad_query, $order_scenario);
        });

        if ($groupOrder['pay_price'] == 0) {
            return app('json')->status('error', "支付金额不能为0", ['order_id' => $groupOrder->group_order_id]);

            // $this->repository->paySuccess($groupOrder);
            //     return app('json')->status('success', '支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }
        if ($isPc) {
            return app('json')->success(['order_id' => $groupOrder->group_order_id]);
        }
        try {
            return $this->repository->pay($payType, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'), $this->request->isApp());
        } catch (\Exception $e) {
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    /**
     * 提交订单2
     *
     * @param StoreCartRepository $cartRepository
     * @param StoreOrderCreateRepository $orderCreateRepository
     *
     * @return mixed
     */
    public function v2CreateOrder2(StoreCartRepository $cartRepository, StoreOrderCreateRepository $orderCreateRepository)
    {
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $couponIds = (array)$this->request->param('use_coupon', []);
        $takes = (array)$this->request->param('takes', []);
        $useIntegral = (bool)$this->request->param('use_integral', false);
        $receipt_data = (array)$this->request->param('receipt_data', []);
        $extend = (array)$this->request->param('extend', []);
        $mark = (array)$this->request->param('mark', []);
        $payType = $this->request->param('pay_type');
        $post = (array)$this->request->param('post');
        $clipCoupons = (int)$this->request->param('clipCoupons',1);
        // 营销页优惠
        $marketingDiscount = (array)$this->request->param('marketing_discount', []);
        // 卡包回流券信息
        $refluxCoil = isset($marketingDiscount['refluxCoil']) ? (array)$marketingDiscount['refluxCoil'] : [];
        $ad_type = (int)$this->request->param('ad_type',1);
        $ad_query = $this->request->param('gdt_params','');
        $order_scenario = $this->request->param('order_scenario',0);
        if ($clipCoupons == 2) {
            $couponIds = [];
        }

        if ($ad_query!=''){
            $ad_query = json_decode($ad_query,true);
            $ad_query['unionid'] = $this->request->unionid();
            $ad_query['appid']  = $this->request->header('appid');
            $ad_query = json_encode($ad_query);
        }

        $isPc = $payType === 'pc';
        if ($isPc) {
            $payType = 'balance';
        }

        if (!in_array($payType, StoreOrderRepository::PAY_TYPE, true))
            return app('json')->fail('请选择正确的支付方式');

        $validate = app()->make(UserReceiptValidate::class);
        foreach ($receipt_data as $receipt) {
            if (!is_array($receipt)) throw new ValidateException('发票信息有误');
            $validate->check($receipt);
        }

        $uid = $this->request->uid();
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');
        // if (!$addressId)
        //     return app('json')->fail('请选择地址');

        $groupOrder = app()->make(LockService::class)->exec('order.create', function () use ($orderCreateRepository,
            $receipt_data, $mark, $extend, $cartId, $payType, $takes, $couponIds, $useIntegral, $addressId,
            $post, $marketingDiscount, $refluxCoil, $clipCoupons,$ad_type,$ad_query, $order_scenario) {
            return $orderCreateRepository->v2CreateOrder2(array_search($payType, StoreOrderRepository::PAY_TYPE),
                $this->request->userInfo(), $cartId, $extend, $mark, $receipt_data, $takes, $couponIds,
                $useIntegral, $addressId, $post, $marketingDiscount, $refluxCoil, $clipCoupons,$ad_type,$ad_query,
                $order_scenario);
        });

        $nowUnixTime = time();
        $userBindMer = Db::name('stock_merchant_user')->where('user_id', $this->request->uid())
            ->order('id desc')
            ->limit(1)
            ->find();
        if (!empty($userBindMer) && strtotime($userBindMer['endtime']) <= $nowUnixTime) {
            // 执行让利逻辑
        }

        // dd("111", $groupOrder);

        if ($groupOrder['pay_price'] == 0) {
            return app('json')->status('error', "支付金额不能为0", ['order_id' => $groupOrder->group_order_id]);
        }
        if ($isPc) {
            return app('json')->success(['order_id' => $groupOrder->group_order_id]);
        }
        try {
            return $this->repository->pay($payType, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'), $this->request->isApp());
        } catch (\Exception $e) {
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/10
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['status'] = $this->request->param('status');

        if (isset($where['status']) && $where['status'] == 0){
            $where["paid"] = 1;
        }

        if ($where['status'] == -2) unset($where['status']);
        $where['search'] = $this->request->param('store_name');
        $where['uid'] = $this->request->uid();
//        $where['paid'] = 1;
        $where['is_user'] = 1;
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * @param $id
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function detail($id)
    {
        $order = $this->repository->getDetail((int)$id, $this->request->uid());
        if (!$order)
            return app('json')->fail('订单不存在');
        if ($order->order_type == 1) {
            $order->append(['take', 'refund_status']);
        }
        return app('json')->success($order->toArray());
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function number()
    {
        return app('json')->success(['orderPrice' => $this->request->userInfo()->pay_price] + $this->repository->userOrderNumber($this->request->uid()));
    }

    /**
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function groupOrderList(StoreGroupOrderRepository $groupOrderRepository)
    {
        [$page, $limit] = $this->getPage();
        $list = $groupOrderRepository->getList(['uid' => $this->request->uid(), 'paid' => 0], $page, $limit);
        return app('json')->success($list);
    }

    /**
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function groupOrderDetail($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrder = $groupOrderRepository->detail($this->request->uid(), (int)$id);
        if (!$groupOrder)
            return app('json')->fail('订单不存在');
        else
            return app('json')->success($groupOrder->append(['cancel_time', 'cancel_unix'])->toArray());
    }

    public function groupOrderStatus($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrder = $groupOrderRepository->status($this->request->uid(), intval($id));
        if (!$groupOrder)
            return app('json')->fail('订单不存在');
        if ($groupOrder->paid) $groupOrder->append(['give_coupon']);
        $activity_type = 0;
        $activity_id = 0;
        foreach ($groupOrder->orderList as $order) {
            $activity_type = max($order->activity_type, $activity_type);
            if ($order->activity_type == 4 && $groupOrder->paid) {
                $order->append(['orderProduct']);
                $activity_id = $order->orderProduct[0]['activity_id'];
            }
        }
        $groupOrder->activity_type = $activity_type;
        $groupOrder->activity_id = $activity_id;
        return app('json')->success($groupOrder->toArray());
    }

    /**
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function cancelGroupOrder($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrderRepository->cancel((int)$id, $this->request->uid());
        return app('json')->success('取消成功');
    }

    /**
     * 订单支付
     *
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function groupOrderPay($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        //TODO 佣金结算,佣金退回,物流查询
        $type = $this->request->param('type');
        if (!in_array($type, StoreOrderRepository::PAY_TYPE))
            return app('json')->fail('请选择正确的支付方式');
        $groupOrder = $groupOrderRepository->detail($this->request->uid(), (int)$id, false);
        if (!$groupOrder)
            return app('json')->fail('订单不存在或已支付');
        $this->repository->changePayType($groupOrder, array_search($type, StoreOrderRepository::PAY_TYPE));
        if ($groupOrder['pay_price'] == 0) {
            $this->repository->paySuccess($groupOrder);
            return app('json')->status('success', '支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }

        try {
            return $this->repository->pay($type, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'), $this->request->isApp());
        } catch (\Exception $e) {
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    public function take($id)
    {
        $this->repository->takeOrder($id, $this->request->userInfo());
        return app('json')->success('确认收货成功');
    }

    public function express($id)
    {
        $order = $this->repository->getWhere(['order_id' => $id, 'is_del' => 0]);
        if (!$order)
            return app('json')->fail('订单不存在');
        if (!$order->delivery_type || !$order->delivery_id)
            return app('json')->fail('订单未发货');
        $express = $this->repository->express($id,null);
        $order->append(['orderProduct']);
        return app('json')->success(compact('express', 'order'));
    }

    public function verifyCode($id)
    {
        $order = $this->repository->getWhere(['order_id' => $id, 'uid' => $this->request->uid(), 'is_del' => 0, 'order_type' => 1]);
        if (!$order)
            return app('json')->fail('订单状态有误');
        return app('json')->success(['qrcode' => $this->repository->wxQrcode($id, $order->verify_code)]);
    }

    public function del($id)
    {
        $this->repository->userDel($id, $this->request->uid());
        return app('json')->success('删除成功');
    }

    public function createReceipt($id)
    {
        $data = $this->request->params(['receipt_type' , 'receipt_title' , 'duty_paragraph', 'receipt_title_type', 'bank_name', 'bank_code', 'address','tel', 'email']);
        $order = $this->repository->getWhere(['order_id' => $id, 'uid' => $this->request->uid(), 'is_del' => 0]);
        if (!$order) return app('json')->fail('订单不属于您或不存在');
        app()->make(StoreOrderReceiptRepository::class)->add($data, $order);
        return app('json')->success('操作成功');
    }

    public function getOrderDelivery($id, DeliveryOrderRepository $orderRepository)
    {
        $res = $orderRepository->show($id, $this->request->uid());
        return app('json')->success($res);
    }

}
