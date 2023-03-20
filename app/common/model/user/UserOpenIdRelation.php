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


namespace app\common\model\user;


use app\common\model\BaseModel;
use app\common\model\store\service\StoreService;
use app\common\model\wechat\WechatUser;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserExtractRepository;
use app\common\repositories\user\UserHistoryRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserRepository;

/**
 * Class User
 * @package app\common\model\user
 * @author xaboy
 * @day 2020-04-28
 */
class UserOpenIdRelation extends BaseModel
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'user_openid_relation';
    }

}
