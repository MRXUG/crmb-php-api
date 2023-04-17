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


namespace app\common\dao\black;


use think\Collection;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use app\common\dao\BaseDao;
use app\common\model\black\UserBlackLog;
use app\common\model\BaseModel;
use think\Model;

class UserBlackLogDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return UserBlackLog::class;
    }


       /**
     * 搜索列表
     * @param $uid
     * @param array $where
     * @return BaseQuery
     * @author Qinii
     */
    public function search($uid,array $where)
    {
        $query = UserBlackLog::getDB();

        return $query->where('uid',$uid)->order('sort DESC,create_time DESC');
    }
}