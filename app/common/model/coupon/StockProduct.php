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
    protected $schema = [
        'coupon_stocks_id' => 'int', //批次表主键
        'create_time'      => 'timestamp', //
        'id'               => 'bigint', //
        'is_del'           => 'tinyint', //
        'product_id'       => 'int', //可用商品ID
        'stock_id'         => 'varchar', //商家券批次号

    ];
    

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
