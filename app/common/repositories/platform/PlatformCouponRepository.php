<?php

namespace app\common\repositories\platform;

use app\common\dao\coupon\CouponStocksDao;
use app\common\dao\platform\PlatformCouponDao;
use app\common\dao\platform\PlatformCouponPositionDao;
use app\common\dao\platform\PlatformCouponUseScopeDao;
use app\common\model\coupon\CouponStocks;
use app\common\model\platform\PlatformCoupon;
use app\common\model\platform\PlatformCouponProduct;
use app\common\model\platform\PlatformCouponReceive;
use app\common\model\store\product\Product;
use app\common\repositories\BaseRepository;
use crmeb\jobs\EstimatePlatformCouponProduct;
use crmeb\listens\CreatePlatformCouponInitGoods;
use crmeb\services\MerchantCouponService;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use Exception;
use ValueError;
use Throwable;

/**
 * @property PlatformCouponDao $dao
 */
class PlatformCouponRepository extends BaseRepository
{
    private PlatformCouponUseScopeDao $useScopeDao;
    private PlatformCouponPositionDao $couponPositionDao;

    public function __construct(
        PlatformCouponDao $dao,
        PlatformCouponUseScopeDao $useScopeDao,
        PlatformCouponPositionDao $couponPositionDao
    ) {
        $this->dao = $dao;
        $this->useScopeDao = $useScopeDao;
        $this->couponPositionDao = $couponPositionDao;
    }

    public function selectCouponOne(int $discount_num): array
    {
        $nowDate = date("Y-m-d H:i:s");
        /** @var CouponStocksDao $couponDao */
        $couponDao = app()->make(CouponStocksDao::class);
        $field = [
            'discount_num', # 面值
            'count(distinct(`mer_id`)) as `mer_count`', # 商户数
            'count(distinct(`id`)) as `platform_coupon_count`', # 平台卷优惠券数量
            'max(`transaction_minimum`) as `threshold`', # 最大门槛
            'min(`start_at`) as `min_start_time`', # 最早发券开始时间
            'max(`end_at`)  as `max_end_time`' # 最晚发券结束时间
        ];

        $where = [
            ['is_del', '=', 0],
            ['type', '=', 1],
            ['status', 'in', [1, 2]],
            ['end_at', '>', $nowDate],
            ['start_at', '<', $nowDate],
            ['discount_num', '=', $discount_num]
        ];

        $model = $couponDao->getModelObj()->where($where)->group('discount_num')->field($field)->find();

        return method_exists($model, 'toArray') ? $model->toArray() : [];
    }

