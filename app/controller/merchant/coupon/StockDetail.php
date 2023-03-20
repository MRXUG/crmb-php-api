<?php
/**
 * @user: BEYOND 2023/3/6 15:34
 */

namespace app\controller\merchant\coupon;

use app\common\repositories\coupon\BuildCouponRepository;
use crmeb\basic\BaseController;

class StockDetail extends BaseController
{
    /**
     * @param $id
     * @param BuildCouponRepository $buildCouponRepository
     *
     * @return mixed
     */
    public function detail($id, BuildCouponRepository $buildCouponRepository)
    {
        $where = [
            'id' => $id,
        ];
        $result = $buildCouponRepository->stockProduct($where);
        if (empty($result)) {
            return app('json')->fail('批次不存在');
        }
        return app('json')->success($result);

    }
}