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


    protected $schema = [
        'id' => 'int',//
        'mer_id' => 'int',//
        'uid' => 'int',//系统uid 由payer_openid 关联查出
        'wechat_notify_id' => 'varchar',//微信通知id
        'create_time' => 'datetime',//通知创建时间——微信时间
        'event_type' => 'varchar',//事件类型，COMPLAINT.CREATE：产生新投诉 COMPLAINT.STATE_CHANGE：投诉状态变化
        'summary' => 'varchar',//回调摘要
        'complaint_id' => 'varchar',//微信投诉单号**important--此表唯一
        'action_type' => 'varchar',//动作类型：
        'out_trade_no' => 'varchar',//【商户订单号】 投诉单关联的商户订单号
        'complaint_time' => 'datetime',//通常与create time一致
        'amount' => 'int',//订单金额*100
        'payer_phone' => 'varchar',//【投诉人联系方式】 投诉人联系方式
        'complaint_detail' => 'varchar',//投诉详情
        'complaint_state' => 'tinyint',//【投诉单状态】 1.PENDING-待处理,2.PROCESSING-处理中,3.PROCESSED-已处理完成
        'transaction_id' => 'varchar',//【微信订单号】 投诉单关联的微信订单号
        'complaint_handle_state' => 'varchar',//
        'complaint_full_refunded' => 'int',//【投诉单是否已全额退款】 投诉单下所有订单是否已全部全额退款
        'complaint_media_list' => 'text',//json
        'incoming_user_response' => 'int',//【是否有待回复的用户留言】 投诉单是否有待回复的用户留言
        'payer_openid' => 'varchar',//【投诉人OpenID】 投诉人在商户AppID下的唯一标识，支付分服务单类型无
        'payer_phone_encrypt' => 'text',//投诉电话-加密
        'problem_description' => 'varchar',//【问题描述】 用户发起投诉前选择的faq标题（2021年7月15日之后的投诉单均包含此信息）
        'problem_type' => 'tinyint',//【问题类型】REFUND,SERVICE_NOT_WORK,OTHERS
        'user_complaint_times' => 'int',//用户投诉次数
        'service_order_info' => 'text',//服务单信息
        'additional_info' => 'text',//【补充信息】 用在特定行业或场景下返回的补充信息
        'user_tag_list' => 'varchar',//用户标签
        'apply_refund_amount' => 'int',//【申请退款金额】 仅当问题类型为申请退款时, 有值, (单位:分)
        'log_create_time' => 'datetime',//日志记录时间
        'update_time' => 'datetime',//

    ];

}