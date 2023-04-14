<?php

namespace crmeb\listens;

use app\common\repositories\system\merchant\MerchantBindUserRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use think\facade\Log;

class UnbindMerAndUserRelationListen extends TimerService implements ListenerInterface
{
    protected string $name = "定时解绑过期的商户客户关系:" . __CLASS__;
    /**
     * 定时解绑过期的商户客户关系
     *
     * @param $event
     *
     * @date    2023/3/3 9:47
     */
    public function handle($event): void
    {
        if (env('app_name') == 'wandui_prod'){
            $interval = 1000 * 60 * 1;//todo-fw 2023/3/16 10:50: 测试时1分钟，生产改为10分钟
        }else{
            $interval = 1000 * 60 * 1;
        }
        $this->tick($interval, function () {
            Log::info("执行开始：解绑过期的商户客户关系");
            try {
                /**
                 * @var MerchantBindUserRepository $repo
                 */
                $repo = app()->make(MerchantBindUserRepository::class);
                $repo->removeBindingRelation();
            } catch (\Exception $e) {
                Log::error('解绑过期的商户客户关系执行错误：'.$e->getMessage());
                sendMessageToWorkBot([
                    'module' => '商户-用户绑定',
                    'msg'    => '解绑出现异常：'.$e->getMessage(),
                    'file'   => $e->getFile(),
                    'line'   => $e->getLine()
                ]);
            }
            Log::info("执行结束：解绑过期的商户客户关系");
        });
    }
}