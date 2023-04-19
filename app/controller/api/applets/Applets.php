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

namespace app\controller\api\applets;

use think\App;
use crmeb\basic\BaseController;

class Applets extends BaseController{
    
    public function __construct(App $app){
        
        parent::__construct($app);
    }
    
    //腾讯广告回传
    public function sendData(){
        //监测链接
// https://dianshang.sasz.cn/pages/goods_details/index?account_id=__ACCOUNT_ID__&adgroup_id=__ADGROUP_ID__&ad_id=__AD_ID__&click_id=__CLICK_ID__&click_time=__CLICK_TIME__&request_id=__REQUEST_ID__&wechat_openid=__WECHAT_OPEN_ID__&c=__CALLBACK__

// 'user_id' => [
//     'wechat_openid' => $param['wechat_openid'], // wechat_openid 和 wechat_unionid 二者必填一
//     'wechat_unionid' => '', // 企业微信必填
//     'wechat_app_id' => ''  // 微信类上报必填，且必须通过授权。授权请参考微信数据接入
// ],
        $param = $this->request->param();
        file_put_contents('applets.txt',json_encode($param).PHP_EOL,FILE_APPEND);
        if($this->request->has('qz_gdt') || $this->request->has('gdt_vid')){
            //click_id
            $click_id = $this->request->has('gdt_vid') ? $this->request->has('gdt_vid') : $this->request->has('qz_gdt');
            $param = $this->request->param();

            // $url = urldecode($param['$param']);
            $url = 'http://tracking.e.qq.com/conv';

            $data = [
                'actions' => [
                    [
                        // 'outer_action_id' => 'outer_action_identity',
                        'action_time' => time(),
                        'user_id' => [
                            'wechat_openid' => '', // wechat_openid 和 wechat_unionid 二者必填一
                            'wechat_unionid' => '', // 企业微信必填
                            'wechat_app_id' => ''  // 微信类上报必填，且必须通过授权。授权请参考微信数据接入
                        ],
                        'action_type' => 'LANDING_PAGE_CLICK', //必填 行为类型  下单 COMPLETE_ORDER   点击 LANDING_PAGE_CLICK
                        'url' => 'https://dianshang.sasz.cn',
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
//  return json_encode($data);die;
            //提交
            $result = $this->httpCURL($url,json_encode($data));

            return app('json')->success(json_decode($result,true));
        }
    }

    /**
     * 抖音广告回传
     */
    public function videoSendData(){
        //URL https://dianshang.sasz.cn/pages/h-advert/index?adid=__AID__&creativeid=__CID__&creativetype=__CTYPE__&clickid=__CLICKID__
        $param = $this->request->param();
        file_put_contents('applets.txt',json_encode($param).PHP_EOL,FILE_APPEND);

        if($this->request->has('clickid')){
            $url = 'https://analytics.oceanengine.com/api/v2/conversion';
            $data = [
                'event_type' => 'successful_pay',
                'context' => [
                    'ad' => [
                        'callback' => $param['clickid']
                    ],
                'timestamp' => $this->getMillisecond()
                ]
            ];
 
            //提交
            $result = $this->httpCURL($url,json_encode($data));

            return app('json')->success(json_decode($result,true));
        }

    }
    
    
    /**
     * 使用CURL模拟POST请求
     * 配置参数 根据具体使用场景修改
     *
     * @param Array $data 需要提交的数据
     * @return Bool OR String
     */
    function httpCURL($url, $data){

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
    function getMillisecond() {
      list($s1, $s2) = explode(' ', microtime());
      return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }
}