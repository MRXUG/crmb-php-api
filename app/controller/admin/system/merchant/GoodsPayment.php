<?php


namespace app\controller\admin\system\merchant;

use app\common\model\system\merchant\MerchantGoodsPayment;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use app\common\repositories\system\merchant\MerchantProfitRecordRepository;
use app\common\repositories\system\merchant\MerchantProfitRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

class GoodsPayment extends BaseController
{
    public function __construct(App $app, MerchantGoodsPaymentRepository $repo)
    {
        parent::__construct($app);
        $this->repo = $repo;
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function lst()
    {
        $params = $this->request->params([
            'order_sn',
            'settlement_status',
            'merchant_source',
            'service_fee_status',
            'mer_id',
            'platform_mer_id',
            'date',
        ]);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repo->getPagedList($params, $page, $limit));
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function export()
    {
        $params = $this->request->params([
            'order_sn',
            'settlement_status',
            'merchant_source',
            'service_fee_status',
            'mer_id',
            'platform_mer_id',
            'date',
        ]);
        [$page, $limit] = $this->getPage();
        /* @var ExcelService $serv*/
        $serv = app()->make(ExcelService::class);
        $data = $serv->getGoodsPaymentListExport($params, $page, $limit, ExcelService::FROM_PLATFORM);
        return app('json')->success($data);
    }
    // 平台端-平台分佣-统计
    public function serviceFeeStat()
    {
        $totalReceived = $this->repo->getServiceFeeSum(MerchantGoodsPayment::SERVICE_FEE_STATUS_RECEIVED);
        $totalTemporary = $this->repo->getServiceFeeSum(MerchantGoodsPayment::SERVICE_FEE_STATUS_TEMPORARY);
        $totalWaitingReceive = $this->repo->getServiceFeeSum(MerchantGoodsPayment::SERVICE_FEE_STATUS_WAITING_RECEIVE);
        $data = [
            'total_received'        => sprintf('%.2f', $totalReceived),
            'total_temporary'       => sprintf('%.2f', $totalTemporary),
            'total_waiting_receive' => sprintf('%.2f', $totalWaitingReceive)
        ];
        return app('json')->success($data);
    }
}