<?php
/**
 * @user: BEYOND 2023/3/3 11:17
 */

namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponConfigDao;
use app\common\dao\coupon\CouponStocksUserDao;
use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\user\UserHistoryDao;
use app\common\model\coupon\CouponStocks;
use app\common\model\coupon\CouponStocksUser;
use app\common\model\platform\PlatformCouponReceive;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;


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
     * @param $type  1=首页弹窗 2=个人中心弹窗 3=卡包召回落地弹窗 4=广告回流券弹窗 5=支付/下单后列表
     * @return int 返回发放券数量
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userSuitablePlatformCoupon($uid = 0,$type = 0,$couponConfig = [])
    {


        if (in_array($type,[1,2,3])){
            $popups = $this->typePopups1($uid,$couponConfig,$type);
            if ($popups === false) return 0;

            $userCoupon = $this->getUserUseCoupon($uid,$couponConfig);
            if ($userCoupon === false) return  0 ;
        }

//        if ($type == 4){
//            $userReflow = $this->userReflowCoupon($uid,$couponConfig);
//            if ($userReflow === false)return  0 ;
//        }

        $userCoupon = $this->getUserUseCoupon($uid,$couponConfig);
        if ($userCoupon === false) return  0 ;

        //返回对应弹窗发券数量
        if ($type == 1) return $couponConfig['homepagePopupNum'];

        if ($type == 2) return $couponConfig['personalCenterPopup'];

        if ($type == 3) return $couponConfig['cardBagRecallLandingPagePopupNotificationNum'];

        if ($type == 4) return $couponConfig['advertisingReflowCouponPopupNum'];

        if ($type == 5) return $couponConfig['listAfterPaymentAndOrderPlacementNum'];

        return 0;
    }

    //用户回流券数量是否大于发券设置
    public function userReflowCoupon($uid=0,$couponConfig){
        $reflow = (new CouponStocksUserDao())->search(0,['uid'=>$uid,'type'=>2])->count();
        if ($reflow >= $couponConfig['advertisingReflowCouponPopupNum'])return false;
        return  true;

    }

    //获取用户可使用券是否超出
    public function getUserUseCoupon($uid,$couponConfig){
        $date = date('Y-m-d H:i:s');

        //查询用户未使用的券有多少
        $userCouponNum  = CouponStocksUser::getDB()->where("uid",'=',$uid)
            ->where("start_at",'<',$date)
            ->where("end_at",'>',$date)
            ->where("written_off",'=',0)
            ->where("is_del",'=',0)
            ->count();
        //用户待用券超出数量
        if ($userCouponNum >= $couponConfig['userUseCouponExceedNum'])return false;

        return  true;
    }

    //获取上次领券是否超出发券设置的间隔天数
    public function typePopups1($uid,$couponConfig,$type){
        $date = date('Y-m-d H:i:s');
        //获取上次用户平台券时间
        $userPlatFormCouponDate = PlatformCouponReceive::getDB()->where("user_id",$uid)->where("use_type",$type)->order("id desc")->value("create_time");

        $time1=strtotime($date);

        $time2=strtotime($userPlatFormCouponDate);

        $diff_seconds = $time1 - $time2;

        $diff_days = floor($diff_seconds/86400);
        //发券弹窗间隔天数
        if ($diff_days <= $couponConfig['issueCouponsIntervalDate']) return  false;

        return  true;

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
    //查询用户是那种类型
    public function getUserType($uid){

        //首次访问
        $history = (new UserHistoryDao())->userTotalHistory($uid);
        if ($history ==  0) return 2;

        //新客
        $payOrder = (new StoreOrderDao())->getWhereCount(['uid'=>$uid,'paid'=>1]);
        if ($payOrder == 0) return  3;

        //老客
        if ($payOrder > 0) return  4;

        return  0;
    }


}
