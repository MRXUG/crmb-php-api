<?php


namespace app\common\repositories\wechat;


use app\common\model\system\merchant\Merchant;
use app\common\model\wechat\MerchantComplaintRequestLog;
use app\common\repositories\BaseRepository;
use app\validate\Elasticsearch\MerchantComplaintRequestValidate;
use crmeb\services\ElasticSearch\ElasticSearchService;
use crmeb\services\WechatService;

class MerchantComplaintRepository extends BaseRepository
{
    public function __construct(ElasticSearchService $es)
    {
        $this->es = $es;
    }

    public function notify($action, $mer_id, $header, $url, $param, $input, $content){
        //TODO
        $logInfo = [
            'mer_id' => $mer_id,
            'param' => json_encode($param, true),
            'url' => $url,
            'request_time' => date("Y-m-d H:i:s"),
            'input' => $input,
            'content' => $content,
            'header' => json_encode($header),
        ];
        //校验header
        if($action){
            return $this->action($action);
        }

        //$this->es->create(MerchantComplaintRequestValidate::$tableIndexName, $logInfo);
        MerchantComplaintRequestLog::create($logInfo);
        return $logInfo;
    }

    public function action($action, $merId = 10){
        //创建 TODO 测试阶段默认10
        $wechatService = WechatService::getMerPayObj($merId)->MerchantComplaint();
        $url = env('APP.HOST'). '/api/notice/wechat_complaint_notify/'.$merId;
        $updateInfo = [
            'wechat_complaint_notify_url' => $url,
            'wechat_complaint_notify_status' => 1
        ];
        switch ($action){
            case 'get':
                //获取
                return ['action' => $action, 'res' => $wechatService->getNotification()];
            case 'create':
                //创建
                Merchant::getInstance()->where('mer_id', $merId)->update($updateInfo);
                return ['action' => $action, 'res' => $wechatService->createNotification($url)];
            case 'update':
                //更新
                Merchant::getInstance()->where('mer_id', $merId)->update($updateInfo);
                return ['action' => $action, 'res' => $wechatService->updateNotification($url)];
            case 'delete':
                $updateInfo['wechat_complaint_notify_status'] = 0;
                Merchant::getInstance()->where('mer_id', $merId)->update($updateInfo);
                return ['action' => $action, 'res' => $wechatService->deleteNotification()];
        }

        return 'errorAction:'.$action.":".$url;
    }



}