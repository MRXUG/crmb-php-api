<?php
/**
 * @user: BEYOND 2023/3/3 11:17
 */

namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponConfigDao;
use app\common\model\coupon\CouponStocks;
use app\common\repositories\BaseRepository;


class CouponConfigRepository extends BaseRepository
{

    public function __construct(CouponConfigDao $dao)
    {
        $this->dao = $dao;
    }

    public function updateCouponConfig($data){
        $this->dao->updateCouponConfig($data);
    }


    /**用户是否可以发券
     * @param $uid
     * @param $platformCouponId 平台优惠券id
     * @param $type  1=首页弹窗 2=个人中心弹窗 3=卡包召回落地弹窗 4=广告回流券弹窗 5=支付/下单后列表
     * @return numeric 返回发放券数量
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userSuitablePlatformCoupon($uid = 0,$platformCouponId = 0,$type = 0)
    {

        //查询发券风险设置
        $couponConfig = $this->getCouponConfig();

        //是否关闭弹窗
        if ($couponConfig['closeClickToSendCoupons'] == 1)return 0;

        $date = date('Y-m-d H:i:s');

        //查询用户未使用的券有多少
        $userCouponNum  = CouponStocks::getDB()->where("uid",'=',$uid)
            ->where("start_at",'>',$date)
            ->where("end_at",'<',$date)
            ->where("written_off",'=',0)
            ->where("is_del",'=',0)
            ->count();

        //用户待用券超出数量
        if ($userCouponNum >= $couponConfig['userUseCouponExceedNum'])return 0;

        //获取上次用户平台券时间
        $userPlatFormCouponDate = '';

        $time1=strtotime($date);

        $time2=strtotime($userPlatFormCouponDate);

        $diff_seconds = $time2 - $time1;

        $diff_days = floor($diff_seconds/86400);
        //发券弹窗间隔天数
        if ($diff_days >= $couponConfig['issueCouponsIntervalDate']) return  0;


        //返回对应弹窗发券数量
        if ($type == 1) return $couponConfig['homepagePopupNum'];

        if ($type == 2) return $couponConfig['personalCenterPopup'];

        if ($type == 3) return $couponConfig['cardBagRecallLandingPagePopupNotificationNum'];

        if ($type == 4) return $couponConfig['advertisingReflowCouponPopupNum'];

        if ($type == 5) return $couponConfig['listAfterPaymentAndOrderPlacementNum'];




        return 0;
    }

    /**
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCouponConfig(){
        $list = $this->dao->selectWhere([],'configKey,configValue')->toArray();
        return array_column($list,'configValue','configKey');
    }


}
