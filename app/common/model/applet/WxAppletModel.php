<?php
namespace app\common\model\applet;

use app\common\model\BaseModel;
use app\common\model\user\UserGroup;
use think\model\relation\HasOne;

class WxAppletModel extends BaseModel
{
    /**
     * 健康状态：1：健康，2：中风险，3：高风险
     */
    const APPLET_HEALTHY = 1;
    const APPLET_MEDIUM_RISK = 2;
    const APPLET_HIGH_RISK = 3;

    /**
     * is_del 是否删除，0=默认，未删除; 1=删除
     */
    const DELETED_YES = 1;
    const DELETED_NO = 0;

    /**
     * 小程序健康状态
     */
    const HEALTH_STATUS_NAME = [
        self::APPLET_HEALTHY => '健康',
        self::APPLET_MEDIUM_RISK => '中风险',
        self::APPLET_HIGH_RISK => '高风险',
    ];

    /**
     * 是否删除
     */
    const IS_DEL_YES = 1;
    const IS_DEL_NO = 0;

    /**
     * is_release 是否发布：0未发布，1已发布
     */
    const IS_RELEASE_YES = 1;
    const IS_RELEASE_NO = 0;

    /**
     * appid和appsecret映射缓存
     * 参数：appid
     */
    const CACHE_APPID_TO_SECRET = 'appid_to_secret:%s';

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'applet';
    }

    public function subject(): HasOne
    {
        return $this->hasOne(WxAppletSubjectModel::class, 'id', 'subject_id');
    }

    /**
     * 获取最新提审记录
     * @return HasOne
     * @author  wzq
     * @date    2023/3/8 14:38
     */
    public function submit(): HasOne
    {
        return $this->hasOne(WxAppletSubmitAuditModel::class, 'original_appid', 'original_appid')->order('id', 'desc');
    }

}