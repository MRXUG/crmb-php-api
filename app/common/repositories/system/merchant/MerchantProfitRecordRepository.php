<?php

namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantProfitDao;
use app\common\dao\system\merchant\MerchantProfitDayLogDao;
use app\common\dao\system\merchant\MerchantProfitRecordDao;
use app\common\model\system\merchant\MerchantProfitDayLog;
use app\common\model\system\merchant\MerchantProfitRecord;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use think\facade\Db;
use think\facade\Log;

/**
 * Class MerchantProfitRecordRepository
 * @package app\common\repositories\system\merchant
 * @mixin MerchantProfitRecordDao
 */
class MerchantProfitRecordRepository extends BaseRepository
{
    /**
     * @var MerchantProfitRecordDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param  MerchantProfitRecordDao  $dao
     */
    public function __construct(MerchantProfitRecordDao $dao)
    {
        $this->dao = $dao;
    }

    public function create($data)
    {
        $data['create_time'] = date('Y-m-d H:i:s');
        return $this->dao->create($data);
    }

    /**
     * 订单收货T+15后，更新收益记录为有效状态，并更新商户收益账户表
     *
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function setRecordsValidAndUpdateProfit($mId): void
    {
        $today = date('Y-m-d')." 00:00:00";
        $records = $this->dao->query(['status' => MerchantProfitRecord::STATUS_NOT_VALID,'profit_mer_id'=>$mId])->select()->toArray();


        $profitDayLogDao = app()->make(MerchantProfitDayLogDao::class);

        if (!$records) {

            $profitDaoInfo = $profitDayLogDao->getWhere(['update_time'=>$today,"mer_id"=>$mId],"profit_id");
            if (!$profitDaoInfo){
                $profitDayLogDao->create(
                    [
                        'mer_id'      => $mId,
                        'total_money' => 0,
                        'update_time' => date('Y-m-d')." 00:00:00"
                    ]
                );
            }



            return;
        }

        $recordsChunk = array_chunk($records, 100);
        /* @var $orderStatusRepo StoreOrderStatusRepository */
        $orderStatusRepo = app()->make(StoreOrderStatusRepository::class);
        foreach ($recordsChunk as $chunk) {
            $orderIds = array_column($chunk, 'order_id');
            $orderIds2MerIds = array_column($chunk, 'profit_mer_id', 'order_id');
            $orderIdsFitCondition = $orderStatusRepo->selectOrders15DaysAfterReceive($orderIds);
            if (!$orderIdsFitCondition) {
                continue;
            }

            foreach ($orderIdsFitCondition as $orderId) {
                $merId = 0;
                try {
                    Db::transaction(function () use ($orderId, $orderIds2MerIds, $profitDayLogDao,$today,$mId) {
                        // 更新明细记录为有效
                        MerchantProfitRecord::getDB()->where(["order_id"=>$orderId,"profit_mer_id"=>$mId])->update([
                            'status'             => MerchantProfitRecord::STATUS_VALID,
                            'profit_affect_time' => date('Y-m-d H:i:s')
                        ]);
                        $merId = $orderIds2MerIds[$orderId];

                        $profitMoney = MerchantProfitRecord::getDB()
                            ->where([
                                'order_id' => $orderId,
                                'profit_mer_id' => $mId,
                            ])
                            ->value('profit_money');

                        //查询商户今日收益是否有数据
                        $profitDaoInfo = $profitDayLogDao->getWhere(['update_time'=>$today,"mer_id"=>$merId],"profit_id,total_money");
                        if ($profitDaoInfo){
                            $profitDayLogDao->incField($profitDaoInfo["profit_id"],"total_money",$profitMoney);
                        }else{
                            $profitDayLogDao->create(
                                [
                                    'mer_id'      => $merId,
                                    'total_money' => $profitMoney,
                                    'update_time' => $today
                                ]
                            );
                        }
                        $have = MerchantProfitRecord::getDB()
                            ->where([
                                'mer_id' => $merId,
                            ])->count();
                        if ($have > 0){
                            MerchantProfitRecord::getDB()->where(["mer_id"=>$merId])->inc("total_money",$profitMoney)->update();
                        }else{
                            MerchantProfitRecord::getDB()->insert([
                                'mer_id'      => $merId,
                                'total_money' => $profitMoney,
                                'update_time' => date('Y-m-d H:i:s')
                            ]);
                        }
                    });
                } catch (\Exception $e) {
                    Log::error(sprintf('更新收益明细和账户失败,商户：%s,订单：%s,错误：%s', $merId, $orderId,
                        $e->getMessage()));
                    sendMessageToWorkBot([
                        'module' => '商户收益',
                        'type'   => 'error',
                        'msg'    => '更新收益明细和账户异常：'.$e->getMessage(),
                        'params' => json_encode([
                            'merId'   => $merId,
                            'orderId' => $orderId
                        ]),
                        'file'   => __FILE__,
                        'line'   => __LINE__
                    ]);
                }
            }
        }
    }

    /**
     * @param  string  $fields
     * @param  array  $where
     * @param  int  $page
     * @param  int  $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getPagedList(string $fields, array $where, int $page, int $limit): array
    {
        $query = $this->dao->search('*', $where)->with([
            'storeOrder'     => function ($query) {
                $query->field('order_id,order_sn');
            },
            'orderMerchant'  => function ($query) {
                $query->field('mer_id,mer_name');
            },
            'profitMerchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
        ]);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)
            ->field($fields)
            ->select();
        if ($list){
            foreach ($list as &$item){
                $item['service_fee_rate'] = sprintf('%d%%',$item['service_fee_rate'] * 100 );
                $item['profit_rate'] = sprintf('%d%%',$item['profit_rate'] * 100 );
                $item['order_sn'] = $item->storeOrder->order_sn;
                $item['order_mer_name'] = $item->orderMerchant->mer_name;
                $item['profit_mer_name'] = $item->profitMerchant->mer_name;
                unset($item->storeOrder, $item->orderMerchant, $item->profitMerchant);
            }
        }

        return compact('count', 'list');
    }

    /**
     * @param  string  $fields
     * @param  array  $where
     * @param  int  $page
     * @param  int  $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getPagedListSimple(string $fields, array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($fields, $where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }
}