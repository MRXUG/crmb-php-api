<?php

namespace app\common\repositories\store\shipping;

use app\common\model\store\CityArea;
use app\common\model\store\product\Product;
use app\common\model\store\shipping\PostageTemplateRuleModel;
use app\common\RedisKey;
use app\common\repositories\BaseRepository;
use app\common\model\store\shipping\PostageTemplateModel;
use think\db\Query;
use think\facade\Cache;
use think\facade\Db;

class PostageTemplateRepository extends BaseRepository
{

    /**
     * ShippingTemplateRepository constructor.
     * @param PostageTemplateModel $dao
     */
    public function __construct(PostageTemplateModel $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param int $merId
     * @param $id
     * @return bool
     * @throws \think\db\exception\DbException
     */
    public function merExists(int $merId,$id)
    {
        return  $this->dao->where('mer_id', $merId)->where($this->dao->getPk(), $id)->count() > 0;
    }

    public function getProductUse(int $merId ,int $id)
    {
        return Product::getDB()->where('mer_id', $merId)
            ->where('temp_id', $id) //使用老字段
            ->count() > 0;
    }

    /**
     * @param int $id
     * @return PostageTemplateModel|array|mixed|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function detail(int $id)
    {
        $result = $this->dao->where([$this->dao->getPk() => $id])
            ->with(['rules'])->find();

        return $result;

    }

    /**
     * @param int $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function list(int $merId,array $where, int $page, int $limit)
    {
        $query = $this->dao
            ->where(['mer_id' => $merId])
            ->where('template_id <> 0')
            ->when(isset($where['name']) && $where['name'] != '', function (Query $query) use ($where){
                $query->whereLike('name', "%{$where['name']}%");
            })
            ->when(isset($where['type']) && $where['type'], function (Query $query) use ($where){
                $query->whereLike('type', $where['type']);
            });
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->with(['rules'])->select();
        return compact('count', 'list');
    }


    public function getList(int $merId)
    {
        return $this->dao->where(['mer_id' => $merId])
            ->where('template_id <> 0')->field('template_id as shipping_template_id,name')->order('create_time DESC')->select();
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function update(int $id,array $data)
    {
        Db::transaction(function()use ($id,$data) {
            $templateData = [
                'mer_id' => $data['mer_id'],
                'name' => $data['name'],
                'type' => $data['type'],
            ];
            $this->dao->update($templateData, [$this->dao->getPk() => $id]);

            /** @var PostageTemplateRuleModel $roleModel */
            $roleModel = app()->make(PostageTemplateRuleModel::class)->getModel();
            $exitsRules = $roleModel->where([$this->dao->getPk() => $id])->column('id', 'id');
            $insert = [];
            foreach ($data['rules'] as $rule){
                $rule['mer_id'] = $data['mer_id'];
                $rule['area_name'] = '';
                if(!isset($rule['template_id'])){
                    $rule['template_id'] = $id;
                }
                if(isset($rule['id'])){
                    //update
                    unset($exitsRules[$rule['id']]);
                    $roleModel->update($rule, [$roleModel->getPk() => $rule['id']]);
                }else{
                    $insert[] = $rule;
                }
            }
            if(!empty($insert)){
                $roleModel->saveAll($insert);
            }
            if(!empty($exitsRules)){
                $roleModel->whereIn($roleModel->getPk(), array_values($exitsRules))->delete();
            }
        });
        $this->deleteCache($id);
    }

    /**
     * @param $id
     */
    public function delete($id)
    {
        $this->dao->where($this->dao->getPk(), $id)->delete();
        app()->make(PostageTemplateRuleModel::class)->where($this->dao->getPk(), $id)->delete();
        $this->deleteCache($id);
    }

    private function deleteCache($id){
        $cacheKey = RedisKey::POSTAGE_TEMPLATE_RULE . $id;
        Cache::delete($cacheKey);
    }

    /**
     * @param array $data
     */
    public function create(array $data)
    {
        Db::transaction(function()use ($data) {
            $templateData = [
                'mer_id' => $data['mer_id'],
                'name' => $data['name'],
                'type' => $data['type'],
            ];
            $template = $this->dao->create($templateData);
            $rules = [];
            /** @var PostageTemplateRuleModel $ruleModel */
            $ruleModel = app()->make(PostageTemplateRuleModel::class);
            foreach ($data['rules'] as $v){
                $rules[] = [
                    'template_id' => $template['template_id'],
                    'first_unit' => $v['first_unit'],
                    'first_amount' => $v['first_amount'],
                    'keep_unit' => $v['keep_unit'],
                    'keep_amount' => $v['keep_amount'],
                    'area_ids' => $v['area_ids'],
                    'area_name' => '',
                    'not_area_ids' => '',
                    'free_on' => $v['free_on'],
                    'free_unit' => $v['free_unit'],
                    'free_num' => $v['free_num'],
                    'mer_id' => $data['mer_id'],
                ];
            }
            $ruleModel->getModel()->saveAll($rules);
        });
    }

    public function notShipDetail($merId){
        /** @var PostageTemplateRuleModel $ruleModel */
        $ruleModel = app()->make(PostageTemplateRuleModel::class);
        $notAreaIds = $ruleModel->getModel()->where('template_id', 0)
            ->where('mer_id', $merId)->value('not_area_ids');
        if(is_null($notAreaIds)){
            $ruleModel->save([
                'template_id' => 0,
                'first_unit' => 0,
                'first_amount' => 0,
                'keep_unit' => 0,
                'keep_amount' => 0,
                'area_ids' => [],
                'area_name' => '',
                'not_area_ids' => '',
                'free_on' => 0,
                'free_unit' => 0,
                'free_num' => 0,
                'mer_id' => $merId,
            ]);
            return [];
        }
        return $notAreaIds;
    }

    public function notShipUpdate($merId, array $notAreaIds){
        /** @var PostageTemplateRuleModel $ruleModel */
        $ruleModel = app()->make(PostageTemplateRuleModel::class);
        $notAreaIds = implode(',', $notAreaIds);
        $ruleModel->getModel()->where('template_id', 0)
            ->where('mer_id', $merId)
            ->update(['not_area_ids' => $notAreaIds]);

        $cacheKey = RedisKey::POSTAGE_TEMPLATE_RULE_NOT . $merId;
        Cache::delete($cacheKey);

        return $notAreaIds;
    }

    public function allArea(){
        $cacheKey = RedisKey::ALL_AREA_TREE;
        if($allArea = Cache::get($cacheKey)){
            return json_decode($allArea, true);
        }
        $list = CityArea::getModel()->where('level in (1,2,3)')->field('id, parent_id, level, name, path,type')->select()->toArray();
        $allArea = formatCategory($list, 'id', 'parent_id', 'children');
        Cache::set($cacheKey, json_encode($allArea), 86400);
        return $allArea;
    }

}
