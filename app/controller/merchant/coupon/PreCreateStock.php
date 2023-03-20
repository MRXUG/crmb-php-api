<?php
/**
 * @user: BEYOND 2023/3/3 14:05
 */

namespace app\controller\merchant\coupon;

use app\common\repositories\coupon\BuildCouponRepository;
use app\validate\merchant\CreateMerchantCouponValidate;
use crmeb\basic\BaseController;

/**
 * 建券
 */
class PreCreateStock extends BaseController
{
    /**
     * 保持商家券建券信息（未调用微信接口）
     *
     * @param CreateMerchantCouponValidate $createMerchantCouponValidate
     *
     * @return mixed
     */
    public function preCreate(CreateMerchantCouponValidate $createMerchantCouponValidate, BuildCouponRepository $buildCouponRepository)
    {
        $params = $rawParams = $this->request->post();
        // 商家id
        $params['admin_id'] = $this->request->adminId();
        $params['mer_id'] = $this->request->merId();
        $createMerchantCouponValidate->check($params);

        //商家券入库
        $buildCouponRepository->preBuildStock($params, $rawParams['goods_list']);

        return app('json')->success('保存成功');
    }
}