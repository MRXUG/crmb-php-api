<?php
/**
 * @user: BEYOND 2023/3/3 11:22
 */

namespace app\common\model\coupon;

use app\common\model\BaseModel;
use app\common\model\store\product\Product;
use think\model\relation\HasOne;

class StockProduct extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'stock_goods';
    }

    public function stockDetail(): HasOne
    {
        return $this->hasOne(CouponStocks::class, 'stock_id', 'stock_id');
    }

    public function productDetail(): HasOne
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }
}