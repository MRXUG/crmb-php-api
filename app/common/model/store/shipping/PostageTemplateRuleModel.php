<?php


namespace app\common\model\store\shipping;


use app\common\model\BaseModel;
use app\common\repositories\store\CityAreaRepository;

class PostageTemplateRuleModel extends BaseModel
{
    protected $schema = [
        'id' => 'int',//模版规则ID
        'template_id' => 'int',//模版ID 为0 代表设置的不配送区域
        'area_name' => 'varchar',//选择区域名称 北京，上海，广州
        'first_unit' => 'int',//首件N个
        'first_amount' => 'int',//首件金额 单位分
        'keep_unit' => 'int',//续件N个
        'keep_amount' => 'int',//续件金额 单位分
        'area_ids' => 'text',//配送地域ID
        'not_area_ids' => 'text',//不配送区域ID
        'free_on' => 'tinyint',//指定条件包邮 0 不指定 1 指定
        'free_unit' => 'tinyint',//指定条件包邮 1件，2元
        'free_num' => 'int',//指定条件数量 单位分
        'mer_id' => 'int',//商户 id
        'create_time' => 'timestamp',//添加时间
        'update_time' => 'timestamp',//

    ];

    const Free_Type_Amount = 2;
    const Free_Type_Num = 1;
    /**
     * @inheritDoc
     */
    public static function tablePk(): ?string
    {
        return 'id';
    }

    /**
     * @inheritDoc
     */
    public static function tableName(): string
    {
        return 'postage_template_rule';
    }

    public function setAreaIdsAttr($value)
    {
        $res = '';
        foreach ($value as $v){
            $res .= '/'.( implode('/',$v)).'/,';
        }
        return rtrim($res, ',');
    }

    public function getAreaIdsAttr($value)
    {
        $value = explode(',', $value);
        $res = [];
        foreach ($value as $v){
            $res[] = explode('/', trim($v, '/'));
        }
        return $res;
    }

    public function setAreaNameAttr($value, $data){
        $city_id = [];
        $areaIds = $data['area_ids'];
        foreach ($areaIds as $area){
            foreach ($area as $city){
                $city_id[] = $city;
            }
        }

        $areaMap = app()->make(CityAreaRepository::class)->search([])->where('id','in',$city_id)->column('id,name');
        $areaMap = array_column($areaMap, 'name', 'id');
        $result = '';
        foreach ($areaIds as $area){
            foreach ($area as $city){
                $result.= ($areaMap[$city] ?? '') . ',';
            }
        }
        $result = rtrim($result, ',');
        return strlen($result) > 255 ? mb_substr($result, 0, 252).'...' : $result;
    }

    public function getFirstAmountAttr($value){
        return bcdiv($value, 100, 2);
    }

    public function setFirstAmountAttr($value){
        return bcmul($value, 100, 0);
    }

    public function getKeepAmountAttr($value){
        return bcdiv($value, 100, 2);
    }

    public function setKeepAmountAttr($value){
        return bcmul($value, 100, 0);
    }

    public function getFreeNumAttr($value, $data){
        return $data['free_unit'] == self::Free_Type_Amount ? bcdiv($value, 100, 2) : $value;
    }

    public function setFreeNumAttr($value, $data){
        return $data['free_unit'] == self::Free_Type_Amount ? bcmul($value, 100, 0) : $value;
    }
}