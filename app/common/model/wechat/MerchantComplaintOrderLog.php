<?php


namespace app\common\model\wechat;


use app\common\model\BaseModel;

class MerchantComplaintOrderLog extends BaseModel
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
        return 'wechat_merchant_complaint_order_log';
    }

    protected $schema = [
        'id' => 'int',//
        'mer_id' => 'int',//商户id
        'wechat_notify_id' => 'varchar',//微信通知id
        'create_time' => 'datetime',//通知创建时间——微信时间
        'event_type' => 'varchar',//事件类型，COMPLAINT.CREATE：产生新投诉 COMPLAINT.STATE_CHANGE：投诉状态变化
        'resource_type' => 'varchar',//资源类型
        'summary' => 'varchar',//回调摘要
        'complaint_id' => 'varchar',//微信投诉单号**important
        'action_type' => 'varchar',//动作类型：
        'out_trade_no' => 'varchar',//【商户订单号】 投诉单关联的商户订单号，关联store_order表
        'complaint_time' => 'datetime',//通常与create time一致
        'amount' => 'int',//订单金额*100
        'payer_phone' => 'varchar',//【投诉人联系方式】 投诉人联系方式
        'complaint_detail' => 'varchar',//投诉详情
        'complaint_state' => 'varchar',//【投诉单状态】1.PENDING-待处理,2.PROCESSING-处理中,3.PROCESSED-已处理完成
        'transaction_id' => 'varchar',//【微信订单号】 投诉单关联的微信订单号
        'complaint_handle_state' => 'varchar',//
        'complaint_full_refunded' => 'int',//【投诉单是否已全额退款】 投诉单下所有订单是否已全部全额退款
        'complaint_media_list' => 'text',//json
        'incoming_user_response' => 'int',//【是否有待回复的用户留言】 投诉单是否有待回复的用户留言
        'payer_openid' => 'varchar',//【投诉人OpenID】 投诉人在商户AppID下的唯一标识，支付分服务单类型无
        'payer_phone_encrypt' => 'text',//投诉电话-加密
        'problem_description' => 'varchar',//【问题描述】 用户发起投诉前选择的faq标题（2021年7月15日之后的投诉单均包含此信息）
        'problem_type' => 'varchar',//【问题类型】REFUND,SERVICE_NOT_WORK,OTHERS
        'user_complaint_times' => 'int',//用户投诉次数
        'service_order_info' => 'text',//服务单信息
        'additional_info' => 'text',//【补充信息】 用在特定行业或场景下返回的补充信息
        'user_tag_list' => 'varchar',//用户标签
        'apply_refund_amount' => 'int',//【申请退款金额】 仅当问题类型为申请退款时, 有值, (单位:分)
        'log_create_time' => 'datetime',//日志记录时间

    ];

}