    /**
     * 选卷
     *
     * @param int $page
     * @param int $limit
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function selectCoupon(int $page = 1, int $limit = 10, array $where = []): array
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

        $where = array_merge($where, [
            ['is_del', '=', 0],
            ['type', '=', 1],
            ['status', 'in', [1, 2]],
            ['end_at', '>', $nowDate],
            ['start_at', '<', $nowDate]
        ]);

        $model = $couponDao->getModelObj()->where($where)->group('discount_num');

        $list = (clone $model)->field($field)->page($page, $limit)->select()->toArray();

        $productModel = fn() => Product::getInstance();

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

            $item['goods_count'] = (int)$goodsInfo['goods_count'];
            $item['goods_sales'] = (int)$goodsInfo['goods_sales'];

            unset($item['scope_arr'], $item['coupon_id_arr'], $item['mer_id_arr']);
        }

        return [
            'list' => $list,
            'count' => (clone $model)->count()
        ];
    }

    /**
     * 获取商品id组
     *
     * @param array $coupon_id_arr
     * @param array $scope_arr
     * @param array $mer_id_arr
     * @return array
     */
    public function getProductId(array $coupon_id_arr, array $scope_arr, array $mer_id_arr): array
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
        SQL
        ))->column('product_id');

        return array_merge(array_unique(array_merge($couponProductId, $merProductId)), []);
    }

    /**
     * 平台优惠券商户明细
     *
     * @param int $amount
     * @param int $page
     * @param int $limit
     * @throws null
     * @return void
     */
    public function platformCouponMerDetails(int $amount, int $page = 1, int $limit = 10): array
    {
        $couponModel = fn() => CouponStocks::getInstance()->alias('a')
            ->where([
                ['a.discount_num', '=', $amount]
            ])
            ->group('a.mer_id');

        $coupon = $couponModel()->field([
            'a.mer_id', 'b.mer_name', 'b.real_name', 'c.category_name',
            'min(a.start_at) as `min_start_time`', # 最早发券开始时间
            'max(a.end_at)  as `max_end_time`', # 最晚发券结束时间
            'max(`a`.`transaction_minimum`) as `threshold`', # 最大门槛
            'group_concat(`a`.`id` order by `id` desc) as `coupon_id_arr`', # 优惠券id组
            'group_concat(`a`.`scope` order by `id` desc) as `scope_arr`', # 优惠券id组
            'group_concat(`a`.`mer_id` order by `id` desc) as `mer_id_arr`', # 优惠券id组
        ])
            ->leftJoin('eb_merchant b', 'a.mer_id = b.mer_id')
            ->leftJoin('eb_merchant_category c', 'b.category_id = c.merchant_category_id')
            ->page($page, $limit)
            ->select()->toArray();

        $productModel = fn() => Product::getInstance();

        # 获取用于查询商品数据的 productId
        foreach ($coupon as &$item) {
            $coupon_id_arr = explode(',', $item['coupon_id_arr']);
            $scope_arr = explode(',', $item['scope_arr']);
            $mer_id_arr = explode(',', $item['mer_id_arr']);
            $productIdArr = $this->getProductId($coupon_id_arr, $scope_arr, $mer_id_arr);

            $goodsInfo = $productModel()->field([
                Db::raw('count(`product_id`) as goods_count'),
                Db::raw('sum(`sales`) as goods_sales'),
            ])->whereIn('product_id', $productIdArr)->find()->toArray();

            $item['goods_count'] = (int)$goodsInfo['goods_count'];
            $item['goods_sales'] = (int)$goodsInfo['goods_sales'];

            unset($item['scope_arr'], $item['coupon_id_arr'], $item['mer_id_arr']);
        }

        return [
            'list' => $coupon,
            'count' => $couponModel()->count('a.id')
        ];
    }

    /**
     * 进行存储 操作 有 id 时进行修改操作
     *
     * @param array{
     *     discount_num: int,
     *     use_type: int,
     *     scope_id_arr: int[],
     *     coupon_position: int[],
     *     threshold: int,
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
     * @return void
     * @throws null
     */
    public function save(array $param, ?int $platformCouponId = null): void
    {
        # 不为空时为修改操作
        $isUpdate = !empty($platformCouponId);
        # 判断需要设置范围的时候是否有范围数据
        if (in_array($param['use_type'], [2, 3, 4]) && empty($param['scope_id_arr'])) {
            throw new ValidateException('请选择范围');
        }
        # 判断门槛是否小于或者等于面值
        if ($param['threshold'] >= $param['discount_num']) {
            throw new ValidateException('门槛必须大于面值');
        }
        # 判断设置了限量设置的数量必须大于0
        if ($param['is_limit'] == 1 && $param['limit_number'] <= 0) {
            throw new ValidateException('设置的领取限量值必须大于 0');
        }
        if ($param['is_user_limit'] == 1 && $param['user_limit_number'] <= 0){
            throw new ValidateException('设置的每人领取限量值必须大于 0');
        }
        # 检测是否勾选发券位置
        if (empty($param['coupon_position'])) throw new ValidateException('发券位置必须选择');

        try {
            $platform_coupon_id = Db::transaction(function () use ($param, $isUpdate, $platformCouponId) {
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
                    $this->useScopeDao->getModelObj()->insertAll((function () use ($param, $platformCouponModel, $nowDate): array {
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
                return $platformCouponModel->getAttr('platform_coupon_id');
            });
            # 调用初始化队列
            Queue::push(CreatePlatformCouponInitGoods::class, [
                'platform_coupon_id' => $platform_coupon_id
            ]);
        } catch (Exception|ValueError|Throwable $e) {
            Log::error($e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * 商品预估
     *
     * @param int $discount_num 面额
     * @param int $threshold 门槛
     * @param int $use_type 使用范围
     * @param int[] $scope_id_arr 使用范围id
     * @param string $receive_start_time 领取开始时间
     * @param string $receive_end_time 领取结束时间
     * @return string
     */
    public function productEstimate(
        int $discount_num,
        int $threshold,
        int $use_type,
        array $scope_id_arr,
        string $receive_start_time,
        string $receive_end_time
    ): string
    {
        $jobNumber = uniqid('EstimatePlatformCouponProduct');
        Queue::push(EstimatePlatformCouponProduct::class, compact(
            'discount_num',
            'threshold',
            'use_type',
            'scope_id_arr',
            'receive_start_time',
            'receive_end_time',
            'jobNumber'
        ));
        return $jobNumber;
    }

    /**
     * 平台优惠券列表
     *
     * @param int $page
     * @param int $limit
     * @param array $where
     * @return array
     * @throws null
     */
    public function platformCouponList (int $page = 1, int $limit = 10, array $where = []): array
    {
        $nowDate = date("Y-m-d H:i:s");

        $platformCouponModel = fn() => $this->dao->getModelObj()->alias('a')->when(!empty($where), function (BaseQuery $query) use($where, $nowDate) {
            # 根据状态筛选
            if (!empty($where['status']) && $where['status'] >= 0) {
                switch ($where['status']) {
                    case 0: # 待发布
                        $query->where([
                            ['a.status', '=', 0],['a.receive_end_time', '>', $nowDate]
                        ]);
                        break;
                    case 1: # 进行中
                        $query->where([
                            ['a.status', '=', 1],['a.receive_start_time', '<', $nowDate],['a.receive_end_time', '>', $nowDate]
                        ]);
                        break;
                    case 2: # 失效
                        $query->where([
                            ['a.status', '=', 2]
                        ]);
                        break;
                    case 3: # 未开始
                        $query->where([
                            ['a.status', '=', 1],['a.receive_start_time', '>', $nowDate]
                        ]);
                        break;
                    case 4: # 已结束
                        $query->where([
                            ['a.status', '=', 1],['a.receive_end_time', '<', $nowDate]
                        ]);
                        break;
                }
            }
            # 根据优惠券名称查询
            if (!empty($where['name'])) {
                $query->where('a.coupon_name', 'like',"%{$where['name']}%");
            }
            # 微信商户号
            if (!empty($where['wechat_business_number'])) {
                $query->where('a.wechat_business_number', 'like', "%{$where['wechat_business_number']}%");
            }
            # 发放人群
            if (isset($where['crowd']) && $where['crowd'] > 0) {
                $query->where('a.crowd', '=', $where['crowd']);
            }
            # 优惠券id查询
            if (isset($where['stock_id']) && $where['stock_id'] > 0) {
                $query->where('a.stock_id', 'like', "%{$where['stock_id']}%");
            }
            # 发放位置搜索
            if (!empty($where['position']) && $where['position'] > 0) {
                $query->whereIn('a.platform_coupon_id', Db::raw(<<<SQL
                    select platform_coupon_id from eb_platform_coupon_position where position = {$where['position']} group by platform_coupon_id
                SQL));
            }
        });

        $platformCoupon = $platformCouponModel()
            ->field([
                'a.platform_coupon_id',
                'a.use_type',
                'a.coupon_name',
                'a.receive_start_time',
                'a.receive_end_time',
                'a.wechat_business_number',
                'a.threshold',
                'a.discount_num',
                'a.is_user_limit',
                'a.user_limit_number',
                'a.status',
                'a.crowd',
                'a.stock_id',
                'a.is_limit',
                'a.limit_number',
                'a.received',
            ])
            ->page($page, $limit)
            ->order('a.platform_coupon_id', 'desc')
            ->select()
            ->toArray();

        $nowUnixTime = time();
        # 声明优惠券领取表操作模型
        $platformCouponReceive = fn (int $platformCouponId) => PlatformCouponReceive::getInstance()
            ->where('platform_coupon_id', $platformCouponId);

        foreach ($platformCoupon as &$item) {
            $endTime = strtotime($item['receive_end_time']);
            # 领取倒计时
            $item['receive_end_day'] = (int) ($nowUnixTime >= $endTime ? 0 : ($endTime - $nowUnixTime) / 86400);
            # 可用商品数
            $item['product_count'] = PlatformCouponProduct::getInstance()->where([
                ['platform_coupon_id', '=', $item['platform_coupon_id']],
                ['use_type', '=', $item['use_type']]
            ])->count('id') ?? 0;
            # 优惠信息
            $item['discount_info'] = (function () use(&$item): string {
                $productType = [
                    1 => '全部商品',
                    2 => '指定商品分类',
                    3 => '指定商户',
                    4 => '指定商户分类'
                ][$item['use_type']] ?? '';
                return "满{$item['threshold']}减{$item['discount_num']},{$productType} " .
                    ($item['is_user_limit'] == 1 ? "每人限量{$item['user_limit_number']}次" : '每人不限量');
            })();
            # 处理状态
            $item['statusCn'] = (function () use (&$item, $nowUnixTime): string {
                $startTime = strtotime($item['receive_start_time']);
                $endTime = strtotime($item['receive_end_time']);
                if ($startTime > $nowUnixTime) {
                    $item['status'] = 3;
                    return '活动未开始';
                }
                if ($endTime < $nowUnixTime) {
                    $item['status'] = 4;
                    return '已结束';
                }
                return [
                    '待发布',
                    '进行中',
                    '已失效'
                ][$item['status']];
            })();
            # 使用数量
            $item['use_count'] = [
                'received' => $platformCouponReceive($item['platform_coupon_id'])->count('id'),
                'used' => $platformCouponReceive($item['platform_coupon_id'])->where('status', 1)->count('id'),
            ];
            # 库存
            $item['stock'] = (function () use (&$item): array {
                $a = [];
                if ($item['is_limit'] == 1) {
                    $remain = $item['limit_number'] - $item['received'];
                    $remain = max($remain, 0);
                    $a[] = "总量:{$item['limit_number']}";
                    $a[] = "剩余:{$remain}";
                } else {
                    $a[] = '不限量';
                }
                return $a;
            })();
        }

        return [
            'list' => $platformCoupon,
            'count' => $platformCouponModel()->count()
        ];
    }

    /**
     * 修改状态
     *
     * @param int $platformCouponId 平台优惠券id
     * @param int $status 状态 1 发布 2 失效
     * @throws null
     * @return void
     */
    public function updateStatus(int $platformCouponId, int $status): void
    {
        if (!in_array($status, [1,2])) throw new ValidateException('参数错误');
        /** @var PlatformCoupon $platformCoupon */
        $platformCoupon = $this->dao->getModelObj()->where([
            ['platform_coupon_id', '=', $platformCouponId],
        ])->find();
        if (!$platformCoupon) throw new ValidateException('操作错误');

        Db::transaction(function () use ($platformCoupon, $status) {
            # 判断状态进行对应操作
            if ($status == 1) { # 发布
                $res = $this->buildPlatformCoupon($platformCoupon);
                $platformCoupon['wechat_business_number'] = $res['params']['belong_merchant']; # 填入生成优惠券的商户号
                $platformCoupon['stock_id'] = $res['result']['stock_id']; # 填入批次号
            }
            if ($status == 2) { # 失效
                $this->failPlatformCoupon($platformCoupon);
            }
            # 修改状态
            $platformCoupon->setAttr('status', $status);
            $platformCoupon->save();
        });
    }

    public function buildPlatformCoupon(PlatformCoupon $coupon): array
    {
        return MerchantCouponService::create(MerchantCouponService::BUILD_COUPON, [], $merchantConfig)
            ->coupon()
            ->buildPlatformCoupon($coupon, $merchantConfig);
    }

    /**
     * 将平台优惠券失效
     *
     * @param PlatformCoupon $coupon
     * @return void
     */
    public function failPlatformCoupon(PlatformCoupon $coupon)
    {

    }

    /**
     * 获取状态个数
     *
     * @throws null
     * @return array
     */
    public function getStatusCount(): array
    {
        $modelFn = fn (array $where = []) => PlatformCoupon::getInstance()->where($where)->count('platform_coupon_id');
        $nowDate = date("Y-m-d H:i:s");

        return [
            'all' => $modelFn(),
            'wait_to_released' => $modelFn([ # 待发布
                ['status', '=', 0],['receive_end_time', '>', $nowDate]
            ]),
            'has_not_started' => $modelFn([ # 未开始
                ['status', '=', 1],['receive_start_time', '>', $nowDate]
            ]),
            'in_progress' => $modelFn([ # 进行中
                ['status', '=', 1],['receive_start_time', '<', $nowDate],['receive_end_time', '>', $nowDate]
            ]),
            'over' => $modelFn([ # 已结束
                ['status', '=', 1],['receive_end_time', '<', $nowDate]
            ]),
            'cancel' => $modelFn([ # 已取消
                ['status', '=', 2]
            ])
        ];
    }
}
