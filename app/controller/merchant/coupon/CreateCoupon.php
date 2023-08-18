<?php
/**
 * @user: BEYOND 2023/3/3 14:05
 */

namespace app\controller\merchant\coupon;

use app\common\model\coupon\CouponStocks;
use app\common\repositories\coupon\BuildCouponRepository;
use app\common\repositories\coupon\ChangeBatchStatusRepository;
use crmeb\basic\BaseController;
use crmeb\services\MerchantCouponService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Log;

/**
 * 建券
 */
class CreateCoupon extends BaseController
{
    /**
     * 建券-发布
     *
     * @param BuildCouponRepository $buildCouponRepository
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function create(BuildCouponRepository $buildCouponRepository, ChangeBatchStatusRepository $changeBatchStatusRepository)
    {
        $id = $this->request->post('id');
        // 1.批次详情
        $where = [
            'id' => $id,
            'status' => CouponStocks::STATUS_DEFAULT,
        ];
        $params = $buildCouponRepository->stockProduct($where);
        if (empty($params)) {
            return app('json')->fail('批次状态不正确');
        }
        $buildCouponRepository->createCoupon($params, $this->request->adminId(), $id);
        // 触发券开始
        $changeBatchStatusRepository->changeStatus($id, 'in_progress');
        return app('json')->success('发布成功');
    }
}
