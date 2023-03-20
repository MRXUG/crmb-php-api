<?php

namespace app\common\repositories\applet;

use app\common\dao\applet\WxAppletDao;
use app\common\dao\applet\WxAppletSubjectDao;
use app\common\model\applet\WxAppletModel;
use app\common\repositories\BaseRepository;
use app\validate\admin\AppletSubjectValidate;
use app\validate\admin\AppletValidate;
use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Error;
use think\exception\ValidateException;
use think\response\Json;

class WxAppletSubjectRepository extends BaseRepository
{
    protected $dao;
    /**
     * @var WxAppletDao
     */
    private $appletDao;

    public function __construct(WxAppletSubjectDao $dao,WxAppletDao $appletDao)
    {
        $this->dao = $dao;
        $this->appletDao = $appletDao;
    }


    /**
     * 新增小程序主体
     *
     * @param AppletSubjectValidate $validate
     * @param $data
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:49
     */
    public function create(AppletSubjectValidate $validate, $data)
    {
        $this->checkParam($validate, $data);
        $data = [
            'subject' => $data['subject']
        ];
        $this->dao->create($data);
    }

    /**
     * 编辑小程序主体
     *
     * @param $id
     * @param AppletSubjectValidate $validate
     * @param $data
     *
     * @return Json|void
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:50
     */
    public function update($id, AppletSubjectValidate $validate, $data)
    {
        try {
            $this->checkParam($validate, $data);
            $applet = $this->dao->get($id);
            if ($applet) {
                $this->dao->update($id, $data);
            }
        } catch (Exception $e) {
            // 这是进行异常捕获
            return json($e->getMessage());
        }
    }

    /**
     * 获取小程序主体
     *
     * @param $id
     *
     * @return array|Json
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:50
     */
    public function show($id)
    {
        try {
            return $this->dao->get($id)->toArray();
        } catch (Exception $e) {
            // 这是进行异常捕获
            return json($e->getMessage());
        }
    }

    /**
     * 删除小程序主体
     *
     * @param $id
     *
     * @return Json|void
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:50
     */
    public function delete($id)
    {
        try {
            $this->dao->update($id, ['is_del' => WxAppletModel::DELETED_YES]);
        } catch (Exception $e) {
            // 这是进行异常捕获
            return json($e->getMessage());
        }
    }

    /**
     * 获取小程序主体列表
     *
     * @param $subject
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 21:02
     */
    public function getSubjectList($subject): array
    {
        $query = $this->dao->search($subject);

        return $query->select()->toArray();
    }


    public function checkParam(AppletSubjectValidate $validate, $data)
    {
        $validate->check($data);

        return $data;
    }

    public function checkRepeat($subject): array
    {
        $data = $this->dao->checkRepeat($subject);

        return $data ? $data->toArray() : [];
    }

    public function getAppletBySubjectId($id)
    {
        return $this->appletDao->getAppletBySubjectId($id);
    }
}