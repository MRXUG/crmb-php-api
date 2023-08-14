<?php

namespace crmeb\jobs;

use app\common\RedisKey;
use app\common\repositories\store\product\SpuRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Cache;
use think\facade\Log;
use function app;

class SyncProductTopJob implements JobInterface
{

    public function fire($job, $data)
    {
        try{
            $SpuRepository = app()->make(SpuRepository::class);
            $prefix = RedisKey::HOT_RANKING;
            $hot = systemConfig(['hot_ranking_switch','hot_ranking_lv']);
            if (!$hot['hot_ranking_switch']) return ;
    
            $where['mer_status'] = 1;
            $where['status'] = 1;
            $where['is_del'] = 0;
            $where['product_type'] = 0;
            $where['order'] = 'sales';
            $list = $SpuRepository->search($where)->setOption('field',[])->field('spu_id,cate_id,S.mer_id,S.image,S.price,S.product_type,P.product_id,P.sales,S.status,S.store_name,P.ot_price,P.cost')->select();
            Cache::store("redis")->handler()->set($prefix,json_encode($list,true));
        }catch (\Exception $e){
            Log::info('热卖排行统计:' . $e->getMessage());
        }
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }

    public function work()
    {

    }
}
