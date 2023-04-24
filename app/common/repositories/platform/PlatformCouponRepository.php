<?php

namespace app\common\repositories\platform;

use app\common\dao\coupon\CouponStocksDao;
use app\common\dao\platform\PlatformCouponDao;
use app\common\dao\platform\PlatformCouponPositionDao;
use app\common\dao\platform\PlatformCouponUseScopeDao;
use app\common\model\coupon\CouponStocks;
use app\common\model\platform\PlatformCoupon;
use app\common\model\platform\PlatformCouponPosition;
use app\common\model\platform\PlatformCouponReceive;
use app\common\model\platform\PlatformCouponUseScope;
use app\common\model\store\product\Product;
use app\common\model\store\StoreCategory;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\StoreCategoryRepository;
use crmeb\jobs\CancelPlatformCouponJob;
use crmeb\jobs\CanceUserCouponJob;
use crmeb\jobs\EstimatePlatformCouponProduct;
use crmeb\listens\CreatePlatformCouponInitGoods;
use crmeb\services\MerchantCouponService;
use Exception;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use Throwable;
use ValueError;

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
            'a.discount_num', # 面值
            'count(distinct(a.mer_id)) as `mer_count`', # 商户数
            'count(distinct(a.id)) as `platform_coupon_count`', # 平台卷优惠券数量
            'max(a.transaction_minimum) as `threshold`', # 最大门槛
            'min(a.start_at) as `min_start_time`', # 最早发券开始时间
            'max(a.end_at)  as `max_end_time`' # 最晚发券结束时间
        ];

        $where = [
            ['a.is_del', '=', 0],
            ['a.type', '=', 1],
            ['a.status', 'in', [1, 2]],
            ['a.end_at', '>', $nowDate],
//            ['a.start_at', '<', $nowDate],
            ['a.discount_num', '=', $discount_num],
            ['b.is_del', '=', 0],
            ['b.mer_state', '=', 1],
        ];
        /** @var CouponStocks $model */
        $model = $couponDao->getModelObj()->alias('a')
            ->leftJoin('eb_merchant b', 'a.mer_id = b.mer_id')
            ->where($where)->group('discount_num')->field($field)->find();
        if (!$model) throw new ValidateException('无商家优惠券数据');
        # 保证有优惠券在范围内
        $model->setAttr('min_start_time', date("Y-m-d H:i:s", strtotime($model->getAttr('min_start_time')) + 10));
        $model->setAttr('max_end_time', date("Y-m-d H:i:s", strtotime($model->getAttr('max_end_time')) - 10));
        # 返回数据
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
    public function selectCoupon(int $page = 1, int $limit = 10, array $where = [],$order = 'discount_num asc'): array
    {
        $nowDate = date("Y-m-d H:i:s");
        /** @var CouponStocksDao $couponDao */
        $couponDao = app()->make(CouponStocksDao::class);
        $field = [
            'a.discount_num', # 面值
            'count(distinct(a.mer_id)) as `mer_count`', # 商户数
//            'count(distinct(`id`)) as `platform_coupon_count`', # 平台卷优惠券数量
            'group_concat(a.id order by a.id desc) as `coupon_id_arr`', # 优惠券id组
            'group_concat(a.scope order by id desc) as `scope_arr`', # 优惠券id组
            'group_concat(a.mer_id order by id desc) as `mer_id_arr`', # 优惠券id组
            'max(a.transaction_minimum) as `threshold`', # 最大门槛
            'min(a.start_at) as `min_start_time`', # 最早发券开始时间
            'max(a.end_at)  as `max_end_time`' # 最晚发券结束时间
        ];

        $where = array_merge($where, [
            ['a.is_del', '=', 0],
            ['a.type', '=', 1],
            ['a.status', 'in', [1, 2]],
            ['a.end_at', '>', $nowDate],
            ['b.status', '=', 1],
            ['b.is_del', '=', 0],
            ['b.mer_state', '=', 1],
        ]);

        $model = $couponDao->getModelObj()->alias('a')
            ->leftJoin('eb_merchant b', 'a.mer_id = b.mer_id')
            ->where($where)
            ->group('a.discount_num');

        $list = (clone $model)->field($field)->order('a.' . $order)->page($page, $limit)->select()->toArray();

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

            $item['platform_coupon_count'] = PlatformCoupon::getInstance()->where('discount_num', $item['discount_num'])->count('platform_coupon_id');

            $item['scope_count'] = $this->scopeCount([1,3], $item['discount_num']);

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
        $productModel = fn() => Product::getInstance()->alias('a')
            ->leftJoin('eb_store_spu b', 'a.product_id = b.product_id')
            ->where([
                ['a.is_del', '=', 0],
                ['a.status', '=', 1],
                ['a.is_show', '=', 1],
                ['a.mer_status', '=', 1],
                ['a.product_type', '=', 0],
                ['b.status', '=', 1],
            ]);
        # 根据商户id获取所有的商品id
        $merProductId = $productModel()->whereIn('a.mer_id', $merArr)->column('a.product_id');
        # 根据优惠券id获取选择的商品id
        $couponProductId = empty($couponArr) ? [] : $productModel()->whereIn('a.product_id', Db::raw(<<<SQL
            select product_id from eb_stock_goods where coupon_stocks_id in ({$couponStr})
        SQL
        ))->column('a.product_id');

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
    public function platformCouponMerDetails(int $amount, int $page = 1, int $limit = 10,$order='mer_id asc'): array
    {
        $nowDate = date("Y-m-d H:i:s");

        $couponModel = fn() => CouponStocks::getInstance()->alias('a')
            ->leftJoin('eb_merchant b', 'a.mer_id = b.mer_id')
            ->where([
                ['a.discount_num', '=', $amount],
                ['a.type', '=', 1],
                ['a.status', '=', 2],
                ['a.end_at', '>', $nowDate],
                ['b.status', '=', 1],
                ['b.is_del', '=', 0],
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
            ->leftJoin('eb_merchant_category c', 'b.category_id = c.merchant_category_id')
            ->order($order)
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
        if ($param['threshold'] < $param['discount_num']) {
            throw new ValidateException('门槛必须大于面值');
        }
        # 判断设置了限量设置的数量必须大于0
        if ($param['is_limit'] == 1 && $param['limit_number'] <= 0) {
            throw new ValidateException('设置的领取限量值必须大于 0');
        }
        if ($param['is_user_limit'] == 1 && $param['user_limit_number'] <= 0){
            throw new ValidateException('设置的每人领取限量值必须大于 0');
        }
        if ($param['use_type'] != 1 && empty($param['scope_id_arr'])) {
            throw new ValidateException('没有选择范围');
        }
        $param['limit_number'] = (int) $param['limit_number'];
        $param['user_limit_number'] = (int) $param['user_limit_number'];
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
            ], 'CreatePlatformCouponInitGoods');
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
     * @param string $orderProductCount
     * @param string $orderReceiveEndDay
     * @return array
     * @throws null
     */
    public function platformCouponList (int $page = 1, int $limit = 10, array $where = [],$order = 'platform_coupon_id desc'): array
    {
        $nowDate = date("Y-m-d H:i:s");

        $platformCouponModel = fn() => $this->dao->getModelObj()->alias('a')
            ->where('a.is_del', '=', 0)
            ->when(!empty($where), function (BaseQuery $query) use($where, $nowDate) {
            # 根据状态筛选
            if (isset($where['status']) && $where['status'] !== '' && $where['status'] >= 0) {
                switch ($where['status']) {
                    case 0: # 待发布
                        $query->where([
                            ['a.status', '=', 0],['a.receive_end_time', '>', $nowDate],['a.is_cancel', '=', 0]
                        ]);
                        break;
                    case 1: # 进行中
                        $query->where([
                            ['a.status', '=', 1],['a.receive_start_time', '<', $nowDate],['a.receive_end_time', '>', $nowDate],['a.is_cancel', '=', 0]
                        ]);
                        break;
                    case 2: # 已取消
                        $query->where([
                            ['a.is_cancel', '=', 1]
                        ]);
                        break;
                    case 3: # 未开始
                        $query->where([
                            ['a.status', '=', 1],['a.receive_start_time', '>', $nowDate],['a.is_cancel', '=', 0]
                        ]);
                        break;
                    case 4: # 已结束
                        $query->where(function (BaseQuery $query) use($nowDate) {
                            $query->where([
                                ['a.status', '=', 1],['a.receive_end_time', '<', $nowDate]
                            ])->whereOr([
                                ['a.status', '=', 2]
                            ]);
                        });
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
        $nowUnixTime = time();

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
                'a.effective_day_number',
                'a.is_init',
                'a.is_cancel',
                'a.cancel_time',
                '(select count(platform_coupon_id) as productNum
from eb_platform_coupon_product ab
         left join eb_store_product bb on ab.product_id = bb.product_id
where ab.platform_coupon_id = a.platform_coupon_id
    and bb.is_used = 1) as product_count',
                "(
	IF
		(unix_timestamp(NOW())  >= unix_timestamp( a.receive_end_time ), 0,( unix_timestamp( a.receive_end_time ) - unix_timestamp(NOW()))/ 86400 )
	) AS receive_end_day ",
            ])
            ->page($page, $limit)
            ->order($order)
//            ->fetchSQL()
            ->select()
            ->toArray();


        # 声明优惠券领取表操作模型
        $platformCouponReceive = fn (int $platformCouponId) => PlatformCouponReceive::getInstance()
            ->where('platform_coupon_id', $platformCouponId);

        foreach ($platformCoupon as &$item) {
            $endTime = strtotime($item['receive_end_time']);
            # 领取倒计时
            $item['receive_end_day'] = (int) ($nowUnixTime >= $endTime ? 0 : ($endTime - $nowUnixTime) / 86400);
            # 可用商品数
//            $item['product_count'] = PlatformCouponProduct::getInstance()->where([
//                ['platform_coupon_id', '=', $item['platform_coupon_id']],
//                ['use_type', '=', $item['use_type']]
//            ])->count('id') ?? 0;
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
                    if ($item['status'] == 0) {
                        return '待发布';
                    }
                    $item['status'] = 3;
                    return '活动未开始';
                }
                if ($endTime < $nowUnixTime || $item['status'] == 2) {
                    $item['status'] = 4;
                    return '已结束';
                }
                if ($item['is_cancel'] == 1) {
                    return '已取消';
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

            $item['use_time'] = "领券{$item['effective_day_number']}天内有效";
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
                $platformCoupon->setAttr('wechat_business_number', $res['params']['belong_merchant']); # 填入生成优惠券的商户号
                $platformCoupon->setAttr('stock_id', $res['result']['stock_id']); # 填入批次号
                $platformCoupon->setAttr('release_time', time());
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
        Queue::push(CancelPlatformCouponJob::class, [
            'platform_coupon_id' => $coupon->getAttr('platform_coupon_id'),
        ]);
    }

    /**
     * 用户加入黑名单时调用，使用户所用未使用优惠券失效
     *
     * @param $uid
     * @return void
     */
    public function cancelPlatformUserCoupon($uid)
    {
        Queue::push(CanceUserCouponJob::class, [
            'user_id' => $uid,
        ]);
    }

    /**
     * 获取状态个数
     *
     * @throws null
     * @return array
     */
    public function getStatusCount(): array
    {
        $modelFn = fn ($where = [], ?callable $callback = null) => PlatformCoupon::getInstance()->where('is_del', 0)
            ->where($where)
            ->when(is_callable($callback), $callback)
            ->count('platform_coupon_id');

        $nowDate = date("Y-m-d H:i:s");

        return [
            'all' => $modelFn(),
            'wait_to_released' => $modelFn([ # 待发布
                ['status', '=', 0],['receive_end_time', '>', $nowDate],['is_cancel', '=', 0]
            ]),
            'has_not_started' => $modelFn([ # 未开始
                ['status', '=', 1],['receive_start_time', '>', $nowDate],['is_cancel', '=', 0]
            ]),
            'in_progress' => $modelFn([ # 进行中
                ['status', '=', 1],['receive_start_time', '<', $nowDate],['receive_end_time', '>', $nowDate],['is_cancel', '=', 0]
            ]),
            'over' => $modelFn(function (BaseQuery $query) use($nowDate) {
                $query->where([
                    ['status', '=', 1],['receive_end_time', '<', $nowDate]
                ])->whereOr([
                    ['status', '=', 2]
                ]);
            }),
            'cancel' => $modelFn([ # 已取消
                ['is_cancel', '=', 1]
            ])
        ];
    }

    /**
     * 获取编辑优惠券商品信息
     *
     * @param int $platformCouponId 平台优惠券id
     * @throws null
     * @return void
     */
    public function getEditCouponProductInfo(int $platformCouponId): array
    {
        $coupon = PlatformCoupon::getInstance()->where('platform_coupon_id', $platformCouponId)->find();

        if (!$coupon) return [];

        $nowUnixTime = date("Y-m-d H:i:s");

        $arr = [
            'platform_coupon_id' => $platformCouponId,
            'name' => $coupon->getAttr('coupon_name'),
            'stock_id' => $coupon->getAttr('stock_id'),
            "stock" => (function () use ($coupon) {
                $a = [];
                if ($coupon['is_limit'] == 1) {
                    $remain = $coupon['limit_number'] - $coupon['received'];
                    $remain = max($remain, 0);
                    $a[] = "总量:{$coupon['limit_number']}";
                    $a[] = "剩余:{$remain}";
                } else {
                    $a[] = '不限量';
                }
                return implode(" ", $a);
            })(),
            'status' => $coupon->getAttr('status'),
            'build_business_number' => $coupon->getAttr('wechat_business_number'),
            'receive_start_time' => $coupon->getAttr('receive_start_time'),
            'receive_end_time' => $coupon->getAttr('receive_end_time'),
        ];

        $arr['status_cn'] = (function () use (&$arr, $nowUnixTime, $coupon): string {
            $startTime = strtotime($coupon->getAttr('receive_start_time'));
            $endTime = strtotime($coupon->getAttr('receive_end_time'));
            if ($startTime > $nowUnixTime) {
                $arr['status'] = 3;
                return '活动未开始';
            }
            if ($endTime < $nowUnixTime) {
                $arr['status'] = 4;
                return '已结束';
            }
            return [
                '待发布',
                '进行中',
                '已失效'
            ][$arr['status']];
        })();

        # 声明优惠券领取表操作模型
        $platformCouponReceive = fn (int $platformCouponId) => PlatformCouponReceive::getInstance()
            ->where('platform_coupon_id', $platformCouponId);

        $arr['use_count'] = [
            'received' => $platformCouponReceive($platformCouponId)->count('id'),
            'used' => $platformCouponReceive($platformCouponId)->where('status', 1)->count('id'),
        ];

        $arr['use_time'] = "领券{$coupon->getAttr('effective_day_number')}天内有效";

        return $arr;
    }

    /**
     * 获取编辑优惠券商品列表
     *
     * @param int $platformCouponId
     * @param int $page
     * @param int $limit
     * @param array $where
     * @return void
     * @throws null
     */
    public function getEditCouponProductList(int $platformCouponId, int $page = 1, int $limit = 10, array $where = [],$order= 'product_id desc'): array
    {
        $orderArr = explode(" ",$order);

        if ($orderArr[0] == "product_id" || $orderArr[0] == "sales" || $orderArr[0] == "sort" || $orderArr[0] == 'price'){
            $order = 'a.'.$order;
        }

        $platformCouponModel = fn () => Product::getInstance()
            ->alias('a')
            ->field([
                'a.product_id',
                'a.store_name',
                'a.store_info',
                'a.keyword',
                'a.is_used',
                'a.mer_id',
                'a.sort',
                'a.image',
                'a.slider_image',
                'a.price',
                'a.sales',
                'c.mer_name',
                'c.real_name',
                'd.path'
            ])
            ->leftJoin('eb_platform_coupon_product b', 'a.product_id = b.product_id')
            ->leftJoin('eb_merchant c', 'a.mer_id = c.mer_id')
            ->leftJoin('eb_store_category d', 'a.cate_id = d.store_category_id')
            ->where('b.platform_coupon_id', $platformCouponId)
            ->when(!empty($where), function (BaseQuery $query) use ($where) {
                if (!empty($where['store_name'])) {
                    $query->whereLike('a.store_name', "%{$where['store_name']}%");
                }
                if (!empty($where['mer_id']) && $where['mer_id'] > 0) {
                    $query->where('a.mer_id', $where['mer_id']);
                }
                if (!empty($where['pid']) && $where['pid'] > 0) {
                    $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
                    $ids = array_merge($storeCategoryRepository->findChildrenId((int)$where['pid']), [(int)$where['pid']]);
                    if (count($ids)) $query->whereIn('a.cate_id', $ids);
                }
            });

        $platformCoupon = $platformCouponModel()->order($order)->page($page, $limit)
            ->select()
            ->toArray();

        /** @var CouponStocksDao $couponStockDao */
        $couponStockDao = app()->make(CouponStocksDao::class);

        foreach ($platformCoupon as &$item) {
            # 获取分类路径
            $pathArr = array_merge(array_filter(explode('/', $item['path'])), []);
            $pathInfo = array_column(StoreCategory::getInstance()->field(['store_category_id', 'cate_name'])->whereIn('store_category_id', $pathArr)->select()->toArray(), null, 'store_category_id');
            $item['path_cn'] = (function () use ($pathArr,$pathInfo): string {
                $a = [];
                foreach ($pathArr as $v) $a[] = $pathInfo[$v]['cate_name'];
                return implode('/', $a);
            })();
            $item['couponList'] = $couponStockDao->getCouponListFromProductId($item['product_id']);
        }

        return [
            'list' => $platformCoupon,
            'count' => $platformCouponModel()->count('a.product_id')
        ];
    }

    /**
     * <a href="https://www.apifox.cn/link/project/2413062/apis/api-76534942">范围计数</a>
     *
     * @param array $searchStatus 1 进行中 3 未开始
     * @param int $discount_num
     * @return array
     */
    public function scopeCount(array $searchStatus, int $discount_num = 0): array
    {
        foreach ($searchStatus as $item) if (!in_array($item, [0,1,2,3,4])) throw new ValidateException('参数错误 不支持的状态');

        $arr = [];

        $nowDate = date("Y-m-d H:i:s");

        $searchFn = function (int $type) use ($nowDate, $discount_num): array {
            $func = fn (int $position) => PlatformCoupon::getInstance()
                ->alias('a')
                ->leftJoin('eb_platform_coupon_position b', 'a.platform_coupon_id = b.platform_coupon_id')
                ->when($type == 0, fn (BaseQuery $query) => $query->where([['a.status', '=', 0],['a.receive_end_time', '>', $nowDate]]))
                ->when($type == 1, fn (BaseQuery $query) => $query->where([['a.status', '=', 1],['a.receive_start_time', '<', $nowDate],['a.receive_end_time', '>', $nowDate],['a.is_cancel','=',0]]))
                ->when($type == 2, fn (BaseQuery $query) => $query->where([['a.status', '=', 2]]))
                ->when($type == 3, fn (BaseQuery $query) => $query->where([['a.status', '=', 1],['a.receive_start_time', '>', $nowDate],['a.is_cancel','=',0]]))
                ->when($type == 4, fn (BaseQuery $query) => $query->where([['a.status', '=', 1],['a.receive_end_time', '<', $nowDate]]))
                ->when($discount_num > 0, function (BaseQuery $query) use ($discount_num) {
                    $query->where('a.discount_num', $discount_num);
                })
                ->where('b.position', '=', $position)
                ->where('a.is_del', '=', 0)
                ->count('a.platform_coupon_id');

            return [
                'home' => $func(1), # 首页
                'personal_center' => $func(2), # 个人中心
                'card_pack_recall' => $func(3), # 卡包召回
                'ad_reflow' => $func(4), # 广告回流
                'pay' => $func(5), # 支付/下单
            ];
        };

        foreach ($searchStatus as $item) {
            $arr[(function (int $type) {
                switch ($type) {
                    case 0:
                        return 'wait_release';
                    case 1:
                        return 'in_progress';
                    case 2:
                        return 'fail';
                    case 3:
                        return 'not_started';
                    case 4:
                        return 'over';
                    default:
                        return $type;
                }
            })($item)] = $searchFn($item);
        }

        return $arr;
    }

    /**
     * 获取一条平台优惠券信息
     *
     * @param int $platformCouponId
     * @throws null
     * @return array
     */
    public function getPlatformCouponOne(int $platformCouponId): array
    {
        $couponModel = PlatformCoupon::getInstance()
            ->alias('a')
            ->where('platform_coupon_id', $platformCouponId)
            ->find();
        if (!$couponModel) return [];
        # 获取使用范围
        $couponModel->setAttr('scope_id_arr', PlatformCouponUseScope::getInstance()->where([
            ['platform_coupon_id', '=', $platformCouponId],
            ['scope_type', '=', $couponModel->getAttr('use_type')]
        ])->column('scope_id'));

        if ($couponModel->getAttr('use_type') == 3) {
            $couponModel->setAttr('scope_id_arr', Merchant::getInstance()->field([
                'mer_id', 'mer_avatar', 'mer_name'
            ])->whereIn('mer_id', $couponModel->getAttr('scope_id_arr'))->select());
        }
        # 优惠券展示位置
        $couponModel->setAttr('coupon_position', PlatformCouponPosition::getInstance()
            ->where('platform_coupon_id', $platformCouponId)
            ->column('position'));

        return $couponModel->toArray();
    }

    /**
     * 修改平台优惠券状态
     *
     * @param int $platformCouponId
     * @param array $params
     * @throws null
     * @return void
     */
    public function platformCouponStatusUpdate(int $platformCouponId, array $params)
    {
        unset($params['id']);
        foreach ($params as $k => $v) if (!in_array($k, ['is_cancel', 'is_del']) || !in_array($v, [0,1])) {
            throw new ValidateException('不支持的参数 或 值错误');
        }
        /** @var PlatformCoupon $platformCoupon */
        $platformCoupon = PlatformCoupon::getInstance()->where('platform_coupon_id', $platformCouponId)->find();

        $nowDate = date("Y-m-d H:i:s");

        if (!empty($params['is_cancel'])) {
            $platformCoupon->setAttr('is_cancel', $params['is_cancel']);
            $platformCoupon->setAttr('cancel_time', $nowDate);
        }

        if (!empty($params['is_del'])) {
            $platformCoupon->setAttr('is_del', $params['is_del']);
            $platformCoupon->setAttr('del_time', $nowDate);
        }

        $platformCoupon->save();
    }

    /**
     * 领取日志
     *
     * @param int $page
     * @param int $limit
     * @param int|null $platformCouponId
     * @param array $search
     * @throws null
     * @return array
     */
    public function receiveLog(int $page = 1, int $limit = 10, ?int $platformCouponId = null, array $search = []): array
    {
        $modelFn = fn () => PlatformCouponReceive::getInstance()->alias('a')->field([
            'b.coupon_name',
            'a.stock_id',
            'a.coupon_code',
            'c.nickname',
            'a.discount_num',
            'b.threshold',
            'a.start_use_time',
            'a.end_use_time',
            'a.status',
            'b.status as couponStatus',
            'b.is_cancel',
            'b.is_del',
            'b.receive_end_time',
        ])->when(!empty($platformCouponId), function (BaseQuery $query) use ($platformCouponId) {
            $query->where([
                ['a.platform_coupon_id', '=', $platformCouponId],
            ]);
        })->leftJoin('eb_platform_coupon b', 'a.platform_coupon_id = b.platform_coupon_id')
            ->leftJoin('eb_user c', 'c.uid = a.user_id')
            ->when(!empty($search), function (BaseQuery $query) use (&$search) {
                # 使用状态
                if (isset($search['status']) && is_numeric($search['status'])) {
                    $query->where('a.status', $search['status']);
                }
                # 领取人
                if (!empty($search['nickname'])) {
                    $query->where('c.nickname', 'like', "%{$search['nickname']}%");
                }
                # 优惠券名称
                if (!empty($search['coupon_name'])) {
                    $query->where('b.coupon_name', 'like', "%{$search['coupon_name']}%");
                }
                # 批次号
                if (!empty($search['stock_id'])) {
                    $query->where('a.stock_id', 'like', "%{$search['stock_id']}%");
                }
                # 券id
                if (!empty($search['coupon_code'])) {
                    $query->where('a.coupon_code', 'like', "%{$search['coupon_code']}%");
                }
            });

        $unixTime = time();

        return [
            'list' => $modelFn()->page($page, $limit)->order('a.id', 'desc')->select()->each(function (PlatformCouponReceive $receive) use($unixTime) {
                $receive->setAttr('available', (function () use ($receive, $unixTime) {
                    # 0 不可用 1 可用
                    $status = $receive->getAttr('status') == 0 ? 1 : 0;
                    if (
                        $receive->getAttr('couponStatus') != 1
                        || $receive->getAttr('is_cancel') == 1
                        || $receive->getAttr('is_del')
                        || strtotime($receive->getAttr('receive_end_time')) < $unixTime
                    ) {
                        $status = 0;
                    }

                    return $status;
                })());
            }),
            'count' => $modelFn()->count('a.platform_coupon_id')
        ];
    }
}
