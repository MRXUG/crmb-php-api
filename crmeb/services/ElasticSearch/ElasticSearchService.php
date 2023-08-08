<?php


namespace crmeb\services\ElasticSearch;


use Elasticsearch\ClientBuilder;
use think\facade\Log;
use Godruoyi\Snowflake\Sonyflake;

class ElasticSearchService
{
    private $client;
    public function __construct()
    {
        $this->client = ClientBuilder::create()->setHosts(
            [
                'host' => config('elasticsearch.host'),
                'hort' => config('elasticsearch.port'),
                'scheme' => config('elasticsearch.scheme'),
            ]
        )->setConnectionPool('Elasticsearch\ConnectionPool\SimpleConnectionPool')
            ->setRetries(5)
            ->setBasicAuthentication(config('elasticsearch.user'), config('elasticsearch.pass'))
            ->build();
    }

    /**
     * search
     * @param array $param
     * @param bool $returnId
     * @param bool $returnSort
     * @return array
     */
    final public function get(array $param, $returnId = true, $returnSort = false){
        Log::info(json_encode([
            'class' => __CLASS__,
            'func' => __FUNCTION__,
            'param' => $param,
            'returnId' => $returnId,
            'returnSort' => $returnSort
        ]));
        $data   = $this->client->search($param);
        $nList  = [];
        foreach ($data['hits']['hits'] ?? [] as $v) {
            if ($returnId) {
                $v['_source']['_id'] = $v['_id'];
            }
            if ($returnSort) {
                $v['_source']['sort'] = $v['sort'][0];
            }
            $nList[] = $v['_source'] ?? [];
        }
        return ['total' => $data['hits']['total']['value'], 'list' => $nList];
    }

    /**
     * @param array $param
     * @return array
     */
    final public function aggregationsSearch(array $param){
        Log::info(json_encode([
            'class' => __CLASS__,
            'func' => __FUNCTION__,
            'param' => $param,
        ]));
        $data   = $this->client->search($param);
        return $data['aggregations'];
    }

    /**
     * create one
     * @param string $index table name
     * @param array $param
     * @param null $id
     * @return array
     * @throws \Exception
     */
    final public function create(string $index, array $param, $id = null){
        Log::info(json_encode([
            'class' => __CLASS__,
            'func' => __FUNCTION__,
            'param' => $param,
        ]));
        if(!$id){
            $machineId = 1;
            /** @var Sonyflake $snowflake */
            $snowflake = app()->make(Sonyflake::class, [$machineId]);
            $id = $snowflake->id();
            $param['es_uuid'] = $id;
        }
        return $this->client->create(['index' => $index, 'body' => $param, 'id' => $id]);
    }

    /**
     * update one
     * @param string $index
     * @param array $param
     * @param $id
     */
    final public function update(string $index, array $param, $id){
        Log::info(json_encode([
            'class' => __CLASS__,
            'func' => __FUNCTION__,
            'param' => $param,
            'id' => $id,
        ]));
        $this->client->update(['index' => $index, 'body' => ['doc' => $param], 'id' => $id]);
    }

    /**
     * multiple index/update/delete operations, should limit document number.
     * @param string $index
     * @param array $param
     */
    final public function bulk(array $param, string $index = null){
//        Log::info(json_encode([
//            'class' => __CLASS__,
//            'func' => __FUNCTION__,
//            'param' => $param,
//            'index' => $index,
//        ]));
        $esParams = ['body' => $param];
        if($index){
            $esParams['index'] = $index;
        }
        $this->client->bulk($esParams);
    }



}
