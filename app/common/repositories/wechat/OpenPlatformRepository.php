<?php

namespace app\common\repositories\wechat;

use app\common\Constant;
use app\common\dao\applet\WxAppletSubmitAuditDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\applet\WxAppletSubmitAuditModel;
use app\common\RedisKey;
use app\common\repositories\BaseRepository;
use Carbon\Carbon;
use crmeb\exceptions\WechatException;
use crmeb\jobs\WechatSubmitAuditJob;
use crmeb\services\UploadService;
use GuzzleHttp\Exception\GuzzleException;
use app\common\dao\applet\WxAppletDao;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\facade\Log;
use GuzzleHttp\Client;
use think\facade\Queue;

class OpenPlatformRepository extends BaseRepository
{

    /**
     * @var mixed
     */
    private $component_appid;
    /**
     * @var mixed
     */
    private $component_appsecret;
    /**
     * @var mixed
     */
    private $encodingAESKey;
    /**
     * @var WxAppletDao
     */
    private $appletDao;
    private $appletSubmitAuditDao;

    public function __construct(WxAppletDao $appletDao, WxAppletSubmitAuditDao $appletSubmitAuditDao)
    {
        $this->appletDao = $appletDao;
        $this->appletSubmitAuditDao = $appletSubmitAuditDao;
        $config = config('wechat.open_platform');
        $this->component_appid = $config['app_id'];
        $this->component_appsecret = $config['secret'];
        $this->encodingAESKey = $config['aes_key'];
    }

    /**
     * 接收微信授权事件：ticket、授权回调
     * @param $params
     * @param $xml
     * @return array
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:35
     */
    public function serve($params,$xml): array
    {
        Log::info('接收微信推送ticket:params-'.json_encode($params).'content-'.json_encode($xml));
        try {
            $xmlData = $this->fromXml($xml);
            $res = $this->decryptMsg($xmlData['Encrypt']);
            $data = $this->fromXml($res);
            Log::info('接收微信推送ticket:data-'.json_encode($data, JSON_UNESCAPED_UNICODE));
            if($data['InfoType'] == 'component_verify_ticket'){// 推送ticket
                $ticketKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_TICKET, $xmlData['AppId']);
                Cache::store('redis')->handler()->set($ticketKey, $data['ComponentVerifyTicket']);
            }elseif($data['InfoType'] == 'authorized'){//授权成功
                // 删除授权码
                $preAuthCodeKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_PRE_AUTH_CODE, $this->component_appid);
                Cache::store('redis')->handler()->del($preAuthCodeKey);
                // 授权成功，更新授权状态
                $this->appletDao->createOrUpdate(
                    [
                        'original_appid' => $data['AuthorizerAppid']
                    ],
                    [
                        'original_appid' => $data['AuthorizerAppid'],
                        'authorization_status' => 1
                    ]
                );
                // 根据授权码获取刷新令牌，存储授权小程序授权信息
                $this->getApiQueryAuth($data['AuthorizerAppid'], $data['AuthorizationCode']);
                // 配置隐私协议
                $this->setPrivacySetting($data['AuthorizerAppid']);
                // 配置服务器域名
                $this->setModifyDomainSetting($data['AuthorizerAppid']);
                // 配置类目
                $this->setCategorySetting($data['AuthorizerAppid']);
                // 配置插件
                $this->setPluginSetting($data['AuthorizerAppid']);
                $msg = '小程序授权成功'.$data['AuthorizerAppid'] . json_encode($data, JSON_UNESCAPED_UNICODE);
                log::info($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            }
        } catch (\Exception $e) {
            $msg = '接收微信推送ticket:error-'.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        return [];
    }

