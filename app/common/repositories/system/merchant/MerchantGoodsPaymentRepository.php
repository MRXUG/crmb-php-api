<?php

namespace app\common\repositories\system\merchant;

use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\system\merchant\MerchantGoodsPaymentDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\system\merchant\MerchantGoodsPayment;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use think\facade\Log;

/**
 * Class MerchantProfitRepository
 * @package app\common\repositories\system\merchant
 * @day 2020-05-06
 * @mixin MerchantGoodsPaymentDao
 */
class MerchantGoodsPaymentRepository extends BaseRepository
{
    /**
     * @var MerchantGoodsPaymentDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param  MerchantGoodsPaymentDao  $dao
     */
    public function __construct(MerchantGoodsPaymentDao $dao)
    {
        $this->dao = $dao;
    }

    // 创建货款记录
    public function saveGoodsPayment($params)
    {
        if (!isset($params['orderList'])) {
            throw new \Exception('参数不完整');
        }
        /* @var StoreOrderRepository $orderRepo */
        $orderRepo = app()->make(StoreOrderRepository::class);
        foreach ($params['orderList'] as $order) {
            if (!isset($order['order_id'])) {
                throw new \Exception('订单参数不完整');
            }
            if ($this->orderExists($order['order_id'])) {
                continue;
            }
            $date = date('Y-m-d H:i:s');
            $serviceFee = $orderRepo->calculateServiceFee($order['pay_price'], $order['merchant_source']);
            $goodsMoney = bcsub($order['pay_price'], $serviceFee, 2);// 应到货款=订单支付金额-服务费
            $insert = [
                'mer_id'             => $order['mer_id'],
                'order_id'           => $order['order_id'],
                'settlement_status'  => MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE,
                'service_fee_status' => $this->tellServiceFeeStatus((int)$order['merchant_source'], MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE),
                'mer_received_money' => $this->calculateReceivedMoney($order['merchant_source'],
                    MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE, $goodsMoney, 0),
                'service_fee'        => $serviceFee,
                'goods_money'        => $goodsMoney,
                'create_time'        => $date,
                'latest_flow_time'   => $date
            ];
            $this->create($insert);
        }
    }



    /**
     * 发货T+1时更新
     *
     * @param $orderId
     * @param $params
     * @return void
     */
    public function updateWhenDeliveryPlus1Day($orderId, $params)
    {
        try {
            $record = $this->dao->getWhere([
                'order_id'          => $orderId,
                'settlement_status' => MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE
            ]);
            if (!$record) {
                return;
            }
            /* @var StoreOrderRepository $orderRepo */
            $orderRepo = app()->make(StoreOrderRepository::class);
            $merchantSource = $orderRepo->getValue(['order_id' => $orderId], 'merchant_source');
            $updateArr = [
                'settlement_status'  => MerchantGoodsPayment::SETTLE_STATUS_PART,
                'platform_mer_id'    => $params['mchId'] ?? '',
                'service_fee_status' => $this->tellServiceFeeStatus((int)$merchantSource, MerchantGoodsPayment::SETTLE_STATUS_PART),
                'mer_received_money' => $this->calculateReceivedMoney($merchantSource,
                    MerchantGoodsPayment::SETTLE_STATUS_PART, $record['goods_money'],
                    bcsub(bcmul($params['deposit_money'], 0.01, 2), $record['service_fee'], 2)),// deposit_money里包含了服务费
                'update_time'        => date('Y-m-d H:i:s')
            ];
            $this->dao->updateByWhere(['payment_id' => $record['payment_id']], $updateArr);
        } catch (\Exception $e) {
            Log::error('更新货款发生异常：'.$e->getMessage().json_encode(func_get_args()));
            sendMessageToWorkBot([
                'module' => '货款与服务费',
                'msg'    => '更新货款发生异常：'.$e->getMessage(),
                'params' => __METHOD__.json_encode([$orderId, $params]),
            ]);
        }
    }

