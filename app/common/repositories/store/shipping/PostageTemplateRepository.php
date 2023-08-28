<?php

namespace app\common\repositories\store\shipping;

use app\common\model\store\product\Product;
use app\common\model\store\shipping\PostageTemplateRuleModel;
use app\common\repositories\BaseRepository;
use app\common\model\store\shipping\PostageTemplateModel;
use think\db\Query;
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
            ->where('postage_template_id', $merId)
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
            $roleModel = app()->make(PostageTemplateRuleModel::class);
            $exitsRules = $roleModel->where([$this->dao->getPk() => $id])->column('id');
            $exitsRules = array_column($exitsRules, 'id', 'id');
            $insert = [];
            foreach ($data['rules'] as $rule){
                if(isset($rule['rule_id'])){
                    //update
                    $ruleId = $rule['rule_id'];
                    unset($rule['rule_id']);
                    unset($exitsRules[$ruleId]);
                    $roleModel->where([$roleModel->getPk() => $ruleId])->update($rule);
                }else{
                    $insert[] = $rule;
                }
            }
            if(!empty($insert)){
                $roleModel->insertAll($insert);
            }
            if(!empty($exitsRules)){
                $roleModel->whereIn($roleModel->getPk(), array_values($exitsRules))->delete();
            }
        });
    }

    /**
     * @param $id
     */
    public function delete($id)
    {
        $this->dao->where($this->dao->getPk(), $id)->delete();
        app()->make(PostageTemplateRuleModel::class)->where($this->dao->getPk(), $id)->delete();
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
            foreach ($data['rules'] as $v){
                $rules[] = [
                    'template_id' => $template['template_id'],
                    'first_unit' => $v['first_unit'],
                    'first_amount' => $v['first_amount'],
                    'keep_unit' => $v['keep_unit'],
                    'keep_amount' => $v['keep_amount'],
                    'area_ids' => $v['area_ids'],
                    'not_area_ids' => '',
                    'free_on' => $v['free_on'],
                    'free_unit' => $v['free_unit'],
                    'free_num' => $v['free_num'],
                    'mer_id' => $v['mer_id'],
                ];
                app()->make(PostageTemplateRuleModel::class)->insertAll($rules);
            }
        });
    }

}
