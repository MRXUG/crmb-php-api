<?php


namespace app\common\model\wechat;


use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;

class MerchantComplaintOrder extends BaseModel
{

    /**
     * @inheritDoc
     */
    public static function tablePk(): ?string
    {
        return 'id';
    }

    /**
     * @inheritDoc
     */
    public static function tableName(): string
    {
        return 'wechat_merchant_complaint_order';
    }

    const COMPLAINT_STATUS_PENDING = 1;
    const COMPLAINT_STATUS_PROCESSING = 2;
    const COMPLAINT_STATUS_PROCESSED = 3;
    const COMPLAINT_STATE = [
        'PENDING' => self::COMPLAINT_STATUS_PENDING,
        'PROCESSING' => self::COMPLAINT_STATUS_PROCESSING,
        'PROCESSED' => self::COMPLAINT_STATUS_PROCESSED,
    ];


    const PROBLEM_TYPE_REFUND = 1;
    const PROBLEM_TYPE_SERVICE_NOT_WORK = 2;
    const PROBLEM_TYPE_OTHERS = 3;
    const PROBLEM_TYPE = [
        'REFUND' => self::PROBLEM_TYPE_REFUND,
        'SERVICE_NOT_WORK' => self::PROBLEM_TYPE_SERVICE_NOT_WORK,
        'OTHERS' => self::PROBLEM_TYPE_OTHERS,
    ];

    CONST TIMEOUT_NO = 1;
    CONST TIMEOUT_YES = 2;

    /**
     *
     *
     * action_type
     * 常规通知：
    CREATE_COMPLAINT：用户提交投诉
    CONTINUE_COMPLAINT：用户继续投诉
    USER_RESPONSE：用户新留言
    RESPONSE_BY_PLATFORM：平台新留言
    SELLER_REFUND：商户发起全额退款
    MERCHANT_RESPONSE：商户新回复
    MERCHANT_CONFIRM_COMPLETE：商户反馈处理完成

    申请退款单的附加通知：
    以下通知会更新投诉单状态，建议收到后查询投诉单详情。
    MERCHANT_APPROVE_REFUND：商户同意退款
    MERCHANT_REJECT_REFUND：商户驳回退款
    REFUND_SUCCESS：退款到账
     *
     *
     *
     * problem_type
    选填
    string
    【问题类型】 问题类型为申请退款的单据是需要最高优先处理的单据
    可选取值：
    REFUND: 申请退款
    SERVICE_NOT_WORK: 服务权益未生效
    OTHERS: 其他类型
     */


    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function relationUser()
    {
        // relation
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function storeOrder()
    {
        return $this->hasOne(StoreOrder::class, 'pay_order_sn', 'out_trade_no');
    }

    public static function operationType($actionType){
        return [
                'USER_CREATE_COMPLAINT' => '用户提交投诉',
                'USER_CONTINUE_COMPLAINT' => '用户继续投诉',
                'USER_RESPONSE' => '用户留言',
                'PLATFORM_RESPONSE' => '平台留言',
                'MERCHANT_RESPONSE' => '商户留言',
                'MERCHANT_CONFIRM_COMPLETE' => '商户申请结单',
                'USER_CREATE_COMPLAINT_SYSTEM_MESSAGE' => '用户提交投诉系统通知',
                'COMPLAINT_FULL_REFUNDED_SYSTEM_MESSAGE' => '投诉单发起全额退款系统通知',
                'USER_CONTINUE_COMPLAINT_SYSTEM_MESSAGE' => '用户继续投诉系统通知',
                'USER_REVOKE_COMPLAINT' => '用户主动撤诉（只存在于历史投诉单的协商历史中）',
                'USER_COMFIRM_COMPLAINT' => '用户确认投诉解决（只存在于历史投诉单的协商历史中）',
                'PLATFORM_HELP_APPLICATION' => '平台催办',
                'USER_APPLY_PLATFORM_HELP' => '用户申请平台协助',
                'MERCHANT_APPROVE_REFUND' => '商户同意退款申请',
                'MERCHANT_REFUSE_RERUND' => '商户拒绝退款申请, 此时操作内容里展示拒绝原因',
                'USER_SUBMIT_SATISFACTION' => '用户提交满意度调查结果,此时操作内容里会展示满意度分数',
                'SERVICE_ORDER_CANCEL' => '服务订单已取消',
                'SERVICE_ORDER_COMPLETE' => '服务订单已完成',
                'COMPLAINT_PARTIAL_REFUNDED_SYSTEM_MESSAGE' => '投诉单发起部分退款系统通知',
                'COMPLAINT_REFUND_RECEIVED_SYSTEM_MESSAGE' => '投诉单退款到账系统通知',
                'COMPLAINT_ENTRUSTED_REFUND_SYSTEM_MESSAGE' => '投诉单受托退款系统通知',
            ][$actionType] ?? '未知类型';
    }

    public static function actionType($actionType){
        return
            [
                'CREATE_COMPLAINT' => '用户提交投诉',
                'CONTINUE_COMPLAINT' => '用户继续投诉',
                'USER_RESPONSE' => '用户新留言',
                'RESPONSE_BY_PLATFORM' => '平台新留言',
                'SELLER_REFUND' => '商户发起全额退款',
                'MERCHANT_RESPONSE' => '商户新回复',
                'MERCHANT_CONFIRM_COMPLETE' => '商户反馈处理完成',
                ][$actionType] ?? $actionType;
    }

}