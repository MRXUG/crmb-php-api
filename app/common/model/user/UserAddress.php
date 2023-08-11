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
use app\common\repositories\store\CityAreaRepository;

class UserAddress extends BaseModel
{

    protected $schema = [
        'address_id'  => 'mediumint', //用户地址id
        'city'        => 'varchar', //收货人所在市
        'city_id'     => 'int', //城市id
        'create_time' => 'timestamp', //添加时间
        'detail'      => 'varchar', //收货人详细地址
        'district'    => 'varchar', //收货人所在区
        'district_id' => 'int', //区域 id
        'is_default'  => 'tinyint', //是否默认
        'is_del'      => 'tinyint', //是否删除
        'latitude'    => 'varchar', //纬度
        'longitude'   => 'varchar', //经度
        'phone'       => 'varchar', //收货人电话
        'post_code'   => 'int', //邮编
        'province'    => 'varchar', //收货人所在省
        'province_id' => 'int', //省 id
        'real_name'   => 'varchar', //收货人姓名
        'street'      => 'varchar', //街/镇
        'street_id'   => 'int', //街镇 id
        'uid'         => 'int', //用户id

    ];

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'address_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'user_address';
    }

    public function getAreaAttr()
    {
        return app()->make(CityAreaRepository::class)->search([])->whereIn('id', [$this->province_id, $this->city_id, $this->district_id, $this->street_id])->order('id ASC')->select();
    }
}