    /**
     * 收货T+15时更新
     *
     * @param $orderId
     * @param $params
     * @return void
     */
    public function updateWhenReceivePlus15Days($orderId, $params)
    {
        try {
            $record = $this->dao->getWhere([
                'order_id'          => $orderId,
                'settlement_status' => MerchantGoodsPayment::SETTLE_STATUS_PART
            ]);
            if (!$record) {
                return;
            }
            /* @var StoreOrderRepository $orderRepo */
            $orderRepo = app()->make(StoreOrderRepository::class);
            $merchantSource = $orderRepo->getValue(['order_id' => $orderId], 'merchant_source');
            $update = [
                'settlement_status'  => MerchantGoodsPayment::SETTLE_STATUS_ALL,
                'service_fee_status' => $this->tellServiceFeeStatus((int)$merchantSource, MerchantGoodsPayment::SETTLE_STATUS_ALL ),
                'mer_received_money' => $this->calculateReceivedMoney($merchantSource,
                    MerchantGoodsPayment::SETTLE_STATUS_ALL, $record['goods_money'],
                    bcsub(bcmul($params['deposit_money'], 0.01, 2), $record['service_fee'], 2)),// deposit_money里包含了服务费
                'update_time'        => date('Y-m-d H:i:s')
            ];
            $this->dao->updateByWhere(['payment_id' => $record->payment_id], $update);
        } catch (\Exception $e) {
            Log::error('更新货款发生异常：'.$e->getMessage().json_encode(func_get_args()));
            sendMessageToWorkBot([
                'module' => '货款与服务费',
                'msg'    => '更新货款发生异常：'.$e->getMessage(),
                'params' => __METHOD__.json_encode([$orderId, $params]),
            ]);
        }
    }

    /**
     * 订单取消或退款后更新
     *
     * @param $orderId
     * @return int|void
     */
    public function updateWhenOrderCancelOrRefund($orderId)
    {
        try {
            $record = $this->dao->getWhere([
                'order_id'          => $orderId,
            ]);
            if (!$record) {
                return;
            }
            /* @var StoreOrderRepository $orderRepo */
            $orderRepo = app()->make(StoreOrderRepository::class);
            $merchantSource = $orderRepo->getValue(['order_id' => $orderId], 'merchant_source');
            $update = [
                'settlement_status'  => MerchantGoodsPayment::SETTLE_STATUS_AFTER_SALE_REFUND,
                'service_fee_status' => $this->tellServiceFeeStatus((int)$merchantSource, MerchantGoodsPayment::SETTLE_STATUS_AFTER_SALE_REFUND),
                'mer_received_money' => $this->calculateReceivedMoney($merchantSource,
                    MerchantGoodsPayment::SETTLE_STATUS_ALL, $record['goods_money'],
                    0),
                'update_time'        => date('Y-m-d H:i:s')
            ];
            $where = [
                'order_id' => $orderId,
            ];
            return $this->dao->updateByWhere($where, $update);
        } catch (\Exception $e) {
            Log::error('更新货款发生异常：'.$e->getMessage().json_encode(func_get_args()));
            sendMessageToWorkBot([
                'module' => '货款与服务费',
                'msg'    => '更新货款发生异常：'.$e->getMessage(),
                'params' => __METHOD__.';order_id:'.$orderId,
            ]);
        }
    }

