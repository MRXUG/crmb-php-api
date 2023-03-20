<?php
namespace app\common\dao\applet;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletSubmitAuditModel;
use app\common\model\BaseModel;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;


class WxAppletSubmitAuditDao extends BaseDao
{
    /**
     * @return BaseModel
     * @author  wzq
     * @date    2023/3/7 15:52
     */
    protected function getModel(): string
    {
        return WxAppletSubmitAuditModel::class;
    }

    /**
     * 覆盖查询
     * @param array $where
     * @return BaseModel|BaseQuery
     * @author  wzq
     * @date    2023/3/8 14:57
     */
    public function getSearch(array $where)
    {
        $query = ($this->getModel()::getDB());
        foreach ($where as $k => $v){
            if(is_array($v)){
                $query = $query->whereIn($k, $v);
            }else{
                $query = $query->where($k, $v);
            }
        }
        return $query->order('id', 'desc')->limit(1);
    }

    /**
     * 获取最后一条提审数据
     * @param $appId
     * @param string $field
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wzq
     * @date    2023/3/9 15:02
     */
    public function getLastSubmit($appId, string $field = '*'): array
    {
        $res = ($this->getModel()::getDB())
            ->where('original_appid', $appId)
            ->field($field)
            ->order('id', 'desc')
            ->find();

        return $res ? $res->toArray() : [];
    }
}