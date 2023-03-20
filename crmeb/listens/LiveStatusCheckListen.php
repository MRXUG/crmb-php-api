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


namespace crmeb\listens;


use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\OrderFlow;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\store\order\StoreOrderProfitsharingRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use app\common\repositories\system\merchant\PlatformMerchantRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\LiveStatusCheckJob;
use crmeb\jobs\OrderProfitsharingJob;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\db\exception\DbException;
use think\Exception;
use think\exception\InvalidArgumentException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\Log;

class LiveStatusCheckListen extends TimerService implements ListenerInterface
{
    protected string $name = "job(tick)存活检测" . __CLASS__;

    public function handle($params): void
    {
        $this->tick(5000, function () {
            $uniqueId = mini_unique_id(4);
            \think\facade\Queue::push(LiveStatusCheckJob::class, $uniqueId, $queue = null);
        });
    }
}
