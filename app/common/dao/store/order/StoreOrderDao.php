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


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreOrderProduct;
use app\common\model\store\order\StoreOrderStatus;
use app\common\model\store\order\StoreRefundOrder;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductGroupBuyingRepository;

use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Model;

/**
 * Class StoreOrderDao
 * @package app\common\dao\store\order
 * @author xaboy
 * @day 2020/6/8
 */
class StoreOrderDao extends BaseDao
{
    //订单状态（0：待发货；1：待收货；2：待评价；3：已完成； 4.退款中 5.退款失败 9: 拼团中 10:  待付尾款 11:尾款超时未付 -1：已退款）
    const ORDER_STATUS_BE_SHIPPED = 0;
    const ORDER_STATUS_BE_RECEIVE = 1;
    const ORDER_STATUS_REPLY = 2;
    const ORDER_STATUS_SUCCESS = 3;
    const ORDER_STATUS_REDUNDING = 4;
    const ORDER_STATUS_REDUND_FAIL = 5;
    const ORDER_STATUS_SPELL = 9;
    const ORDER_STATUS_TAIL = 10;
    const ORDER_STATUS_TAIL_FAIL = 11;
    const ORDER_STATUS_REFUND = -1;

    const ORDER_STATUS_TEXTS = [
        self::ORDER_STATUS_BE_SHIPPED => '待发货',
        self::ORDER_STATUS_BE_RECEIVE => '待收货',
        self::ORDER_STATUS_REPLY      => '待评价',
        self::ORDER_STATUS_SUCCESS    => '已完成',
        self::ORDER_STATUS_REDUNDING  => '退款中',
        self::ORDER_STATUS_REDUND_FAIL=> '退款失败',
        self::ORDER_STATUS_SPELL      => '拼团中',
        self::ORDER_STATUS_TAIL       => '待付尾款',
        self::ORDER_STATUS_TAIL_FAIL  => '尾款超时未付',
        self::ORDER_STATUS_REFUND     => '已退款',
    ];

    /**
     * @param $status
     * @return string
     */
    public static function  getStatusText(int $status):string
    {
        return self::ORDER_STATUS_TEXTS[$status] ?? '';
    }
    /**
     * @return string
     * @author xaboy
     * @day 2020/6/8
     */
    protected function getModel(): string
    {
        return StoreOrder::class;
    }

