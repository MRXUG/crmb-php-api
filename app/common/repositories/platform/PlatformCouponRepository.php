<?php

namespace app\common\repositories\platform;

use app\common\dao\coupon\CouponStocksDao;
use app\common\dao\platform\PlatformCouponDao;
use app\common\dao\platform\PlatformCouponPositionDao;
use app\common\dao\platform\PlatformCouponUseScopeDao;
use app\common\model\platform\PlatformCoupon;
use app\common\model\store\product\Product;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * @property PlatformCouponDao $dao
 */
class PlatformCouponRepository extends BaseRepository
{
    private PlatformCouponUseScopeDao $useScopeDao;
    private PlatformCouponPositionDao $couponPositionDao;

    public function __construct(PlatformCouponDao $dao, PlatformCouponUseScopeDao $useScopeDao, PlatformCouponPositionDao $couponPositionDao)
    {
        $this->dao = $dao;
        $this->useScopeDao = $useScopeDao;
        $this->couponPositionDao = $couponPositionDao;
    }

    /**
     * 选卷
     *
     * @param int $page
     * @param int $limit
     * @throws null
     * @return array
     */
    public function selectCoupon(int $page = 1, int $limit = 10): array
    {
        $nowDate = date("Y-m-d H:i:s");
        /** @var CouponStocksDao $couponDao */
        $couponDao = app()->make(CouponStocksDao::class);
        $field = [
            'discount_num', # 面值
            'count(distinct(`mer_id`)) as `mer_count`', # 商户数
            'count(distinct(`id`)) as `platform_coupon_count`', # 平台卷优惠券数量
            'group_concat(`id` order by `id` desc) as `coupon_id_arr`', # 优惠券id组
            'group_concat(`scope` order by `id` desc) as `scope_arr`', # 优惠券id组
            'group_concat(`mer_id` order by `id` desc) as `mer_id_arr`', # 优惠券id组
            'max(`transaction_minimum`) as `threshold`', # 最大门槛
            'min(`start_at`) as `min_start_time`', # 最早发券开始时间
            'max(`end_at`)  as `max_end_time`' # 最晚发券结束时间
        ];

        $model =  $couponDao->getModelObj()->where([
            ['is_del', '=', 0],
            ['type', '=', 1],
            ['status', 'in', [1,2]],
            ['end_at', '>', $nowDate],
            ['start_at', '<', $nowDate]
        ])->group('discount_num');

        $list = (clone $model)->field($field)->page($page, $limit)->select()->toArray();

        $productModel =  fn() => Product::getInstance();

        # 获取用于查询商品数据的 productId
        foreach ($list as &$item) {
            $coupon_id_arr = explode(',', $item['coupon_id_arr']);
            $scope_arr = explode(',', $item['scope_arr']);
            $mer_id_arr = explode(',', $item['mer_id_arr']);
            $productIdArr = $this->getProductId($coupon_id_arr, $scope_arr, $mer_id_arr);

            $goodsInfo = $productModel()->field([
                Db::raw('count(`product_id`) as goods_count'),
                Db::raw('sum(`sales`) as goods_sales'),
            ])->whereIn('product_id', $productIdArr)->find()->toArray();

            $item = array_merge($item, $goodsInfo);

            unset($item['scope_arr'], $item['coupon_id_arr'], $item['mer_id_arr']);
        }

        return [
            'list' => $list,
            'count' => (clone $model)->count()
        ];
    }


