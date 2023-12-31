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


namespace app\controller\admin\order;

use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\merchant\MerchantBindUserRepository;
use crmeb\basic\BaseController;
use app\common\repositories\store\ExcelRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\order\StoreOrderRepository as repository;
use crmeb\services\ExcelService;
use think\App;

class Order extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    public function lst($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','order_sn','order_type','keywords','username','activity_type','group_order_sn','store_name']);
        $where['reconciliation_type'] = $this->request->param('status', 1);
        $where['mer_id'] = $id;
        return app('json')->success($this->repository->adminMerGetList($where, $page, $limit));
    }

    public function markForm($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->adminMarkForm($id)));
    }

    public function mark($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['admin_mark']);
        $this->repository->update($id, $data);
        return app('json')->success('备注成功');
    }

    public function title()
    {
        $where = $this->request->params(['type', 'date', 'mer_id','keywords','status','username','order_sn','is_trader','activity_type']);
        return app('json')->success($this->repository->getStat($where, $where['status']));
    }
    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function getAllList()
    {
        [$page, $limit] = $this->getPage();
        $where =
            $this->request->params(
                [
                    'type',
                    'date',
                    'mer_id',
                    'keywords',
                    'status',
                    'username',
                    'order_sn',
                    'is_trader',
                    'activity_type',
                    'group_order_sn',
                    'store_name',
                    'platform_source',
                    'merchant_source',
                    'transaction_id',
                    'appid',
                    'ad_channel_id',
                    'ad_account_id',
                    'ad_id',
                    'mer_name',
                ]
            );
        $data = $this->repository->adminGetList($where, $page, $limit);
        return app('json')->success($data);
    }

    public function takeTitle()
    {
        $where = $this->request->params(['date','order_sn','keywords','username','is_trader']);
        $where['take_order'] = 1;
        $where['status'] = '';
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        return app('json')->success($this->repository->getStat($where, ''));
    }

    /**
     * TODO 自提订单列表
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function getTakeList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','order_sn','keywords','username','is_trader']);
        $where['take_order'] = 1;
        $where['status'] = '';
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        return app('json')->success($this->repository->adminGetList($where, $page, $limit));
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function chart()
    {
        return app('json')->success($this->repository->OrderTitleNumber(null,null));
    }

    /**
     * TODO 自提订单头部统计
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function takeChart()
    {
        return app('json')->success($this->repository->OrderTitleNumber(null,1));
    }

    /**
     * TODO 订单类型
     * @return mixed
     * @author Qinii
     * @day 2020-08-15
     */
    public function orderType()
    {
        return app('json')->success($this->repository->orderType([]));
    }

    public function detail($id)
    {
        $data = $this->repository->getOne($id, null);
        if (!$data)
            return app('json')->fail('数据不存在');
        // 下单小程序
        $data['applet_name'] = $data['applet'] ? $data['applet']['name'] : '无';
        return app('json')->success($data);
    }

    public function status($id)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getOrderStatus($id, $page, $limit));
    }

    /**
     * TODO 快递查询
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function express($id)
    {
        if (!$this->repository->getWhereCount(['order_id' => $id, 'delivery_type' => 1]))
            return app('json')->fail('订单信息或状态错误');
        return app('json')->success($this->repository->express($id,null));
    }

    public function reList($id)
    {
        [$page, $limit] = $this->getPage();
        $where = ['reconciliation_id' => $id, 'type' => 0];
        return app('json')->success($this->repository->reconList($where, $page, $limit));
    }

    /**
     * TODO 导出文件
     * @author Qinii
     * @day 2020-07-30
     */
    public function excel()
    {
        $where = $this->request->params(
            [
                'type',
                'date',
                'mer_id',
                'keywords',
                'status',
                'username',
                'order_sn',
                'is_trader',
                'activity_type',
                'group_order_sn',
                'store_name',
                'platform_source',
                'merchant_source',
                'transaction_id',
                'appid',
                'ad_channel_id',
                'ad_account_id',
                'ad_id',
                'mer_name',
            ]
        );
        //没有这个参数
//        if($where['take_order']){
//            $where['verify_date'] = $where['date'];
//            unset($where['date']);
//        }
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->order($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function flow()
    {
        $params = $this->request->params(['order_sn']);
        /* @var $repo StoreOrderRepository */
        $repo = app()->make(StoreOrderRepository::class);
        $data = $repo->getFlows([$params['order_sn']]);
        return app('json')->success($data[0]);
    }
    /**
     * 多个订单的流水记录导出
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function flowExport()
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
        /* @var ExcelService $serv */
        $serv = app()->make(ExcelService::class);
        $data = $serv->getFlowExport($params, ExcelService::FROM_PLATFORM, $page, $limit);
        return app('json')->success($data);
    }

    //todo-fw 2023/3/13 10:36:
    public function test(){
        /* @var StoreOrderRepository $orderRepo */
        $orderRepo = app()->make(StoreOrderRepository::class);
        $orderRepo->testGetGroupOrder(15718);
        echo 'end';
    }
}
