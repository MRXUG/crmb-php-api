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


namespace app\controller\api\risk;

use app\common\repositories\risk\RiskRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserRepository;
use app\common\repositories\platform\PlatformCouponReceiveRepository;
use app\common\repositories\user\FeedbackRepository;


class Risk extends BaseController{
    
    /**
     * @var RiskRepository
     */
    protected $repository;
    protected $userrepository;
    protected $platformCouponReceiveRepository;
    protected $feedbackrepository;

    /**
     * Article constructor.
     * @param App $app
     * @param RiskRepository $repository
     */
    public function __construct(App $app,RiskRepository $repository,UserRepository $userrepository,PlatformCouponReceiveRepository $platformCouponReceiveRepository,FeedbackRepository $feedbackrepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userrepository = $userrepository;
        $this->platformCouponReceiveRepository = $platformCouponReceiveRepository;
        $this->feedbackrepository = $feedbackrepository;
    }
    
    
    //获取风控设置
    public function getRisk(){
        $risk = $this->repository->getRisk();
        return app('json')->success($risk);
    }

    //黑名单规则监测
    public function checkBlack(){
        $uid = $this->request->param('uid');
        
        if($uid > 0){
            $info = $this->userrepository->get($uid);
            if($info->white == 1){
                return app('json')->success('当前用户为白名单用户');
            }

            if($info->black == 1){
                return app('json')->success('用户已经在黑名单中');
            }


            //获取设置的参数
            $risk = $this->repository->getRisk();
            
            //获取平台券数量
            $platcouponinfo = $this->platformCouponReceiveRepository->getList($uid, 1, 1);
           
            $platcouponnum = $platcouponinfo['count'];
             
            if($risk['usecoupon'] < $platcouponnum){
                $data = ['black'=>1,'wb_time'=>time()];
                $info = $this->userRepository->update($uid,$data);

                if($info){
                    $this->userRepository->cancelUserCoupon($uid);
                }

                return app('json')->success('用户触发风控,加入黑名单成功');
            }
            
            //近30天
            $now = time();
            $start = $now - 30*86400;

            //30天卡券召回次数规则

            //30天反馈次数规则
            $feednum = $this->feedbackrepository->get30day($uid,$start,$now);
            if($feednum >= $risk['day30feedback']){

                $data = ['black'=>1,'wb_time'=>time()];
                $info = $this->userRepository->update($uid,$data);

                if($info){
                    $this->userRepository->cancelUserCoupon($uid);
                }
                return app('json')->success('用户触发风控,加入黑名单成功');
            }

            return app('json')->success('未触发风控');
        }
    }



    public function checkBlackApi($uid){
        if($uid > 0){
            $info = $this->userrepository->get($uid);
            if($info->white == 1){
                return app('json')->success('当前用户为白名单用户');
            }

            if($info->black == 1){
                return app('json')->success('用户已经在黑名单中');
            }


            //获取设置的参数
            $risk = $this->repository->getRisk();

            //获取平台券数量
            $platcouponinfo = $this->platformCouponReceiveRepository->getList($uid, 1, 1);

            $platcouponnum = $platcouponinfo['count'];

            if($risk['usecoupon'] < $platcouponnum){
                $data = ['black'=>1,'wb_time'=>time()];
                $info = $this->userRepository->update($uid,$data);

                if($info){
                    $this->userRepository->cancelUserCoupon($uid);
                }

                return app('json')->success('用户触发风控,加入黑名单成功');
            }

            //近30天
            $now = time();
            $start = $now - 30*86400;

            //30天卡券召回次数规则

            //30天反馈次数规则
            $feednum = $this->feedbackrepository->get30day($uid,$start,$now);
            if($feednum >= $risk['day30feedback']){

                $data = ['black'=>1,'wb_time'=>time()];
                $info = $this->userRepository->update($uid,$data);

                if($info){
                    $this->userRepository->cancelUserCoupon($uid);
                }
                return app('json')->success('用户触发风控,加入黑名单成功');
            }

            return app('json')->success('未触发风控');
        }
    }

}