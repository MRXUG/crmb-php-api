<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\common\repositories\store\product;

use app\common\dao\store\product\ProductAttrValueDao;
use app\common\dao\store\product\ProductDao;
use think\db\exception\DbException;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductDao as dao;
use think\facade\Log;

/**
 * Class ProductRepository
 * @package app\common\repositories\store\product
 * @author xaboy
 * @mixin dao
 */
class ProductStockSetRepository extends BaseRepository
{

    /**
     * 初始库存
     */
    const DEFAULT_STOCK = '999999';
    protected $dao;
    /**
     * @var ProductAttrValueDao
     */
    private $attrValueDao;

    /**
     * ProductRepository constructor.
     * @param dao $dao
     * @param ProductAttrValueDao $attrValueDao
     */
    public function __construct(ProductDao $dao, ProductAttrValueDao $attrValueDao)
    {
        $this->dao = $dao;
        $this->attrValueDao = $attrValueDao;
    }

    /**
     * 自动恢复商品库存
     *
     * @throws DbException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/11 18:08
     */
    public function stockSet($productId = 0)
    {
        // 批量设置
        if ($productId == 0) {
            $limit = 500;
            $page = 0;
            while (true) {
                $page ++;
                Log::info('自动恢复商品库存，第'.$page.'波开始');
                $list = $this->dao->getAll($page, $limit, $productId);
                $count = count($list);
                Log::info('自动恢复商品库存，商品id：'.json_encode(array_column($list, 'product_id'), JSON_UNESCAPED_UNICODE));
                $this->dao->updates(array_column($list, 'product_id'), ['stock' => self::DEFAULT_STOCK]);
                Log::info('自动恢复商品库存，第'.$page.'波结束, 恢复了'.$count.'件商品');
                if ($count < $limit) {
                    Log::info('自动恢复商品库存完成');
                    break;
                }
            }
        } else {
            // 指定设置
            $this->dao->update($productId, ['stock' => self::DEFAULT_STOCK]);
        }

   }

    /**
     * 自动恢复商品属性库存
     *
     * @param $productId
     *
     * @throws DbException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/13 19:14
     */
    public function attrValueStockSet($productId = 0)
    {
        // 批量更新
        if ($productId == 0) {
            $limit = 100;
            $page = 0;
            while (true) {
                $page ++;
                Log::info('自动恢复商品属性库存，第'.$page.'波开始');
                $list = $this->attrValueDao->getAll($page, $limit, $productId);
                $count = count($list);
                Log::info('自动恢复商品属性库存，商品id：'.json_encode(array_column($list, 'product_id'), JSON_UNESCAPED_UNICODE));
                $this->attrValueDao->whereIn('product_id', array_column($list, 'product_id'))->update(['stock' => self::DEFAULT_STOCK]);
                Log::info('自动恢复商品属性库存，第'.$page.'波结束, 恢复了'.$count.'件商品');
                if ($count < $limit) {
                    Log::info('自动恢复商品属性库存完成');
                    break;
                }
            }
        } else {
            // 指定更新
            $this->attrValueDao->query([])
                ->where('product_id', $productId)->update(['stock' => self::DEFAULT_STOCK]);
        }

   }
}
