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

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\command;

use app\common\repositories\user\UserRepository;
use app\common\repositories\risk\RiskRepository;
use crmeb\jobs\DelUserBlackJob;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\console\input\Option;

class delUserBlack extends Command{

    protected function configure(){

        // 指令配置
        $this->setName('delblack')
            ->setDescription('del user black');
    }

    protected function execute(Input $input, Output $output){
        //获取风控设置
        $riskModel = app()->make(RiskRepository::class);
        $risk = $riskModel->getRisk();

        //获取黑名单中的用户
        $userModel = app()->make(UserRepository::class);
        $where = ['black' => 1];
        $user = $userModel->where($where)->field('uid,black,wb_time')->select();

        $now = strtotime(date('Y-m-d'),time());

        //移除黑名单id数组
        $del = [];
        if($user){
            foreach($user as $k => $v){
                if($v['wb_time'] > 0){
                    $start = strtotime(date('Y-m-d'),$V['wb_time']);
                    if(($now - $start) >= $risk->blacklist_vid){
                        // $save = ['black' => 0,'wb_time' => 0];

                        Queue::push(DelUserBlackJob::class,$v);
                        // $userModel->where(['uid'=>$v['uid']])->update($save);
                    }
                }
            }
        }
    }
}