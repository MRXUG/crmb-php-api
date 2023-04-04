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

namespace crmeb\services;

use app\common\dao\applet\WxAppletDao;
use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\system\merchant\MerchantProfitRecordDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\store\order\OrderFlow;
use app\common\model\system\merchant\MerchantGoodsPayment;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreRefundOrder;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\store\order\StoreImportDeliveryRepository;
use app\common\repositories\store\order\StoreOrderProfitsharingRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\ExcelRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\system\financial\FinancialRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use app\common\repositories\system\merchant\MerchantIntentionRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserExtractRepository;
use app\common\repositories\user\UserVisitRepository;
use MongoDB\Driver\Query;
use think\Exception;
use think\facade\Db;

class ExcelService
{

    public function getAll($data)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $this->{$data['type']}($data['where'], $data['excel_id']);
    }

    /**
     * TODO 导出操作
     * @param $id
     * @param $path
     * @param $header
     * @param $title
     * @param array $export
     * @param string $filename
     * @param array $end
     * @param string $suffix
     * @author Qinii
     * @day 3/17/21
     */
    public function export($id, $path, $header, $title, $export = [], $filename = '', $end = [], $suffix = 'xlsx')
    {
        try {
            $_path = SpreadsheetExcelService::instance()
                ->createOrActive($id)
                ->setExcelHeader($header, count($title['mark']) + 2)
                ->setExcelTile($title)
                ->setExcelContent($export)
                ->setExcelEnd($end)
                ->excelSave($filename, $suffix, $path);

            app()->make(ExcelRepository::class)->update($id, [
                'name'   => $filename . '.' . $suffix,
                'status' => 1,
                'path'   => '/' . $_path
            ]);
        } catch (Exception $exception) {
            app()->make(ExcelRepository::class)->update($id, [
                'name'    => $filename . '.' . $suffix,
                'status'  => 2,
                'message' => $exception->getMessage()
            ]);
        }
    }

    /**
     * TODO 搜索记录导出
     * @param array $where
     * @param int $id
     * @author xaboy
     * @day 6/10/21
     */
    public function searchLog(array $where, int $id)
    {
        $header = ['序号', '用户ID', '用户昵称', '用户类型', '搜索词', '搜索时间', '首次访问时间'];
        $user_type = [
            'h5'      => 'H5',
            'wechat'  => '公众号',
            'routine' => '小程序',
        ];
        $export = [];
        $title = [];
        $query = app()->make(UserVisitRepository::class)->search($where)->with([
            'user' => function ($query) {
                $query->field('uid,nickname,avatar,user_type,create_time');
            }
        ])->order('create_time DESC');
        $count = $query->count();
        $logs = $query->select();
        foreach ($logs as $log) {
            $export[] = [
                $log['user_visit_id'],
                $log['user'] ? $log['user']['uid'] : '未登录',
                $log['user'] ? $log['user']['nickname'] : '未知',
                $log['user'] ? ($user_type[$log['user']['user_type']] ?? $log['user']['user_type']) : '未知',
                $log['content'],
                $log['create_time'],
                $log['user'] ? $log['user']['create_time'] : '未知',
            ];
        }
        $filename = '搜索记录_' . date('YmdHis');
        $foot = [];

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 导出订单
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 2020-08-10
     */
    public function order(array $where, int $page, int $limit)
    {
        $paytype = [0 => '余额', 1 => '微信', 2 => '小程序', 3 => 'H5', 4 => '支付宝', 5 => '支付宝扫码', 6 => '微信扫码',];
        $make = app()->make(StoreOrderRepository::class);
        $status = $where['status'];
        $del = $where['mer_id'] > 0 ? 0 : null;
        unset($where['status']);
        $query = $make->search($where, $del)->where($make->getOrderType($status))->with([
            'orderProduct',
            'merchant' => function ($query) {
                return $query->field('mer_id,mer_name');
            },
            'user',
            'spread',
            'storeRefundOrder',
            'merchantAd',
        ])->order('order_id ASC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->append(['refund_price']);

        $export = [];
        foreach ($list as $item) {
            $product = [];
            foreach ($item['orderProduct'] as $value) {
                $product[] = [
                    $value['cart_info']['product']['store_name'],
                    $value['product_id'], //商品id
                    $value['cart_info']['productAttr']['sku'] ?: '无',
                    $value['product_num'] . ' ' . $value['unit_name'],
                    $value['cart_info']['productAttr']['price'],
                    $value['cart_info']['productAttr']['price'] * $value['product_num']. ' ' . $value['unit_name'] // 商品总价
                ];
            }
            $one = [
                $item['merchant']['mer_name'],
                $item['merchant']['mer_id'],// 商户编码
                merchantConfig($item['merchant']['mer_id'], 'pay_routine_mchid'),// 微信商户号
                $item['order_sn'],
                StoreOrder::PLATFORM_SOURCE_TEXT[$item['platform_source']],//订单来源
                $item['ad_channel_id'] > 0 ? StoreOrder::AD_CHANNEL[$item['ad_channel_id']] : '无',//广告渠道
                !empty($item['merchantAd']) && $item['merchantAd']['ad_account_id'] > 0 ? $item['merchantAd']['ad_account_id'] : '无',//广告账户ID
                $item['ad_id'] > 0 ? $item['ad_id'] : '',//广告ID
                get_order_status($item),// 订单状态
                !$item['storeRefundOrder'] || $item['storeRefundOrder']['status'] == -2  ? '无' :StoreRefundOrder::REFUND_ORDER_STATUS[$item['storeRefundOrder']['status']],// 退款类型
                $item['order_type'] ? '核销订单' : '普通订单',
                $item['user']['nickname'] ?? $item['uid'],
                $product,
                $item['coupon_price'],
                $item['pay_postage'],
                $value['product_price'],
                $item['refund_price'],
                $item['real_name'],
                $item['user_phone'],
                $item['user_address'] ?: '',
                $item['delivery_id'] ?: '',
                $item['create_time'],
                $paytype[$item['pay_type']],
                $item['paid'] ? '已支付' : '未支付',
                $item['transaction_id'] ?? '无',//支付流水号
                $item['pay_time'] ?: '无',//支付时间
                $item['delivery_time'] ?: '无',//发货时间
                $item['finish_time'] ?: '无',//交易成功/关闭时间
                $item['mark'] ?: '',
                $item['remark'] ?: ''
            ];
            $export[] = $one;
        }
        $header =
            [
                '商户名称',
                '商户编码',
                '微信商户号',
                '订单编号',
                '订单来源',
                '广告渠道',
                '广告账户ID',
                '广告ID',
                '订单状态',
                '退款类型',
                '订单类型',
                '用户信息',
                '商品名称',
                '商品ID',
                '商品规格',
                '商品数量',
                '商品单价',
                '商品总价',
                '优惠总额',
                '运费金额(元)',
                '实收金额(元)',
                '已退款金额(元)',
                '收货人',
                '收货人电话',
                '收货地址',
                '物流/电话',
                '下单时间',
                '支付方式',
                '支付状态',
                '支付流水号',
                '支付时间',
                '发货时间',
                '交易成功/关闭时间',
                '买家留言',
                '商家备注'
            ];
        $filename = '订单列表_' . date('YmdHis');
        $title = ['订单列表', '导出时间：' . date('Y-m-d H:i:s', time())];
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 流水记录导出
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 2020-08-10
     */
    public function financial(array $where, int $page, int $limit)
    {
        $_key = [
            'mer_accoubts'           => '财务对账',
            'sys_accoubts'           => '财务对账',
            'refund_order'           => '退款订单',
            'brokerage_one'          => '一级分佣',
            'brokerage_two'          => '二级分佣',
            'refund_brokerage_one'   => '返还一级分佣',
            'refund_brokerage_two'   => '返还二级分佣',
            'order'                  => '订单支付',
            'order_platform_coupon'  => '平台优惠券补贴',
            'refund_platform_coupon' => '退回平台优惠券',
            'order_svip_coupon'      => '付费会员卷',
            'refund_svip_coupon'     => '退回付费会员卷',
        ];
        $make = app()->make(FinancialRecordRepository::class);
        $query = $make->search($where)->with([
            'merchant',
            'orderInfo',
            'refundOrder'
        ]);

        $header = ['商户名称', '交易流水单号', '类型', '总订单号', '订单号/退款单号', '用户名', '用户ID', '交易类型', '收入/支出', '金额', '创建时间'];
        $title = [
            '流水列表',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $export = [];
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $v) {
            $wx = (substr($v['order_sn'], 0, 2) === 'wx');
            $export[] = [
                $v['merchant']['mer_name'],
                $v['financial_record_sn'],
                $wx ? '订单' : '退款单',
                $wx ? $v['orderInfo']['groupOrder']['group_order_sn'] : '',
                $wx ? $v['order_sn'] : $v['refundOrder']['refund_order_sn'],
                $v['user_info'],
                $v['user_id'],
                $_key[$v['financial_type']],
                $v['financial_pm'] ? '收入' : '支出',
                ($v['financial_pm'] ? '+ ' : '- ') . $v['number'],
                $v['create_time'],
            ];
        }

        $filename = '流水列表_' . date('YmdHis');
        $foot = [];

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 获取待发货订单 发货信息
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 3/13/21
     */
    public function delivery(array $where, int $page, int $limit)
    {
        $make = app()->make(StoreOrderRepository::class);
        $where['order_type'] = 0;
        $query = $make->search($where)->with(['orderProduct'])->order('order_id ASC');
        $header = ['订单编号', '物流公司', '物流编码', '物流单号', '发货地址', '用户信息', '手机号', '商品信息', '支付时间'];
        $title = [
            '批量发货单',
            '生成时间:' . date('Y-m-d H:i:s', time()),
        ];
        $filename = '批量发货单_' . date('YmdHis');
        $export = [];
        $count = $query->count();
        $data = $query->page($page, $limit)->select();
        foreach ($data as $item) {
            $product = '';
            foreach ($item['orderProduct'] as $value) {
                $product =
                    $product . $value['cart_info']['product']['store_name'] . '【' . $value['cart_info']['productAttr']['sku'] . '】【' . $value['product_num'] . '】' . PHP_EOL;
            }
            $export[] = [
                $item['order_sn'] ?? '',
                '',
                $item['delivery_name'] ?? "",
                $item['delivery_id'] ?? "",
                $item['user_address'] ?? "",
                $item['real_name'] ?? '',
                $item['user_phone'] ?? '',
                $product,
                $item['pay_time'] ?? '',
            ];
        }

        $foot = [];

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 导出 发货导入记录
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 3/17/21
     */
    public function importDelivery(array $where, int $page, int $limit)
    {
        $make = app()->make(StoreImportDeliveryRepository::class);
        $query = $make->getSearch($where)->order('create_time ASC');
        $title = [
            '发货记录',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['订单编号', '物流公司', '物流单号', '发货状态', '备注'];
        $filename = '发货单记录_' . date('YmdHis');
        $export = [];
        $count = $query->count();
        $data = $query->page($page, $limit)->select();
        foreach ($data as $item) {
            $export[] = [
                $item['order_sn'],
                $item['delivery_name'],
                $item['delivery_id'],
                $item['status'],
                $item['mark'],
            ];
        }
        $foot = [];

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 平台/商户 导出日月账单信息
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 3/25/21
     */
    public function exportFinancial(array $where, int $page, int $limit)
    {
        /*
           order 收入 公共 新订单
           brokerage_one 支出 公共 一级佣金
           brokerage_two 支出 公共 二级佣金
           order_charge 支出 商户 手续费
           order_true 支出 平台 商户入账
           refund_order 支出 公共 退款
           refund_brokerage_one 收入 公共 返还一级佣金
           refund_brokerage_two 收入 公共 返还二级佣金
           refund_charge 收入 商户 返还手续费
           refund_true 收入 平台 商户返还入账
           presell 收入 公共 新订单
           presell_charge 支出 商户 手续费
           presell_true 支出 平台 商户入账
        */
        $financialType = [
            'order'                  => '订单支付',
            'presell'                => '预售订单（尾款）',
            'brokerage_one'          => '一级佣金',
            'brokerage_two'          => '二级佣金',
            'order_charge'           => '手续费',
            'order_true'             => '商户入账',
            'refund_order'           => '退款',
            'refund_charge'          => '返还手续费',
            'refund_true'            => '商户返还入账',
            'presell_charge'         => '预售订单（手续费）',
            'presell_true'           => '商户入账',
            'refund_brokerage_one'   => '返还一级佣金',
            'refund_brokerage_two'   => '返还二级佣金',
            'mer_presell'            => '预售订单（总额）',
            'order_presell'          => '预售订单（定金）',
            'refund_platform_coupon' => '退回优惠券补贴',
            'order_platform_coupon'  => '优惠券补贴',
        ];
        $sys_pm_1 =
            [
                'order',
                'presell',
                'order_charge',
                'order_presell',
                'presell_charge',
                'refund_brokerage_one',
                'refund_brokerage_two'
            ];
        $mer_pm_1 =
            [
                'order',
                'presell',
                'refund_charge',
                'refund_brokerage_one',
                'refund_brokerage_two',
                'mer_presell',
                'order_platform_coupon'
            ];
        $date_ = $where['date'];
        unset($where['date']);
        $make = app()->make(FinancialRecordRepository::class);

        $query = $make->search($where)->with(['orderInfo', 'refundOrder', 'merchant.merchantCategory']);

        if ($where['type'] == 1) {
            $title_ = '日账单';
            $start_date = $date_ . ' 00:00:00';
            $end_date = $date_ . ' 23:59:59';
            $query->whereDay('create_time', $date_);
        } else {
            $title_ = '月账单';
            $start_date = (date('Y-m-01', strtotime($date_)));
            $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
            $query->whereMonth('create_time', $date_);
        }


        $income = $make->countIncome($where['type'], $where, $date_);
        $expend = $make->countExpend($where['type'], $where, $date_);
        $refund = $make->countRefund($where['type'], $where, $date_);
        $charge = bcsub($income['number'], $expend['number'], 2);
        $filename = $title_ . '(' . $date_ . ')' . time();
        $export = [];
        $limit = min($limit, 20);
        $count = $query->count();
        $i = 1;
        $order_make = app()->make(StoreOrderRepository::class);
        //平台
        if (!$where['is_mer']) {
            $header = ['商户类别', '商户分类', '商户名称', '总订单号', '订单编号', '交易流水号', '交易时间', '对方信息', '交易类型', '收支金额', '备注'];
            $list = $query->page($page, $limit)->order('create_time DESC')->select();
            foreach ($list as $value) {
                $order = $order_make->get($value['order_id']);
                $export[] = [
                    $value['merchant']['is_trader'] ? '自营' : '非自营',
                    $value['merchant']['merchantCategory']['category_name'] ?? '平台',
                    $value['merchant']['mer_name'] ?? '平台',
                    $order['groupOrder']['group_order_sn'] ?? '无数据',
                    $value['order_sn'],
                    $value['financial_record_sn'],
                    $value['create_time'],
                    $value['user_info'],
                    $financialType[$value['financial_type']],
                    (in_array($value['financial_type'], $sys_pm_1) ? '+' : '-') . $value['number'],
                    ''
                ];
            }
            $foot = [
                '合计：平台应入账手续费 ' . $charge,
                '收入合计： ' . '订单支付' . $income['count'] . '笔,' . '实际支付金额共:' . $income['number'] . '元;',
                '支出合计： ' . '佣金支出' . $expend['count_brokerage'] . '笔,支出金额：' . $expend['number_brokerage'] . '元；商户入账支出' . $expend['count_order'] . '笔，支出金额：' . $expend['number_order'] . '元；退款手续费' . $expend['count_charge'] . '笔，支出金额' . $expend['number_charge'] . '元；合计支出' . $expend['number'],
            ];
            //商户
        } else {
            $header = ['序号', '总订单号', '子订单编号', '交易流水号', '交易时间', '对方信息', '交易类型', '收支金额', '备注'];
            $mer_name = '';
            $list = $query->page($page, $limit)->order('create_time DESC')->select();
            foreach ($list as $key => $value) {
                $order = $order_make->get($value['order_id']);
                $export[] = [
                    $i,
                    $order['groupOrder']['group_order_sn'] ?? '无数据',
                    $value['order_sn'],
                    $value['financial_record_sn'],
                    $value['create_time'],
                    $value['user_info'],
                    $financialType[$value['financial_type']],
                    (in_array($value['financial_type'], $mer_pm_1) ? '+' : '-') . $value['number'],
                    ''
                ];
                $i++;
                $mer_name = $mer_name ? $mer_name : ($value['merchant']['mer_name'] ?? '');
            }

            $count_brokeage = $expend['count_brokerage'] + $expend['count_refund_brokerage'];
            $number_brokeage = bcsub($expend['number_brokerage'], $expend['number_refund_brokerage'], 2);
            $count_charge = $expend['count_charge'] + $expend['count_order_charge'];
            $number_charge = bcsub($expend['number_order_charge'], $expend['number_charge'], 2);
            $foot = [
                '合计：商户应入金额 ' . $charge,
                '收入合计： ' . '订单支付' . $income['count'] . '笔,' . '实际支付金额共:' . $income['number'] . '元;',
                '支出合计： ' . '佣金支出' . $count_brokeage . '笔,支出金额：' . $number_brokeage . '元；退款' . $expend['count_refund'] . '笔，支出金额:' . $expend['number_refund'] . '元；平台手续费' . $count_charge . '笔，支出金额：' . $number_charge . '元；合计支出金额：' . $expend['number'] . '元；',
                //'商户应入金额 '.$charge,
            ];
            $mer_name = '商户名称:' . $mer_name;
        }

        $title = [
            $title_,
            $mer_name ?? '平台',
            '结算账期：【' . $start_date . '】至【' . $end_date . '】',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 退款单导出
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 6/10/21
     */
    public function refundOrder(array $where, int $page, int $limit)
    {
        $query = app()->make(StoreRefundOrderRepository::class)->search($where)
            ->where('is_system_del', 0)->where('status', '<>', -2)->with([
                'order'    => function ($query) {
                    $query->field('order_id,order_sn,activity_type,real_name,user_address');
                },
                'refundProduct.product',
                'user'     => function ($query) {
                    $query->field('uid,nickname,phone');
                },
                'merchant' => function ($query) {
                    $query->field('mer_id,mer_name');
                },
            ])->order('StoreRefundOrder.create_time DESC');

        $title = [
            '退款订单',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header =
            [
                '商户名称',
                '退款单号',
                '申请时间',
                '最新更新时间',
                '退款金额',
                '退货件量',
                '退货商品信息',
                '退款类型',
                '订单状态',
                '拒绝理由',
                '退货人',
                '退货地址',
                '相关订单号',
                '退货物流公司',
                '退货物流单号',
                '备注'
            ];
        $filename = '退款订单' . time();

        $status = [
            0  => '待审核',
            1  => '待退货',
            2  => '待收货',
            3  => '已退款',
            -1 => '审核未通过',
            5 => '退款失败',
            4 => '退款中',
        ];
        $count = $query->count();
        $data = $query->page($page, $limit)->select()->toArray();

        $export = [];
        foreach ($data as $datum) {
            $product = '';
            foreach ($datum['refundProduct'] as $value) {
                $product .= '【' . $value['product']['cart_info']['product']['product_id'] . '】' . $value['product']['cart_info']['product']['store_name'] . '*' . $value['refund_num'] . $value['product']['cart_info']['product']['unit_name'] . PHP_EOL;
            }
            $export[] = [
                $datum['merchant']['mer_name'],
                $datum['refund_order_sn'],
                $datum['create_time'],
                $datum['status_time'] ?? ' ',
                $datum['refund_price'],
                $datum['refund_num'],
                $product,
                ($datum['refund_type'] == 1) ? '仅退款' : '退款退货',
                $status[$datum['status']],
                $datum['fail_message'],
                $datum['order']['real_name'],
                $datum['order']['user_address'],
                $datum['order']['order_sn'],
                $datum['delivery_type'],
                $datum['delivery_id'],
                $datum['mark'],
            ];
        }

        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 积分日志导出
     * @param $where
     * @param $id
     * @author Qinii
     * @day 6/10/21
     */
    public function integralLog($where, int $page, int $limit)
    {
        $title = [
            '积分日志',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['用户ID', '用户昵称', '积分标题', '变动积分', '当前积分余额', '备注', '时间'];
        $filename = '积分日志' . time();
        $export = [];
        $query = app()->make(UserBillRepository::class)->searchJoin($where)->order('a.create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $export[] = [
                $item['uid'],
                $item['nickname'],
                $item['title'],
                $item['number'],
                $item['balance'],
                $item['mark'],
                $item['create_time'],
            ];
        }
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    public function intention($where, int $page, int $limit)
    {
        $title = [
            '申请列表',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['商户姓名', '联系方式', '备注', '店铺名称', '店铺分类', '时间'];
        $filename = '申请列表' . time();
        $export = [];
        $query =
            app()->make(MerchantIntentionRepository::class)->search($where)->with(['merchantCategory', 'merchantType']
            )->order('a.create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $export[] = [
                $item['name'],
                $item['phone'],
                $item['mark'],
                $item['mer_name'],
                $item['category_name'],
                $item['create_time'],
            ];
        }
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 转账记录
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 9/28/21
     */
    public function financialLog(array $where, int $page, int $limit)
    {
        $title = [
            '转账记录',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['商户名称', '申请时间', '转账金额', '到账状态', '审核状态', '拒绝理由', '商户余额', '转账信息'];
        $filename = '转账记录_' . time();
        $export = [];
        $query =
            app()->make(FinancialRepository::class)->search($where)->with('merchant')->order(
                'Financial.create_time DESC'
            );
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            if ($item->financial_type == 1) {
                $acount = '姓名：' . $item->financial_account->name . PHP_EOL;
                $acount .= '银行名称：' . $item->financial_account->bank . PHP_EOL;
                $acount .= '银行卡号：' . $item->financial_account->bank_code;
            }
            if ($item->financial_type == 2) {
                $acount = '姓名：' . $item->financial_account->name . PHP_EOL;
                $acount .= '微信号：' . $item->financial_account->wechat . PHP_EOL;
                $acount .= '收款二维码地址：' . $item->financial_account->wechat_code;
            }
            if ($item->financial_type == 3) {
                $acount = '姓名：' . $item->financial_account->name . PHP_EOL;
                $acount .= '支付宝号：' . $item->financial_account->alipay . PHP_EOL;
                $acount .= '收款二维码地址：' . $item->financial_account->alipay_code;
            }
            $export[] = [
                $item->merchant->mer_name,
                $item->create_time,
                $item->extract_money,
                $item->financial_status == 1 ? '已转账' : '未转账',
                $item->status == 1 ? '通过' : ($item->status == 0 ? '待审核' : '拒绝'),
                $item->refusal,
                $item->mer_money,
                $acount,
            ];
        }
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 用户提现申请
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 9/28/21
     */
    public function extract(array $where, int $page, int $limit)
    {
        $title = [
            '提现申请',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $type = [
            '银行卡',
            '微信',
            '支付宝',
            '微信零钱',
        ];
        $header = ['用户名', '用户UID', '提现金额', '余额', '审核状态', '拒绝理由', '提现方式', '转账信息'];
        $filename = '提现申请_' . time();
        $path = 'extract';
        $export = [];
        $query = app()->make(UserExtractRepository::class)->search($where)->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $acount = '';
            if ($item->extract_type == 0) {
                $acount .= '银行地址：' . $item->bank_address . PHP_EOL;
                $acount .= '银行卡号：' . $item->bank_code;
            }
            if ($item->extract_type == 2) {
                $acount .= '微信号：' . $item->wechat . PHP_EOL;
                $acount .= '收款二维码地址：' . $item->extract_pic;
            }
            if ($item->extract_type == 1) {
                $acount .= '支付宝号：' . $item->alipay . PHP_EOL;
                $acount .= '收款二维码地址：' . $item->extract_pic;
            }
            $export[] = [
                $item->real_name,
                $item->uid,
                $item->extract_price,
                $item->balance,
                $item->status == 1 ? '通过' : ($item->status == 0 ? '待审核' : '拒绝'),
                $item->fail_msg,
                $type[$item->extract_type],
                $acount,
            ];
        }
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 分账管理
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 9/28/21
     */
    public function profitsharing(array $where, int $page, int $limit)
    {
        $title = [
            '分账明细',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['订单编号', '商户名称', '订单类型', '状态', '分账时间', '订单金额'];
        $filename = '分账明细_' . time();
        $export = [];
        $query =
            app()->make(StoreOrderProfitsharingRepository::class)->search($where)->with('order', 'merchant')->order(
                'create_time DESC'
            );
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $info = '分账金额：' . $item->profitsharing_price . PHP_EOL;
            if (isset($item->profitsharing_price) && $item->profitsharing_price > 0) {
                $info .= '退款金额：' . $item->profitsharing_refund . PHP_EOL;
            }
            $info .= '分账给商户金额：' . $item->profitsharing_mer_price;
            $export[] = [
                $item->order->order_sn ?? '',
                $item->merchant->mer_name,
                $item->typeName,
                $item->statusName,
                $item->profitsharing_time,
                $info
            ];
        }
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * TODO 资金记录
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 9/28/21
     */
    public function bill(array $where, int $page, int $limit)
    {
        $title = [
            '资金记录',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['用户ID', '昵称', '金额', '明细类型', '备注', '时间'];
        $filename = '资金记录_' . time();
        $export = [];
        $query = app()->make(UserBillRepository::class)
            ->searchJoin($where)->order('a.create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $export[] = [
                $item->uid,
                $item->user->nickname ?? '',
                $item->number,
                $item->title,
                $item->mark,
                $item->create_time,
            ];
        }
        $export = array_reverse($export);
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * 小程序汇总信息导出
     *
     * @param string $name
     * @param int $healthStatus
     * @param int $page
     * @param int $limit
     *
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 21:48
     */
    public function appletExport(string $name, int $healthStatus, int $page, int $limit)
    {
        $title = [
            '小程序汇总信息',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = ['小程序主体', '小程序名称', '原始ID', 'AppID', 'AppSecret', '交易体验分', '总扣分记录数', '健康状态'];
        $filename = '小程序汇总信息_' . date('Y-m-d H:i:s', time());
        $export = [];
        $query = app()->make(WxAppletDao::class)
            ->search($name, $healthStatus)->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $export[] = [
                $item->subject->subject ?? '',
                $item->name,
                $item->original_id,
                $item->original_appid,
                $item->original_appsecret,
                $item->current_score,
                $item->total_num,
                WxAppletModel::HEALTH_STATUS_NAME[$item->health_status],
            ];
        }
        $export = array_reverse($export);
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * 商户收益列表导出
     *
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function profitList(int $page, int $limit)
    {
        $title = [
            '商户收益明细',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = [
            '订单商户',
            '订单编号',
            '实收金额',
            '服务费比例',
            '服务费金额',
            '收益商户信息',
            '收益时间',
            '收益比例',
            '收益金额'
        ];
        $filename = '商户收益_' . date('Y-m-d H:i:s', time());
        $export = [];
        $query = app()->make(MerchantProfitRecordDao::class)
            ->search('*')->with([
                'storeOrder'     => function ($query) {
                    $query->field('order_id,order_sn');
                },
                'orderMerchant'  => function ($query) {
                    $query->field('mer_id,mer_name,mer_phone');
                },
                'profitMerchant' => function ($query) {
                    $query->field('mer_id,mer_name,mer_phone');
                },
            ])
            ->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $export[] = [
                $item->orderMerchant->mer_name.' '.$item->orderMerchant->mer_id,
                $item->storeOrder->order_sn,
                sprintf('%.2f', $item->order_receive_money),
                ($item->service_fee_rate * 100) . '%',
                sprintf('%.2f', $item->service_fee),
                $item->profitMerchant->mer_name . ' ' . $item->profitMerchant->mer_id,
                $item->create_time,
                ($item->profit_rate * 100) . '%',
                sprintf('%.2f', $item->profit_money),
            ];
        }
        $export = array_reverse($export);
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function profitListSimple(array $where, int $page, int $limit)
    {
        $title = [
            '收益资金明细',
            '生成时间:' . date('Y-m-d H:i:s', time())
        ];
        $header = [
            '收益时间',
            '收益金额'
        ];
        $filename = '收益资金明细_' . date('Y-m-d H:i:s', time());
        $export = [];
        $fields = 'profit_record_id,create_time,profit_money,status';
        /* @var MerchantProfitRecordDao $dao */
        $dao = app()->make(MerchantProfitRecordDao::class);
        $dao->search($fields, $where);
        $count = $dao->count();
        $list = $dao->page($page, $limit)->select();
        foreach ($list as $item) {
            $export[] = [
                $item->create_time,
                sprintf('%.2f', $item->profit_money),
            ];
        }
        $export = array_reverse($export);
        $foot = '';

        return compact('count', 'header', 'title', 'export', 'foot', 'filename');
    }

    /**
     * @param  array  $params
     * @param  int  $page
     * @param  int  $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodsPaymentListExport(array $params, int $page, int $limit, int $from):array
    {
        $sheetName = $from == self::FROM_PLATFORM ? '平台分佣' : '资产账户数据';
        $title = [
            $sheetName,
            '生成时间:'.date('Y-m-d H:i:s', time())
        ];
        $header = [
            '订单编号',
            '订单金额',
            '下单时间',
            $from == self::FROM_PLATFORM ? '商户订单来源' : '订单来源',
            '订单状态',
            '货款金额（元）',
            '货款到账金额（元）',
            '服务费金额（元）',
        ];
        if ($from == self::FROM_PLATFORM){
            array_push($header, '服务费到账');
        }
        $header = array_merge($header, [
            '结算状态',
            '最近流水时间',
        ]);
        $filename = $sheetName.'_'.date('Y-m-d H:i:s', time());
        $export = [];
        /* @var $repo MerchantGoodsPaymentRepository */
        $repo = app()->make(MerchantGoodsPaymentRepository::class);
        $query= $repo->getSearchQuery($params);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item){
            $tmp = [
                $item->storeOrder->order_sn,
                sprintf('%.2f', $item->storeOrder->pay_price),
                $item->storeOrder->create_time,
                StoreOrder::getMerchantSourceText($item->storeOrder->merchant_source),
                StoreOrderDao::getStatusText($item->storeOrder->status),
                sprintf('%.2f', $item->goods_money),
                sprintf('%.2f', $item->mer_received_money),
                sprintf('%.2f', $item->service_fee),
            ];
            if ($from == self::FROM_PLATFORM) {
                array_push($tmp, MerchantGoodsPayment::getServiceFeeStatusText($item->service_fee_status));
            }
            $tmp = array_merge($tmp, [
                MerchantGoodsPayment::getServiceFeeStatusText($item->settlement_status),
                $item->latest_flow_time,
            ]);
            $export[] = $tmp;
        }
        $export = array_reverse($export);
        $foot = '';
        return compact('count','header','title','export','foot','filename');
    }

    const FROM_PLATFORM = 1;
    const FROM_MERCHANT = 2;

    /**
     * @param  array  $params
     * @param  int  $from
     * @param  int  $page
     * @param  int  $limit
     * @return array
     */
    public function getFlowExport(array $params, int $from, int $page, int $limit):array
    {
        $sheetName = $from == self::FROM_PLATFORM ? '平台分佣流水明细' : '资产流水明细';
        $title = [
            $sheetName,
            date('Y-m-d H:i:s', time())
        ];
        $header = [
            '订单编号',
            '订单金额（元）',
            '下单时间',
            $from == self::FROM_PLATFORM ? '商户订单来源' : '订单来源',
            '结算状态',
            '微信业务单号',
            '流水入账时间',
            '收支类型',
            '收支金额（元）',
            '原因备注'
        ];
        $filename = $sheetName;
        /* @var $flowRepo OrderFlowRepository */
        $flowRepo = app()->make(OrderFlowRepository::class);
        $data = $flowRepo->getPagedListFromPayment($params, $page, $limit);
        if (!$data){
            return [];
        }
        $export = [];
        foreach ($data['list'] as $item){
            $sign = (($item['type'] == OrderFlow::FLOW_TYPE_IN) && ($item['amount'] > 0)) ? '+' : '';
            $export[] = [
                $item['order_sn'],
                sprintf('%.2f', $item['pay_price']),
                $item['order_create_time'],
                StoreOrder::getMerchantSourceText($item['merchant_source']),
                MerchantGoodsPayment::getSettlementStatusText($item['settlement_status']),
                $item['profit_sharing_id'],
                $item['create_time'],
                $item['type_text'] = OrderFlow::getFlowTypeText($item['type']),
                sprintf('%s%.2f', $sign, $item['amount'] * 0.01),
                $item['remark'],
            ];
        }
        $export = array_reverse($export);
        $foot = '';
        $count = count($export);
        return compact('count','header','title','export','foot','filename');
    }
}
