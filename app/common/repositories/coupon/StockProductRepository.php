<?php
/**
 * @user: BEYOND 2023/3/6 15:06
 */

namespace app\common\repositories\coupon;

use app\common\dao\coupon\StockProductDao ;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class StockProductRepository extends BaseRepository
{
    public function __construct(StockProductDao $stockProductDao)
    {
        $this->dao = $stockProductDao;
    }

    /**
     * 保存商家券关联的商品
     *
     * @param array $goodsList
     * @param $couponStocksId
     *
     * @return void
     */
    public function insertGoods(array $goodsList, $couponStocksId)
    {
        foreach ($goodsList as $productId) {
            $goodsData[] = [
                'coupon_stocks_id' => $couponStocksId,
                'product_id' => $productId,
                'create_time' => date('Y-m-d H:i:s')
            ];
        }

        $this->dao->insertAll($goodsData);
    }

    /**
     * 删除优惠券商品
     *
     * @param $couponStocksId
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 20:41
     */
    public function delGoods($couponStocksId)
    {
        $this->dao->delGoods(['coupon_stocks_id' => $couponStocksId]);
    }

    /**
     * 更新
     *
     * @param $where
     * @param $data
     *
     * @return \app\common\model\coupon\StockProduct
     */
    public function updateWhere($where, $data)
    {
        return $this->dao->updateWhere($where, $data);
    }

    /**
     * 商品推优
     *
     * @param $productId
     * @param $merId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 16:53
     */
    public function productBestOffer($productId, $merId, $isFirst = true,$price = 0, bool $returnAll = false): array
    {
        return $this->dao->productBestOffer($productId, $merId, $isFirst,$price, $returnAll);
    }

    public function existsWhere($where)
    {
        return $this->dao->existsWhere($where);
    }

    public function getWhere(array $where, string $field = '*', array $with = [])
    {
        return $this->dao->getWhere($where, $field, $with);
    }
}
