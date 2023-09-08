<?php


namespace app\validate\merchant;

use app\common\model\store\shipping\PostageTemplateRuleModel;
use think\Validate;

class PostageTemplateValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name|模板名称' => 'require|max:32',
        'type|计费方式' => 'in:0,1,2',
        'rules|配送区域信息' => 'Array|require|min:1|rules',
    ];

    protected function rules($value,$rule,$data)
    {
        foreach ($value as $k => $v){
            if (empty($v['area_ids']) || !is_array($v['area_ids']))
                return '配送城市信息不能为空';
            foreach ($v['area_ids'] as $id){
                if(intval($id) != $id){
                    return '城市id格式不正确';
                }
            }
            if (!$this->filter($v['first_unit'], FILTER_VALIDATE_INT) || $v['first_unit'] <= 0)
                return '首件条件不能小0';
            if (!$this->filter($v['first_amount'], FILTER_VALIDATE_FLOAT) || $v['first_amount'] < 0)
                return '首件金额不能小于0';
            if (!$this->filter($v['keep_unit'], FILTER_VALIDATE_INT) || ($v['keep_unit'] < 0))
                return '续件必须为不小于零的整数';
            if (!$this->filter($v['keep_amount'], FILTER_VALIDATE_FLOAT)  ||
                ($v['keep_unit'] > 0 && $v['keep_amount'] < 0) )
                return '有续件续费，续件金额不能填0';
            if(!in_array($v['free_on'], [PostageTemplateRuleModel::STATUS_CLOSE, PostageTemplateRuleModel::STATUS_OPEN])){
                return '指定条件包邮参数有误';
            }
            if(!in_array($v['free_unit'], [PostageTemplateRuleModel::Free_Type_Num, PostageTemplateRuleModel::Free_Type_Amount])){ // 1件 2元
                return '指定条件包邮参数有误';
            }
            if($v['free_on'] == PostageTemplateRuleModel::STATUS_OPEN && $v['free_unit'] == PostageTemplateRuleModel::Free_Type_Num &&
                !$this->filter($v['free_num'], FILTER_VALIDATE_INT)){
                return '指定条件包邮件数有误';
            }
            if($v['free_on'] == PostageTemplateRuleModel::STATUS_OPEN && $v['free_unit'] == PostageTemplateRuleModel::Free_Type_Amount &&
                !$this->filter($v['free_num'], FILTER_VALIDATE_FLOAT)){
                return '指定条件包邮金额有误';
            }
        }
        return true;
    }
}