    /**
     * 接收微信消息与事件
     * @param $params
     * @param $xml
     * @return array
     * @author  wzq
     * @date    2023/3/1 20:34
     */
    public function accountServe($params,$xml): array
    {
        Log::info('接收微信推送accountServe:params-'.json_encode($params).'content-'.json_encode($xml));
        $updateTime = Carbon::now()->toDateTimeString();
        try {
            $xmlData = $this->fromXml($xml);
            $res = $this->decryptMsg($xmlData['Encrypt']);
            $data = $this->fromXml($res);
            if($data['Event'] == 'weapp_audit_success'){// 审核通过
                $desc = "小程序审核通过";
                $updateData = [
                    'status' => WxAppletSubmitAuditModel::STATUS_AUDIT_SUCCESS,
                    'submit_audit_status' => WxAppletSubmitAuditModel::SUBMIT_AUDIT_STATUS_SUCCESS,
                    'submit_audit_success_time' => $updateTime,
                ];
            }elseif($data['Event'] == 'weapp_audit_fail'){// 审核不通过
                $desc = "小程序审核不通过";
                $updateData = [
                    'status' => WxAppletSubmitAuditModel::STATUS_AUDIT_FAIL,
                    'submit_audit_fail_time' => $updateTime,
                    'submit_audit_status' => WxAppletSubmitAuditModel::SUBMIT_AUDIT_STATUS_FAIL,
                    'submit_audit_result' => json_encode($data['Reason'], JSON_UNESCAPED_UNICODE),
                ];
            }elseif($data['Event'] == 'weapp_audit_delay'){// 审核延后
                $desc = "小程序审核延后";
                $updateData = [
                    'submit_audit_status' => WxAppletSubmitAuditModel::SUBMIT_AUDIT_STATUS_DELAY,
                    'submit_audit_result' => json_encode($data['Reason'], JSON_UNESCAPED_UNICODE),
                ];
            }else{
                $desc = "unknown";
                $updateData = [];
            }
            Log::info("接收微信推送accountServe:data-{$params['appid']} - $desc - ".json_encode($data, JSON_UNESCAPED_UNICODE));
            if($updateData){
                $this->appletSubmitAuditDao
                    ->getSearch([
                        'original_appid' => $params['appid'],
                        'status' => WxAppletSubmitAuditModel::STATUS_AUDITING,
                    ])
                    ->update($updateData);
            }
            $msg = '小程序审核通知'.$params['appid'] . $desc;
            Log::info($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);

        } catch (\Exception $e) {
            $msg = '小程序审核error'.$params['appid'].$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        return [];
    }

    /**
     * 小程序授权三方接口
     * @return string[]
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:34
     */
    public function authorization(): array
    {
        $host = config('app.app_host');
        $wxUrl = Constant::MP_COMPONENT_LOGIN_PAGE;
        $redirectUri = "$host/admin/setting/miniprogram";
        $backUrl = "$host/api/applet/open-platform/serve";
        $proAuthCode = $this->getPreAuthCode();
        $url = "$wxUrl?pre_auth_code=$proAuthCode&component_appid=$this->component_appid&redirect_uri=$redirectUri?back=$backUrl";
        return ['url' => $url];
    }

    /**
     * 获取微信三方token
     * @return string
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:34
     */
    private function getComponentToken(): string
    {
        $tokenKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_TOKEN, $this->component_appid);
        $token = Cache::store('redis')->handler()->get($tokenKey);
        if($token){
            return $token;
        }
        $ticketKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_TICKET, $this->component_appid);
        $ticket = Cache::store('redis')->handler()->get($ticketKey);

