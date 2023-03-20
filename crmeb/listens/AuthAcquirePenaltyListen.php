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


namespace crmeb\listens;


use app\common\dao\applet\WxAppletDao;
use app\common\repositories\applet\WxAppletRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use think\facade\Log;


class AuthAcquirePenaltyListen extends TimerService implements ListenerInterface
{

    protected string $name = "自动更新小程序获取交易体验分违规记录:" . __CLASS__;
    /**
     * 自动更新小程序获取交易体验分违规记录
     *
     * @param $event
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/3 9:47
     */
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 60 * 4 , function () {
            $limit = 50;
            $page = 0;
            while (true) {
                $page ++;
                Log::info('自动更新小程序获取交易体验分违规记录，第'.$page.'波开始');
                $appletDao = app()->make(WxAppletDao::class);
                $appletList = $appletDao->search()->page($page, $limit)->select()->toArray();
                $count = count($appletList);
                $repository = app()->make(WxAppletRepository::class);
                foreach ($appletList as $applet) {
                    Log::info('自动更新小程序获取交易体验分违规记录，小程序'.$applet['id'].'开始');
                    $repository->acquirePenaltyList($applet['id'], $applet['original_appid'], $applet['original_appsecret']);
                }
                Log::info('第'.$page.'波共获取'.$count.'个小程序交易体验分违规记录');
                if ($count < $limit) {
                    Log::info('自动更新小程序获取交易体验分违规记录完成');
                    break;
                }
            }
        });
    }
}
