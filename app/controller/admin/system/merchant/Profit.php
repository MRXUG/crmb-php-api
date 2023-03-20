<?php

namespace app\controller\admin\system\merchant;

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
        return app('json')->success($this->recordRepo->getPagedList('*', $where, $page, $limit));
    }

    public function export()
    {
        [$page, $limit] = $this->getPage();
        /* @var ExcelService $serv*/
        $serv = app()->make(ExcelService::class);
        $data = $serv->profitList($page, $limit);
        return app('json')->success($data);
    }

    // 累计收益
    public function sum()
    {
        $sum = $this->repo->getProfitSum();
        return app('json')->success(['sum' => $sum]);
    }
}