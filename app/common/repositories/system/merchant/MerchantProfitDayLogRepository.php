<?php
namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantProfitDao;
use app\common\dao\system\merchant\MerchantProfitDayLogDao;
use app\common\dao\system\merchant\MerchantProfitRecordDao;
use app\common\model\system\merchant\MerchantProfitRecord;
use app\common\repositories\BaseRepository;

class MerchantProfitDayLogRepository extends BaseRepository
{
    protected $dao;

    public function __construct(MerchantProfitDayLogDao $dao)
    {
        $this->dao = $dao;
    }
    public function getPagedListSimple(string $fields, array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($fields, $where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }
}