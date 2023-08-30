<?php


namespace app\common\repositories\merchant\DataCenter;


use app\common\dao\store\order\StoreOrderDao;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\BaseRepository;
use app\validate\Elasticsearch\StoreOrderValidate;
use crmeb\services\ElasticSearch\ElasticSearchService;

class OrderInElasticSearchRepository extends BaseRepository
{
    /**
     * UserRepository constructor.
     * @param ElasticSearchService $es
     * @param StoreOrderDao $dao
     */

    const maxBatchSIze = 500;
    public function __construct(ElasticSearchService $es, StoreOrderDao $dao)
    {
        $this->es = $es;
        $this->dao = $dao;
    }

    public function create($order){
        if(is_object($order) && method_exists($order, 'toArray')){
            $order = $order->toArray();
        }
        $orderArray = $this->floatToInt($order);
        $this->es->create(StoreOrderValidate::$tableIndexName, $orderArray, $order['order_id']);
    }

    /**
     * 为节省ES内存以及方便聚合和避免浮点计算，大部分订单金额*100存入ES，取数据需要 / 100.
     * @param array $order
     * @return array
     */
    public function floatToInt(array $order){
        foreach (StoreOrderValidate::getFloatColumn() as $key){
            $order[$key] = intval($order[$key] * 100);
        }
        return $order;
    }

    /**
     * update one
     * @param $orderId
     * @param array $order
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function update($orderId, array $order = []){
        if(!empty($order)){
            $order['order_id'] = $orderId;
        }else{
            $order = StoreOrder::where('order_id','=', $orderId)
                ->find()->toArray();
        }
        $orderArray = $this->floatToInt($order);
        $this->es->update(StoreOrderValidate::$tableIndexName, $orderArray, $orderId);
    }

    /**
     * update batch
     * @param array $orderIds
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function batchUpdate(array $orderIds){
        foreach (array_chunk($orderIds, self::maxBatchSIze) as $once){
            $order = StoreOrder::whereIn('order_id', $once)
                ->select()->toArray();
            $esData = [];
            foreach ($order as $v){
                $v = $this->floatToInt($v);
                $esData[] = [
                    'update' => [
                        '_index' => StoreOrderValidate::$tableIndexName,
                        '_id' => $v['order_id'],
                    ]
                ];
                $esData[] = [
                    'doc' => $v
                ];
            }
            echo json_encode($esData);
            $this->es->bulk($esData, StoreOrderValidate::$tableIndexName);

        }
    }

    /**
     * bulk, update & insert
     * @param null $beginDate
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function bulk($beginDate = null){
        if(!$beginDate){
            $beginDate = date('Y-m-d H:i:s', strtotime('-30 day'));
        }
        $data = StoreOrder::where('update_time','>', $beginDate)
            ->order('order_id ASC')
            ->select()->toArray();
        $res = [];
        foreach (array_chunk($data, self::maxBatchSIze) as $once){
            $esData = [];
            foreach ($once as $v){
                $v = $this->floatToInt($v);
                $esData[] = [
                    'index' => [
                        '_index' => StoreOrderValidate::$tableIndexName,
                        '_id' => $v['order_id'],
                    ]
                ];
                $esData[] = $v;
            }
            $this->es->bulk($esData, StoreOrderValidate::$tableIndexName);
            $res[] = count($esData);
        }
        $res[] = count($data);
        return $res;



    }

}