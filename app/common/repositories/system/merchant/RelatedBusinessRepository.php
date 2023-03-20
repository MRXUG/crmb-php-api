<?php

namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\RelatedBusinessDao;
use app\common\repositories\BaseRepository;

class RelatedBusinessRepository  extends BaseRepository
{
    /**
     * 开启分佣
     */
    const ENABLE_COMMISSION = 1;

    public function __construct(RelatedBusinessDao $dao)
    {
        $this->dao = $dao;
    }

    public function getAll($merId, $id = 0)
    {
        return $this->dao->relatedBusiness($merId, $id);
    }

    public function createRelatedBusiness($data)
    {
        $this->dao->create($data);
    }

    public function save($id, $data)
    {
        return $this->dao->update($id, $data);
    }

    public function del($id)
    {
        return $this->dao->delete($id);
    }

    public function uniqueName($name, $id = 0)
    {
        return $this->dao->query([])
            ->where('name', $name)
            ->when($id > 0, function ($query) use ($id) {
                $query->where('id',  '<>' ,$id);
            })
            ->find();
    }


}
