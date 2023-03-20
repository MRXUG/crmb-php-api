<?php


namespace app\common\repositories\delivery;


use app\common\dao\delivery\DeliveryProfitSharingLogsDao;
use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class DeliveryProfitSharingLogsRepository extends BaseRepository
{
    public function __construct(DeliveryProfitSharingLogsDao $dao)
    {
        $this->dao = $dao;
    }
    
     /**
     * 获取分账订单
     *
     * @param string $key
     * @param array $values
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/10 10:57
     */
    public function getProfitSharingOrder(string $key, array $values)
    {
        return $this->dao->getProfitSharingOrder($key, $values);
    }

    /**
     * 获取分佣记录
     *
     * @param $orderId
     *
     * @return array|\think\Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 17:01
     */
    public function getProfitSharingOrderByOrderId($orderId)
    {
        return $this->dao->getProfitSharingOrderByOrderId($orderId);
    }

    /**
     * 获取解冻中的日志
     *
     * @param string $key
     * @param array $values
     *
     * @return
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 14:24
     */
    public function getUnfreezeIngOrderLog(string $key, array $values)
    {
        return $this->dao->getUnfreezeIngOrderLog($key, $values);
    }

    /**
     * 获取回退中的日志
     *
     * @param string $key
     * @param array $values
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 17:38
     */
    public function getProfitSharingReturnLog(string $key, array $values)
    {
        return $this->dao->getProfitSharingReturnLog($key, $values);
    }
}