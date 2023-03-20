<?php
namespace app\common\model\applet;

use app\common\model\BaseModel;

class WxAppletSubmitAuditModel extends BaseModel
{
    /**
     * 状态：0：从未提审，1：未提审，2：提审中，3：提审通过，4：提审失败，5：发布成功，6：发布失败
     */
    const STATUS_NONE = 0;
    const STATUS_WAIT = 1;
    const STATUS_AUDITING = 2;
    const STATUS_AUDIT_SUCCESS = 3;
    const STATUS_AUDIT_FAIL = 4;
    const STATUS_RELEASE_SUCCESS = 5;
    const STATUS_RELEASE_FAIL = 6;

    /**
     * 检测状态：0：待检测，1：检查通过，2：检测未通过
     */
    const DETECTION_STATUS_WAIT = 0;
    const DETECTION_STATUS_SUCCESS = 1;
    const DETECTION_STATUS_FAIL = 2;

    /**
     * 审核状态：-1：未审核，0：审核成功，1：审核被拒绝，2：审核中，3：已撤回，4：审核延后
     */
    const SUBMIT_AUDIT_STATUS_WAIT = -1;
    const SUBMIT_AUDIT_STATUS_SUCCESS = 0;
    const SUBMIT_AUDIT_STATUS_FAIL = 1;
    const SUBMIT_AUDIT_STATUS_AUDITING = 2;
    const SUBMIT_AUDIT_STATUS_WITHDRAW = 3;
    const SUBMIT_AUDIT_STATUS_DELAY = 4;

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'applet_submit_audit';
    }
}