<?php
/**
 * @user: lucky 2023/8/3 11:17
 */

namespace app\common\repositories\merchant\DataCenter;

use app\common\model\store\order\StoreOrder;
use app\common\repositories\BaseRepository;
use app\validate\Elasticsearch\StoreOrderValidate;
use app\validate\Elasticsearch\UserVisitLogValidate;
use crmeb\services\ElasticSearch\ElasticSearchService;

class DataCenterDashboardRepository extends BaseRepository
{

    /**
     * UserRepository constructor.
     * @param ElasticSearchService $es
     */
    public function __construct(ElasticSearchService $es)
    {
        $this->es = $es;
    }
    /**
     * 商家数据中心
     *
     * @param $mer_id
     * @param $params
     * @return array
     */
    public function main($mer_id, $params)
    {
        $startTime = $params['start_date']. ' 00:00:00';
        $endTime = $params['end_date']. ' 23:59:59';
        $userEsParam = [
            'index' => UserVisitLogValidate::$tableIndexName,
            'body'  => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'mer_id' => $mer_id
                                ]
                            ],
                            [
                                'range' => [
                                    'visit_time' => [
                                        'gte' => $startTime,
                                        'lte' => $endTime,
                                    ]
                                ]
                            ],
                        ]
                    ]
                ],
                'aggs' => [
                    'total_count' => [
                        'value_count' => [
                            'field' => 'uid'
                        ]
                    ],
                    'user_distinct' => [
                        'cardinality' => [
                            'field' => 'uid'
                        ]
                    ]
                ]
            ]
        ];
        $userCount = $this->es->aggregationsSearch($userEsParam);
        $totalUserVisit = $userCount['total_count']['value'];
        $userDistinct = $userCount['user_distinct']['value'];
        $avgUser = $userDistinct > 0 ? $totalUserVisit / $userDistinct : 0;

        $orderEsParam = [
            'index' => StoreOrderValidate::$tableIndexName,
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'mer_id' => $mer_id
                                ]
                            ],
                            [
                                'range' => [
                                    'create_time' => [
                                        'gte' => $startTime,
                                        'lte' => $endTime,
                                    ]
                                ]
                            ],
                        ]
                    ]
                ],
                'aggs' => [
                    'is_paid' => [
                        'filter' => [
                            'term' => [
                                'paid' => 1
                            ]
                        ],
                        'aggs' => [
                            'product_sum' => [
                                'sum' => [
                                    'field' => 'total_num'
                                ]
                            ],
                            'price_sum' => [
                                'sum' => [
                                    'field' => 'pay_price'
                                ]
                            ],
                            'user_count' => [
                                'cardinality' => [
                                    'field' => 'uid'
                                ]
                            ],
                            'order_source' => [
                                'terms' => [
                                    'field' => 'merchant_source'
                                ],
                                'aggs' => [
                                    'price_sum' => [
                                        'sum' => [
                                            'field' => 'pay_price',
                                        ]
                                    ],
                                    'refund_order' => [
                                        'filter' => [
                                            'term' => [
                                                'status' => StoreOrder::ORDER_STATUS_REFUND
                                            ]
                                        ],
                                        'aggs' => [
                                            'price_sum' => [
                                                'sum' => [
                                                    'field' => 'pay_price'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'total_count' => [
                        'value_count' => [
                            'field' => 'order_id'
                        ]
                    ],
                ],
            ]
        ];
        $orderCount = $this->es->aggregationsSearch($orderEsParam);
        //a
        $totalOrderCount = $orderCount['total_count']['value'];
        $paidOrderCount = $orderCount['is_paid']['doc_count'];
        $paidUserCount = $orderCount['is_paid']['user_count']['value'];
        $paidProductCount = $orderCount['is_paid']['product_sum']['value'];
        $GMV = $orderCount['is_paid']['price_sum']['value'] / 100;
        $avgPaid = $paidUserCount > 0 ? $GMV / $paidUserCount : 0;
        $orderRate = $totalUserVisit > 0 ? $totalOrderCount / $totalUserVisit : 0;
        $paidRate = $totalUserVisit > 0 ? $paidOrderCount / $totalUserVisit : 0;

        //b
        $adsPaidPrice = 0;
        $adsPaidOrderCount = 0;
        $naturePaidPrice = 0;
        $naturePaidOrderCount = 0;
        //c
        $refundPrice = 0;
        $refundOrderCount = 0;
        $adsRefundOrderCount= 0;
        $natureRefundOrderCount = 0;

        foreach ($orderCount['is_paid']['order_source']['buckets'] as $v){
            if(!$v){
                continue;
            }
            if($v['key'] == StoreOrder::MERCHANT_SOURCE_NATURE){
                $naturePaidPrice = $v['price_sum']['value'] / 100;
                $naturePaidOrderCount = $v['doc_count'];
                $natureRefundOrderCount = $v['refund_order']['doc_count'];
            }else{
                $adsPaidPrice += $v['price_sum']['value'] / 100;
                $adsPaidOrderCount += $v['doc_count'];
                $adsRefundOrderCount += $v['refund_order']['doc_count'];
            }
            $refundPrice += $v['refund_order']['price_sum']['value'] / 100;
            $refundOrderCount += $v['refund_order']['doc_count'];
        }


        $refundRate = $paidOrderCount > 0 ? $refundOrderCount / $paidOrderCount : 0;

        $result = [
            'total_user_visit' => $totalUserVisit,
            'user_count' => $userDistinct,
            'avg_user' => round($avgUser, 2),

            'total_order_count' => $totalOrderCount,
            'paid_order_count' => $paidOrderCount,
            'paid_user_count' => $paidUserCount,
            'paid_product_count' => $paidProductCount,
            'gmv' => round($GMV, 2),
            'avg_paid' => round($avgPaid, 2),
            'order_rate' => round($orderRate, 4),
            'paid_rate' => round($paidRate, 4),

            'ads_paid_price' => round($adsPaidPrice, 2),
            'ads_paid_order_count' => $adsPaidOrderCount,
            'nature_paid_price' => round($naturePaidPrice, 2),
            'nature_paid_order_count' => $naturePaidOrderCount,

            'refund_price' => round($refundPrice, 2),
            'refund_order_count' => $refundOrderCount,
            'ads_refund_order_count' => $adsRefundOrderCount,
            'nature_refund_order_count' => $natureRefundOrderCount,
            'refund_rate' => round($refundRate, 4),
        ];

        return $result;
    }

    /**
     * for test
     * @return mixed
     */
    public function batchInsertOrderData(){
        $repository = app()->make(OrderInElasticSearchRepository::class);
        $repository->bulk();
        return app('json')->success('ok');
    }

}
