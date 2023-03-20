<?php


namespace app\controller\merchant\coupon;

use app\common\repositories\coupon\BuildCouponRepository;
use app\common\repositories\coupon\SaveCouponRepository;
use app\validate\merchant\CreateMerchantCouponValidate;
use app\validate\merchant\SaveMerchantCouponValidate;
use crmeb\basic\BaseController;
use crmeb\services\MerchantCouponService;

/**
 * 编辑优惠券
 */
class SaveCoupon extends BaseController
{

    /**
     * 编辑优惠券
     *
     * @param $id
     * @param SaveMerchantCouponValidate $saveMerchantCouponValidate
     * @param SaveCouponRepository $saveCouponRepository
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 20:54
     */
    public function preSaveCreate(
        $id,
        SaveMerchantCouponValidate $saveMerchantCouponValidate,
        SaveCouponRepository $saveCouponRepository
    ) {
        $params = $rawParams = $this->request->post();
        $saveMerchantCouponValidate->check($params);
        unset($params['goods_list']);

        //商家券入库
        $saveCouponRepository->preSaveStock($id, $params, $rawParams['goods_list']);

        return app('json')->success('保存成功');
    }
}