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


namespace app\common\dao\system\config;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\config\MerchantPayConf;
use app\common\model\system\config\SystemConfigValue;
use think\db\exception\DbException;

/**
 * Class SystemConfigValueDao
 * @package app\common\dao\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class MerchantPayConfigDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantPayConf::class;
    }



}
