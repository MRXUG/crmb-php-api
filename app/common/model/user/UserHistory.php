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

namespace app\common\model\user;

use app\common\model\BaseModel;
use app\common\model\store\product\Product;
use app\common\model\store\product\Spu;

class UserHistory extends BaseModel
{

    protected $schema = [
        'user_history_id' => 'int', //
        'res_id'          => 'int', //历史记录对象的ID
        'res_type'        => 'int', //历史记录类型
        'uid'             => 'int', //
        'create_time'     => 'timestamp', //
        'update_time'     => 'int', //

    ];

    public static function tablePk(): ?string
    {
        return 'user_history_id';
    }

    public static function tableName(): string
    {
        return 'user_history';
    }

    public function getStopTimeAttr()
    {
        if (!$this->spu) {
            return '';
        }

        if ($this->spu->product_type == 1 && $this->spu->seckillActive) {
            $day     = date('Y-m-d', time());
            $_day    = strtotime($day);
            $end_day = strtotime($this->spu->seckillActive['end_day']);
            if ($end_day >= $_day) {
                return strtotime($day . $this->spu->seckillActive['end_time'] . ':00:00');
            }

            if ($end_day < strtotime($day)) {
                return strtotime(date('Y-m-d', $end_day) . $this->spu->seckillActive['end_time'] . ':00:00');
            }

        }
        return '';
    }

    public function spu()
    {
        return $this->hasOne(Product::class, 'product_id', 'res_id');
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchHistoryIdAttr($query, $value)
    {
        $query->where('history_id', $value);
    }
    public function searchHistoryIdsAttr($query, $value)
    {
        $query->where('history_id', 'in', $value);
    }
}
