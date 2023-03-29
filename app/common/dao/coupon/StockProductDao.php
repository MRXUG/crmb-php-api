<?php
/**
 * @user: BEYOND 2023/3/6 14:36
 */

namespace app\common\dao\coupon;

use app\common\dao\BaseDao;
use app\common\model\coupon\CouponStocks;
use app\common\model\coupon\StockProduct;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class StockProductDao extends BaseDao
{

    /**
     * @var CouponStocksDao
     */
    private $dao;
    /**
     * @var CouponStocksUserDao
     */
    private $userDao;

    public function __construct(CouponStocksDao $dao, CouponStocksUserDao $userDao)
    {
        $this->dao = $dao;
        $this->userDao = $userDao;
    }

    /**
     * @return string
     */
    protected function getModel(): string
    {
        return StockProduct::class;
    }

    public function updateWhere(array $where, array $data)
    {
        return StockProduct::where($where)->update($data);
    }

    /**
     * 商品优惠推优
     *
     * @param $productId
     * @param $merId
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 15:57
     */
    public function productBestOffer($productId, $merId, $isFirst = true)
    {
        // 全场券
        $where['scope'] = CouponStocks::SCOPE_YES;
        $where['mer_id'] = $merId;
        $where['status'] = CouponStocks::IN_PROGRESS;
        $where['type'] = CouponStocks::TYPE_DISCOUNT;
        $query = $this->dao->search(0, $where);
        $query->field('id');
        $fullCourt = $query->select()->toArray();


        // 指定券
        $specify = ($this->getModel()::getDB())->alias('P')
            ->join('CouponStocks G', 'P.stock_id = G.stock_id')
            ->where('G.is_del', 0)
            ->where('G.mer_id', $merId)
            ->where('G.start_at', "<",date("Y-m-d H:i:s"))
            ->where('G.end_at', ">",date("Y-m-d H:i:s"))
            ->where('G.status', CouponStocks::IN_PROGRESS)
            ->where('G.is_del', 0)
            ->field('G.id')
            ->where('P.product_id', $productId)
            ->where('G.type', CouponStocks::TYPE_DISCOUNT)
            ->select()->toArray();

        $bestOffer = $this->dao
            ->whereIn('id', array_column(array_merge($fullCourt, $specify), 'id'))
            ->order('discount_num DESC');

        $bestOffer = $isFirst ? $bestOffer->find(): $bestOffer->select();

        if ($bestOffer) {
            $bestOffer = $bestOffer->toArray();

            foreach ($bestOffer as &$item){
                if (isset($item["transaction_minimum"]) && isset($item["discount_num"]) && ($item["transaction_minimum"] == 0)){
                    $item["transaction_minimum"] = $item["discount_num"]+0.01;
                }
            }

            return $bestOffer;
        } else {
            return [];
        }
    }

    /**
     * 删除优惠券商品
     *
     * @param $where
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 20:40
     */
    public function delGoods($where)
    {
        ($this->getModel()::getDB())
            ->where($where)->update(['is_del' => 1]);
    }

    public function getStockIdInfo($stockId){
       return ($this->getModel()::getDB())->where("stock_id",$stockId)->find();
    }
}
