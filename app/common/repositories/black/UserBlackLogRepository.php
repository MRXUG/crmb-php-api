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


namespace app\common\repositories\black;


use app\common\dao\black\UserBlackLogDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;

/**
 * Class ArticleCategoryRepository
 * @package app\common\repositories\article
 * @author xaboy
 * @day 2020-04-20
 * @mixin ArticleCategoryDao
 */
class UserBlackLogRepository extends BaseRepository{
    /**
     * ArticleCategoryRepository constructor.
     * @param UserBlackLogDao $dao
     */
    public function __construct(UserBlackLogDao $dao){
        $this->dao = $dao;
    }


     /**
     * TODO 添加记录
     * $data 参数数组
     * @day 2020-10-27
     */
    public function create(array $data){
        $result = $this->dao->create($data);
        return $result;
    }

}