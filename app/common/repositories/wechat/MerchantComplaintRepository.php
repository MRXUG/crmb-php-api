<?php


namespace app\common\repositories\wechat;


use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;
use app\common\model\wechat\MerchantComplaintOrder;
use app\common\model\wechat\MerchantComplaintOrderLog;
use app\common\model\wechat\MerchantComplaintRequestLog;
use app\common\model\wechat\MerchantComplaintMedia;
use app\common\repositories\BaseRepository;
use crmeb\jobs\Merchant\ComplaintNotifyIntoDBJob;
use crmeb\services\easywechat\merchantComplaint\MerchantComplaintClient;
use crmeb\services\ElasticSearch\ElasticSearchService;
use crmeb\services\WechatService;
use think\db\BaseQuery;
use think\db\Query;
use think\Exception;
use think\facade\Cache;
use think\facade\Queue;
use think\file\UploadedFile;

class MerchantComplaintRepository extends BaseRepository
{

    public function __construct(ElasticSearchService $es,
                                MerchantComplaintRequestLog $dao)
    {
        $this->es = $es;
        $this->dao = $dao;
    }
    const WeChatMediaImageCachePrefix = "WechatMediaImage:";
    const WeChatComplaintDetailHistoryCachePrefix = "WechatComplaintDetailHistory:";

