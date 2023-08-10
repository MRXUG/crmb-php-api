<?php


namespace app\common\model\wechat;


use app\common\model\BaseModel;

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

}