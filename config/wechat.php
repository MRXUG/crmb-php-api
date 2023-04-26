<?php
/*
 * 小程序授权域名
 */
$url =  env('app.applet_auth_url', '');

return [
    /*
     * 开放平台第三方平台
     */
    'open_platform' => [
        'app_id'  => env('WECHAT.OPEN_PLATFORM_APPID', ''),
        'secret'  => env('WECHAT.OPEN_PLATFORM_SECRET', ''),
        'token'   => env('WECHAT.OPEN_PLATFORM_TOKEN', ''),
        'aes_key' => env('WECHAT.OPEN_PLATFORM_AES_KEY', ''),
    ],
    /*
     * 插件配置
     */
    'plugin_setting' => [
        [
            'plugin_appid' => '',
            'reason' => '向指定用户发放指定批次的商家券/支付券。'
        ]
    ],
    /*
     * 隐私协议配置
     */
    'privacy_setting' => [
        "owner_setting" => [
            "contact_email" => "601248452@qq.com",
            "notice_method" => "contact_email"
        ],
        "setting_list" => [
            [
                "privacy_key" => "UserInfo",
                "privacy_text" => "为了小程序展示、注册、登录小程序"
            ],
            [
                "privacy_key" => "Location",
                "privacy_text" => "位置信息"
            ],
            [
                "privacy_key" => "Invoice",
                "privacy_text" => "开具消费电子发票"
            ],
            [
                "privacy_key" => "AlbumWriteOnly",
                "privacy_text" => "存储活动分享图以及产品分享图"
            ],
            [
                "privacy_key" => "EXOrderInfo",
                "privacy_text" => "订单信息"
            ],
            [
                "privacy_key" => "Address",
                "privacy_text" => "地址"
            ],
            [
                "privacy_key" => "PhoneNumber",
                "privacy_text" => "手机号码"
            ],
            [
                "privacy_key" => "DeviceInfo",
                "privacy_text" => "优化手机端页面展示"
            ],
            [
                "privacy_key" => "MessageFile",
                "privacy_text" => "你上传图片更新头像信息"
            ],
            [
                "privacy_key" => "Camera",
                "privacy_text" => "你拍摄图片更新头像信息"
            ],
            [
                "privacy_key" => "Email",
                "privacy_text" => "邮箱"
            ],
            [
                "privacy_key" => "Album",
                "privacy_text" => "用户可以在商城内自定义自己的头像"
            ]
        ]
    ],
    /*
     * 添加类目配置
     */
    "category_setting" => [
        "categories" => [
            [
                "first" => 304,//商城自营
                "second" => 786,//美妆
//                "certicates" => [// 若需添加类目不需要资质，可不填
//                    [
//                        'key' => '资质名称',
//                        'value' => 'media_id',//资质上传返回id
//                    ]
//                ]
            ],
            /*[
                "first" => 304,//商城自营
                "second" => 307,//服饰内衣
            ],
            [
                "first" => 304,//商城自营
                "second" => 309,//母婴用品
            ],
            [
                "first" => 304,//商城自营
                "second" => 311,//3C数码
            ],*/
//            [
//                "first" => 304,//商城自营
//                "second" => 315,//珠宝玉石
//            ],
            [
                "first" => 304,//商城自营
                "second" => 317,//运动户外
            ],
            [
                "first" => 882,//汽车服务
                "second" => 1253,//汽车用品
            ],
//            [
//                "first" => 780,//电商平台
//                "second" => 782,//电商平台
//            ],
//            [
//                "first" => 304,//商城自营
//                "second" => 321,//食品饮料
//            ],
            [
                "first" => 304,//商城自营
                "second" => 325,//家居家纺
            ],
        ]
    ],
    /*
     * 审核项列表(小程序代码审核)
     */
    "submit_audit_list" => [
        [
            'first_class' => '商城自营',
            'second_class' => '美妆',
            'first_id' => 304,
            'second_id' => 786,
        ],
        /*[
            'first_class' => '商城自营',
            'second_class' => '服饰内衣',
            'first_id' => 304,
            'second_id' => 307,
        ],
        [
            'first_class' => '商城自营',
            'second_class' => '母婴用品',
            'first_id' => 304,
            'second_id' => 309,
        ],
        [
            'first_class' => '商城自营',
            'second_class' => '3C数码',
            'first_id' => 304,
            'second_id' => 311,
        ],*/
//        [
//            'first_class' => '商城自营',
//            'second_class' => '珠宝玉石',
//            'first_id' => 304,
//            'second_id' => 315,
//        ],
        [
            'first_class' => '商城自营',
            'second_class' => '运动户外',
            'first_id' => 304,
            'second_id' => 317,
        ],
        [
            'first_class' => '汽车服务',
            'second_class' => '汽车用品',
            'first_id' => 882,
            'second_id' => 1253,
        ],
//        [
//            'first_class' => '电商平台',
//            'second_class' => '电商平台',
//            'first_id' => 780,
//            'second_id' => 782,
//        ],
//        [
//            'first_class' => '商城自营',
//            'second_class' => '食品饮料',
//            'first_id' => 304,
//            'second_id' => 321,
//        ],
        [
            'first_class' => '商城自营',
            'second_class' => '家居家纺',
            'first_id' => 304,
            'second_id' => 325,
        ],
    ],
    /*
     * 服务器域名配置
     */
    "modify_domain_setting" => [
        "action" => "set",
        "requestdomain" => ["https://" . $url],
        "wsrequestdomain" => ["wss://" . $url],
        "uploaddomain" => ["https://" . $url],
        "downloaddomain" => [
            "https://" . $url,
            "",
            ""
        ],
        "udpdomain" => [
            "udp://" . $url,
            "",
            "",
            "",
        ],
        "tcpdomain" => [
            "tcp://" . $url,
            "",
            "",
            "",
        ]
    ],
    /*
     * 小程序配置
     */
    "ext_json" => <<<EOD
{
  "pages": [
    "pages/index/index",
    "pages/user/index",
    "pages/goods_cate/goods_cate",
    "pages/news_list/index",
    "pages/news_details/index",
    "pages/auth/index",
    "pages/order_pay_status/index",
    "pages/error/index",
    "pages/order_pay_back/index"
  ],
  "subPackages": [
    {
      "root": "pages/goods_details",
      "pages": [
        "index"
      ],
      "name": "goods_details"
    },
    {
      "root": "pages/order_details",
      "pages": [
        "index",
        "stay",
        "delivery"
      ],
      "name": "order_details"
    },
    {
      "root": "pages/advert",
      "pages": [
        "index/index"
      ],
      "name": "advert"
    },
    {
      "root": "pages/users",
      "pages": [
        "retrievePassword/index",
        "user_setting/index",
        "user_about/index",
        "agreement_rules/index",
        "user_info/index",
        "user_nickname/index",
        "user_get_coupon/index",
        "user_goods_collection/index",
        "user_sgin/index",
        "user_sgin_list/index",
        "user_money/index",
        "user_bill/index",
        "user_integral/index",
        "user_brokerage/index",
        "user_grade/index",
        "user_grade_list/index",
        "user_coupon/index",
        "user_spread_user/index",
        "user_spread_code/index",
        "user_spread_money/index",
        "user_address_list/index",
        "user_address/index",
        "user_phone/index",
        "user_modify_phone/index",
        "user_modify_pwd/index",
        "user_payment/index",
        "user_pwd_edit/index",
        "order_confirm/index",
        "goods_details_store/index",
        "promoter-list/index",
        "promoter-order/index",
        "promoter_rank/index",
        "commission_rank/index",
        "order_list/index",
        "order_list/search",
        "presell_order_list/index",
        "goods_logistics/index",
        "user_return_list/index",
        "goods_return/index",
        "login/index",
        "goods_comment_list/index",
        "goods_comment_con/index",
        "feedback/index",
        "feedback/list",
        "feedback/detail",
        "refund/index",
        "refund/confirm",
        "refund/detail",
        "refund/select",
        "refund/goods/index",
        "refund/list",
        "refund/logistics",
        "user_store_attention/index",
        "browsingHistory/index",
        "distributor/index",
        "user_invoice_list/index",
        "user_invoice_form/index",
        "user_invoicing/index",
        "user_invoice_order/index",
        "privacy/index"
      ],
      "name": "users"
    },
    {
      "root": "pages/store",
      "pages": [
        "index",
        "home/index",
        "detail/index",
        "list/index",
        "settled/index",
        "applicationRecord/index",
        "merchantDetails/index",
        "shopStreet/index",
        "qualifications/index"
      ],
      "name": "store"
    },
    {
      "root": "pages/admin",
      "pages": [
        "order/index",
        "orderList/index",
        "orderRefund/index",
        "business/index",
        "orderDetail/index",
        "refundDetail/index",
        "delivery/index",
        "statistics/index",
        "order_cancellation/index",
        "cancellate_result/index",
        "goods_details/index"
      ],
      "name": "adminOrder"
    },
    {
      "root": "pages/product",
      "pages": [
        "list/index",
        "goodsOnSale/index",
        "soldOutGoods/index",
        "recycleBin/index",
        "storeClassification/index",
        "storeClassification/addStoreClass",
        "addGoods/index",
        "addGoods/secound",
        "addGoods/addGoodDetils",
        "addGoods/singleSpecification",
        "addGoods/mulSpecification",
        "addGoods/specificationProperties",
        "addGoods/freightTemplate",
        "addGoods/addFreightTemplate",
        "addGoods/modifyPrice"
      ],
      "name": "product"
    },
    {
      "root": "pages/plantGrass",
      "pages": [
        "plant_detail/index",
        "plant_release/index",
        "plant_show/index",
        "plant_topic/index",
        "plant_search/index",
        "plant_search_list/index",
        "plant_featured/index",
        "plant_user/index",
        "plant_user_attention/index",
        "plant_user_fans/index"
      ],
      "name": "plant_grass"
    },
    {
      "root": "pages/columnGoods",
      "pages": [
        "HotNewGoods/index",
        "goods_list/index",
        "goods_coupon_list/index",
        "goods_search/index",
        "goods_search_con/index"
      ],
      "name": "columnGoods",
      "plugins": {}
    },
    {
      "root": "pages/annex",
      "pages": [
        "web_view/index",
        "vip_paid/index",
        "vip_center/index",
        "vip_grade/index",
        "vip_clause/index"
      ],
      "name": "annx"
    }
  ],
  "window": {
    "navigationBarTextStyle": "black",
    "navigationBarTitleText": "加载中...",
    "navigationBarBackgroundColor": "#fff",
    "backgroundColor": "#F8F8F8",
    "titleNView": true
  },
  "tabBar": {
    "color": "#282828",
    "selectedColor": "#E93323",
    "borderStyle": "white",
    "backgroundColor": "#ffffff",
    "list": [
      {
        "pagePath": "pages/index/index",
        "iconPath": "static/images/1-001.png",
        "selectedIconPath": "static/images/1-002.png",
        "text": "首页"
      },
      {
        "pagePath": "pages/goods_cate/goods_cate",
        "iconPath": "static/images/2-001.png",
        "selectedIconPath": "static/images/2-002.png",
        "text": "分类"
      },
      {
        "pagePath": "pages/user/index",
        "iconPath": "static/images/4-001.png",
        "selectedIconPath": "static/images/4-002.png",
        "text": "我的"
      }
    ]
  },
  "preloadRule": {
    "pages/goods_details/index": {
      "network": "all",
      "packages": [
        "advert"
      ]
    }
  },
  "permission": {
    "scope.userLocation": {
      "desc": "获取您的位置"
    }
  },
  "requiredPrivateInfos": [
    "getLocation",
    "chooseAddress"
  ],
  "plugins": {
    "sendCoupon": {
      "version": "latest",
      "provider": "wxf3f436ba9bd4be7b"
    }
  },
  "usingComponents": {
    "send-coupon": "plugin://sendCoupon/send-coupon",
    "skeleton": "/components/skeleton/index",
    "base-money": "/components/BaseMoney",
    "com-footer": "/components/common/com-footer/index",
    "com-header": "/components/common/com-header/index",
    "com-image": "/components/common/com-image/index",
    "com-popup": "/components/common/com-popup/index",
    "com-send-coupon": "/components/common/com-send-coupon/index"
  }
}
EOD
];
