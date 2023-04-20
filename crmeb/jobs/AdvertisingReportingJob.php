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
use crmeb\interfaces\JobInterface;

class AdvertisingReportingJob implements JobInterface
{
    public function fire($job, $data)
    {

        if (!$data['ad_id']) return;
        if (!$data['type']) return;
        if (!$data['query']) return;
        if (!$data['orderId']) return;
        if (!$data['merchant_source']) return;
        try {
            //查询订单对应的广告
            $ad = app()->make(MerchantAdDao::class);
            $adPostbackProportion = $ad->getValue(['ad_id'=>$data['ad_id']],'postback_proportion');

            if ($data['merchant_source'] == 1){
                //回流流量判断是否回传
                if ($adPostbackProportion == 0) return ;

                //回传几率
                $num = rand(1, 100);

                if ($adPostbackProportion <  $num) return;
            }


            $query = json_decode($data['query'],true);
            if ($data['type'] == 1){
                $click_id = $query['qz_gdt']?$query['qz_gdt']:$query['gdt_vid'];
                //腾讯广告
                $this->sendData($click_id);

                file_put_contents('orderApplets.txt',json_encode($query).PHP_EOL,FILE_APPEND);
            }elseif ($data['type'] == 2){
                //抖音广告
                $click_id = $query['clickid'];
                $this->videoSendData($click_id);
                file_put_contents('orderApplets.txt',json_encode($query).PHP_EOL,FILE_APPEND);

            }

            if ($data['merchant_source'] == 1){
                $order = app()->make(StoreOrderDao::class);
                $order->update($data['orderId'],['merchant_source'=>2]);
            }

        } catch (\Exception $e) {
            file_put_contents('orderAppletsErr.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

        };
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }


    //腾讯广告回传
    public function sendData($click_id){

        $url = 'http://tracking.e.qq.com/conv';

        $data = [
            'actions' => [
                [
                    // 'outer_action_id' => 'outer_action_identity',
                    'action_time' => time(),
                    'user_id' => [
                        'wechat_openid' => '', // wechat_openid 和 wechat_unionid 二者必填一
                        'wechat_unionid' => '', // 企业微信必填
                        'wechat_app_id' => 'gh_339196192718'  // 微信类上报必填，且必须通过授权。授权请参考微信数据接入
                    ],
                    'action_type' => 'LANDING_PAGE_CLICK', //必填 行为类型  下单 COMPLETE_ORDER   点击 LANDING_PAGE_CLICK
                    "trace" => [
                        "click_id" => $click_id // 不设置监测链接，必填 click_id
                    ],
                    'action_param' => [
                        'value' => '100',
                        'object' => 'product'
                    ]
                ]
            ]
        ];
        //提交
        $result = $this->httpCURL($url,json_encode($data));

    }

    /**
     * 抖音广告回传
     */
    public function videoSendData($click_id){
        //URL https://dianshang.sasz.cn/pages/h-advert/index?adid=__AID__&creativeid=__CID__&creativetype=__CTYPE__&clickid=__CLICKID__
        if($click_id){
            $url = 'https://analytics.oceanengine.com/api/v2/conversion';
            $data = [
                'event_type' => 'successful_pay',
                'context' => [
                    'ad' => [
                        'callback' => $click_id
                    ],
                    'timestamp' => $this->getMillisecond()
                ]
            ];

            //提交
            $result = $this->httpCURL($url,json_encode($data));
        }

    }


    /**
     * 使用CURL模拟POST请求
     * 配置参数 根据具体使用场景修改
     *
     * @param Array $data 需要提交的数据
     * @return Bool OR String
     */
    private function httpCURL($url, $data){

        $headers = [
            'Content-type: application/json',
            'cache-control: no-cache'
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $code = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $code;
    }

    //获取毫秒时间戳
    private function getMillisecond() {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }
}
