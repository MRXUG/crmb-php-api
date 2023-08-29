<?php

namespace app\controller\merchant\DataCenter;

use app\common\repositories\merchant\DataCenter\DataCenterDashboardRepository;
use app\validate\merchant\DashboardValidate;
use crmeb\basic\BaseController;
use think\App;

class DataCenterDashboardController extends BaseController
{

    /**
     * @var DataCenterDashboardRepository
     */
    private $repository;

    public function __construct(
        App $app,
        DataCenterDashboardRepository $repository
    ) {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 数据看板中心
     *
     * @return mixed
     * @author  lucky
     * @date    2023/8/3 11:00
     */
    public function getStatisticsData()
    {
        $params = $this->request->params(['start_date', 'end_date', 'last_info']);
        app()->make(DashboardValidate::class)->check($params);
        //var_dump(124124);exit;
        if(strtotime($params['start_date']) > strtotime($params['end_date'])){
            return app('json')->fail('时间选择错误');
        }
        $mer_id = $this->request->merId();
        $info = $this->repository->main($mer_id, $params);
        if(isset($params['last_info']) && !empty($params['last_info'])){
            $time = strtotime($params['end_date']) - strtotime($params['start_date']);
            $lastDayEnd = date('Y-m-d', strtotime($params['start_date']) - 1);
            $lastDayStart = date('Y-m-d', strtotime($params['start_date']) - $time - 1);
            $lastInfo = $this->repository->main($mer_id, ['start_date' => $lastDayStart, 'end_date' => $lastDayEnd]);
            return app('json')->success(['info' => $info, 'last_info' => $lastInfo]);
        }
        return app('json')->success($info);
    }
}
