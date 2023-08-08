<?php


namespace app\common\repositories\user;


use app\common\repositories\BaseRepository;
use app\validate\Elasticsearch\UserVisitLogValidate;
use crmeb\services\ElasticSearch\ElasticSearchService;

/**
 * Class UserVisitLogRepository
 * @package app\common\repositories\user
 */
class UserVisitLogRepository extends BaseRepository
{
    /**
     * UserRepository constructor.
     * @param ElasticSearchService $es
     */
    public function __construct(ElasticSearchService $es)
    {
        $this->es = $es;
    }

    public function create($params)
    {
        $this->es->create(UserVisitLogValidate::$tableIndexName,$params);
    }
}
