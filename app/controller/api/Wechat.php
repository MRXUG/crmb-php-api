<?php
namespace app\controller\api;

use app\common\repositories\wechat\OpenPlatformRepository;
use crmeb\basic\BaseController;
use crmeb\services\WechatService;

class Wechat extends BaseController
{
    public function jsConfig()
    {
        $data = WechatService::create()->jsSdk($this->request->param('url')?:$this->request->host());
        $data['openTagList'] = ['wx-open-launch-weapp'];
        return app('json')->success($data);
    }

    /**
     * 接收微信授权事件：ticket、授权回调
     * @return mixed
     * @author  wzq
     * @date    2023/2/28 10:30
     */
    public function serve()
    {
        $make = app()->make(OpenPlatformRepository::class);
        $make->serve($this->request->param(), $this->request->getContent());
        return app('json')->success([]);
    }


    /**
     * 接收微信消息与事件
     * @return mixed
     * @author  wzq
     * @date    2023/3/1 20:38
     */
    public function accountServe(){
        $make = app()->make(OpenPlatformRepository::class);
        $make->accountServe($this->request->param(), $this->request->getContent());

//        return app('json')->success([]);
        return  "success";
    }
}