        try {
            // 获取token
            $url = Constant::API_COMPONENT_API_COMPONENT_TOKEN;
            $params = [
                'component_appid' => $this->component_appid,
                'component_appsecret' => $this->component_appsecret,
                'component_verify_ticket' => $ticket,
            ];
            $data = sendRequest('post', $url,$params);
            if(!isset($data['component_access_token'])){
                $msg = '获取微信三方token-失败'.json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
                throw new WechatException("获取微信三方token-失败");
            }
            $token = $data['component_access_token'];
            Cache::store('redis')->handler()->set($tokenKey, $token, ["EX" => 3000]);
        } catch (\Exception $e) {
            $msg = '获取微信三方token-error'.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new WechatException('获取微信三方token:error-'.$e->getMessage());
        }
        return $token;
    }

    /**
     * todo 清除微信三方token
     * @author  wzq
     * @date    2023/3/7 17:26
     */
    private function delComponentToken()
    {
        $tokenKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_TOKEN, $this->component_appid);
        Cache::store('redis')->handler()->del($tokenKey);
    }

    /**
     * 配置小程序用户隐私保护
     * @param $authorizerAppid
     * @throws GuzzleException|DbException
     * @author  wzq
     * @date    2023/3/2 17:57
     */
    private function setPrivacySetting($authorizerAppid){
        $token = $this->getAuthorizerToken($authorizerAppid);
        $url = Constant::API_COMPONENT_SET_PRIVACY_SETTING . '?access_token='.$token;
        $params = config('wechat.privacy_setting');
        $data = sendRequest('post', $url, $params);
        Log::info("配置小程序用户隐私保护:data-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE));
        if($data['errcode'] == 0){
            // 配置小程序用户隐私保护成功，更新状态
            $this->appletDao->createOrUpdate(
                [
                    'original_appid' => $authorizerAppid
                ],
                [
                    'original_appid' => $authorizerAppid,
                    'privacy_status' => 1
                ]
            );
        }else{
            $msg = '配置小程序用户隐私保护:error-'.$authorizerAppid.json_encode($data, JSON_UNESCAPED_UNICODE);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    }

    /**
     * 配置类目
     * @param $authorizerAppid
     * @throws GuzzleException|DbException
     * @author  wzq
     * @date    2023/3/2 20:29
     */
    private function setCategorySetting($authorizerAppid){
        // 获取已存在的二级类目id
        $categoryIds = $this->getSettingCategories($authorizerAppid);
        $params = config('wechat.category_setting.categories');
        if($categoryIds){
            foreach ($params as $k => $param){
                if(in_array($param['second'], $categoryIds)){
                    unset($params[$k]);
                }
            }
        }
        if(count($params) == 0) return ;
        $token = $this->getAuthorizerToken($authorizerAppid);
        $url = Constant::API_WX_OPEN_ADD_CATEGORY . '?access_token='.$token;
        $data = sendRequest('post', $url, ['categories' => array_values($params)]);
        Log::info("配置类目:data-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE));
        if($data['errcode'] == 0){
            // 配置类目成功，更新状态
            $this->appletDao->createOrUpdate(
                [
                    'original_appid' => $authorizerAppid
                ],
                [
                    'original_appid' => $authorizerAppid,
                    'category_status' => 1
                ]
            );
        }else{
            $msg = "配置类目:error-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    }

    /**
     * 配置插件
     * @param $authorizerAppid
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/16 11:11
     */
    private function setPluginSetting($authorizerAppid){
        $token = $this->getAuthorizerToken($authorizerAppid);
        $url = Constant::API_WXA_PLUGIN . '?access_token='.$token;
        $pluginSetting = config('wechat.plugin_setting');
        //批量处理插件
        foreach($pluginSetting as $item){
            $msg = "{$authorizerAppid}插件{$item['plugin_appid']}:";
            // 查询插件
            $pluginData = sendRequest('post', $url, ['action' => 'list', 'plugin_appid' => $item['plugin_appid']]);
            if($pluginData['errcode'] == 0){
                if(empty($pluginData['plugin_list'])){
                    // 申请插件
                    $item['action'] = 'apply';
                    $data = sendRequest('post', $url, $item);
                    if($data['errcode'] == 0){
                        $msg .= "申请成功";
                    }else{
                        $msg .= "申请失败".json_encode($data, JSON_UNESCAPED_UNICODE);;
                    }
                }else{
                    $msg .= "已存在";
                }
                $item['action'] = 'apply';
                $data = sendRequest('post', $url, ['action' => 'list', $item]);
                Log::info("配置类目:data-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE));
            }else{
                $msg .= "获取失败" . json_encode($pluginData, JSON_UNESCAPED_UNICODE);
            }
            Log::info($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    }

    /**
     * 获取已设置的所有类目
     * @param $authorizerAppid
     * @return array
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/10 16:56
     */
    private function getSettingCategories($authorizerAppid): array
    {
        $token = $this->getAuthorizerToken($authorizerAppid);
        // 获取已存在的二级类目id
        $url = Constant::API_WX_OPEN_GET_CATEGORY . '?access_token=' . $token;
        $data = sendRequest('get', $url, []);
        $categoryIds = [];
        if(isset($data['categories'])){
            foreach($data['categories'] as $category){
                $categoryIds[] = $category['second'];
            }
        }
        return $categoryIds;
    }

    /**
     * 配置服务器域名
     * @param $authorizerAppid
     * @throws GuzzleException|DbException
     * @author  wzq
     * @date    2023/3/2 20:59
     */
    private function setModifyDomainSetting($authorizerAppid){
        $token = $this->getAuthorizerToken($authorizerAppid);
        $url = Constant::API_WXA_MODIFY_DOMAIN . '?access_token='.$token;
        $params = config('wechat.modify_domain_setting');
        $data = sendRequest('post', $url, $params);
        Log::info("配置服务器域名:data-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE));
        if($data['errcode'] == 0){
            // 配置服务器域名成功，更新状态
            $this->appletDao->createOrUpdate(
                [
                    'original_appid' => $authorizerAppid
                ],
                [
                    'original_appid' => $authorizerAppid,
                    'modify_domain_status' => 1
                ]
            );
        }else{
            $msg = "配置服务器域名:error-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    }

    /**
     * 使用授权码获取授权信息
     *
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/2 10:15
     */
    private function getApiQueryAuth($authorizerAppid, $authorizationCode){
        $token = $this->getComponentToken();
        $url = Constant::API_COMPONENT_API_QUERY_AUTH . '?component_access_token='.$token;
        $params = [
            'component_appid' => $this->component_appid,
            'authorization_code' => $authorizationCode,
        ];
        $data = sendRequest('post', $url, $params);
        Log::info("使用授权码获取授权信息:data-$authorizerAppid".json_encode($data, JSON_UNESCAPED_UNICODE));
        if(isset($data['authorization_info'])){
            // 存储小程序接口调用令牌、小程序刷新令牌
            $this->updateAuthorizerToken($authorizerAppid, $data['authorization_info']['authorizer_access_token'], $data['authorization_info']['authorizer_refresh_token']);
        }else{
            $msg = "使用授权码获取授权信息:error-$authorizerAppid - $authorizationCode".json_encode($data, JSON_UNESCAPED_UNICODE);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    }

    /**
     * 获取/刷新小程序调用令牌
     *
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/2 10:15
     */
    private function getAuthorizerToken($authorizerAppid){
        $authorizerKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_AUTHORIZER_TOKEN, $authorizerAppid);
        $authorizerData = Cache::store('redis')->handler()->get($authorizerKey);
        if(!$authorizerData){
            $msg = "获取/刷新小程序调用令牌:缓存失败-$authorizerAppid 严重错误";
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
            return false;
        }
        $authorizerData = json_decode($authorizerData, true);
        if($authorizerData['expires_at'] > time()){
            return $authorizerData['authorizer_access_token'];
        }
        // token已过期，调用刷新小程序token接口
        $token = $this->getComponentToken();
        $url = Constant::API_COMPONENT_API_AUTHORIZER_TOKEN . '?component_access_token='.$token;
        $params = [
            'component_appid' => $this->component_appid,
            'authorizer_appid' => $authorizerAppid,
            'authorizer_refresh_token' => $authorizerData['authorizer_refresh_token'],
        ];
        $data = sendRequest('post', $url, $params);
        if(!isset($data['authorizer_access_token'])){
            $msg = "获取/刷新小程序调用令牌:刷新token失败-$authorizerAppid";
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
            return false;
        }
        $this->updateAuthorizerToken($authorizerAppid, $data['authorizer_access_token'], $data['authorizer_refresh_token']);
        return $data['authorizer_access_token'];
    }

    /**
     * todo 过期小程序调用令牌
     * @param $authorizerAppid
     * @return void
     * @author  wzq
     * @date    2023/3/7 18:00
     */
    private function delAuthorizerToken($authorizerAppid)
    {
        $authorizerKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_AUTHORIZER_TOKEN, $authorizerAppid);
        $authorizerData = Cache::store('redis')->handler()->get($authorizerKey);
        if (!$authorizerData) {
            $msg = "过期小程序调用令牌:error-$authorizerAppid";
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
            return;
        }
        $authorizerData['expires_at'] = -1;
        $authorizerData = json_decode($authorizerData, true);
        Cache::store('redis')->handler()->set($authorizerKey, $authorizerData);
    }

    /**
     * 更新小程序调用令牌
     * @param $authorizerAppid
     * @param $authorizerAccessToken
     * @param $authorizerRefreshToken
     * @author  wzq
     * @date    2023/3/2 14:51
     */
    private function updateAuthorizerToken($authorizerAppid, $authorizerAccessToken, $authorizerRefreshToken){
        $authorizerKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_AUTHORIZER_TOKEN, $authorizerAppid);
        $data = [
            'authorizer_access_token' => $authorizerAccessToken,
            'authorizer_refresh_token' => $authorizerRefreshToken,
            'expires_at' => time() + 7000,
        ];
        Cache::store('redis')->handler()->set($authorizerKey, json_encode($data));
    }

    /**
     * 批量上传代码并生成体验版
     * @param $appIds
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws GuzzleException
     * @throws ModelNotFoundException
     * @author  wzq
     * @date    2023/3/1 20:33
     */
    public function submitAuditFlow($appIds): array
    {
        // 获取token
        $token = $this->getComponentToken();
        // 获取草稿箱缓存id
        $templateDraftId = $this->getTemplateDraftId($token);
        if($templateDraftId < 0){
            // 获取草稿箱失败
            $msg = '批量上传代码并生成体验版:error:获取草稿箱失败' . json_encode($appIds);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
            throw new WechatException("获取草稿箱信息失败");
        }
        // 查询缓存草稿id，判断是否需要添加到模板库
        $templateDraftIdKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_TEMPLATE_DRAFT_ID, $this->component_appid);
        $templateDraftIdOld = Cache::store('redis')->handler()->get($templateDraftIdKey);
        if(($templateDraftId == 0 && $templateDraftIdOld === false) || $templateDraftId > $templateDraftIdOld){
            // 将草稿添加到模板库
            $res = $this->addToTemplate($token, $templateDraftId);
            if(!$res){
                // 将草稿添加到模板库失败
                $msg = '批量上传代码并生成体验版:error:将草稿添加到模板库失败' . json_encode($appIds);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
                throw new WechatException("将草稿添加到模板库失败");
            }
            // 保存草稿箱缓存id
            Cache::store('redis')->handler()->set($templateDraftIdKey, $templateDraftId);
        }
        // 获取最新模板信息
        $template = $this->getTemplateDetail($token);
        if(!$template){
            $msg = '批量上传代码并生成体验版:error:获取最新模板信息失败-' . json_encode($appIds);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
            throw new WechatException("获取最新模板信息失败");
        }
        Log::info('批量上传代码并生成体验版:template-'.json_encode($template, JSON_UNESCAPED_UNICODE));
        foreach ($appIds as $appId){
            $data = [
                'app_id' => $appId,
                'template_id' => $template['template_id'],
                'user_desc' => $template['user_desc'],
                'user_version' => $template['user_version'],
            ];
            // 异步执行微信上传代码并生成体验版
            Queue::push(WechatSubmitAuditJob::class, $data);
        }
        return [];
    }

    /**
     * 上传代码并生成体验版
     * @param $data
     * @return bool
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/3 11:14
     */
    public function uploadCode($data): bool
    {
        $appId = $data['app_id'];
        $templateId = $data['template_id'];
        $userDesc = $data['user_desc'];
        $userVersion = $data['user_version'];
        try {
            // 获取最后一次提审数据
            $lastSubmit = $this->appletSubmitAuditDao
                ->getLastSubmit($appId, 'status,user_version');
            if($lastSubmit){
                // 判断是否符合提审条件
                if(in_array($lastSubmit['status'], [
                    WxAppletSubmitAuditModel::STATUS_NONE,
                    WxAppletSubmitAuditModel::STATUS_WAIT,
                    WxAppletSubmitAuditModel::STATUS_AUDITING,
                    WxAppletSubmitAuditModel::STATUS_AUDIT_SUCCESS,
                ])){
                    return false;
                }
                // 处理版本号
                $versionArr = explode('-', $lastSubmit['user_version']);
                if($userVersion == $versionArr[0]){
                    $last = isset($versionArr[1]) ? (int)$versionArr[1] + 1 : 1;
                    $userVersion = $versionArr[0] . '-' . $last;
                }
            }

            // 获取token
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_COMMIT . '?access_token='.$token;
            $extJson = config('wechat.ext_json');

            // 执行微信上传代码并生成体验版
            $params = [
                'template_id' => $templateId,
                'user_version' => $userVersion,
                'user_desc' => $userDesc,
                'ext_json' => $extJson
            ];
            $data = sendAsyncRequest('post', $url, $params);
            Log::info('上传代码并生成体验版:result-'. $appId . json_encode($data, JSON_UNESCAPED_UNICODE));
            if($data['errcode'] == 0){
                Log::info('上传代码并生成体验版:success-'.$appId);
                $this->appletSubmitAuditDao->create([
                    'original_appid' => $appId,
                    'template_id' => $templateId,
                    'status' => WxAppletSubmitAuditModel::STATUS_AUDITING,
                    'user_version' => $userVersion,
                    'user_desc' => $userDesc,
                ]);
                // 查询是否存在体验版码
                $applet = $this->appletDao->selectWhere(['original_appid' => $appId])->first();
                if($applet && $applet->qrcode == ''){
                    $applet->qrcode = $this->getQrcode($appId);
                    $applet->save();
                }
                return true;
            }else{
                $msg = '上传代码并生成体验版:error' . $appId . json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
                return false;
            }
        } catch (\Exception $e) {
            $msg = '上传代码并生成体验版:error-'. $appId . $e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }

    }

    /**
     * 提交代码审核
     * @param $id
     * @param $appId
     * @return void
     * @throws GuzzleException|DbException
     * @author  wzq
     * @date    2023/3/6 10:23
     */
    private function submitAudit($id, $appId){
        $token = $this->getAuthorizerToken($appId);
        $url = Constant::API_WXA_SUBMIT_AUDIT . '?access_token='.$token;
        $params = [
            'item_list' => config('wechat.submit_audit_list')
        ];
        $data = sendRequest('post', $url, $params);
        Log::info('异步处理小程序提审流程:提审result-'.$appId.json_encode($data, JSON_UNESCAPED_UNICODE));
        if($data['errcode'] == 0){
            Log::info('异步处理小程序提审流程:提审success-'.$appId);
            $data = [
                'audit_id' => $data['auditid'],
                'submit_audit_status' => WxAppletSubmitAuditModel::SUBMIT_AUDIT_STATUS_AUDITING,
            ];
        }else{
            $data = [
                'status' => WxAppletSubmitAuditModel::STATUS_AUDIT_FAIL,
                'submit_audit_status' => WxAppletSubmitAuditModel::SUBMIT_AUDIT_STATUS_FAIL,
                'submit_audit_result' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ];
            $msg = '异步处理小程序提审流程:提审error-'.$appId . json_encode($data, JSON_UNESCAPED_UNICODE);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
        $this->appletSubmitAuditDao->update((int)$id, $data);
    }

    /**
     * 撤回代码审核
     * @param string $appId
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/7 10:25
     */
    public function undoCodeAudit(string $appId)
    {
        try {
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_UNDO_CODE_AUDIT . '?access_token='.$token;
            $data = sendRequest('get', $url, []);
            Log::info('撤回代码审核:result-'.$appId . json_encode($data, JSON_UNESCAPED_UNICODE));
            $updateData = [];
            $submitAuditResult = '';
            if($data['errcode'] == 0){
                Log::info('撤回代码审核:success-'.$appId);
                $updateData['submit_audit_fail_time'] = Carbon::now()->toDateTimeString();
                $updateData['status'] = WxAppletSubmitAuditModel::STATUS_AUDIT_FAIL;
                $updateData['submit_audit_status'] = WxAppletSubmitAuditModel::SUBMIT_AUDIT_STATUS_WITHDRAW;
            }else{
                if($data['errcode'] == 40001){
                    $submitAuditResult = '获取 access_token 时 AppSecret 错误，或者 access_token 无效。请开发者认真比对 AppSecret 的正确性，或查看是否正在为恰当的公众号调用接口';
                }elseif($data['errcode'] == -1){
                    $submitAuditResult = '系统繁忙，此时请开发者稍候再试';
                }elseif($data['errcode'] == 87011){
                    $submitAuditResult = '现网已经在灰度发布，不能进行版本回退';
                }elseif($data['errcode'] == 87012){
                    $submitAuditResult = '该版本不能回退，可能的原因：1:无上一个线上版用于回退 2:此版本为已回退版本，不能回退 3:此版本为回退功能上线之前的版本，不能回退';
                }elseif($data['errcode'] == 87013){
                    $submitAuditResult = '撤回次数达到上限（每天5次，每个月 10 次）';
                }else{
                    $submitAuditResult = $data;
                }
            }
            $msg = '撤回代码审核:fail-'.$appId . json_encode($data, JSON_UNESCAPED_UNICODE);
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => __FILE__,
                'line' => __LINE__
            ]);
            $updateData['submit_audit_result'] = json_encode($submitAuditResult, JSON_UNESCAPED_UNICODE);
            // 更新撤回代码审核数据
            $this->appletSubmitAuditDao
                ->getSearch([
                    'original_appid' => $appId,
                    'status' => [
                        WxAppletSubmitAuditModel::STATUS_NONE,
                        WxAppletSubmitAuditModel::STATUS_WAIT,
                        WxAppletSubmitAuditModel::STATUS_AUDITING,
                    ],
                ])
                ->update($updateData);
        } catch (\Exception $e) {
            $msg = '撤回代码审核:error-'. $appId . $e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

    }

    /**
     * 小程序版本回退
     * @param string $appId
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/7 10:25
     */
    public function revertCodeRelease(string $appId)
    {
        try {
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_REVERT_CODE_RELEASE . '?access_token='.$token;
            $data = sendRequest('get', $url, []);
            Log::info('小程序版本回退:result-'.$appId . json_encode($data, JSON_UNESCAPED_UNICODE));
            if($data['errcode'] == 0){
                $msg = '小程序版本回退:success-'.$appId. 'success';
                Log::info($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            }else{
                $msg = '小程序版本回退:fail-'.$appId. json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            }
        } catch (\Exception $e) {
            $msg = '小程序版本回退:error-'. $appId. $e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 小程序版本发布已通过审核的小程序
     * @param string $appId
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/7 10:25
     */
    public function release(string $appId)
    {
        try {
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_RELEASE . '?access_token='.$token;
            $data = sendAsyncRequest('post', $url, '{}');
            Log::info('发布已通过审核的小程序:result-'.$appId . json_encode($data, JSON_UNESCAPED_UNICODE));
            $updateData = [];
            $updateData['release_time'] = Carbon::now()->toDateTimeString();
            if($data['errcode'] == 0){
                $msg = '发布已通过审核的小程序:success-'.$appId;
                Log::info($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
                $updateData['status'] = WxAppletSubmitAuditModel::STATUS_RELEASE_SUCCESS;
                // 更新小程序发布状态
                $this->appletDao->updateByWhere(['original_appid' => $appId],['is_release' => WxAppletModel::IS_RELEASE_YES]);
            }else{
                $updateData['status'] = WxAppletSubmitAuditModel::STATUS_RELEASE_FAIL;
                $updateData['release_result'] = json_encode($data, JSON_UNESCAPED_UNICODE);
                $msg = '发布已通过审核的小程序:fail-'.$appId.json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            }

            // 更新发布已通过审核的小程序
            $this->appletSubmitAuditDao->getSearch([
                'original_appid' => $appId,
                'status' => [
                    WxAppletSubmitAuditModel::STATUS_AUDIT_SUCCESS,
                    WxAppletSubmitAuditModel::STATUS_RELEASE_FAIL
                ],
            ])
                ->update($updateData);
        } catch (\Exception $e) {
            $msg = '发布已通过审核的小程序:error-'. $appId.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 获取代码草稿列表
     * @param $token
     * @return int
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:31
     */
    private function getTemplateDraftId($token): int
    {
        // 获取草稿箱
        $url = Constant::API_WXA_GET_TEMPLATE_DRAFT_LIST . '?access_token='.$token;
        $params = [];
        $data = sendRequest('post', $url, $params);
        if($data['errcode'] == 0 && $data['draft_list']){
            return $data['draft_list'][count($data['draft_list']) - 1]['draft_id'];
        }
        return -1;
    }

    /**
     * 将草稿添加到代码模板库
     * @param $token
     * @param $draftId
     * @return bool
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:31
     */
    private function addToTemplate($token,$draftId): bool
    {
        $url = Constant::API_WXA_ADD_TO_TEMPLATE . '?access_token='.$token;
        $params = [
            'draft_id' => $draftId,
            'template_type' => 0,
        ];

        $data = sendRequest('post', $url, $params);
        if($data['errcode'] == 0){
            return true;
        }
        return false;
    }

    /**
     * 获取模版信息
     * @param $token
     * @return array
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:30
     */
    private function getTemplateDetail($token): array
    {
        $url = Constant::API_WXA_GET_TEMPLATE_LIST . '?access_token='.$token;
        $params = [
            'template_type' => 0,
        ];
        $data = sendRequest('get', $url,$params);
        if($data['errcode'] == 0 && $data['template_list']){
            return $data['template_list'][count($data['template_list']) - 1];
        }
        return [];
    }

    /**
     * 获取隐私接口检测结果
     * @param $id
     * @param $appId
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/3 11:35
     */
    public function getCodePrivacyInfo($id, $appId)
    {
        try {
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_SECURITY_GET_CODE_PRIVACY_INFO . '?access_token='.$token;
            $params = [];
            $data = sendRequest('get', $url, $params);
            $updateTime = Carbon::now()->toDateTimeString();
            Log::info('异步处理小程序提审流程:检测:result-'. $appId . json_encode($data, JSON_UNESCAPED_UNICODE));
            if($data['errcode'] == 0){
                Log::info('异步处理小程序提审流程:检测:success-'.$appId);
                $this->appletSubmitAuditDao->update((int)$id, [
                    'detection_time' => $updateTime,
                    'detection_status' => WxAppletSubmitAuditModel::DETECTION_STATUS_SUCCESS,
                ]);
                // 提审小程序
                $this->submitAudit($id, $appId);
            }else{
                $updateData = [];
                $type = 'error';
                if($data['errcode'] == 61039){
                    $type = 'info';
                    $desc = '异步处理小程序提审流程:检测:wait-'.$appId;
                    $updateData = [
                        'detection_time' => $updateTime,
                        'detection_result' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ];
                }elseif($data['errcode'] == 61040){
                    $desc = '异步处理小程序提审流程:检测:fail-'. $appId;
                    $updateData = [
                        'status' => WxAppletSubmitAuditModel::STATUS_AUDIT_FAIL,
                        'detection_status' => WxAppletSubmitAuditModel::DETECTION_STATUS_FAIL,
                        'detection_time' => $updateTime,
                        'submit_audit_fail_time' => $updateTime,
                        'detection_result' => json_encode($data, JSON_UNESCAPED_UNICODE),
                        'submit_audit_result' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ];
                }else{
                    $desc = '异步处理小程序提审流程:检测:error-'. $appId;
                }
                if($updateData){
                    $this->appletSubmitAuditDao->update((int)$id, $updateData);
                }
                $msg = $desc. json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            }

        } catch (\Exception $e) {
            $msg = '异步处理小程序提审流程:检测:error-'. $appId.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

    }

    /**
     * 获取体验版二维码
     * @param $appId
     * @return mixed|string
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/3 19:00
     */
    private function getQrcode($appId)
    {
        try {
            // 获取小程序体验码
            $path = config('app.app_name').'/wechat/platform/qrcode';
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_GET_QRCODE . '?access_token='.$token;
            $client = new Client();
            $response = $client->request('get',$url);
            // 上传体验码
            $key = substr(md5(rand(0, 9999)), 0, 5) . date('YmdHis') . rand(0, 999999) . '.jpg';
            $uploadType = (int)systemConfig('upload_type') ?: 1;
            $upload = UploadService::create($uploadType);
            $upload->to($path)->validate()->stream($response->getBody()->getContents(), $key);
            return $upload->getUploadInfo()['thumb_path'];
        } catch (\Exception $e) {
            $msg = '获取体验版二维码:检测:error-'. $appId.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return '';
        }
    }

    /**
     * 小程序授权第三方
     * @return mixed
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/1 20:29
     */
    private function getPreAuthCode()
    {
        $preAuthCodeKey = sprintf(RedisKey::WECHAT_OPEN_PLATFORM_PRE_AUTH_CODE, $this->component_appid);
        $preAuthCode = Cache::store('redis')->handler()->get($preAuthCodeKey);
        if($preAuthCode){
            return $preAuthCode;
        }

        $token = $this->getComponentToken();

        // 获取token
        $url = Constant::API_COMPONENT_API_CREATE_PRE_AUTH_CODE . '?component_access_token='.$token;
        $params = [
            'component_appid' => $this->component_appid,
        ];
        try {
            // 获取授权码失败
            $data = sendRequest('post', $url, $params);
            if(!isset($data['pre_auth_code'])){
                $msg = '获取授权码失败:'. json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
                throw new WechatException('获取授权码失败');
            }
            $preAuthCode = $data['pre_auth_code'];
            Cache::store('redis')->handler()->set($preAuthCodeKey, $preAuthCode, ["EX" => 600]);
            return $preAuthCode;
        } catch (\Exception $e) {
            $msg = '获取授权码失败:'.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new WechatException('获取授权码失败');
        }
    }


    /**
     * 解密ticket
     * @param $msg_encrypt
     * @return false|string
     * @author  wzq
     * @date    2023/3/1 20:22
     */
    private function decryptMsg($msg_encrypt)
    {

        $EncodingAESKey = $this->encodingAESKey;

        $AESKey = base64_decode($EncodingAESKey.'=');

        $iv = substr($AESKey, 0, 16);

        $msg_decode = base64_decode($msg_encrypt);

        $msg = openssl_decrypt($msg_decode, 'AES-256-CBC', $AESKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

        $msg_len = unpack('N', substr($msg, 16, 4));

        $len = $msg_len[1];

        return substr($msg, 20, $len);

    }

    /**
     * 将xml转为array
     * @param $xml
     * @return mixed
     * @author  wzq
     * @date    2023/3/1 20:21
     */
    private function fromXml($xml)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }


    /**
     * 获取scheme码
     * @param $appId
     * @param $params
     * @return mixed|string
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/3 19:00
     */
    public function getScheme($appId,$params)
    {
        try {
            $token = $this->getAuthorizerToken($appId);
            $url = Constant::API_WXA_SCHEME . '?access_token='.$token;

            $data = sendRequest('post', $url, $params);
            if ($data['errcode'] != 0 || !isset($data['openlink'])){
                $msg = '获取scheme码失败:'. json_encode($data, JSON_UNESCAPED_UNICODE);
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
                throw new WechatException('获取scheme码失败');
            }

            return $data['openlink'];

        } catch (\Exception $e) {
            $msg = '获取scheme码:检测:error-'. $appId.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return '';
        }
    }

    public function getAuditstatus($appid,$auditid){
        try {
            $token = $this->getAuthorizerToken($appid);
            $url = 'https://api.weixin.qq.com/wxa/get_auditstatus'. '?access_token='.$token;
            $params = [
                'auditid'=>$auditid
            ];
            $data = sendRequest('post', $url, $params);

            var_dump($data);

        } catch (\Exception $e) {
            $msg = '检测:error-'. $appid.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return '';
        }
    }


    public function getprivacysetting($appid,$privacy_ver){
        try {
            $token = $this->getAuthorizerToken($appid);
            $url = 'https://api.weixin.qq.com/cgi-bin/component/getprivacysetting'. '?access_token='.$token;
            $params = [
                'privacy_ver'=>$privacy_ver
            ];
            $data = sendRequest('post', $url, $params);

            var_dump($data);

        } catch (\Exception $e) {
            $msg = ':检测:error-'. $appid.$e->getMessage();
            Log::error($msg);
            sendMessageToWorkBot([
                'msg' => $msg,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return '';
        }
    }

    public function setPrivacySetting2($appid){
        $this->setPrivacySetting($appid);
    }
}
