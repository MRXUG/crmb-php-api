<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/6 18:16
 */

namespace app\common\repositories\system\merchant;

use think\db\exception\DbException;
use app\common\repositories\BaseRepository;
use app\common\dao\system\merchant\MerchantAdCouponDao;

class MerchantAdCouponRepository extends BaseRepository
{
    /**
     * @var MerchantAdCouponDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param MerchantAdCouponDao $dao
     */
    public function __construct(MerchantAdCouponDao $dao)
    {
        $this->dao = $dao;
    }

    public function insertAll($data)
    {
        $this->dao->insertAll($data);
    }

    /**
     * @throws DbException
     */
    public function dels($where)
    {
        $this->dao->dels($where);
    }
}