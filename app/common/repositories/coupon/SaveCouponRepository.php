<?php
/**
 * @user: BEYOND 2023/3/3 11:17
 */

namespace app\common\repositories\coupon;

use app\common\dao\coupon\CouponStocksDao;
use app\common\model\coupon\CouponStocks;
use app\common\repositories\BaseRepository;
use crmeb\jobs\CouponEntrustJob;
use crmeb\services\MerchantCouponService;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class SaveCouponRepository extends BaseRepository
{
    private CouponStocks $couponStocks;

    public function __construct(CouponStocksDao $dao, CouponStocks $couponStocks)
    {
        $this->dao = $dao;
        $this->couponStocks = $couponStocks;
    }


    /**
     * @param $id
     * @param $params
     * @param $goodsList
     *
     * @return void
     */
    public function preSaveStock($id, $params, $goodsList)
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
            'max_coupons_per_user' => $params['is_limit'] == CouponStocks::IS_USER_LIMIT_NO ? CouponStocks::MAX_COUPONS_PER_USER :(int)$params['max_coupons_per_user'],
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
            'is_public' => $params['is_public'],
            'mer_id' => $params['mer_id'],
            'is_limit' => (int)$params['is_limit'],
            'is_user_limit' => (int)$params['is_user_limit'],
            'type_date' => (int)$params['type_date'],
            'date_range' => json_encode($params['date_range'] ?? []),
        ];


        Db::startTrans();
        try {
            /**
             * @var $stockProductRepository StockProductRepository
             */
            $stockProductRepository = app()->make(StockProductRepository::class);

            $model = $this->dao->update($id, $data);
            if ($params['scope'] == CouponStocks::SCOPE_NO) {
                // 删除历史优惠券商品
                $stockProductRepository->delGoods($id);
                $couponStocksId = $id;
                $stockProductRepository->insertGoods($goodsList, $couponStocksId);

                // 变更状态 - 创建开始和结束延迟队列
                app()->make(CouponStocksRepository::class)->changeStatus($id);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('商家券编辑失败' . $e->getMessage() .json_encode( compact('params', 'goodsList')));
            throw new ValidateException('商家券编辑失败');
        }
    }





}