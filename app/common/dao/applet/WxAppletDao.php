<?php
namespace app\common\dao\applet;

use app\common\dao\BaseDao;
use app\common\model\applet\WxAppletModel;
use app\common\model\applet\WxAppletSubjectModel;
use app\common\model\BaseModel;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;


class WxAppletDao extends BaseDao
{
    /**
     * @return BaseModel
     * @author  wzq
     * @date    2023/3/7 15:52
     */
    protected function getModel(): string
    {
        return WxAppletModel::class;
    }

    public function search(string $name = '', int $healthStatus = 0, $orderBy = '', array $condition = [])
    {
        $query = ($this->getModel()::getDB())
            ->with(['subject', 'submit']);

        if (isset($name) && $name != ''){

            //查询主体ids
            $subjectIds = WxAppletSubjectModel::getDB()->where('subject', 'like', '%' . $name . '%')->column("id");

            $query->where(function ($query) use ($subjectIds,$name) {
                $query->where('subject_id', 'in', $subjectIds)->whereOr('name', 'like', '%' . $name . '%');

            });
        }
//            $query->when(isset($name), function (Query $query) use ($name) {
//                $query->where(function (Query $query) use ($name) {
//                    $query->where('name', 'like', '%' . $name . '%');
//                });
//            })
            $query->when($healthStatus > 0, function ($query) use ($healthStatus) {
                $query->where('health_status', $healthStatus);
            })
            ->when(!empty($condition), function ($query) use ($condition) {
                $query->where($condition);
            })
            ->where('is_del', WxAppletModel::IS_DEL_NO)
            ->when(!empty($orderBy), function ($query) use ($orderBy) {
                $query->order($orderBy);
            });

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

    public function getAppletBySubjectId($id)
    {
       return ($this->getModel()::getDB())->where('subject_id', $id)->get()->toArray();
    }

    public function checkRepeat($appId)
    {
        return ($this->getModel()::getDB())->where('original_appid', $appId)->find();
    }

    public function show($id)
    {
        return ($this->getModel()::getDB())->where('id', $id)->find();
    }

    /**
     * 获取健康小程序
     *
     * @return array
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/3 12:02
     */
    public function healthyApplet(): array
    {
        $healthApplet = ($this->getModel()::getDB())
            ->where('health_status', WxAppletModel::APPLET_HEALTHY)
            ->where('is_release', WxAppletModel::IS_RELEASE_YES)
            ->where('is_del', 0)
            ->select()->toArray();

        return !empty($healthApplet) ? $healthApplet[array_rand($healthApplet, 1)] : [];
    }

}
