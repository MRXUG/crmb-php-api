<?php


namespace app\common\repositories\delivery;


use app\common\model\delivery\DeliveryProfitSharingStatusPart;
use app\common\repositories\BaseRepository;

class DeliveryProfitSharingStatusPartRepository extends BaseRepository
{
    public function __construct(DeliveryProfitSharingStatusPart $dao)
    {
        $this->dao = $dao;
    }

}