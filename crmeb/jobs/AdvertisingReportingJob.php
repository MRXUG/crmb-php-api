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


use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\system\merchant\MerchantAdDao;
use app\common\model\applet\AppletsTx;
use crmeb\interfaces\JobInterface;
use crmeb\services\ads\Action\CompleteOrder;
use think\facade\Log;

class AdvertisingReportingJob implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info("gdt-order-queue".json_encode($data));
        $gdt_param = json_decode($data['gdt_params'],1);
        //['gdt_params'=>$order->ad_query,'order_id'=>$order->order_id,'pay_price'=>$order->pay_price]
        $result = (new CompleteOrder($gdt_param['appid'],$gdt_param['unionid'],$gdt_param['gdt_vid']??$gdt_param['qz_gdt'],$data['pay_price']))->handle();
        if ($result['code'] != 0) {
            sendMessageToWorkBot([
                'module' => '广告订单回传',
                'type'   => 'error',
                'msg'    => '回传失败' . json_encode($result),
            ]);
        }
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
