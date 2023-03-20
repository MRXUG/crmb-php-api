<?php


namespace app\common\repositories\store\order;


use app\common\dao\store\order\OrderFlowDao;
use app\common\repositories\BaseRepository;

class OrderFlowRepository extends BaseRepository
{
    public function __construct(OrderFlowDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 记录退款订单流水
     *
     * @param $insert
     *
     * @return \app\common\dao\BaseDao|\think\Model
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/8 17:28
     */
    public function refundOrderFlowWrite($insert)
    {
        return $this->dao->create($insert);
    }

    public function getPagedList($fields,$where,$page,$limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)
            ->field($fields)
            ->select()
            ->toArray();

        return compact('count', 'list');
    }

    /**
     * @param $fields
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     */
    public function getPagedListFromPayment($where,$page,$limit){
        $query = $this->dao->searchWithOrder($where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)
            ->select()
            ->toArray();

        return compact('count', 'list');

    }
}