    /**
     * @param $where
     * @return mixed
     */
    public function getSearchQuery($where)
    {
        return $this->dao->search($where);
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
    public function getPagedList(array $where, int $page, int $limit): array
    {
        $query = $this->getSearchQuery($where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)
            ->select();
        if ($list) {
            foreach ($list as &$item) {
                $item['goods_money'] = sprintf('%.2f', $item['goods_money']);
                $item['mer_received_money'] = sprintf('%.2f', $item['mer_received_money']);
                $item['service_fee'] = sprintf('%.2f', $item['service_fee']);
                $item['order_status_text'] = StoreOrderDao::getStatusText($item['order_status']);
                $item['pay_price'] = sprintf('%.2f', $item['pay_price']);
                $item['payment_mer_name'] = $item['mer_name'];
                $item['platform_mer_id'] = ($item['platform_mer_id'] == 0 ? '-' : $item['platform_mer_id']);
            }
        }

        return compact('count', 'list');
    }

    /**
     * @param  string  $fields
     * @param  array  $where
     * @return mixed
     */
    public function getList(string $fields, array $where)
    {
        return $this->getSearchQuery($where)->select();
    }

    /**
     * @param $status
     * @return float
     */
    public function getServiceFeeSum($status)
    {
        return $this->dao->query(['service_fee_status' => $status])->sum('service_fee');
    }

    /**
     * @param  array  $where
     * @return float
     */
    public function getSettlementSum(array $where)
    {
        return $this->dao->query($where)->sum('goods_money');
    }

    /**
     * @param  int  $merchantSource
     * @param  int  $settlementStatus
     * @return int
     */
    private function tellServiceFeeStatus(int $merchantSource, int $settlementStatus): int
    {
        switch ($merchantSource) {
            case StoreOrder::MERCHANT_SOURCE_AD:
                return MerchantGoodsPayment::SERVICE_FEE_STATUS_NONE;
            case StoreOrder::MERCHANT_SOURCE_BACK_NOT_TRANSMIT:
            case StoreOrder::MERCHANT_SOURCE_BACK_TRANSMITTED:
            case StoreOrder::MERCHANT_SOURCE_NATURE:
                switch ($settlementStatus) {
                    case MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE:
                        return MerchantGoodsPayment::SERVICE_FEE_STATUS_WAITING_RECEIVE;
                    case MerchantGoodsPayment::SETTLE_STATUS_PART:
                        return MerchantGoodsPayment::SERVICE_FEE_STATUS_TEMPORARY;
                    case MerchantGoodsPayment::SETTLE_STATUS_ALL:
                        return MerchantGoodsPayment::SERVICE_FEE_STATUS_RECEIVED;
                    case MerchantGoodsPayment::SETTLE_STATUS_AFTER_SALE_REFUND:
                        return MerchantGoodsPayment::SERVICE_FEE_STATUS_INVALID;
                }
        }
        return MerchantGoodsPayment::SERVICE_FEE_STATUS_NONE;
    }

    /**
     * 计算商家到账货款
     *
     * @param $merchantSource
     * @param $settlementStatus
     * @param $goodsMoney 应收货款
     * @param  string  $depositMoney  单位：元,不包含服务费
     * @return int|string
     * @throws \Exception
     */
    private function calculateReceivedMoney($merchantSource, $settlementStatus, $goodsMoney, $depositMoney)
    {
        switch ($merchantSource) {
            case StoreOrder::MERCHANT_SOURCE_BACK_NOT_TRANSMIT:
            case StoreOrder::MERCHANT_SOURCE_BACK_TRANSMITTED:
                switch ($settlementStatus) {
                    case MerchantGoodsPayment::SETTLE_STATUS_PART:
                        // 到账货款 = 应收货款-（不含服务费部分的）押款
                        return bcsub($goodsMoney, $depositMoney, 2);
                    case MerchantGoodsPayment::SETTLE_STATUS_ALL:
                        return $goodsMoney;
                    case MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE:
                    case MerchantGoodsPayment::SETTLE_STATUS_AFTER_SALE_REFUND:
                    default:
                        return 0;
                }
            case StoreOrder::MERCHANT_SOURCE_NATURE:
                switch ($settlementStatus) {
                    case MerchantGoodsPayment::SETTLE_STATUS_PART:
                    case MerchantGoodsPayment::SETTLE_STATUS_ALL:
                        // 到账货款 = 应收货款
                        return $goodsMoney;
                    case MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE:
                    case MerchantGoodsPayment::SETTLE_STATUS_AFTER_SALE_REFUND:
                    default:
                        return 0;
                }
            case StoreOrder::MERCHANT_SOURCE_AD:
                switch ($settlementStatus) {
                    case MerchantGoodsPayment::SETTLE_STATUS_PART:
                    case MerchantGoodsPayment::SETTLE_STATUS_ALL:
                        // 到账货款 = 应收货款-（不含服务费部分的）押款
                        return bcsub($goodsMoney, $depositMoney, 2);
                    case MerchantGoodsPayment::SETTLE_STATUS_WAITING_SETTLE:
                    case MerchantGoodsPayment::SETTLE_STATUS_AFTER_SALE_REFUND:
                    default :
                        return 0;
                }
            default:
                throw new \Exception('订单商家来源错误');
        }
    }
}