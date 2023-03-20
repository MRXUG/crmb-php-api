<?php
/**
 * @user: BEYOND 2023/3/1 16:56
 */

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use app\common\model\coupon\StockProduct;
use think\model\relation\HasMany;

/**
 * 商户关联企业信息表
 */
class RelatedBusiness extends BaseModel
{
    /**
     * 主键
     *
     * @return string|null
     */
    public static function tablePk(): ?string
    {
        return 'id';
    }

    /**
     * 表名
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'related_business';
    }

    public function relatedBusiness(): HasMany
    {
        return $this->hasMany(RelatedBusiness::class, 'mer_id', 'mer_id');
    }

}