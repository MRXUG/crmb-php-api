<?php


namespace app\controller\admin\Erp;


use app\common\model\erp\JuShuiTanAuthorizeConfig;
use crmeb\basic\BaseController;
use crmeb\services\erp\JuShuiTan\Auth\Auth;
use think\facade\Log;

class JuShuiTanController extends BaseController
{
    public function AuthorizeCallback(){
        $param = [
            "app_key" =>  $this->request->get('app_key'),
            "code" =>  $this->request->get('code'),
            "state" =>  $this->request->get('state'),
            "sign" =>  $this->request->get('sign'),
        ];
        Log::info("聚水潭授权回调参数:".json_encode($param));
        if($param['code'] != "" && $param["app_key"] != ""){
            $model = JuShuiTanAuthorizeConfig::getInstance()
                ->where("app_key", $param["app_key"])
//                ->Where("mer_id", $param['state'])
                ->find();
            if(!$model){
                Log::error("聚水潭授权回调错误");
                return response(["code" => 0], 200, [], 'json');
            }
            $service = new Auth($model->toArray());
            $res = $service->getAccessToken($param['code']);
            //更新
            if(!isset($res['code']) || $res['code'] != 0){
                Log::error("聚水潭授权获取token错误:".json_encode($param).":".json_encode($res));
                return response(["code" => 0], 200, [], 'json');
            }
            $model->access_token = $res['data']['access_token'];
            $model->refresh_token = $res['data']['refresh_token'];
            $model->access_token_expire_at = date("Y-m-d H:i:s", time() + $res['data']['access_token_expire_at']);
            $model->authorize_time = date("Y-m-d H:i:s");
            $model->save();

            return response(["code" => 0], 200, [], 'json');
        }else{
            return response(["code" => 500, "message" => "参数缺失"], 200, [], 'json');
        }
    }


    /**
     * 生成授权链接
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function createUrl(){
        $param = [
            "app_key" => config("erp.jushuitan.app_key"),
            "state" =>  $this->request->get('mer_id'),
        ];
        $model = JuShuiTanAuthorizeConfig::getInstance()
            ->where("app_key", $param["app_key"])
            ->Where("mer_id", $param['state'])
            ->find();
        $service = new Auth($model->toArray());
        $url = $service->createUrl($param['state']);
        return app('json')->success(["url" => $url]);
    }

}