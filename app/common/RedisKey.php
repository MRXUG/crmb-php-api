<?php

namespace app\common;

/**
 * redisKey定义
 *
 * @package app\common
 */
class RedisKey
{
    /**
     * 微信推送ticket
     * %s ticket
     */
    const WECHAT_OPEN_PLATFORM_TICKET = 'wechat:open_platform:ticket:%s';

    /**
     * 微信三方token
     * %s 第三方appid
     */
    const WECHAT_OPEN_PLATFORM_TOKEN = 'wechat:open_platform:token:%s';

    /**
     * 微信小程序token
     * %s 小程序appid
     */
    const WECHAT_OPEN_PLATFORM_AUTHORIZER_TOKEN = 'wechat:open_platform:authorizerToken:%s';

    /**
     * 微信三方草稿箱id
     * %s 草稿箱id
     */
    const WECHAT_OPEN_PLATFORM_TEMPLATE_DRAFT_ID = 'wechat:open_platform:templateDraftId:%s';

    /**
     * 微信三方授权码
     * %s 授权码
     */
    const WECHAT_OPEN_PLATFORM_PRE_AUTH_CODE = 'wechat:open_platform:preAuthCode:%s';

    /**
     * 商品详情页
     */

     const GOODS_DETAIL = 'goods_detail:%s';
     const GOODS_DETAIL_TIMEOUT = 86400;
     const GOODS_DETAIL_WithUid_TIMEOUT = 600;

    /**
     * 分类列表
     */

     const CATEGORY_LIST = 'category:list';
      /**
     * 围观数据
     */

     const GOODS_DETAIL_WATCH = 'goods_detail_watch';

    /**
     * 围观数据
     */

     const HOT_RANKING = 'goods_hot:hot_ranking';

    /**
     * 商家菜单权限数据 区分商家类型
     */
     const MERCHANT_MENU_ROUTES = 'all_menu_relevance_routes:';
     const MERCHANT_MENU_ROUTES_TIMEOUT = 24*3600;
}
