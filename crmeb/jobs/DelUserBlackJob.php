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


namespace crmeb\jobs;


use app\common\repositories\user\UserRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class DelUserBlackJob implements JobInterface{
    public function fire($job, $data){  

        $userModel = app()->make(UserRepository::class);

        $save = ['black' => 0,'wb_time' => 0];
        $userModel->where(['uid'=>$data['uid']])->update($save);
    }


    public function failed($data)
    {
        // TODO 移除用户黑名单失败
    }
}