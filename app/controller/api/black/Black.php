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


namespace app\controller\api\black;

use app\common\model\black\UserBlackLog;
use app\common\repositories\user\UserRepository;
use app\common\repositories\black\UserBlackLogRepository;
use think\App;
use crmeb\basic\BaseController;


class Black extends BaseController{

    protected $userRepository;
    protected $userblackLogRepository;
    protected $user;

    public function __construct(App $app, UserRepository $userRepository,UserBlackLogRepository $userblackLogRepository)
    {
        parent::__construct($app);
        $this->userRepository = $userRepository;
        $this->userblackLogRepository = $userblackLogRepository;
    }

    /**
     * 设置黑名单
     */
    public function Operate($uid=0,$operate='add'){
        if($this->request->has('uid')){
            $uid = $this->request->param('uid');

            $this->user = $this->userRepository->get($uid);
        }else{
            if($uid){
                $this->user = $this->userRepository->get($uid);
            }
        }

        if($this->user){
            if($this->request->has('operate')){
                $operate = $this->request->param('operate');

                switch($operate){
                    case 'add':
                        //拉入黑名单

                        if($this->user->black == 1){
                            return app('json')->success('用户已经加入黑名单');
                        }

                        if($this->user->white == 1){
                            return app('json')->success('白名单用户不能加入黑名单');
                        }

                        $data = ['black'=>1,'wb_time'=>time()];
                        $info = $this->userRepository->update($uid,$data);
                        if($info){
                            //优惠券失效
                            $this->userRepository->cancelUserCoupon($uid);
                        }
                        UserBlackLog::getInstance()->insert([
                            'operate' => 1,
                            'uid' => $uid,
                            'type' => 2,
                            'create_time' => time()
                        ]);
                        return app('json')->success('黑名单设置成功');
                        break;
                    case 'del':
                        //移除黑名单
                        $data = ['black'=>0,'wb_time'=>time()];
                        $this->userRepository->update($uid,$data);

                        UserBlackLog::getInstance()->insert([
                            'operate' => 0,
                            'uid' => $uid,
                            'type' => 2,
                            'create_time' => time()
                        ]);

                        return app('json')->success('黑名单移除成功');
                        break;
                    default:
                        return app('json')->success('黑名单状态获取成功');
                }
            }
        }else{
            return app('json')->fail('参数错误');
        }
    }


    /**
     * 黑名单操作记录
     * $type 1加入黑名单0移出黑名单
     * $uid  用户id
     * $operate  变更形式1系统判定2人工添加3用户主动
     */
    public function setLog($data=[]){
        if($this->request->has('uid')){
            $param = $this->request->param();
            $arr = [
                'uid' => $param['uid'],
                'type' => $param['type'],
                'operate' => $param['operate'],
                'create_time' => time()
            ];
        }else{
            if(isset($data['uid'])){
                $arr = [
                    'uid' => $data['uid'],
                    'type' => $data['type'],
                    'operate' => $data['operate'],
                    'create_time' => time()
                ];
            }
        }

        $info = app()->make(UserBlackLogRepository::class)->create($arr);
        if($info){
            return app('json')->success('记录成功');
        }else{
            return app('json')->fail('参数错误');
        }
    }

    /**
     * 用户详情 黑名单记录
     */
    public function getLog(){
        if($this->request->has('uid')){
            $uid = $this->request->param('uid');

            if($uid > 0){
                [$page, $limit] = $this->getPage();
                $where = [];
                return app('json')->success($this->userblackLogRepository->search($where, $page, $limit));
            }
        }else{
            return app('json')->fail('参数错误');
        }
    }

}




