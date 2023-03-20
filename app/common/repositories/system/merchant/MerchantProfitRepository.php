<?php
namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantProfitDao;
use app\common\dao\system\merchant\MerchantProfitRecordDao;
use app\common\model\system\merchant\MerchantProfitRecord;
use app\common\repositories\BaseRepository;

/**
 * Class MerchantProfitRepository
 * @package app\common\repositories\system\merchant
 * @day 2020-05-06
 * @mixin MerchantProfitDao
 */
class MerchantProfitRepository extends BaseRepository
{
    /**
     * @var MerchantProfitDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param MerchantProfitDao $dao
     */
    public function __construct(MerchantProfitDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @return mixed
     */
    public function getProfitSum(array $where = [])
    {
        return $this->dao->query($where)->sum('total_money');
    }
}