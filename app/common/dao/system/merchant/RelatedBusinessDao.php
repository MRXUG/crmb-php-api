<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/2 11:49
 */

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\system\merchant\RelatedBusiness;
use think\db\Query;

class RelatedBusinessDao extends BaseDao
{

    protected function getModel(): string
    {
        return RelatedBusiness::class;
    }

    public function relatedBusiness($merId, $id = 0)
    {
        return $this->getModel()::getDB()->where('mer_id', $merId)
            ->when($id > 0, function ($query) use ($id) {
                $query->whereIn('id', $id);
            })
            ->select();
    }

}