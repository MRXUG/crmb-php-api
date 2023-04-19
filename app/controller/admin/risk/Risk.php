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

namespace app\controller\admin\risk;

use crmeb\basic\BaseController;
use app\common\repositories\risk\RiskRepository;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class Risk extends BaseController
{
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

    //添加风控设置
    public function setRisk(){
        
        $param = $this->request->params(['usecoupon','day30coupon','day30feedback','day30applet','day30mppay','blacklist_vid','voidReceivedCoupon','platformCouponGrantPop','platformCouponGrantList','adReflowCouponPop']);

        if($param){
            $rid = $this->repository->getRiskId();
            if($rid){
                $this->repository->update($rid,$param);
                
                return app('json')->success('保存成功');
            }else{
                return app('json')->fail('参数错误');
            }
        }
    }
    
    //获取风控设置
    public function getRisk(){
        $risk = $this->repository->getRisk();
        return app('json')->success($risk);
    }
    
    



    

}