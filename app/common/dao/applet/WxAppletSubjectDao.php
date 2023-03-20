<?php
namespace app\common\dao\applet;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\applet\WxAppletSubjectModel;
use think\db\BaseQuery;
use think\db\Query;


class WxAppletSubjectDao extends BaseDao
{
    protected function getModel(): string
    {
        return WxAppletSubjectModel::class;
    }

    public function search(string $subject = '')
    {
        $query = ($this->getModel()::getDB())
            ->when(isset($subject), function (Query $query) use ($subject) {
                $query->where(function (Query $query) use ($subject){
                    $query->where('subject', 'like', '%' . $subject . '%');
                });
        })
            ->where('is_del', WxAppletModel::IS_DEL_NO);

        return $query->order('update_time DESC');
    }

    /**
     * 小程序列表-分配授权小程序
     *
     * @param array|null $where
     *
     * @return BaseQuery
     */
    public function appletList(?array $where)
    {
        $fields = 'id, subject, name, original_appid';
        $query = WxAppletModel::getDB()->field($fields);
        if (empty($where)) {
            $query->order('update_time DESC');
        } else {
            $query->where($where);
        }

        return $query;
    }

    public function checkRepeat($subject)
    {
        return ($this->getModel()::getDB())->where('subject', $subject)->find();
    }
}