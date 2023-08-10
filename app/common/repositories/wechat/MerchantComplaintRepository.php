<?php


namespace app\common\repositories\wechat;


use app\common\model\system\merchant\Merchant;
use app\common\model\wechat\MerchantComplaintOrder;
use app\common\model\wechat\MerchantComplaintOrderLog;
use app\common\model\wechat\MerchantComplaintRequestLog;
use app\common\repositories\BaseRepository;
use crmeb\jobs\Merchant\ComplaintNotifyIntoDBJob;
use crmeb\services\ElasticSearch\ElasticSearchService;
use crmeb\services\WechatService;
use think\Exception;
use think\facade\Queue;

class MerchantComplaintRepository extends BaseRepository
{

    public function __construct(ElasticSearchService $es,
                                MerchantComplaintRequestLog $dao)
    {
        $this->es = $es;
        $this->dao = $dao;
    }

    public function notify(string $action, int $mer_id, array $header, string $url, $param, $content){
        $logInfo = [
            'mer_id' => $mer_id,
            'param' => json_encode($param, true),
            'url' => $url,
            'request_time' => date("Y-m-d H:i:s"),
            'content' => $content,
            'header' => json_encode($header),
        ];
        //本地action 后面可以考虑删除，迁移到command和功能中
        if($action){
            return $this->action($action);
        }
        //$this->es->create(MerchantComplaintRequestValidate::$tableIndexName, $logInfo);

        $complaintService = WechatService::getMerPayObj($mer_id)->MerchantComplaint();
        if(!$complaintService->verifySignature($header,$content)){
            $logInfo['verify_status'] = 0;
            $this->dao->create($logInfo);
            return 'request header verified false';
        }
        $id = $this->dao->insertGetId($logInfo);

        $data = ['request_db_id' => $id,'mer_id' => $mer_id, 'content' => $content];
         Queue::push(ComplaintNotifyIntoDBJob::class, $data);
//        return $this->notifyIntoDb($data);

        return 'ok';
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

    public function notifyIntoDb(array $data){
        $mer_id = $data['mer_id'];
        $content = json_decode($data['content'], true);
        $complaintService = WechatService::getMerPayObj($mer_id)->MerchantComplaint();
        $resource = $complaintService->decrypt($content['resource'], 1);
        $resource = json_decode($resource, true);
        $complaintId = $resource['complaint_id'];
        $detail = $complaintService->complaintDetail($complaintId);

        /** @var MerchantComplaintOrderLog $logModel */
        $logModel = app()->make(MerchantComplaintOrderLog::class);

        if($logModel->where('wechat_notify_id','=', $content['id'])
            ->where('mer_id', '=', $mer_id)
            ->where('complaint_id', '=', $complaintId)
            ->value('id')){
            return ['error' => 'exists'];
        }
        $logInfo = $this->formatOrderLogData($mer_id, $content, $resource, $detail);
        $logModel->create($logInfo);

        // 更新order 表状态
        /** @var MerchantComplaintOrder $orderModel */
        $orderModel = app()->make(MerchantComplaintOrder::class);
        $id = $orderModel->where('mer_id', '=', $mer_id)
            ->where('complaint_id', '=', $complaintId)
            ->value('id');
        $orderInfo = $this->formatOrderDetailData($mer_id, $content, $resource, $detail);
        if($id){
            unset($orderInfo['log_create_time']);
            unset($orderInfo['create_time']);
            unset($orderInfo['complaint_time']);
            $orderModel->where('id', '=', $id)->update($orderInfo);
        }else{
            $orderModel->create($orderInfo);
        }

        return $detail;

    }

    public function formatOrderLogData($mer_id, array $content, array $resource, array $detail){
        return [
            'mer_id' => $mer_id,
            'wechat_notify_id' => $content['id'],
            'create_time' => date("Y-m-d H:i:s", strtotime($content['create_time'])),
            'event_type' => $content['event_type'] ?? '',
            'resource_type' => $content['resource_type'] ?? '',
            'summary' => $content['summary'] ?? '',
            'complaint_id' => $resource['complaint_id'],
            'action_type' => $resource['action_type'] ?? '',
            'out_trade_no' => $resource['out_trade_no'] ?? $detail['complaint_order_info']['out_trade_no'] ?? '',
            'complaint_time' => date("Y-m-d H:i:s", strtotime($resource['complaint_time'])),
            'amount' => $resource['amount'] ?? 0,
            'pay_phone' => $resource['pay_phone'] ?? '',
            'complaint_detail' => $resource['complaint_detail'] ?? $detail['complaint_detail'] ?? '',
            'complaint_state' => $detail['complaint_state'] ?? '', //log表存初始值
            'transaction_id' => $resource['transaction_id'] ?? '',
            'complaint_handle_state' => $resource['complaint_handle_state'] ?? '',
            'complaint_full_refunded' => intval($detail['complaint_full_refunded'] ?? 0),
            'complaint_media_list' => json_encode($detail['complaint_media_list'] ?? []),
            'incoming_user_response' => intval($detail['incoming_user_response'] ?? 0),
            'payer_openid' => $detail['payer_openid'] ?? '',
            'payer_phone_encrypt' => $detail['payer_phone'] ?? '',
            'problem_description' => $detail['problem_description'] ?? '',
            'problem_type' => $detail['problem_type'] ?? '', //log表存初始值
            'user_complaint_times' => $detail['user_complaint_times'] ?? '',
            'service_order_info' => json_encode($detail['service_order_info'] ?? []),
            'additional_info' => json_encode($detail['additional_info'] ?? []),
            'user_tag_list' => json_encode($detail['user_tag_list'] ?? []),
            'apply_refund_amount' => $detail['apply_refund_amount'] ?? '',
            'log_create_time' => date('Y-m-d H:i:s'),
        ];
    }

    public function formatOrderDetailData($mer_id,  array $content, array $resource, array $detail){
        $data = $this->formatOrderLogData($mer_id, $content, $resource, $detail);
        unset($data['resource_type']);
        //order表存转换int 便于查询
        $data['complaint_state'] = MerchantComplaintOrder::COMPLAINT_STATE[$data['complaint_state']] ?? '';
        $data['problem_type'] = MerchantComplaintOrder::PROBLEM_TYPE[$data['problem_type']] ?? '';
        return $data;
    }

    public function list(){
        return [];
    }

    public function statistics(){
        return [];
    }

    /**
     * @param int $id
     * @return array
     */
    public function detail($id){
        return [];
    }

    public function response($complaint_id, $mer_id, $params){
        $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();

        $service->responseUser($complaint_id, $params['response_content'], []);
        return 'ok';
    }

    public function checkComplaintId($mer_id, $complaintId){
        /** @var MerchantComplaintOrder $orderModel */
        $orderModel = app()->make(MerchantComplaintOrder::class);
        return $orderModel->where('mer_id', '=', $mer_id)
            ->where('complaint_id', '=', $complaintId)
            ->find();
    }

    public function refund($id){
        return [];
    }

    public function complete($complaint_id, $mer_id){
        $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();

        $service->complete($complaint_id);
        return 'ok';
    }

    public function uploadImage($id){
        return [];
    }




}