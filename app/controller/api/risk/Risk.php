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


namespace app\controller\api\risk;

use app\common\repositories\risk\RiskRepository;
use think\App;
use crmeb\basic\BaseController;


class Risk extends BaseController{
    
    /**
     * @var RiskRepository
     */
    protected $repository;

    /**
     * Article constructor.
     * @param App $app
     * @param RiskRepository $repository
     */
    public function __construct(App $app,RiskRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }
    
    
    //获取风控设置
    public function getRisk(){
        $risk = $this->repository->getRisk();
        return app('json')->success($risk);
    }
}