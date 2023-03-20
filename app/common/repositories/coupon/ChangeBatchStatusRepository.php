<?php


namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponStocksDao;
use app\common\model\coupon\CouponStocks;
use app\common\repositories\BaseRepository;
use Exception;
use think\facade\Log;

class ChangeBatchStatusRepository extends BaseRepository
{
    // 券状态：0待发布，1活动未开始，2进行中，3已结束，4已取消
    const NOT_STARTED = 'not_started';
    const IN_PROGRESS = 'in_progress';
    const HAVE_ENDED  = 'have_ended';
    const FAILURE     = 'failure';
    const CANCELLED   = 'cancelled';
    /**
     * @var CouponStocksDao
     */
    private $model;

    public function __construct(CouponStocksDao $couponStocksDao, CouponStocks $couponStocks)
    {
        $this->dao = $couponStocksDao;
        $this->model = $couponStocks;
    }

    /**
     * 变更优惠券批次状态
     *
     * @param $stockId
     * @param $event
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 14:20
     */
    public function changeStatus($id, $event)
    {
        try {
            $status = $this->checkState($id, $event);
            Log::info('活动' . $id . '变更状态参数：' . $status);
            if ($status > 0) {
                Log::info('活动' . $id . '变更状态参数：' . $status. '开始');
                $this->dao->update($id, ['status' => (int)$status]);
            }
        } catch (Exception $e) {
            Log::error('活动' . $id . '变更状态失败');
        }
    }

    /**
     * 检查优惠券批次状态
     *
     * @param $id
     * @param $event
     *
     * @return bool
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 14:19
     */
    public function checkState($id, $event)
    {
        try {
            $query = $this->model->where('id', $id)->find();
            if (!$query) {
                Log::info('活动' . $id . '不存在');

                return true;
            }
            $query = $query->toArray();
            Log::info('活动' . $id . '数据:' .json_encode($query, JSON_UNESCAPED_UNICODE));
            switch ($event) {
                //活动未开始
                case self::NOT_STARTED:
                    Log::info('活动' . $id . '变更状态为' . CouponStocks::COUPON_STATUS_NAME[CouponStocks::NOT_STARTED] . '进入');
                    if ($query['create_admin_id'] > 0 && $query['status'] == CouponStocks::TO_BE_RELEASED) {
                        return CouponStocks::NOT_STARTED;
                    }
                    break;
                // 进行中
                case  self::IN_PROGRESS:
                    Log::info(
                        '活动' . $id . '变更状态为' . CouponStocks::COUPON_STATUS_NAME[CouponStocks::IN_PROGRESS] . '进入'
                    );
                    if ($query['create_admin_id'] > 0 &&
                        $query['start_at'] <= date("Y-m-d H:i:s") &&
                        $query['end_at'] >= date("Y-m-d H:i:s") &&
                        $query['status'] == CouponStocks::NOT_STARTED) {

                        return CouponStocks::IN_PROGRESS;
                    }
                    break;
                // 已结束（时间结束）
                case self::HAVE_ENDED:
                    Log::info('活动' . $id . '变更状态为' . CouponStocks::COUPON_STATUS_NAME[CouponStocks::HAVE_ENDED] . '（时间结束）进入');
                    if ($query['create_admin_id'] > 0 && $query['end_at'] <= date("Y-m-d H:i:s")) {

                        return CouponStocks::HAVE_ENDED;
                    }
                    break;
                // 已结束（失效）
                case self::FAILURE:
                    Log::info(
                        '活动' . $id . '变更状态为' . CouponStocks::COUPON_STATUS_NAME[CouponStocks::IN_PROGRESS] . '(失效)进入'
                    );
                    if ($query['create_admin_id'] > 0) {
                        return CouponStocks::HAVE_ENDED;
                    }
                    break;
                // 已取消
                case self::CANCELLED:
                    Log::info('活动' . $id . '变更状态为' . CouponStocks::COUPON_STATUS_NAME[CouponStocks::CANCELLED] . '进入');
                    if ( $query['status'] == CouponStocks::TO_BE_RELEASED) {

                        return CouponStocks::CANCELLED;
                    }
                    break;
            }

            return 0;
        } catch (Exception $e) {
            Log::error('变更优惠券批次状态失败：' . $e->getMessage());
        }
    }
}