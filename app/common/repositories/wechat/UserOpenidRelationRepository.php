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


namespace app\common\repositories\wechat;


use app\common\dao\user\UserDao;
use app\common\dao\user\UserOpenIdRelationDao;
use app\common\dao\wechat\WechatUserDao;
use app\common\repositories\article\ArticleRepository;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\SendNewsJob;
use crmeb\services\WechatUserGroupService;
use crmeb\services\WechatUserTagService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;

/**
 * Class WechatUserRepository
 * @package app\common\repositories\wechat
 * @author xaboy
 * @day 2020-04-28
 * @mixin WechatUserDao
 */
class UserOpenidRelationRepository extends BaseRepository
{
    /**
     * WechatUserRepository constructor.
     * @param UserOpenIdRelationDao $dao
     */
    public function __construct(UserOpenIdRelationDao $dao)
    {
        $this->dao = $dao;
    }
}
