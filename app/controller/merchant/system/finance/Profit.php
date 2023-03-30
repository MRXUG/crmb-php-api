<?php
namespace app\controller\merchant\system\finance;

use app\common\repositories\system\merchant\MerchantProfitDayLogRepository;
use app\common\repositories\system\merchant\MerchantProfitRecordRepository;
use app\common\repositories\system\merchant\MerchantProfitRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

class Profit extends BaseController
{
    public function __construct(App $app, MerchantProfitRecordRepository $recordRepo, MerchantProfitRepository $repo)
    {
        parent::__construct($app);
        $this->repo = $repo;
        $this->recordRepo = $recordRepo;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([]);
        $where['mer_id'] = $this->request->merId();
        $fields = 'profit_id,mer_id,total_money as profit_money,update_time as profit_affect_time';

        $serv = app()->make(MerchantProfitDayLogRepository::class);
        $list = $serv->getPagedListSimple($fields,$where, $page, $limit);

        return app('json')->success($list);
    }

    public function export()
    {
        [$page, $limit] = $this->getPage();
        /**
         * @var $serv ExcelService
         */
        $serv = app()->make(ExcelService::class);
        $where['profit_mer_id'] = $this->request->merId();
        $data = $serv->profitListSimple($where, $page, $limit);
        return app('json')->success($data);
    }

}