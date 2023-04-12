<?php

namespace app\common\repositories\platform;

use app\common\dao\platform\PlatformCouponUseScopeDao;
use app\common\repositories\BaseRepository;

/**
 * @property PlatformCouponUseScopeDao $dao
 */
class PlatformCouponUseScopeRepository extends BaseRepository
{
    public function __construct(PlatformCouponUseScopeDao $dao)
    {
        $this->dao = $dao;
    }
}
