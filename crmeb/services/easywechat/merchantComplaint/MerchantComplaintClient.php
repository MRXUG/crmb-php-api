<?php


namespace crmeb\services\easywechat\merchantComplaint;


use crmeb\services\easywechat\BaseClient;
use crmeb\services\easywechat\certficates\Client;
use think\exception\ValidateException;

class MerchantComplaintClient extends BaseClient
{
    const NOTIFICATIONS_URI = '/v3/merchant-service/complaint-notifications';
    const RESPONSE_USER_URI = '/v3/merchant-service/complaints-v2/{complaint_id}/response';
    const COMPLETE_URI = '/v3/merchant-service/complaints-v2/{complaint_id}/complete';
    const UPDATE_REFUND_PROGRESS_URI = '/v3/merchant-service/complaints-v2/{complaint_id}/update-refund-progress';
    const IMAGE_UPLOAD_URI = '/v3/merchant-service/images/upload';
    const COMPLAINTS_LIST_URI = '/v3/merchant-service/complaints-v2';
    const COMPLAINTS_DETAIL_URI = '/v3/merchant-service/complaints-v2/{complaint_id}';
    const NEGOTIATION_HISTORY_URI = '/v3/merchant-service/complaints-v2/{complaint_id}/negotiation-historys';

    const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

    /**
     * 投诉通知回调接口
     * @param string $method GET POST PUT DELETE ...
     * @param array $params
     * @return mixed
     */
    private function notification(string $method, $params = []){
        $options = [];
        if(!empty($params)){
            $options['sign_body'] = json_encode($params);
        }
        $res = $this->request(self::NOTIFICATIONS_URI, $method, $options);
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        return $res;
    }

    /**
     * 创建
     * @param string $url
     * @return mixed
     */
    public function createNotification(string $url){
        return $this->notification('POST', ['url' => $url]);
    }

    /**
     * 查询
     * @return mixed
     */
    public function getNotification(){
        return $this->notification('GET');
    }

    /**
     * 删除
     * @return mixed
     */
    public function deleteNotification(){
        return $this->notification('DELETE');
    }

    /**
     * 更新
     * @param string $url
     * @return mixed
     */
    public function updateNotification(string $url){
        return $this->notification('PUT', ['url' => $url]);
    }

    /**
     * 回复用户
     * @param string $complaint_id 投诉id
     * @param string $response_content 回复内容
     * @param array $response_images
     * @param string $complainted_mchid 被诉商户号
     * @param array $jumpInfo 跳转链接
     * @return mixed
     */
    public function responseUser(string $complaint_id, string $response_content, $response_images = [], string $complainted_mchid = '', $jumpInfo = []){
       if(!$complainted_mchid){
           $complainted_mchid = $this->app['config']['service_payment']['merchant_id'];
       }
        $body = [
            'complainted_mchid' => $complainted_mchid,
            'response_content' => $response_content,
        ];
        if(!empty($response_images)){
            //最多四张图，value是media id
            $body['response_images'] = array_slice($response_images, 0, 4);
        }
        if(isset($jumpInfo['jump_url']) && isset($jumpInfo['jump_url_text'])){
            $body['jump_url'] = $jumpInfo['jump_url'];
            $body['jump_url_text'] = $jumpInfo['jump_url_text'];
        }
        $uri = str_replace('{complaint_id}', $complaint_id,self::RESPONSE_USER_URI);

        $options['sign_body'] = json_encode($body);
        $res = $this->request($uri, 'POST', $options);
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        //无应答
        return true;
    }

    /**
     * 反馈处理完成
     * @param string $complaint_id
     * @param string $complainted_mchid
     * @return mixed
     */
    public function complete(string $complaint_id, string $complainted_mchid = ''){
        if(!$complainted_mchid){
            $complainted_mchid = $this->app['config']['service_payment']['merchant_id'];
        }
        $uri = str_replace('{complaint_id}', $complaint_id, self::COMPLETE_URI);
        $options['sign_body'] = json_encode(['complainted_mchid' => $complainted_mchid]);
        $res = $this->request($uri, 'POST', $options);
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        //无应答
        return true;
    }

