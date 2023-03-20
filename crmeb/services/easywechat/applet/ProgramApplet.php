<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace crmeb\services\easywechat\applet;

use crmeb\services\easywechat\subscribe\ProgramSubscribe;
use EasyWeChat\Core\AbstractAPI;
use EasyWeChat\Core\AccessToken;
use EasyWeChat\Core\Exceptions\HttpException;
use EasyWeChat\Core\Exceptions\InvalidArgumentException;
use EasyWeChat\Support\Collection;
use think\exception\ValidateException;

// 小程序交易体验分违规记录
class ProgramApplet extends AbstractAPI
{

    /**
     * 小程序交易体验分违规记录
     */
    const GET_PENALTY_LIST = 'https://api.weixin.qq.com/wxaapi/wxamptrade/get_penalty_list';


    /**
     * Message backup.
     *
     * @var array
     */
    protected $messageBackup;

    protected $required = ['offset', 'limit'];
    /**
     * Attributes
     * @var array
     */
    protected $message = [
        'offset' => '',
        'limit' => '',
    ];

    /**
     * ProgramSubscribeService constructor.
     * @param AccessToken $accessToken
     */
    public function __construct(AccessToken $accessToken)
    {
        parent::__construct($accessToken);

        $this->messageBackup = $this->message;
    }

    /**
     * 小程序交易体验分违规记录
     * @return Collection|null
     * @throws HttpException
     */
    public function getPenaltyList()
    {
        $params = [
            'offset' => 0,
            'limit' => 100,
        ];
        return $this->parseJSON('get', [self::GET_PENALTY_LIST, $params]);

    }

    /**
     * Magic access..
     *
     * @param $method
     * @param $args
     * @return $this
     */
    public function __call($method, $args)
    {
        $map = [
            'offset' => 'offset',
            'limit' => 'limit',
            'data' => 'data',
            'with' => 'data',
        ];


        if (isset($map[$method])) {
            $this->message[$map[$method]] = array_shift($args);
        }

        return $this;
    }

}