    public function notify($action, int $mer_id, array $header, string $url, $param, $content){
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
        //创建
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
        if(!isset($resource['payer_phone']) && isset($detail['payer_phone']) && $detail['payer_phone'] != ''){
            $resource['payer_phone'] = $complaintService->decryptSensitiveInformation($detail['payer_phone']);
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
            'complaint_time' => date("Y-m-d H:i:s", strtotime($detail['complaint_time'])),
            'amount' => $resource['amount'] ?? 0,
            'payer_phone' => $resource['payer_phone'] ?? '',
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
        $data['uid'] = $data['payer_openid'] ? User::alias('u')
            ->join('wechat_user wu', 'wu.wechat_user_id = u.wechat_user_id')
            ->join('user_openid_relation uo', 'wu.unionid = uo.unionid')
            ->where("uo.routine_openid", '=', $data['payer_openid'])
            ->value('uid') : null;
        return $data;
    }

    public function list(array $param){
        /** @var MerchantComplaintOrder $orderModel */
        $orderModel = app()->make(MerchantComplaintOrder::class);
        $orderModel = $orderModel
            ->alias('co')
            ->when(isset($param['mer_id']) && $param['mer_id'] !== '', function ($query) use ($param) {
                $query->where('co.mer_id', '=', $param['mer_id']);
            })
            ->when(isset($param['complaint_id']) && $param['complaint_id'] !== '', function ($query) use ($param) {
                $query->where('complaint_id', '=', $param['complaint_id']);
            })
            ->when(isset($param['transaction_id']) && $param['transaction_id'] !== '', function ($query) use ($param) {
                $query->where('co.transaction_id', '=', $param['transaction_id']);
            })
            ->when(isset($param['out_trade_no']) && $param['out_trade_no'] !== '', function (Query $query) use ($param) {
                $query->whereLike('out_trade_no', "%".$param['out_trade_no']."%");
            })
            ->when(isset($param['problem_type']) && (int)$param['problem_type'] !== 0, function ($query) use ($param) {
                $query->where('problem_type', '=', $param['problem_type']);
            })
            ->when(isset($param['complaint_state']) && (int)$param['complaint_state'] !== 0, function ($query) use ($param) {
                $query->where('complaint_state', '=', $param['complaint_state']);
            })
            ->when(isset($param['begin_time']) && $param['begin_time'] !== '', function ($query) use ($param) {
                $query->where('complaint_time', '>=', $param['begin_time']);
            })
            ->when(isset($param['end_time']) && $param['end_time'] !== '', function ($query) use ($param) {
                $query->where('complaint_time', '<=', $param['end_time']);
            })
            ->when(isset($param['timeout_type']) && in_array($param['timeout_type'], [MerchantComplaintOrder::TIMEOUT_NO, MerchantComplaintOrder::TIMEOUT_YES]),
                function (BaseQuery $query) use ($param) {
                //超时类型 0 全部，1 未超时: 待处理距投诉时间小于24小时，处理中距投诉时间小于72小时,2 已超时 所有待处理距投诉时间大于24小时，处理中距投诉时间大于72小时
                $before24 = date("Y-m-d H:i:s", strtotime('-24 hour'));
                $before72 = date("Y-m-d H:i:s", strtotime('-72 hour'));
                if($param['timeout_type'] == MerchantComplaintOrder::TIMEOUT_NO){
                    $option = ">";
                }else{
                    $option = "<";
                }
                $query->where("
                        CASE
                         WHEN complaint_state = 1 THEN complaint_time {$option} '{$before24}'
                         WHEN complaint_state = 2 THEN complaint_time {$option} '{$before72}'
                         ELSE 1=1
                         END");
            });
        $count = $orderModel->count();
        $list = $orderModel
            ->leftJoin('user u', 'u.uid = co.uid')
            ->leftJoin('merchant m', 'm.mer_id = co.mer_id')
            ->leftJoin('store_order o', "o.pay_order_sn = co.out_trade_no and o.pay_order_sn != ''")
            ->field('complaint_id, co.mer_id, complaint_time, out_trade_no, co.transaction_id, problem_type, complaint_state,
             u.uid, u.account, u.nickname, u.avatar, 
             o.order_id,
             m.mer_name, m.real_name')
            ->order(['co.id' => 'desc'])
            ->page($param['page'], $param['limit'])->select();
        return ['count' => $count, 'list' => $list];
    }

    public function statistics($mer_id = 0){
        /** @var MerchantComplaintOrder $orderModel */
        $orderModel = app()->make(MerchantComplaintOrder::class);
        $before24 = date("Y-m-d H:i:s", strtotime('-24 hour'));
        $before72 = date("Y-m-d H:i:s", strtotime('-72 hour'));
        $data = $orderModel
            ->when($mer_id != 0, function ($query) use ($mer_id) {
                $query->where('mer_id', '=', $mer_id);
            })
            ->field("CASE
                         WHEN complaint_state = 1 AND complaint_time < '{$before24}' THEN 1
                         WHEN complaint_state = 2 AND complaint_time < '{$before72}' THEN 1
                         ELSE 0
                         END  as is_timeout, complaint_state, count(complaint_id) as count_id")
            ->group('complaint_state, is_timeout')
            ->select();
        $result = [
            'pending' => 0,
            'pending_timeout' => 0,
            'processing' => 0,
            'processing_timeout' => 0,
            'processed' => 0,
        ];
        foreach ($data as $one){
            if($one['complaint_state'] == MerchantComplaintOrder::COMPLAINT_STATUS_PENDING){
                $result['pending'] += $one['count_id'];
                if($one['is_timeout']){
                    $result['pending_timeout'] = $one['count_id'];
                }
            }elseif($one['complaint_state'] == MerchantComplaintOrder::COMPLAINT_STATUS_PROCESSING){
                $result['processing'] += $one['count_id'];
                if($one['is_timeout']){
                    $result['processing_timeout'] = $one['count_id'];
                }
            }else{
                $result['processed'] = $one['count_id'];
            }
        }
        return $result;
    }

    /**
     * @param string $id
     * @param int $mer_id
     * @return array
     */
    public function detail($id, $mer_id){
        /** @var MerchantComplaintOrder $orderModel */
        $orderModel = app()->make(MerchantComplaintOrder::class);
        $detail = $orderModel->alias('co')
            ->field('complaint_id, complaint_time, complaint_state, complaint_detail, problem_type, problem_description,
            apply_refund_amount / 100 as apply_refund_amount,
            amount / 100 as amount, u.uid, u.account, u.nickname, u.avatar, payer_phone,user_complaint_times,
            m.mer_name, m.real_name,
            transaction_id,out_trade_no,complaint_full_refunded
            ')
            ->leftJoin('user u', 'u.uid = co.uid')
            ->leftJoin('merchant m', 'm.mer_id = co.mer_id')
            ->where('co.mer_id', '=', $mer_id)
            ->where('co.complaint_id', '=', $id)
            ->find();
        if($detail){
            switch ($detail->complaint_state){
                case MerchantComplaintOrder::COMPLAINT_STATUS_PENDING:
                    $detail->timeout = date('Y-m-d H:i:s', strtotime('+24 hour', strtotime($detail->complaint_time)));
                    break;
                case MerchantComplaintOrder::COMPLAINT_STATUS_PROCESSING:
                    $detail->timeout = date('Y-m-d H:i:s', strtotime('+72 hour', strtotime($detail->complaint_time)));
                    break;
                default:
                    $detail->timeout = '';
            }

            /** @var \Redis $cacheService */
            $cacheService = Cache::store()->handler();
            $cacheHistory = $cacheService->get(self::WeChatComplaintDetailHistoryCachePrefix.$mer_id.':'.$id);
            if($cacheHistory !== false){
                $weHistory = json_decode($cacheHistory, true);

            }else{
                $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();
                $wxData = $service->negotiationHistory($id);
                $data = $wxData['data'] ?? $wxData;
                foreach ($data as $k => &$history){
                    $history['operate_time'] = date('Y-m-d H:i:s', strtotime($history['operate_time'] ?? ''));
                    $history['operate_type'] = MerchantComplaintOrder::operationType($history['operate_type'] ?? '');
                    if(isset($history['complaint_media_list']['media_url']) && !empty($history['complaint_media_list']['media_url'])){
                        foreach ($history['complaint_media_list']['media_url'] as $key => &$url){
                            $url = env('APP.HOST'). "/api/image/show?".
                                http_build_query(['mer_id' => $mer_id, 'url' => $url]);
                        }
                    }

                }
                $weHistory = [
                    'data' => $data,
                    'limit' => $wxData['limit'] ?? 0,
                    'offset' => $wxData['offset'] ?? 0,
                    'total_count' => $wxData['total_count'] ?? 0
                ];
                $cacheService->set(self::WeChatComplaintDetailHistoryCachePrefix.$mer_id.':'.$id, json_encode($weHistory), 60);
            }
            $detail->wxHistory = $weHistory['data'];
            $detail->wxHistory_limit = $weHistory['limit'];
            $detail->wxHistory_offset = $weHistory['offset'];
            $detail->wxHistory_total_count = $weHistory['total_count'];
        }
        return $detail ?? [];
    }

    public function response($complaint_id, $mer_id, $params){
        $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();
        $images = $params['response_images'] ?? [];

        $service->responseUser($complaint_id, $params['response_content'], $images);
        if($images){
            MerchantComplaintMedia::whereIn('media_id', $images)
                ->update(['complaint_id' => $complaint_id]);
        }

        return 'ok';
    }

    public function checkComplaintId($mer_id, $complaintId){
        /** @var MerchantComplaintOrder $orderModel */
        $orderModel = app()->make(MerchantComplaintOrder::class);
        return $orderModel->where('mer_id', '=', $mer_id)
            ->where('complaint_id', '=', $complaintId)
            ->find();
    }

    public function refund($complaint_id, $param){
        $reject_media_list = $param['reject_media_list'] ?? [];

        $service = WechatService::getMerPayObj($param['mer_id'])->MerchantComplaint();
        $service->updateRefundProgress($complaint_id, $param['action'],
            $param['launch_refund_day'] ?? 0,
            $param['reject_reason'] ?? '',
             $reject_media_list,
            $param['remark'] ?? ''
            );
        if($reject_media_list){
            MerchantComplaintMedia::whereIn('media_id', $reject_media_list)
                ->update(['complaint_id' => $complaint_id]);
        }
        return 'ok';
    }

    public function checkMedia(array $mediaIds){
        if(empty($mediaIds)){
            return true;
        }//鉴权
        return MerchantComplaintMedia::whereIn('media_id', $mediaIds)
            ->count('media_id') == count($mediaIds);
    }

    public function complete($complaint_id, $mer_id){
        $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();

        $service->complete($complaint_id);
        return 'ok';
    }

    /**
     * @param int $mer_id
     * @param $adminId
     * @param $userType
     * @param UploadedFile $file
     * @return array
     */
    public function uploadImage($mer_id, $adminId, $userType, $file){
        $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();

        $mediaId = $service->uploadImage($file->getRealPath(), $file->getOriginalName());
        MerchantComplaintMedia::create([
            'media_id' => $mediaId,
            'mime_type' => $file->getMime(),
            'filesize' => $file->getSize(),
            'mer_id' => $mer_id,
            'admin_id' => $adminId,
            'user_type' => $userType,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
        return ['media_id' => $mediaId];
    }

    public function getMaxImageSize(){
        return MerchantComplaintClient::MAX_IMAGE_SIZE;
    }

    public function imageShow($mer_id, string $url){
        if(strpos($url, 'https://api.mch.weixin.qq.com/v3/merchant-service/images/') !== 0){
            return '';
        }
        /** @var \Redis $cacheService */
        $cacheService = Cache::store()->handler();
        $content = $cacheService->get(self::WeChatMediaImageCachePrefix.$url);
        if($content !== false){
            return $content;
        }
        $service = WechatService::getMerPayObj($mer_id)->MerchantComplaint();
        $content = $service->imageShow($url);
        $cacheService->set(self::WeChatMediaImageCachePrefix.$url, $content, 7 * 24 * 3600);
        return $content;
    }




}