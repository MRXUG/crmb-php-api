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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\user\User;
use think\Collection;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\Model;

/**
 * Class UserDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020-04-28
 */
class UserOpenIdRelationDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return \app\common\model\user\UserOpenIdRelation::class;
    }

    public function routineOpenIdByWhere(array $map)
    {
        $keys = [ 'wechat_user_id' , 'unionid' , 'routine_openid'];
        //wechat_user_id unionid routine_openid
        foreach ($map as $key){
            if(in_array($key , $keys)){
                throw new ValidateException("条件参数不支持");
            }
        }

        return $this->getModel()::getDB()->where(array_filter($map))->find();
    }

}
