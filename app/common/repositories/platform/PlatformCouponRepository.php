<?php

namespace app\common\repositories\platform;

use app\common\dao\platform\PlatformCouponDao;
use app\common\repositories\BaseRepository;

/**
 * @property PlatformCouponDao $dao
 */
class PlatformCouponRepository extends BaseRepository
{
    public function __construct(PlatformCouponDao $dao)
    {
        $this->dao = $dao;
    }

    public function save() {

    }
}