    /**
     * 更新退款审批结果
     * @param string $complaint_id
     * @param string $action REJECT/APPROVE
     * @param int $launch_refund_day
     * @param string $reject_reason
     * @param array $reject_media_list
     * @param string $remark
     * @return mixed
     */
    public function updateRefundProgress(string $complaint_id, string $action, $launch_refund_day = 3, $reject_reason =  '',
                                         $reject_media_list = [], $remark = ''){
        $uri = str_replace('{complaint_id}', $complaint_id, self::UPDATE_REFUND_PROGRESS_URI);
        $body = ['action' => $action, 'remark' => $remark];
        if($action == 'REJECT'){
            $body['reject_reason'] = $reject_reason;
            $body['reject_media_list'] = $reject_media_list;
        }else{
            $body['launch_refund_day'] = $launch_refund_day;
        }
        $options['sign_body'] = json_encode($body);
        $res = $this->request($uri, 'POST', $options);
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        //无应答
        return true;
    }

    /**
     * 投诉列表
     * @param string $begin_date
     * @param string $end_date
     * @param int $limit 0-50
     * @param int $offset
     * @param string $complainted_mchid
     * @return mixed
     */
    public function complaintList(string $begin_date, string $end_date, int $limit, int $offset, $complainted_mchid = ''){
        $queryArray = compact('limit', 'offset', 'begin_date', 'end_date');
        if($complainted_mchid){
            $queryArray['complainted_mchid'] = $complainted_mchid;
        }

        $res = $this->request( self::COMPLAINTS_LIST_URI.'?'. http_build_query($queryArray), 'GET');
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        return $res;
    }

    /**
     * 投诉详情
     * @param string $complaint_id
     * @return mixed
     */
    public function complaintDetail(string $complaint_id){
        $uri = str_replace('{complaint_id}', $complaint_id, self::COMPLAINTS_DETAIL_URI);
        $res = $this->request( $uri, 'GET');
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        return $res;
    }

    /**
     * 投诉单协商历史列表
     * @param string $complaint_id
     * @param int $limit
     * @param int $offset
     * @return mixed
     */
    public function negotiationHistory(string $complaint_id, $limit = 300, $offset = 0){
        $uri = str_replace('{complaint_id}', $complaint_id, self::NEGOTIATION_HISTORY_URI);
        $queryArray = compact('limit', 'offset');
        $res = $this->request( $uri.'?'. http_build_query($queryArray), 'GET');
        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);
        return $res;
    }

    /**
     * 上传单张图片
     * @param string $filepath
     * @param string $filename
     * @return string media_id
     */
    public function uploadImage(string $filepath, string $filename){
        $fileSize = filesize($filepath);
        //上层逻辑校验
//        $maxSize = self::MAX_IMAGE_SIZE;
//        if($fileSize > $maxSize){
//            throw new ValidateException('图片太大:'.$maxSize );
//        }
//        $fi = new \finfo(FILEINFO_MIME_TYPE);
//        $mime_type = $fi->file($filepath);
//        if(!in_array($mime_type, ['image/jpeg', 'image/bmp', 'image/png', 'image/x-ms-bmp'])){
//            throw new ValidateException('图片格式不正确，仅支持bmp, png, jpg.当前格式:'.$mime_type );
//        }

        $boundary = uniqid();
        try{
            $file = fread(fopen($filepath,'r'),$fileSize);
        }catch (\Exception $exception){
            throw new ValidateException($exception->getMessage());
        }

        $options['headers'] = ['Content-Type' => 'multipart/form-data;boundary='.$boundary];

        $options['sign_body'] = json_encode(['filename' => $filename,'sha256' => hash_file("sha256",$filepath)]);

        $boundaryStr = "--{$boundary}\r\n";

        $body = $boundaryStr;
        $body .= 'Content-Disposition: form-data; name="meta"'."\r\n";
        $body .= 'Content-Type: application/json'."\r\n";
        $body .= "\r\n";
        $body .= $options['sign_body']."\r\n";
        $body .= $boundaryStr;
        $body .= 'Content-Disposition: form-data; name="file"; filename="'.$filename.'"'."\r\n";
        $body .= 'Content-Type: image/jpeg'.';'."\r\n";
        $body .= "\r\n";
        $body .= $file."\r\n";
        $body .= "--{$boundary}--";

        $options['data'] = (($body));

        try {
            $res = $this->request(self::IMAGE_UPLOAD_URI, 'POST', $options);
        }catch(\Exception $exception){
            throw new ValidateException($exception->getMessage());
        }

        if(isset($res['code'])) throw new ValidateException('[微信接口返回]:' . $res['message']);

        return $res['media_id'];
    }

    public function imageShow($url){
        $uri = str_replace('https://api.mch.weixin.qq.com', '', $url);
        $curl = curl_init();
        $headers = [
            "Authorization: ".$this->getAuthorization($uri, 'GET', ''),
            "Wechatpay-Serial: ".$this->app->certficates->get()['serial_no'],
            "User-Agent: curl",
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $content = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);
        return substr($content, $headerSize);
    }


}