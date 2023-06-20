<?php

namespace app\controller\api\internal;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use crmeb\jobs\OrderReplyJob;
use think\App;
use app\common\repositories\article\ArticleRepository as repository;
use crmeb\basic\BaseController;
use think\facade\Log;
use think\facade\Queue;

class AutoTakeOrder extends BaseController
{
    /**
     * @var repository
     */
    protected $storeOrderRepository;
    protected $storeOrderStatusRepository;

    /**
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app,StoreOrderRepository $storeOrderRepository, StoreOrderStatusRepository $storeOrderStatusRepository)
    {
        parent::__construct($app);
        $this->storeOrderRepository = $storeOrderRepository;
        $this->storeOrderStatusRepository = $storeOrderStatusRepository;
    }

    /**
     * @return mixed
     * @author Qinii
     */
    public function httpTrigger()
    {
        Log::info("正在执行 自动收货 任务");

        $timer = ((int)systemConfig('auto_take_order_timer')) ?: 15;
        Log::info("使用的系统配置 auto_take_order_timer ${timer}");
        $time = date('Y-m-d H:i:s', strtotime("- $timer day"));
        $ids = $this->storeOrderStatusRepository->getTimeoutDeliveryOrder($time);
        Log::info("截止 ${time} 需要自动收货的订单ID: [" . implode(",", $ids) . "]");

        foreach ($ids as $id) {
            try {
                $this->storeOrderRepository->takeOrder($id);
                Queue::push(OrderReplyJob::class, $id);
            } catch (\Exception $e) {
                Log::error('自动收货失败:' . $e->getMessage());
            }
        }
    }

}
