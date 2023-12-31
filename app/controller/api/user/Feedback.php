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


namespace app\controller\api\user;

use app\controller\api\risk\Risk;
use crmeb\basic\BaseController;
use app\common\repositories\user\FeedbackRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\validate\api\FeedbackValidate;
use think\App;

class Feedback extends BaseController
{
    protected $repository;

    public function __construct(App $app, FeedbackRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @param FeedbackValidate $validate
     * @param FeedbackRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function feedback(FeedbackValidate $validate)
    {
        $data = $this->request->params(['type', 'order_id', 'order_sn', 'content','mer_id', ['images', []], 'realname', 'contact',['status',0]]);
        $validate->check($data);
        $data['uid'] = $this->request->uid();
        $FeedBack = $this->repository->create($data);

        event('user.feedback',compact('FeedBack'));

        //调用黑名单规则
        /**
         * @var Risk $risk
         */
        $risk =  app()->make(Risk::class);
        $risk->checkBlackApi($data['uid']);

        return app('json')->success('反馈成功');
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function feedbackList()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList(['uid' => $this->request->uid(),'is_del' => 0], $page, $limit));
    }

    public function detail($id)
    {
        if (!$this->repository->uidExists($id, $this->request->uid()))
            return app('json')->fail('数据不存在');
        $feedback = $this->repository->get($id);

        if($feedback->orderInfo){
            $feedback->orderInfo->takeOrderCount = count($feedback->orderInfo->takeOrderList);
            //获取商品id
            $productId = $feedback->orderProduct->product_id;
    
            //获取商品信息
            $product  = $this->repository->getProductInfo($productId);
            //商户信息
            $feedback->merchant = $product->merchant;
        }
        
        return app('json')->success($feedback);
    }
}
