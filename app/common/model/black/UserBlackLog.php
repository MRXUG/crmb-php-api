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


namespace app\common\model\black;


use app\common\model\BaseModel;

class UserBlackLog extends BaseModel{

    //变更形式
    protected $logtype = [
        1 => '系统判定',
        2 => '人工添加',
        3 => '用户主动'	  
    ];
    
    //操作类型
    protected $logoperate = [
      0 => '移除黑名单',
      1 => '加入黑名单'
    ];
    
    
    
    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'log_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-04-17
     */
    public static function tableName(): string
    {
        return 'user_black_log';
    }
    
    //操作类型
    public function getOperateAttr($value){
        return $this->logoperate[$value];
    }
    
    public function getTypeAttr($value){
        return $this->logtype[$value];
    }
    
    
}