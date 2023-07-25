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

use app\common\dao\user\UserOpenIdRelationDao;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class WechatUserRelationJob implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info("queue-wechat-user-relation-appid" . json_encode($data));
        $userOpenidRelationData = [
            'routine_openid' => $data['openid'],
            'unionid'        => $data['unionid'],
            'appid'          => $data['appid'],
            'wechat_user_id' => $data['wechat_user_id'],
            'create_time' =>date('Y-m-d H:i:s',time()),
        ];
        (new UserOpenIdRelationDao())->createOrUpdate(
            [
                'routine_openid' => $data['openid'],
                'appid'          => $data['appid'],
            ],
            $userOpenidRelationData
        );

        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
