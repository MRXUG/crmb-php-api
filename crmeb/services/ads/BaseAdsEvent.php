<?php
namespace crmeb\services\ads;

use GuzzleHttp\Client;
use think\facade\Log;

abstract class BaseAdsEvent 
{
    // TODO 沙箱环境 到生产切换为 https://api.e.qq.com
    const API_DOMAIN = 'https://datanexus-sandbox.e.qq.com/v1.1/action/add?access_token=%s&timestamp=%u&nonce=%s'; 
    const ADS_TOKEN = '25cfd1caf5d23aa51da5fbc0cfeff172';
    const ACCOUNT_ID = '31974198';  

    protected $URL = '';

    protected $unionid = '';

    protected $appid = '';

    protected $click_id= '';

    private $data_source_map = [
        'wx7d575dcfbee5d0c5'=>1201311297,
        'wx4e8a0659d2f00321'=>1201311302,
        'wx31ca8abdbb995b8a'=>1201311305,
        'wxacf55f4e68ef8ec7'=>1201311307,
        'wx913a8e8f208e2096'=>1201311308
    ];

    protected $reuestParams = [
        'account_id'=>self::ACCOUNT_ID,
        'data_source_id'=>0,
        'actions'=>[],
    ];

    public function __construct($appid,$unionid,$clickid)
    {
        if ($appid == '' || $unionid =='' || $clickid==''){
            throw new \Exception( 'ads params error');
        }
        if(!array_key_exists($appid,$this->data_source_map)){
            throw new \Exception( 'appid => data_source_id error');
        }
        $this->reuestParams['data_source_id'] = $this->data_source_map[$appid];
        $this->unionid = $unionid;
        $this->appid = $appid;
        $this->click_id = $clickid;
    }

    protected function setRequestUrl(){
        $this->URL = sprintf(self::API_DOMAIN,self::ADS_TOKEN,time(),uniqid("ADS"));
    }

    final public function handle()
    {
        $this->setRequestUrl();
        $action = $this->requestActionParams();
        // [['action_time'=>0,''],]
        $actions = [];
        foreach($action as $item){
            $actions[]= array_merge([
                'user_id'=>[
                    'wechat_unionid'=>$this->unionid,
                    'wechat_app_id'=>$this->appid,
                ],
                'trace'=>[
                    'click_id'=>$this->click_id
                ]

            ],$item);
        }
        $this->reuestParams['actions'] =$actions;
        return $this->send(); 
    }

    abstract function requestActionParams();

    public function send(){
        try {
            Log::debug("gdt-callback:params". json_encode($this->reuestParams, JSON_UNESCAPED_UNICODE));
            $client = new Client();
            $response = $client->request("post", $this->URL, ['json' => $this->reuestParams]);
            $result = json_decode($response->getBody()->getContents(), true);
            Log::debug("gdt-callback:result". json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result;
        } catch (\Exception $exception) {
            Log::error("gdt-callback:error" . $exception->getMessage());
            sendMessageToWorkBot([
                'module' => '广告回传异常',
                'type'   => 'error',
                '`file`' => $exception->getFile(),
                '`line`' => $exception->getLine(),
                'msg'    => $exception->getMessage(),

            ]);
            return [];
        }
    }
}


//"https://api.e.qq.com/v1.1/action/add?access_token=<ACCESS_TOKEN>&timestamp=<TIMESTAMP>&nonce=<NONCE>"