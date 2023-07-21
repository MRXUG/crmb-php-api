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

// 事件定义文件
return [
    'bind'      => [],

    'listen'    => [
        'AppInit'                   => [],
        'HttpRun'                   => [],
        'HttpEnd'                   => [],
        'LogLevel'                  => [],
        'LogWrite'                  => [],
        'swoole.task'               => [\crmeb\listens\SwooleTaskListen::class],
        'swoole.init'               => [
            \crmeb\listens\InitSwooleLockListen::class,
            \crmeb\listens\CreateTimerListen::class,
            //            \crmeb\listens\QueueListen::class,
        ],
        'swoole.workerStart'        => [\app\webscoket\SwooleWorkerStart::class],
        'swoole.workerExit'         => [\crmeb\listens\SwooleWorkerExitListen::class], // 清除所有定时任务
        'swoole.workerError'        => [\crmeb\listens\SwooleWorkerExitListen::class],
        'swoole.workerStop'         => [\crmeb\listens\SwooleWorkerExitListen::class],
        'create_timer'              => env("app_server.run_server") == 'job' ? [
            \crmeb\listens\LiveStatusCheckListen::class, //
            \crmeb\listens\AutoOrderProfitsharingListen::class, // TODO 自动分账 查询逻辑有问题日志一直写
            \crmeb\listens\OrderRefundListen::class, // 用户退款 分账回退
            \crmeb\listens\PlatformCouponEliminateWeChatCoupons::class, // 自动消除快过期平台券
            \crmeb\listens\RefreshPlatformCouponListen::class, // 自动刷新平台优惠券列表商品
            \crmeb\listens\AutoCancelGroupOrderListen::class, // 自动关闭订单
            \crmeb\listens\AuthCancelPresellOrderListen::class,
            \crmeb\listens\AutoUnLockBrokerageListen::class, // 解冻佣金
            \crmeb\listens\AutoSendPayOrderSmsListen::class, // 待支付订单短信通知
            \crmeb\listens\SyncSmsResultCodeListen::class, // 更新短信记录
            \crmeb\listens\ExcelFileDelListen::class, // 自动删除导出文件
            \crmeb\listens\RefundOrderAgreeListen::class, // 自动退款
            \crmeb\listens\AutoOrderReplyListen::class, // 系统默认好评
            \crmeb\listens\SyncSpreadStatusListen::class, // 分销员绑定关系到期状态
            \crmeb\listens\GuaranteeCountListen::class, // 自动更新服务保障统计数据
            \crmeb\listens\AutoUnLockIntegralListen::class, // 冻结积分
            \crmeb\listens\AutoClearIntegralListen::class, // 清除到期积分
            \crmeb\listens\MerchantApplyMentsCheckListen::class, // 申请分账子商户结果查询
            \crmeb\listens\AutoUnlockMerchantMoneyListen::class, // 冻结商户余额
            \crmeb\listens\SumCountListen::class,
            \crmeb\listens\SyncHotRankingListen::class,
            \crmeb\listens\UnbindMerAndUserRelationListen::class,// 解绑失效的商户-用户关系
            \crmeb\listens\AuthAcquirePenaltyListen::class,// 自动更新小程序获取交易体验分违规记录
            \crmeb\listens\UpdateDeliveryProfitSharingStatus::class,// 定时更新发货分佣结果
            \crmeb\listens\UpdateAppletSubmitAuditListen::class, // 异步处理小程序提审流程
            \crmeb\listens\ProfitSharingUnfreezeListen::class,// 解冻商户资金
            \crmeb\listens\UpdateDeliveryOrderUnfreezeStatus::class,// 更新解冻状态
            \crmeb\listens\FinishOrderListen::class,// 15天后分账回退,
            \crmeb\listens\AuthProductStockSetListen::class, // 每天自动恢复商品库存
            \crmeb\listens\UpdateDeliverProfitSharingReturnListen::class, // 更新分账回退结果

            // TODO 待确定是否需要
            // \crmeb\listens\UpdateMerchantProfitListen::class,// 处理商户收益(放在job中每日凌晨执行一次)
            // \crmeb\listens\AuthCancelActivityListen::class, // 自动同步活动状态
            // \crmeb\listens\CloseUserSvipListen::class, // 关闭付费会员
            // \crmeb\listens\SendSvipCouponListen::class, // 公司会员优惠券
            // \crmeb\listens\ProductPresellStatusListen::class, // 检测预售商品状态
            // \crmeb\listens\ProductGroupStatusCheckListen::class, // 自动检测拼团结果
            // \crmeb\listens\SeckillTImeCheckListen::class, // 检测秒杀商品状态
            // \crmeb\listens\SyncBroadcastStatusListen::class, // 同步直播商品
            // \crmeb\listens\AuthTakeOrderListen::class, // 自动收货
        ] : [],
        'pay_success_user_recharge' => [\crmeb\listens\pay\UserRechargeSuccessListen::class],
        'pay_success_user_order'    => [\crmeb\listens\pay\UserOrderSuccessListen::class],
        'pay_success_order'         => [\crmeb\listens\pay\OrderPaySuccessListen::class],
        'pay_success_presell'       => [\crmeb\listens\pay\PresellPaySuccessListen::class],
        'pay_success_meal'          => [\crmeb\listens\pay\MealSuccessListen::class],
        'order.delivery'            => [\crmeb\listens\OrderDeliveryListen::class],
    ],

    'subscribe' => [],
];