    /**
     * @param array $where
     * @param int $sysDel
     * @return BaseQuery
     * @author xaboyCRMEB
     * @day 2020/6/16
     */
    public function search(array $where, $sysDel = 0)
    {
        $query = StoreOrder::hasWhere('merchant', function ($query) use ($where) {
            if (isset($where['is_trader']) && $where['is_trader'] !== '') {
                $query->where('is_trader', $where['is_trader']);
            }
            $query->where('is_del',0);
        });

        $query->when(($sysDel !== null), function ($query) use ($sysDel) {
            $query->where('StoreOrder.is_system_del', $sysDel);
        })
            ->when(isset($where['order_type']) && $where['order_type'] >= 0 && $where['order_type'] !== '', function ($query) use ($where) {
                if ($where['order_type'] == 2) {
                    $query->where('is_virtual', 1);
                } else if($where['order_type'] == 0){ //实体发货订单
                    $query->where('order_type', 0)->where('is_virtual',0);
                } else if($where['order_type'] == 3) { //发货订单
                    $query->where('order_type', 0);
                } else {
                    $query->where('order_type', $where['order_type']);
                }
            })
            ->when(isset($where['activity_type']) && $where['activity_type'] != '', function ($query) use ($where) {
                $query->where('activity_type', $where['activity_type']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                switch ($where['status']) {
                    case 0 :
                        if (isset($where["paid"]) && $where["paid"] == 1){
                            $query->where("StoreOrder.paid",1)->whereIn('StoreOrder.status', [0, 9]);
                        }else{
                            $query->whereIn('StoreOrder.status', [0, 9]);
                        }
                        break;
                    case -2 :
                        $query->where('StoreOrder.paid', 1)->whereNotIn('StoreOrder.status', [10, 11]);
                        break;
                    case 10 :
                        $query->where('StoreOrder.paid', 1)->whereIn('StoreOrder.status', [10, 11]);
                        break;
                    default:
                        $query->where('StoreOrder.status', $where['status']);
                        break;
                }
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['is_user']) && $where['is_user'] !== '', function ($query) use ($where) {
                $query->where(function($query) {
                    $query->where('order_type',0)->whereOr(function($query){
                        $query->where('order_type',1)->where('main_id',0);
                    });
                });
            })
            //待核销订单
            ->when(isset($where['is_verify']) && $where['is_verify'], function ($query) use ($where) {
                $query->where('StoreOrder.order_type', 1)->where('StoreOrder.status',0);
            })
            ->when(isset($where['pay_type']) && $where['pay_type'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.pay_type', $where['pay_type']);
            })
            ->when(isset($where['order_ids']) && $where['order_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('order_id', $where['order_ids']);
            })
            ->when(isset($where['order_id']) && $where['order_id'] !== '', function ($query) use ($where) {
                $query->where('order_id', $where['order_id']);
            })
            ->when(isset($where['take_order']) && $where['take_order'] != '', function ($query) use ($where) {
                $query->where('order_type', 1)->whereNotNull('verify_time');
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.mer_id', $where['mer_id']);
            })
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'StoreOrder.create_time');
            })
            ->when(isset($where['verify_date']) && $where['verify_date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['verify_date'], 'verify_time');
            })
            ->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
                $query->where('order_sn', 'like', '%' . $where['order_sn'] . '%');
            })
            ->when(isset($where['paid']) && $where['paid'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.paid', $where['paid']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.is_del', $where['is_del']);
            })
            ->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
                $query->where('service_id', $where['service_id']);
            })
            ->when(isset($where['username']) && $where['username'] !== '', function ($query) use ($where) {
                $query->join('User U','StoreOrder.uid = U.uid')
                    ->where(function($query) use($where) {
                       $query->where('nickname', 'like', "%{$where['username']}%")
                           ->whereOr('phone', 'like', "%{$where['username']}%")
                           ->whereOr('user_phone', 'like', "%{$where['username']}%");
                    });
            })
            ->when(isset($where['store_name']) && $where['store_name'] !== '', function ($query) use ($where) {
                $orderId = StoreOrderProduct::alias('op')
                    ->join('storeProduct sp','op.product_id = sp.product_id')
                    ->whereLike('store_name',"%{$where['store_name']}%")
                    ->when((isset($where['sp.mer_id']) && $where['mer_id'] !== ''),function($query) use($where){
                        $query->where('mer_id',$where['mer_id']);
                    })->column('order_id');
                $query->whereIn('order_id',$orderId ?: '' );
            })
            ->when(isset($where['search']) && $where['search'] !== '', function ($query) use ($where) {
                $orderId = StoreOrderProduct::alias('op')
                    ->join('storeProduct sp','op.product_id = sp.product_id')
                    ->whereLike('store_name',"%{$where['search']}%")
                    ->when((isset($where['sp.mer_id']) && $where['mer_id'] !== ''),function($query) use($where){
                        $query->where('mer_id',$where['mer_id']);
                    })->column('order_id');
                $query->where(function($query) use($orderId,$where){
                    $query->whereIn('order_id',$orderId ? $orderId : '')
                        ->whereOr('order_sn','like',"%{$where['search']}%")
                        ->whereOr('user_phone','like',"%{$where['search']}%");
                });
            })
            ->when(isset($where['group_order_sn']) && $where['group_order_sn'] !== '', function ($query) use ($where) {
                $query->join('StoreGroupOrder GO','StoreOrder.group_order_id = GO.group_order_id')->where('group_order_sn',$where['group_order_sn']);
            })
            ->when(isset($where['keywords']) && $where['keywords'] !== '', function ($query) use ($where) {
                $query->where(function ($query) use ($where) {
                    $query->whereLike('StoreOrder.real_name|StoreOrder.user_phone|order_sn', "%" . $where['keywords'] . "%");
                });
            })
            ->when(isset($where['reconciliation_type']) && $where['reconciliation_type'] !== '', function ($query) use ($where) {
                $query->when($where['reconciliation_type'], function ($query) use ($where) {
                    $query->where('reconciliation_id', '<>', 0);
                }, function ($query) use ($where) {
                    $query->where('reconciliation_id', 0);
                });
            })
            ->when(isset($where['appid']) && $where['appid'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.appid', $where['appid']);
            })
            ->when(isset($where['pay_time_start']) && $where['pay_time_start'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.pay_time', '>', $where['pay_time_start']);
            })
            ->when(isset($where['pay_time_end']) && $where['pay_time_end'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.pay_time',  '<=',$where['pay_time_end']);
            })
            // 平台订单流量来源
            ->when(isset($where['platform_source']) && $where['platform_source'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.platform_source' ,$where['platform_source']);
            })
            // 商户订单流量来源
            ->when(isset($where['merchant_source']) && $where['merchant_source'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.merchant_source' ,$where['merchant_source']);
            })
            // 支付流水号
            ->when(isset($where['transaction_id']) && $where['transaction_id'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.transaction_id' ,$where['transaction_id']);
            })
            // 小程序
            ->when(isset($where['appid']) && $where['appid'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.appid' ,$where['appid']);
            })
            // 广告渠道
            ->when(isset($where['ad_channel_id']) && $where['ad_channel_id'] !== '', function ($query) use ($where) {
                $query->where('StoreOrder.ad_channel_id' ,$where['ad_channel_id']);
            })
            // 广告账户id
            ->leftJoin('MerchantAd MA','StoreOrder.ad_id = MA.ad_id')
            ->when(isset($where['ad_account_id']) && $where['ad_account_id'] !== '', function ($query) use ($where) {
                    $query->where(function($query) use($where) {
                        $query->whereLike('MA.ad_account_id', "%{$where['ad_account_id']}%");
                    });
            })
            // 广告id
            ->when(isset($where['ad_id']) && $where['ad_id'] !== '', function ($query) use ($where) {
                $query->whereLike('StoreOrder.ad_id' ,"%{$where['ad_id']}%");
            })
            // 商户名称
            ->when(isset($where['mer_name']) && $where['mer_name'] !== '', function ($query) use ($where) {
                /*$query->join('Merchant M','StoreOrder.mer_id = M.mer_id')
                    ->where(function($query) use($where) {*/
                        $query->where('Merchant.mer_name', 'like', "%{$where['mer_name']}%");
                   /* });*/
            })
            ->order('StoreOrder.create_time DESC');

        return $query;
    }

    /**
     * 追加查询
     * @param BaseQuery $query
     * @param array $where
     * @return BaseQuery
     */
    public function merchantGetListAppendQuery(BaseQuery $query, array $where){
        $buyerName = $where['buyer_name'] ?? '';
        $buyerPhone = $where['buyer_phone'] ?? '';
        return $query
            ->when(isset($where['received_name']) && $where['received_name'] != '', function (BaseQuery $query) use ($where) {
                $query->whereLike('StoreOrder.real_name', "%" . $where['received_name'] . "%");
            })
            ->when(isset($where['received_phone']) && $where['received_phone'] != '', function (BaseQuery $query) use ($where) {
                $query->where('StoreOrder.user_phone',  $where['received_phone']);
            })
            ->when($buyerName || $buyerPhone,  function (BaseQuery $query) use  ($buyerName, $buyerPhone){
                $query->leftJoin('user u', 'u.uid = StoreOrder.uid')
                    ->when($buyerName, function ($query) use($buyerName){
                        $query->whereLike('u.nickname', "%" . $buyerName . "%");
                    })
                    ->when($buyerPhone, function ($query) use($buyerPhone){
                        $query->where('u.phone', $buyerPhone);
                    });
            })
            ->when(isset($where['search_Pid']) && $where['search_Pid'] !== '', function ($query) use ($where) {
                $orderId = StoreOrderProduct::alias('op')
                    ->join('storeProduct sp','op.product_id = sp.product_id')
                    ->where('sp.product_id',$where['search_Pid'])->column('order_id');
                $query->whereIn('order_id',$orderId ?: '' );
            })
            //物流异常
            ->when(isset($where['logistics_anomaly']) && $where['logistics_anomaly'] !== '', function ($query) use ($where) {
                // TODO 根据物流状态 ，最好是将异常状态加到 status 中
            })
            //下单场景
            ->when(isset($where['order_scenario']) && $where['order_scenario'] != '' && $where['order_scenario'] != -1, function ($query) use ($where) {
                $query->where('StoreOrder.order_scenario',  $where['order_scenario']);
            })
            //物流单号
            ->when(isset($where['delivery_id']) && $where['delivery_id'] != '', function ($query) use ($where) {
                $query->where('StoreOrder.delivery_id',  $where['delivery_id']);
            })
            //规格编码
            ->when(isset($where['sku_code']) && $where['sku_code'] != '', function ($query) use ($where) {
                $orderId = StoreOrderProduct::alias('op')
                    ->join('storeProduct sp','op.product_id = sp.product_id')
                    ->join('store_product_attr_value sku','sku.product_id = sp.product_id and sku.unique = op.product_sku')
                    ->where(function ($query) use ($where){
                        $query->where('sku.unique|sku.sku', $where['sku_code']);
                    })
                    ->column('order_id');
                $query->whereIn('order_id',$orderId ?: '' );
            })
            //售后场景
            ->when(isset($where['saleStatus']) && $where['saleStatus'] != '', function ($query) use ($where) {
                switch ($where['saleStatus']){
                    case 1://无售后
                        $query->leftJoin('StoreRefundOrder', 'StoreOrder.order_id = StoreRefundOrder.order_id')
                        ->where("StoreRefundOrder.order_id is null");
                        break;
                    case 2://售后中
                        $query->leftJoin('StoreRefundOrder', 'StoreOrder.order_id = StoreRefundOrder.order_id')
                            ->whereIn("StoreRefundOrder.status", [
                                StoreRefundOrder::CHECK_PENDING, StoreRefundOrder::PENDING_RETURN,
                                StoreRefundOrder::UNAPPROVE, StoreRefundOrder::TO_BE_RECEIVED,
                                StoreRefundOrder::REFUNDING, StoreRefundOrder::REFUND_FAILED,
                                ]);
                        break;
                    case 3://售后完成
                    case 4://售后关闭 TODO 关闭售后单 现在没有这个功能
                        $query->leftJoin('StoreRefundOrder', 'StoreOrder.order_id = StoreRefundOrder.order_id')
                            ->where("StoreRefundOrder.status", StoreRefundOrder::REFUNDED);
                        break;
                    default:
                        break;
                }
            });
    }

    public function groupBuyingStatus(array $orderIds, $status)
    {
        if (!count($orderIds)) return 0;
        $make = app()->make(StoreOrderStatusRepository::class);
        foreach ($orderIds as $id){
            $make->status($id,$make::ORDER_STATUS_GROUP_SUCCESS,'拼团成功');
        }
        $return = $this->getModelObj()->whereIn('order_id', $orderIds)->update(compact('status'));
        event('es.order_after_update', ['orderIds' => $orderIds]);
        return $return;
    }

    /**
     * @param $id
     * @param $uid
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/11
     */
    public function userOrder($id, $uid)
    {
        return StoreOrder::getDB()->where('order_id', $id)->where('uid', $uid)->where('is_del', 0)->where('paid', 1)->where('is_system_del', 0)->find();
    }

    /**
     * @param array $where
     * @param $ids
     * @return BaseQuery
     * @author xaboy
     * @day 2020/6/26
     */
    public function usersOrderQuery(array $where, $ids, $uid)
    {
        return StoreOrder::getDB()->where(function ($query) use ($uid, $ids) {
            $query->whereIn('uid', $ids)->whereOr(function ($query) use ($uid) {
                if ($uid) {
                    $query->where('uid', $uid)->where('is_selfbuy', 1);
                }
            });
        })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'pay_time');
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            $query->whereLike('order_id|order_sn', "%{$where['keyword']}%");
        })->where('paid', 1)->order('pay_time DESC');
    }

    /**
     * @param $field
     * @param $value
     * @param int|null $except
     * @return bool
     * @author xaboy
     * @day 2020/6/11
     */
    public function fieldExists($field, $value, ?int $except = null): bool
    {
        return ($this->getModel()::getDB())->when($except, function ($query) use ($field, $except) {
                $query->where($field, '<>', $except);
            })->where($field, $value)->count() > 0;
    }

    /**
     * @param $id
     * @return mixed
     * @author xaboy
     * @day 2020/6/12
     */
    public function getMerId($id)
    {
        return StoreOrder::getDB()->where('order_id', $id)->value('mer_id');
    }

    /**
     * @param array $where
     * @return bool
     * @author Qinii
     * @day 2020-06-12
     */
    public function merFieldExists(array $where)
    {
        return ($this->getModel()::getDB())->where($where)->count() > 0;
    }

    /**
     * TODO
     * @param $reconciliation_id
     * @return mixed
     * @author Qinii
     * @day 2020-06-15
     */
    public function reconciliationUpdate($reconciliation_id)
    {
        $orderIds = $this->getModelObj()->whereIn('reconciliation_id', $reconciliation_id)->column('order_id');
        $return = $this->getModelObj()->whereIn('reconciliation_id', $reconciliation_id)->update(['reconciliation_id' => 0]);
        event('es.order_after_update', ['orderIds' => $orderIds]);
        return $return;
    }

    public function dayOrderNum($day, $merId = null)
    {
        return StoreOrder::getDB()->where('paid', 1)->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($day, function ($query, $day) {
            getModelTime($query, $day, 'pay_time');
        })->count();
    }

    public function dayOrderPrice($day, $merId = null)
    {
        return getModelTime(StoreOrder::getDB()->where('paid', 1)->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        }), $day, 'pay_time')->sum('pay_price');
    }

    public function dateOrderPrice($date, $merId = null)
    {
        return StoreOrder::getDB()->where('paid', 1)->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($date, function ($query, $date) {
            getModelTime($query, $date, 'pay_time');
        })->sum('pay_price');
    }

    public function dateOrderNum($date, $merId = null)
    {
        return StoreOrder::getDB()->where('paid', 1)->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($date, function ($query, $date) {
            getModelTime($query, $date, 'pay_time');
        })->count();
    }

    public function dayOrderUserNum($day, $merId = null)
    {
        return StoreOrder::getDB()->where('paid', 1)->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($day, function ($query, $day) {
            getModelTime($query, $day, 'pay_time');
        })->group('uid')->count();
    }

    public function orderUserNum($date, $paid = null, $merId = null)
    {
        return StoreOrder::getDB()->when($paid, function ($query, $paid) {
            $query->where('paid', $paid);
        })->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($date, function ($query, $date) use ($paid) {
            if (!$paid) {
                getModelTime($query, $date);
//                $query->where(function ($query) use ($date) {
//                    $query->where(function ($query) use ($date) {
//                        $query->where('paid', 1);
//                        getModelTime($query, $date, 'pay_time');
//                    })->whereOr(function ($query) use ($date) {
//                        $query->where('paid', 0);
//                        getModelTime($query, $date);
//                    });
//                });
            } else
                getModelTime($query, $date, 'pay_time');
        })->group('uid')->count();
    }

    public function orderUserGroup($date, $paid = null, $merId = null)
    {
        return StoreOrder::getDB()->when($paid, function ($query, $paid) {
            $query->where('paid', $paid);
        })->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($date, function ($query, $date) {
            getModelTime($query, $date, 'pay_time');
        })->group('uid')->field(Db::raw('uid,sum(pay_price) as pay_price,count(order_id) as total'))->select();
    }

    public function oldUserNum(array $ids, $merId = null)
    {
        return StoreOrder::getDB()->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->whereIn('uid', $ids)->where('paid', 1)->group('uid')->count();
    }

    public function oldUserIds(array $ids, $merId = null)
    {
        return StoreOrder::getDB()->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->whereIn('uid', $ids)->where('paid', 1)->group('uid')->column('uid');
    }

    public function orderPrice($date, $paid = null, $merId = null)
    {
        return StoreOrder::getDB()->when($paid, function ($query, $paid) {
            $query->where('paid', $paid);
        })->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->when($date, function ($query, $date) use ($paid) {
            if (!$paid) {
                $query->where(function ($query) use ($date) {
                    $query->where(function ($query) use ($date) {
                        $query->where('paid', 1);
                        getModelTime($query, $date, 'pay_time');
                    })->whereOr(function ($query) use ($date) {
                        $query->where('paid', 0);
                        getModelTime($query, $date);
                    });
                });
            } else
                getModelTime($query, $date, 'pay_time');
        })->sum('pay_price');
    }

    public function orderGroupNum($date, $merId = null)
    {
        $field = Db::raw('sum(pay_price) as pay_price,count(*) as total,count(distinct uid) as user,pay_time,from_unixtime(unix_timestamp(pay_time),\'%m-%d\') as `day`');
        if ($date == 'year'){
            $field = Db::raw('sum(pay_price) as pay_price,count(*) as total,count(distinct uid) as user,pay_time,from_unixtime(unix_timestamp(pay_time),\'%m\') as `day`');
        }
        $query = StoreOrder::getDB()->field($field)
            ->where('paid', 1)->when($date, function ($query, $date) {
                getModelTime($query, $date, 'pay_time');
            })->when($merId, function ($query, $merId) {
                $query->where('mer_id', $merId);
            });
        return $query->order('pay_time ASC')->group('day')->select();
    }

    public function orderGroupNumPage($where, $page, $limit, $merId = null)
    {
        return StoreOrder::getDB()->when(isset($where['dateRange']), function ($query) use ($where) {
            getModelTime($query, date('Y/m/d 00:00:00', $where['dateRange']['start']) . '-' . date('Y/m/d 00:00:00', $where['dateRange']['stop']), 'pay_time');
        })->field(Db::raw('sum(pay_price) as pay_price,count(*) as total,count(distinct uid) as user,pay_time,from_unixtime(unix_timestamp(pay_time),\'%m-%d\') as `day`'))
            ->where('paid', 1)->when($merId, function ($query, $merId) {
                $query->where('mer_id', $merId);
            })->order('pay_time DESC')->page($page, $limit)->group('day')->select();
    }

    public function dayOrderPriceGroup($date, $merId = null)
    {
        return StoreOrder::getDB()->field(Db::raw('sum(pay_price) as price, from_unixtime(unix_timestamp(pay_time),\'%H:%i\') as time'))
            ->where('paid', 1)->when($date, function ($query, $date) {
                getModelTime($query, $date, 'pay_time');
            })->when($merId, function ($query, $merId) {
                $query->where('mer_id', $merId);
            })->group('time')->select();
    }

    public function dayOrderNumGroup($date, $merId = null)
    {
        return StoreOrder::getDB()->field(Db::raw('count(*) as total, from_unixtime(unix_timestamp(pay_time),\'%H:%i\') as time'))
            ->where('paid', 1)->when($date, function ($query, $date) {
                getModelTime($query, $date, 'pay_time');
            })->when($merId, function ($query, $merId) {
                $query->where('mer_id', $merId);
            })->group('time')->select();
    }

    public function dayOrderUserGroup($date, $merId = null)
    {
        return StoreOrder::getDB()->field(Db::raw('count(DISTINCT uid) as total, from_unixtime(unix_timestamp(pay_time),\'%H:%i\') as time'))
            ->where('paid', 1)->when($date, function ($query, $date) {
                getModelTime($query, $date, 'pay_time');
            })->when($merId, function ($query, $merId) {
                $query->where('mer_id', $merId);
            })->group('time')->select();
    }

    /**
     * 获取当前时间到指定时间的支付金额 管理员
     * @param string $start 开始时间
     * @param string $stop 结束时间
     * @return mixed
     */
    public function chartTimePrice($start, $stop, $merId = null)
    {
        return StoreOrder::getDB()->where('paid', 1)
            ->where('pay_time', '>=', $start)
            ->where('pay_time', '<', $stop)
            ->when($merId, function ($query, $merId) {
                $query->where('mer_id', $merId);
            })
            ->field('sum(pay_price) as num,FROM_UNIXTIME(unix_timestamp(pay_time), \'%Y-%m-%d\') as time')
            ->group('time')
            ->order('pay_time ASC')->select()->toArray();
    }

    /**
     * @param $date
     * @param null $merId
     * @return mixed
     */
    public function chartTimeNum($date, $merId = null)
    {
        return StoreOrder::getDB()->where('paid', 1)->when($date, function ($query) use ($date) {
            getModelTime($query, $date, 'pay_time');
        })->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        })->field('count(order_id) as num,FROM_UNIXTIME(unix_timestamp(pay_time), \'%Y-%m-%d\') as time')
            ->group('time')
            ->order('pay_time ASC')->select()->toArray();
    }

    /**
     * @param $end
     * @return mixed
     * @author xaboy
     * @day 2020/9/16
     */
    public function getFinishTimeoutIds($end)
    {
        return StoreOrderStatus::getDB()->alias('A')->leftJoin('StoreOrder B', 'A.order_id = B.order_id')
            ->where('A.change_type', 'take')
            ->where('A.change_time', '<', $end)->where('B.paid', 1)->where('B.status', 2)
            ->column('A.order_id');
    }


    /**
     * TODO 参与人数
     * @param array $data
     * @param int|null $uid
     * @return BaseQuery
     * @author Qinii
     * @day 2020-11-11
     */
    public function getTattendCount(array $data,?int $uid)
    {
        $query = StoreOrder::hasWhere('orderProduct',function($query)use($data,$uid){
            $query->when(isset($data['activity_id']),function ($query)use($data){
                    $query->where('activity_id',$data['activity_id']);
                })
                ->when(isset($data['product_sku']),function ($query)use($data){
                    $query->where('product_sku',$data['product_sku']);
                })
                ->when(isset($data['product_id']),function ($query)use($data){
                    $query->where('product_id',$data['product_id']);
                })
                ->when(isset($data['exsits_id']),function ($query)use ($data){
                    switch ($data['product_type']){
                        case 3:
                            $make = app()->make(ProductAssistSetRepository::class);
                            $id = 'product_assist_id';
                            break;
                        case 4:
                            $make = app()->make(ProductGroupBuyingRepository::class);
                            $id = 'product_group_id';
                            break;
                    }
                    $where = [$id => $data['exsits_id']];
                    $activity_id = $make->getSearch($where)->column($make->getPk());
                    if($activity_id) {
                        $id = array_unique($activity_id);
                        $query->where('activity_id','in',$id);
                    }else{
                        $query->where('activity_id','<',0);
                    }
                })
                ->where('product_type',$data['product_type']);
            if($uid) $query->where('uid',$uid);
        });
        $query->where('activity_type',$data['product_type']);
        switch($data['product_type'])
        {
            case 0:
                $query->where(function($query){
                    $query->where(function($query){
                        $query->where('paid',1);
                    })->whereOr(function($query){
                        $query->where('paid',0)->where('is_del',0);
                    });
                });
                break;
            case 1: //秒杀
                $query->where(function($query){
                    $query->where(function($query){
                        $query->where('paid',1);
                    })->whereOr(function($query){
                        $query->where('paid',0)->where('is_del',0);
                    });
                })->when(isset($data['day']), function ($query) use ($data) {
                    $query->whereDay('StoreOrder.create_time', $data['day']);
                });
                break;
            case 2: //预售

                /**
                 * 第一阶段参与人数：所有人
                 * 第二阶段参与人数：支付了第一阶段
                 */
                //第二阶段
                if($data['type'] == 1){
                    $query->where(function($query){
                        $query->where('paid',1)->whereOr(function($query){
                            $query->where('paid',0)->where('is_del',0);
                        });
                    });
                }
                if($data['type'] == 2) $query->where('paid',1)->where('status','in',[0,1,2,3,-1]);
                break;
            case 3: //助力
                $query->where(function($query){
                    $query->where('paid',1)->whereOr(function($query){
                        $query->where('paid',0)->where('is_del',0);
                    });
                });
                break;
            case 4: //
                $query->where(function($query){
                    $query->where('paid',1)->whereOr(function($query){
                        $query->where('paid',0)->where('is_del',0);
                    })
                    ->where('status','>',-1);
                });
                break;
        }
        return $query;
    }

    /**
     *  未使用
     * TODO 成功支付人数
     * @param int $productType
     * @param int $activityId
     * @param int|null $uid
     * @param int|null $status
     * @author Qinii
     * @day 2020-10-30
     */
    public function getTattendSuccessCount($data,?int $uid)
    {
        $query = StoreOrder::hasWhere('orderProduct',function($query)use($data,$uid){
            $query->when(isset($data['activity_id']),function ($query)use($data){
                    $query->where('activity_id',$data['activity_id']);
                })
                ->when(isset($data['product_sku']),function ($query)use($data){
                    $query->where('product_sku',$data['product_sku']);
                })
                ->when(isset($data['product_id']),function ($query)use($data){
                    $query->where('product_id',$data['product_id']);
                })
                ->when(isset($data['exsits_id']),function ($query)use ($data){
                    switch ($data['product_type']){
                        case 3:
                            $make = app()->make(ProductAssistSetRepository::class);
                            $id = 'product_assist_id';
                            break;
                        case 4:
                            $make = app()->make(ProductGroupBuyingRepository::class);
                            $id = 'product_group_id';
                            break;
                    }
                    $where = [$id => $data['exsits_id']];
                    $activity_id = $make->getSearch($where)->column($make->getPk());
                    if($activity_id) {
                        $id = array_unique($activity_id);
                        $query->where('activity_id','in',$id);
                    }else{
                        $query->where('activity_id','<',0);
                    }
                })
                ->where('product_type',$data['product_type']);
            if($uid) $query->where('uid',$uid);
        });
        $query->where('activity_type',$data['product_type'])->where('paid',1);

        switch($data['product_type'])
        {
            case 1: //秒杀
                $query->where(function($query){
                    $query->where(function($query){
                        $query->where('paid',1);
                    });
                })->when(isset($data['day']), function ($query) use ($data) {
                    $query->whereDay('StoreOrder.create_time', $data['day']);
                });
                break;
            case 2: //预售
                if($data['type'] == 1){    //第一阶段
                    $query->where('status','in',[0,1,2,3,10]);
                } else {        //第二阶段
                    $query->where('status','in',[0,1,2,3]);
                }
                break;
            case 3: //助力
                break;
            case 4:
                break;
        }
        return $query;
    }


    /**
     * TODO 获取退款单数量
     * @param $where
     * @return mixed
     * @author Qinii
     * @day 1/4/21
     */
    public function getSeckillRefundCount($where,$type = 1)
    {
        $query = StoreOrderProduct::getDB()->alias('P')->join('StoreRefundOrder R','P.order_id = R.order_id');
        $query->join('StoreOrder O','O.order_id = P.order_id');
        $query
            ->when(isset($where['activity_id']),function ($query)use($where){
                $query->where('P.activity_id',$where['activity_id']);
            })
            ->when(isset($where['product_sku']),function ($query)use($where){
                $query->where('P.product_sku',$where['product_sku']);
            })
            ->when(isset($where['day']), function ($query) use ($where) {
                $query->whereDay('P.create_time', $where['day']);
            })
            ->when($type == 1, function ($query) use ($where) {
                $query->where('O.verify_time',null)->where('O.delivery_type',null);
            },function ($query){
                $query ->where('R.refund_type',2);
            })
            ->where('P.product_type',1)->where('R.status',3);
        return $query->count();
    }

    /**
     * 修改订单状态
     *
     * @param int $orderId
     * @param int $status
     * @return void
     */
    public function updateOrderStatus(int $orderId, int $status)
    {
        $this->getModelObj()->where('order_id', $orderId)->update([
            'status' => $status
        ]);
        event('es.order_after_update', ['orderIds' => [$orderId]]);
        /** @var StoreOrderProductDao $orderProductDao */
        $orderProductDao = app()->make(StoreOrderProductDao::class);
        if ($status == 4) {
            $orderProductDao->updateIsRefund($orderId, 1);
        }
        if ($status == -1) {
            $orderProductDao->updateIsRefund($orderId, 3);
        }
    }

    /**
     * TODO 用户的某个商品购买数量
     * @param int $uid
     * @param int $productId
     * @return int
     * @author Qinii
     * @day 2022/9/26
     */
    public function getMaxCountNumber(int $uid, int $productId)
    {
        return StoreOrder::hasWhere('orderProduct',function($query) use($productId){
            $query->where('product_id', $productId);
        })
        ->where(function($query) {
            $query->where('is_del',0)->whereOr(function($query){
                $query->where('is_del',1)->where('paid',1);
            });
        })->where('StoreOrder.uid',$uid)->count()
       ;
    }
}
