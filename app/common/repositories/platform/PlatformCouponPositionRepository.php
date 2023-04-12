<?php

namespace app\common\repositories\platform;

use app\common\dao\platform\PlatformCouponPositionDao;
use app\common\repositories\BaseRepository;

/**
 * @property PlatformCouponPositionDao $dao
 */
class PlatformCouponPositionRepository extends BaseRepository
{
    public function __construct(PlatformCouponPositionDao $dao)
    {
        $this->dao = $dao;
    }
}
