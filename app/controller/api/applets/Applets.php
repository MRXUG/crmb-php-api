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

use app\common\model\applet\AppletsTx;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\basic\BaseController;
use think\App;
use think\facade\Log;

class Applets extends BaseController
{

    public function __construct(App $app)
    {

        parent::__construct($app);
    }

    //腾讯广告回传
    public function sendData()
    {

        $appid   = $this->request->header('appid');
        $gdt_vid = $this->request->has('gdt_vid') ? $this->request->param('gdt_vid') : $this->request->param('qz_gdt');
        $param   = $this->request->param();
        $uinfo   = $this->request->userInfo();
        $make    = app()->make(WechatUserRepository::class);
        $openid  = $make->idByRoutineId((int) $uinfo['wechat_user_id']);
        $data    = [
            'actions' => [
                [
                    'action_time'  => time(),
                    'user_id'      => [
                        'wechat_openid' => $openid, // wechat_openid 和 wechat_unionid 二者必填一
                        // 'wechat_unionid' => '', // 企业微信必填
                        'wechat_app_id' => $appid, // 微信类上报必填，且必须通过授权。授权请参考微信数据接入
                    ],
                    'action_type'  => 'LANDING_PAGE_CLICK', //必填 行为类型  下单 COMPLETE_ORDER   点击 LANDING_PAGE_CLICK
                    "trace"        => [
                        "click_id" => $gdt_vid, // 不设置监测链接，必填 click_id
                    ],
                    'action_param' => [
                        'value'  => 'click',
                        'object' => 'click',
                    ],
                ],
            ],
        ];
        $url = "http://tracking.e.qq.com/conv";
        Log::info("click gdt params:" . json_encode($param));
        Log::info("click gdt request:" . json_encode($data));
        //提交
        $result = $this->httpCURL($url, json_encode($data));

        $model = new AppletsTx();
        $info  = $model->where(['request_id' => $gdt_vid])->find();
        if (!$info) {
            $param['wechat_app_id'] = $appid;
            $param['wechat_openid'] = $openid;
            $param['url']           = $url;
            $param['click_id']      = $gdt_vid;
            //组织数组
            $data = [
                'request_id'    => $gdt_vid,
                'wechat_openid' => $openid,
                'content'       => json_encode($param),
            ];
            $model->save($data);
        }

        return app('json')->success(json_decode($result, true));
        // }
    }

    //腾讯点击监测接口数据保存
    public function getData()
    {
        //监测链接
        // https://dianshang.sasz.cn//api/applets/getdata?account_id=__ACCOUNT_ID__&adgroup_id=__ADGROUP_ID__&ad_id=__AD_ID__&click_id=__CLICK_ID__&click_time=__CLICK_TIME__&request_id=__REQUEST_ID__&wechat_openid=__WECHAT_OPEN_ID__&c=__CALLBACK__

// 获取的参数 {"account_id":"30845926","adgroup_id":"9880016918","click_id":"npxuazadaaaeh5wph5dq","click_time":"1681977198","request_id":"suawdqwsu4tha","wechat_openid":"ofQbV5UgbCjo7S8HmcAEIx4jLBvM","callback":"http:\/\/tracking.e.qq.com\/conv?cb=7-wD3EfpRf9gNiPufDoSexOsW5ElqXnW6BkHlwyjaVw%3D&conv_id=17235723"}

        $param = $this->request->param();
        file_put_contents('applets.txt', json_encode([$param, input()]) . PHP_EOL, FILE_APPEND);

        $model = new AppletsTx();
        $info  = $model->where(['request_id' => $param['request_id']])->find();
        if (!$info) {
            //组织数组
            $data = [
                'request_id'    => $param['request_id'],
                'wechat_openid' => $param['wechat_openid'],
                'content'       => json_encode($param),
            ];
            $insert = $model->save($data);
        }
    }

    /**
     * 抖音广告回传
     */
    public function videoSendData()
    {
        //URL https://dianshang.sasz.cn/pages/h-advert/index?adid=__AID__&creativeid=__CID__&creativetype=__CTYPE__&clickid=__CLICKID__
        $param = $this->request->param();
        file_put_contents('applets.txt', json_encode($param) . PHP_EOL, FILE_APPEND);

        if ($this->request->has('clickid')) {
            $url  = 'https://analytics.oceanengine.com/api/v2/conversion';
            $data = [
                'event_type' => 'successful_pay',
                'context'    => [
                    'ad'        => [
                        'callback' => $param['clickid'],
                    ],
                    'timestamp' => $this->getMillisecond(),
                ],
            ];

            //提交
            $result = $this->httpCURL($url, json_encode($data));

            return app('json')->success(json_decode($result, true));
        }

    }

    /**
     * 使用CURL模拟POST请求
     * 配置参数 根据具体使用场景修改
     *
     * @param Array $data 需要提交的数据
     * @return Bool OR String
     */
    public function httpCURL($url, $data)
    {

        $headers = [
            'Content-type: application/json',
            'cache-control: no-cache',
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $code       = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $code;
    }

    //获取毫秒时间戳
    public function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float) sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }
}
