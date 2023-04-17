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

use app\common\repositories\user\UserRepository;
use think\App;
use crmeb\basic\BaseController;


class Black extends BaseController{

    protected $repository;
    protected $user;

    public function __construct(App $app, UserRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->user = $this->request->userInfo();
    }

    /**
     * 设置黑名单
     */
    public function Operate($uid=0,$operate='add'){
        if($this->request->has('uid')){
            $uid = $this->request->param('uid');

            $user = $this->request->get($uid);
        }else{
            if($uid){
                $user = $this->request->get($uid);
            }
        }

        if($user){
            if($this->request->has('operate')){
                $operate = $this->request->param('operate');
            }

            switch(){
                case 'get':
                    return app('json')->success('获取成功',$data['info'=>1]);
                    break;
                case 'del':
                    return app('json')->success('修改成功');
                    break;
                default:

                    return app('json')->success('修改成功');
                    
            }
        }else{
            return app('json')->fail('参数错误');
        }
    }

}




