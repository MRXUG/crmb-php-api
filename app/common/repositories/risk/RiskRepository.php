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


namespace app\common\repositories\risk;


use app\common\dao\risk\RiskDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;
use app\common\model\risk\Risk;

/**
 * Class ArticleCategoryRepository
 * @package app\common\repositories\article
 * @author xaboy
 * @day 2020-04-20
 * @mixin ArticleCategoryDao
 */
class RiskRepository extends BaseRepository{
    /**
     * ArticleCategoryRepository constructor.
     * @param RiskDao $dao
     */
    public function __construct(RiskDao $dao){
        $this->dao = $dao;
    }

    public function getRiskId(){
        $model = new Risk;
        return $model->order('rid DESC')->value('rid');
    }

}