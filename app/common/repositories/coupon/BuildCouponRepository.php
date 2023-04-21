<?php
/**
 * @user: BEYOND 2023/3/3 11:17
 */

namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponStocksDao;
use app\common\model\coupon\CouponStocks;
use app\common\repositories\BaseRepository;
use crmeb\exceptions\WechatException;
use crmeb\jobs\CouponEntrustJob;
use crmeb\services\MerchantCouponService;
use crmeb\utils\platformCoupon\RefreshPlatformCouponProduct;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class BuildCouponRepository extends BaseRepository
{
    public function __construct(CouponStocksDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 建券-调微信接口
     *
     * @param $params
     * @param $adminId
     * @param $id
     *
     * @return array
     */
    public function createCoupon($params, $adminId, $id)
    {
        RefreshPlatformCouponProduct::runQueue();

        Db::startTrans();
        try {
            // 2.调微信接口
            $stockData = MerchantCouponService::create(MerchantCouponService::BUILD_COUPON, [], $merchantConfig)->coupon()->build($params, $merchantConfig);
            if (!empty($stockData['code'])) {
                Log::error('建券失败,' . json_encode(compact('params', 'merchantConfig')));
                throw new WechatException('建券失败');
            }

            // 3.更新商家券
            $result = array_merge([
                'app_id' => $merchantConfig['app_id'],
                'mch_id' => $merchantConfig['payment']['merchant_id'],
            ], $stockData);
            $this->updateStock($result, $adminId, $id);

            // 批次委托
            Queue::push(CouponEntrustJob::class, $stockData);

            // 变更状态 - 未开始
            app()->make(CouponStocksRepository::class)->changeStatus($id, 'not_started');

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('商家券发布失败:' . $e->getMessage() . '参数:' . json_encode(compact('id')));
            throw $e;
        }

        return $result;
    }

    /**
     * @param $params
     * @param $goodsList
     *
     * @return void
     */
    public function preBuildStock($params, $goodsList)
    {
        $data = [
            'stock_name' => $params['stock_name'],
            'type' => $params['type'],
            'scope' => $params['scope'],
            'discount_num' => (int)$params['discount_num'],
            'transaction_minimum' => (int)$params['transaction_minimum'],
            'wait_days_after_receive' => (int)$params['wait_days_after_receive'],
            'available_day_after_receive' => (int)$params['available_day_after_receive'],
            'start_at' => $params['available_begin_time'],
            'end_at' => $params['available_end_time'],
//            'available_begin_time' => date(DATE_RFC3339, strtotime($params['available_begin_time'])),
//            'available_end_time' => date(DATE_RFC3339, strtotime($params['available_end_time'])),
            'max_coupons' => $params['is_limit'] == CouponStocks::IS_LIMIT_NO ? CouponStocks::MAX_COUPONS : (int)$params['max_coupons'],
            'max_coupons_per_user' => $params['max_coupons_per_user'] == CouponStocks::IS_USER_LIMIT_NO ? CouponStocks::MAX_COUPONS_PER_USER :(int)$params['max_coupons_per_user'],
            'stock_type' => CouponStocks::STOCK_TYPE_REDUCE,
            'coupon_code_mode' => CouponStocks::WECHATPAY_MODE,
            'max_coupons_by_day' => $params['is_limit'] == CouponStocks::IS_LIMIT_NO ? CouponStocks::MAX_COUPONS : (int)$params['max_coupons'],
            'merchant_name' => $params['merchant_name'] ?? '',
            'merchant_logo_url' => $params['merchant_logo_url'] ?? '',
            'background_color' => $params['background_color'] ?? '',
            'coupon_image_url' => $params['coupon_image_url'] ?? '',
            'entrance_words' => '百货清单',
            'guiding_words' => '9.9元起',
//            'mini_programs_path' => 'pages/index/index', // 小程序path
            'goods_name' => '店铺内{全场or部分}商品可用',
            'pre_admin_id' => $params['admin_id'],
            'is_public' => $params['is_public'],
            'mer_id' => $params['mer_id'],
            'create_time' => date('Y-m-d H:i:s'),
            'is_limit' => (int)$params['is_limit'],
            'is_user_limit' => (int)$params['is_user_limit'],
            'type_date' => (int)$params['type_date'],
            'date_range' => json_encode($params['date_range'] ?? []),
        ];


        try {
            Db::startTrans();
            /**
             * @var $stockProductRepository StockProductRepository
             */
            $stockProductRepository = app()->make(StockProductRepository::class);

            $model = $this->dao->create($data);
            $couponStocksId = $model->id;

            /*if (empty($params['id'])) {
                $model = $this->dao->create($data);
                $couponStocksId = $model->id;
            } else {
                $couponStocksId = $params['id'];
                $this->dao->update($couponStocksId, $data);
            }*/
            if ($params['scope'] == CouponStocks::SCOPE_NO) {
                $stockProductRepository->delGoods($couponStocksId);
                $stockProductRepository->insertGoods($goodsList, $couponStocksId);
            }
            Db::commit();
            // 变更状态 - 创建开始和结束延迟队列
            app()->make(CouponStocksRepository::class)->changeStatus($couponStocksId);

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('商家券创建失败' . $e->getMessage() .json_encode( compact('params', 'goodsList')));
            throw new ValidateException('商家券创建失败');
        }
    }

    /**
     * 批次商品
     *
     * @param $id
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function stockProduct($where)
    {
        $hidden = ['is_del', 'create_admin_id', 'pre_admin_id', 'merchant_logo_cos_url',
            'coupon_image_cos_url', 'coupon_image_url', 'background_color', 'merchant_logo_url', 'merchant_name'];
        $result = [];
        $model = $this->dao
            ->getWhere($where, '*', ['product']);

        if (method_exists($model, 'toArray')) {
            $result = $model->hidden($hidden)->toArray();
        }

        return $result;
    }

    public function updateStock($params, $adminId, $couponStocksId)
    {
        // 批次
        $this->dao->update($couponStocksId, [
            'stock_id'        => $params['stock_id'],
            'mch_id'          => $params['mch_id'],
            'applet_appid'    => $params['app_id'],
            'status'          => CouponStocks::STATUS_NOT,
            'create_admin_id' => $adminId,
        ]);

        // 批次商品
        /**
         * @var $stockProductRepository StockProductRepository
         */
        $stockProductRepository = app()->make(StockProductRepository::class);
        $stockProductRepository->updateWhere(['coupon_stocks_id' => $couponStocksId], ['stock_id' => $params['stock_id']]);
    }
}
