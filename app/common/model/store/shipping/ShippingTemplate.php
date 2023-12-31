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


namespace app\common\model\store\shipping;

use app\common\model\BaseModel;

class ShippingTemplate extends BaseModel
{

    protected $schema = [
        'appoint'              => 'tinyint', //开启指定包邮
        'create_time'          => 'timestamp', //添加时间
        'info'                 => 'varchar', //运费说明
        'is_default'           => 'tinyint', //默认模板
        'mer_id'               => 'int', //商户 id
        'name'                 => 'varchar', //模板名称
        'shipping_template_id' => 'int', //编号
        'sort'                 => 'int', //排序
        'type'                 => 'tinyint', //计费方式 0=数量 1=重量 2=体积
        'undelivery'           => 'tinyint', //开启指定区域不配送

    ];
    
    /**
     * Author:Qinii
     * Date: 2020/5/6
     * Time: 14:20
     * @return string
     */
    public static function tablePk(): string
    {
        return 'shipping_template_id';
    }


    /**
     * Author:Qinii
     * Date: 2020/5/6
     * Time: 14:20
     * @return string
     */
    public static function tableName(): string
    {
        return 'shipping_template';
    }

    /**
     * 包邮
     * @Author:Qinii
     * @Date: 2020/5/6
     * @Time: 18:00
     * @return \think\model\relation\HasMany
     */
    public function free()
    {
        return $this->hasMany(ShippingTemplateFree::class, 'temp_id', 'shipping_template_id');
    }

    /**
     * 配送
     * @Author:Qinii
     * @Date: 2020/5/6
     * @Time: 18:01
     * @return \think\model\relation\HasMany
     */
    public function region()
    {
        return $this->hasMany(ShippingTemplateRegion::class, 'temp_id', 'shipping_template_id');
    }

    /**
     * @return \think\model\relation\HasOne
     * @author xaboy
     * @day 2020/6/4
     */
    public function freeAddress()
    {
        return $this->hasOne(ShippingTemplateFree::class, 'temp_id', 'shipping_template_id');
    }

    /**
     * @return \think\model\relation\HasOne
     * @author xaboy
     * @day 2020/6/4
     */
    public function regionAddress()
    {
        return $this->hasOne(ShippingTemplateRegion::class, 'temp_id', 'shipping_template_id');
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/6
     * @Time: 18:01
     * @return \think\model\relation\HasOne
     */
    public function undelives()
    {
        return $this->hasOne(ShippingTemplateUndelivery::class, 'temp_id', 'shipping_template_id');
    }

    public function searchShippingTemplateIdAttr($query,$value)
    {
        $query->where('shipping_template_id',$value);
    }
}
