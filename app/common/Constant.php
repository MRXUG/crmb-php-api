<?php

namespace app\common;

/**
 * 常量定义
 *
 * @package app\common
 */
class Constant
{
    /**
     * 微信第三方授权小程序接口
     */

    // 获取微信三方token
    const API_COMPONENT_API_COMPONENT_TOKEN = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';

    // 获取代码草稿列表
    const API_WXA_GET_TEMPLATE_DRAFT_LIST = 'https://api.weixin.qq.com/wxa/gettemplatedraftlist';

    // 将草稿添加到代码模板库
    const API_WXA_ADD_TO_TEMPLATE = 'https://api.weixin.qq.com/wxa/addtotemplate';

    // 获取模版信息
    const API_WXA_GET_TEMPLATE_LIST = 'https://api.weixin.qq.com/wxa/gettemplatelist';

    // 小程序授权第三方
    const API_COMPONENT_API_CREATE_PRE_AUTH_CODE = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode';

    // 小程序授权三方接口
    const MP_COMPONENT_LOGIN_PAGE = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage';

    // 配置小程序用户隐私保护
    const API_COMPONENT_SET_PRIVACY_SETTING = 'https://api.weixin.qq.com/cgi-bin/component/setprivacysetting';

    // 获取已设置的所有类目
    const API_WX_OPEN_GET_CATEGORY = 'https://api.weixin.qq.com/cgi-bin/wxopen/getcategory';

    // 添加类目
    const API_WX_OPEN_ADD_CATEGORY = 'https://api.weixin.qq.com/cgi-bin/wxopen/addcategory';

    // 配置服务器域名
    const API_WXA_MODIFY_DOMAIN = 'https://api.weixin.qq.com/wxa/modify_domain';

    // 插件管理
    const API_WXA_PLUGIN = 'https://api.weixin.qq.com/wxa/plugin';

    // 使用授权码获取授权信息
    const API_COMPONENT_API_QUERY_AUTH = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth';

    // 调用刷新小程序token接口
    const API_COMPONENT_API_AUTHORIZER_TOKEN = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token';

    // 上传代码
    const API_WXA_COMMIT = 'https://api.weixin.qq.com/wxa/commit';

    // 获取体验版二维码
    const API_WXA_GET_QRCODE = 'https://api.weixin.qq.com/wxa/get_qrcode';

    // 获取隐私接口检测结果
    const API_WXA_SECURITY_GET_CODE_PRIVACY_INFO = 'https://api.weixin.qq.com/wxa/security/get_code_privacy_info';

    // 提交代码审核
    const API_WXA_SUBMIT_AUDIT = 'https://api.weixin.qq.com/wxa/submit_audit';

    // 撤回代码审核
    const API_WXA_UNDO_CODE_AUDIT = 'https://api.weixin.qq.com/wxa/undocodeaudit';

    // 小程序版本回退
    const API_WXA_REVERT_CODE_RELEASE = 'https://api.weixin.qq.com/wxa/revertcoderelease';

    // 小程序版本发布已通过审核的小程序
    const API_WXA_RELEASE = 'https://api.weixin.qq.com/wxa/release';

    // 小程序版本发布已通过审核的小程序
    const API_WXA_SCHEME = 'https://api.weixin.qq.com/wxa/generatescheme';

}