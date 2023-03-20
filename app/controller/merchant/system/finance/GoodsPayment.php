<?php

namespace app\controller\merchant\system\finance;

use app\common\model\system\merchant\MerchantGoodsPayment;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use app\common\repositories\system\merchant\MerchantProfitRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

/**
 * 货款
 */
class GoodsPayment extends BaseController
{
    /**
     * @param  App  $app
     * @param  OrderFlowRepository  $repo
     * @param  MerchantGoodsPaymentRepository  $payRepo
     */
    public function __construct(App $app, OrderFlowRepository $repo, MerchantGoodsPaymentRepository $payRepo)
    {
        parent::__construct($app);
        $this->repo = $repo;
        $this->payRepo = $payRepo;
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
            'date',
        ]);
        $params['mer_id'] = $this->request->merId();
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->payRepo->getPagedList($params, $page, $limit));
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
            'date',
        ]);
        $params['mer_id'] = $this->request->merId();
        [$page, $limit] = $this->getPage();
        /* @var ExcelService $serv */
        $serv = app()->make(ExcelService::class);
        $data = $serv->getGoodsPaymentListExport($params, $page, $limit,ExcelService::FROM_MERCHANT);
        return app('json')->success($data);
    }

    /**
     * @return mixed
     */
    public function stat()
    {
        // 待结算货款预估
        $goodsMoney = $this->payRepo->getSettlementSum([
            'settlement_status' => MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE,
            'mer_id'            => $this->request->merId()
        ]);
        /* @var MerchantProfitRepository $profitRepo */
        $profitRepo = app()->make(MerchantProfitRepository::class);
        $totalProfit = $profitRepo->getProfitSum(['mer_id' => $this->request->merId()]);
        // 分佣比例
        /* @var ConfigValueRepository $configRepo*/
        $configRepo = app()->make(ConfigValueRepository::class);
        $profitRateConfigs = $configRepo->getProfitSharingSetting();
        // 累计收益
        $data = [
            'goods_money'         => is_null($goodsMoney) ? '0.00' : sprintf('%.2f', $goodsMoney),
            'total_profit'        => is_null($totalProfit) ? '0.00' : sprintf('%.2f', $totalProfit),
            'profit_sharing_rate' => [
                'ad_order_deposit_rate'     => $profitRateConfigs['profit_sharing_advertising_flow_deposit'] ?? 0,
                'nature_order_sharing_rate' => $profitRateConfigs['profit_sharing_natural_flow'] ?? 0,
                'back_order_sharing_rate'   => $profitRateConfigs['profit_sharing_return_flow_rate'] ?? 0,
            ]
        ];
        return app('json')->success($data);
    }
}
