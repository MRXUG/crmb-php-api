<?php

namespace crmeb\listens;

use app\common\repositories\system\merchant\MerchantProfitRecordRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use think\facade\Log;

class UpdateMerchantProfitListen extends TimerService implements ListenerInterface
{
    protected string $name = '处理商户收益:' . __CLASS__;
    public function handle($event): void
    {
        if (env('app_name') == 'qianliu_prod'){
            $intervalSec = 3600;
            $intervalSec = 180;//todo-fw 2023/3/16 16:57: just for testing
        }else{
            $intervalSec = 300;
        }
        $this->tick(1000 * $intervalSec, function () {
            Log::info("执行开始：处理商户收益");
            try {
                /**
                 * @var MerchantProfitRecordRepository $repo
                 */
                $repo = app()->make(MerchantProfitRecordRepository::class);
                $repo->setRecordsValidAndUpdateProfit();
            } catch (\Exception $e) {
                Log::error('更新商户收益出错：'.$e->getMessage());
                sendMessageToWorkBot([
                    'module' => '商户收益',
                    'msg'    => '处理商户收益出现异常：'.$e->getMessage(),
                    'file'   => $e->getFile(),
                    'line'   => $e->getLine()
                ]);
            }
            Log::info("执行结束：处理商户收益");
        });
    }
}