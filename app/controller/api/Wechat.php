<?php
namespace app\controller\api;

use app\common\repositories\wechat\MerchantComplaintRepository;
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
//        return app('json')->success([]);
        return  "success";
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


    /**
     * 接收商户创建投诉通知回调
     * @return mixed
     * @author  lucky
     * @date    2023/8/9 20:38
     */
    public function merchantComplaintNotify($mer_id){

        /** @var MerchantComplaintRepository $make */
        $make = app()->make(MerchantComplaintRepository::class);
        $action = $this->request->param('action');
        $res = $make->notify($action, $mer_id,
            $this->request->header(),
            $this->request->server('REQUEST_METHOD').':'.$this->request->host(). $this->request->url(),
            $this->request->param(),
            $this->request->getInput(),
            $this->request->getContent());

        return app('json')->success($res);

    }
}
