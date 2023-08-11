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
class User extends BaseModel
{

    protected $schema = [
        'account'          => 'varchar', //用户账号
        'addres'           => 'varchar', //地址
        'avatar'           => 'varchar', //用户头像
        'birthday'         => 'date', //生日
        'black'            => 'tinyint', //黑名单状态1黑名单0非黑名单
        'brokerage_level'  => 'int', //推广员等级
        'brokerage_price'  => 'decimal', //佣金金额
        'cancel_time'      => 'timestamp', //注销时间
        'card_id'          => 'varchar', //身份证号码
        'count_fans'       => 'int', //用户粉丝数
        'count_start'      => 'int', //用户获赞数
        'create_time'      => 'timestamp', //添加时间
        'group_id'         => 'int', //用户分组id
        'integral'         => 'int', //积分
        'is_promoter'      => 'tinyint', //是否为推广员
        'is_svip'          => 'tinyint', //是否为付费会员 -1未开通过 0到期 1体验卡 2 有效期 3 永久
        'label_id'         => 'varchar', //用户标签 id
        'last_ip'          => 'varchar', //最后一次登录ip
        'last_time'        => 'timestamp', //最后一次登录时间
        'main_uid'         => 'int', //主账号
        'mark'             => 'varchar', //用户备注
        'member_level'     => 'int', //免费会员等级
        'member_value'     => 'int', //免费会员成长值
        'nickname'         => 'varchar', //用户昵称
        'now_money'        => 'decimal', //用户余额
        'pay_count'        => 'int', //用户购买次数
        'pay_price'        => 'decimal', //用户消费金额
        'phone'            => 'char', //手机号码
        'promoter_time'    => 'timestamp', //成功推广时间
        'pwd'              => 'varchar', //用户密码
        'real_name'        => 'varchar', //真实姓名
        'sex'              => 'tinyint', //性别
        'spread_count'     => 'int', //下级人数
        'spread_limit'     => 'timestamp', //推广员到期时间
        'spread_pay_count' => 'int', //下级订单数
        'spread_pay_price' => 'decimal', //下级订单金额
        'spread_time'      => 'timestamp', //推广员关联时间
        'spread_uid'       => 'int', //推广员id
        'status'           => 'tinyint', //1为正常，0为禁止
        'svip_endtime'     => 'timestamp', //会员结束时间
        'svip_save_money'  => 'decimal', //会员节省金额
        'uid'              => 'int', //用户id
        'user_type'        => 'varchar', //用户类型
        'wb_time'          => 'int', //加入白名单时间
        'wechat_user_id'   => 'int', //微信用户 id
        'white'            => 'tinyint', //白名单状态1白名单0非白名单
    ];


    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'uid';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'user';
    }

    /**
     * @param $value
     * @return string
     * @author xaboy
     * @day 2020-05-09
     */
    public function getBirthdayAttr($value)
    {
        return $value == '0000-00-00' ? '' : $value;
    }

    public function getIsSvipAttr($value)
    {

        if (systemConfig('svip_switch_status') == 1) {
            return $value;
        } else {
            return  $value > 0 ? 0 : $value;
        }
    }

    /**
     * @param $value
     * @return array
     * @author xaboy
     * @day 2020-05-09
     */
    public function getLabelIdAttr($value)
    {
        return $value ? explode(',', $value) : [];
    }

    /**
     * @param $value
     * @return string
     * @author xaboy
     * @day 2020-05-09
     */
    public function setLabelIdAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    public function getValidSpreadUidAttr()
    {
        if (!$this->spread_uid) return 0;
        else {
            $data = self::getDB()->where('uid', $this->spread_uid)->field('is_promoter,spread_uid')->find();
            if ($data && $data['is_promoter'])
                return $this->spread_uid;
            else
                return 0;
        }
    }

    public function getValidSpreadAttr()
    {
        if (!$this->spread_uid) return null;
        else {
            $data = self::getDB()->where('uid', $this->spread_uid)->field('is_promoter,spread_uid,brokerage_level,uid')->find();
            if ($data && $data['is_promoter'])
                return $data;
            else
                return null;
        }
    }

    public function getValidTopUidAttr()
    {
        if (!$this->top_uid) return 0;
        else {
            $data = self::getDB()->where('uid', $this->top_uid)->field('is_promoter,spread_uid')->find();
            if ($data && $data['is_promoter'])
                return $this->top_uid;
            else
                return 0;
        }
    }

    public function getValidTopAttr()
    {
        if (!$this->top_uid) return null;
        else {
            $data = self::getDB()->where('uid', $this->top_uid)->field('is_promoter,spread_uid,brokerage_level,uid')->find();
            if ($data && $data['is_promoter'])
                return $data;
            else
                return null;
        }
    }

    public function getTopUidAttr()
    {
        return self::getDB()->where('uid', $this->spread_uid)->value('spread_uid') ?: 0;
    }
    
    public function getWbTimeAttr($value){
        return $value > 0 ? date('Y-m-d H:i:s',$value) : $value;
    }

    /**
     * @return \think\model\relation\HasOne
     * @author xaboy
     * @day 2020-05-09
     */
    public function group()
    {
        return $this->hasOne(UserGroup::class, 'group_id', 'group_id');
    }

    public function spread()
    {
        return $this->hasOne(User::class, 'uid', 'spread_uid');
    }

    public function brokerage()
    {
        return $this->hasOne(UserBrokerage::class, 'brokerage_level', 'brokerage_level')->where('type',0);
    }

    public function member()
    {
        return $this->hasOne(UserBrokerage::class, 'brokerage_level', 'member_level')->where('type',1);
    }

    /**
     * @param $spreadUid
     * @author xaboy
     * @day 2020-04-28
     */
    public function setSpread($spreadUid)
    {
        if (self::getDB()->where('uid', $spreadUid)->value('is_promoter'))
            $this->save([
                'spread_uid' => $spreadUid,
                'spread_time' => date('Y-m-d H:i:s')
            ]);
    }

    public function service()
    {
        return $this->hasOne(StoreService::class, 'uid', 'uid')
            ->where('mer_id', '<>', 0)->field('service_id,uid,nickname,avatar,customer,mer_id,is_verify,is_goods')->where('is_del', 0)->where('status', 1)
            ->order('is_verify DESC,customer DESC');
    }

    public function topService()
    {
        return $this->hasOne(StoreService::class, 'uid', 'uid')
            ->where('mer_id', 0)->field('service_id,uid,nickname,avatar,customer,mer_id,is_verify,is_goods')->where('is_del', 0)->where('status', 1)
            ->order('is_verify DESC,customer DESC');
    }

    public function getLockBrokerageAttr()
    {
        return app()->make(UserBillRepository::class)->lockBrokerage($this->uid) ?: 0;
    }

    public function getLockIntegralAttr()
    {
        return app()->make(UserBillRepository::class)->lockIntegral($this->uid) ?: 0;
    }

    public function getYesterdayBrokerageAttr()
    {
        return app()->make(UserBillRepository::class)->yesterdayBrokerage($this->uid) ?: 0;
    }

    public function getTotalExtractAttr()
    {
        return app()->make(UserExtractRepository::class)->userTotalExtract($this->uid) ?: 0;
    }

    public function getTotalBrokerageAttr()
    {
        return app()->make(UserBillRepository::class)->totalBrokerage($this->uid) ?: 0;
    }

    public function getTotalBrokeragePriceAttr()
    {
        return bcadd($this->lock_brokerage, $this->brokerage_price, 2);
    }

    public function getTotalIntegralAttr()
    {
        return bcadd($this->lock_integral, $this->integral, 0);
    }

    public function getTotalRechargeAttr()
    {
        return app()->make(UserBillRepository::class)->userNowMoneyIncTotal($this->uid);
    }

    public function getTotalConsumeAttr()
    {
        return app()->make(StoreGroupOrderRepository::class)->totalNowMoney($this->uid);
    }

    public function getTotalCollectProductAttr()
    {
        return app()->make(UserRelationRepository::class)->getWhereCount(['uid' => $this->uid, 'type' => 1]);
    }

    public function getTotalCollectStoreAttr()
    {
        return app()->make(UserRelationRepository::class)->getWhereCount(['uid' => $this->uid, 'type' => 10]);
    }

    public function getTotalVisitProductAttr()
    {
        return app()->make(UserHistoryRepository::class)->userTotalHistory($this->uid);
    }

    public function getTotalCouponAttr()
    {
        return app()->make(StoreCouponUserRepository::class)->userTotal($this->uid, 0);
    }

    public function getTotalUnreadAttr()
    {
        return app()->make(StoreServiceLogRepository::class)->totalUnReadNum($this->uid);
    }

    public function getOneLevelCountAttr()
    {
        return app()->make(UserRepository::class)->getOneLevelCount($this->uid);
    }

    public function getTwoLevelCountAttr()
    {
        return app()->make(UserRepository::class)->getTwoLevelCount($this->uid);
    }

    public function getSpreadTotalAttr()
    {
        return $this->one_level_count + $this->two_level_count;
    }

    public function wechat()
    {
        return $this->hasOne(WechatUser::class, 'wechat_user_id', 'wechat_user_id');
    }

    public function getIntegralAttr($val)
    {
        return is_null($val) ? 0 : $val;
    }


//    public function getUserTypeAttr()
//    {
//        if($this->wechat['openid']){
//            return 'wechat';
//        }elseif($this->wechat['routine_openid']){
//            return 'routine';
//        }else{
//            return 'H5';
//        }
//    }

    public function getSubscribeAttr()
    {
        if ($this->wechat['openid'] && $this->wechat['subscribe']) {
            return true;
        } else {
            return false;
        }
    }

}