    private function getProductId (array $coupon_id_arr, array $scope_arr, array $mer_id_arr): array
    {
        $merArr = [];
        $couponArr = [];
        # 区分根据商户获取商品 和 选择的商品
        foreach ($scope_arr as $k => $v) {
            if ($v == 1) {
                $merArr[] = $mer_id_arr[$k];
            } else {
                $couponArr[] = $coupon_id_arr[$k];
            }
        }
        # 两组数据去重
        $merArr = array_unique($merArr);
        $couponArr = array_unique($couponArr);
        $couponStr = implode(",", $couponArr);
        # 定义公共部分模型
        $productModel = fn() => Product::getInstance()->where([
            ['is_del', '=', 0],
            ['status', '=', 1],
            ['is_show', '=', 1],
            ['mer_status', '=', 1],
            ['product_type', '=', 0],
        ]);
        # 根据商户id获取所有的商品id
        $merProductId = $productModel()->whereIn('mer_id', $merArr)->column('product_id');
        # 根据优惠券id获取选择的商品id
        $couponProductId = empty($couponArr) ? [] : $productModel()->whereIn('product_id', Db::raw(<<<SQL
            select product_id from eb_stock_goods where coupon_stocks_id in ({$couponStr})
        SQL))->column('product_id');

        return array_merge(array_unique(array_merge($couponProductId, $merProductId)), []);
    }

    /**
     * 进行存储 操作 有 id 时进行修改操作
     *
     * @param array{
     *     discount_num: int,
     *     use_type: int,
     *     scope_id_arr: int[],
     *     coupon_position: int[],
     *     receive_start_time: string,
     *     receive_end_time: string,
     *     coupon_name: string,
     *     effective_day_number: int,
     *     is_limit: int,
     *     limit_number: int,
     *     is_user_limit:int,
     *     user_limit_number: int,
     *     crowd: int
     * } $param
     * @param int|null $platformCouponId
     * @throws null
     * @return void
     */
    public function save(array $param, ?int $platformCouponId = null)
    {
        # 不为空时为修改操作
        $isUpdate = !empty($platformCouponId);
        # 判断需要设置范围的时候是否有范围数据
        if (in_array($param['use_type'], [2,3,4]) && empty($param['scope_id_arr'])) {
            throw new ValidateException('请选择范围');
        }
        # 检测是否勾选发券位置
        if (empty($param['coupon_position'])) throw new ValidateException('发券位置必须选择');

        Db::transaction(function () use ($param, $isUpdate, $platformCouponId) {
            $nowDate = date("Y-m-d H:i:s");
            # 获取操作对象
            /** @var PlatformCoupon $platformCouponModel */
            $platformCouponModel = $isUpdate
                ? $this->dao->getModelObj()
                    ->where('platform_coupon_id', '=', $platformCouponId)
                    ->find()
                : $this->dao->getModelObj();
            # 获取是否修改了可用范围
            $isUpdateUseType = $isUpdate && $platformCouponModel->getAttr('use_type') != $param['use_type'];
            $platformCouponModel->save($param);
            # 创建/修改 范围数据
            if ($isUpdate && !empty($param['scope_id_arr'])) { # 如果有修改操作那么直接删除掉原有
                $this->useScopeDao->getModelObj()->where('platform_coupon_id', $platformCouponId)->delete();
            }
            if (($isUpdate && !empty($param['scope_id_arr'])) || !$isUpdate) {
                $this->useScopeDao->getModelObj()->insertAll((function () use ($param, $platformCouponModel, $nowDate):array {
                    $arr = [];
                    foreach ($param['scope_id_arr'] as $item) $arr[] = [
                        'scope_id' => $item,
                        'scope_type' => $param['use_type'],
                        'platform_coupon_id' => $platformCouponModel->getAttr('platform_coupon_id'),
                        'create_time' => $nowDate
                    ];
                    return $arr;
                })());
            }
            # 创建/修改 弹窗位置
            $this->couponPositionDao->getModelObj()->where('platform_coupon_id', $platformCouponId)->delete();
            $this->couponPositionDao->getModelObj()->insertAll((function () use ($param, $platformCouponModel, $nowDate): array {
                $arr = [];
                foreach ($param['coupon_position'] as $item) $arr[] = [
                    'position' => $item,
                    'platform_coupon_id' => $platformCouponModel->getAttr('platform_coupon_id'),
                    'create_time' => $nowDate
                ];
                return $arr;
            })());
        });
    }
}
