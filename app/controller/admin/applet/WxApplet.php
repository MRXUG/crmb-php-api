<?php

namespace app\controller\admin\applet;

use app\common\model\applet\WxAppletModel;
use app\common\repositories\applet\WxAppletRepository;
use app\common\repositories\applet\WxAppletSubjectRepository;
use app\common\repositories\store\product\ProductStockSetRepository;
use app\common\repositories\wechat\OpenPlatformRepository;
use app\validate\admin\AppletSubjectValidate;
use app\validate\admin\AppletValidate;
use crmeb\basic\BaseController;
use crmeb\jobs\WechatReleaseJob;
use crmeb\jobs\WechatRevertCodeReleaseJob;
use crmeb\jobs\WechatUndoCodeAuditJob;
use crmeb\services\ExcelService;
use EasyWeChat\Foundation\Application;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Queue;

class WxApplet extends BaseController
{
    protected $repository;
    /**
     * @var WxAppletSubjectRepository
     */
    private $subjectRepository;
    /**
     * @var OpenPlatformRepository
     */
    private $openPlatformRepository;

    public function __construct(App $app, WxAppletRepository $repository, WxAppletSubjectRepository $subjectRepository, OpenPlatformRepository $openPlatformRepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->subjectRepository = $subjectRepository;
        $this->openPlatformRepository = $openPlatformRepository;
    }


    /**
     * 新增小程序
     *
     * @param AppletValidate $validate
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 18:51
     */
    public function create(AppletValidate $validate)
    {
        try {
            $data = $this->request->params(['subject_id', 'name', 'original_id', 'original_appid','original_appsecret']);
            $applet = $this->repository->checkRepeat($data['original_appid']);
            if (!empty($applet)) {
                return app('json')->fail('此APPID的小程序已存在');
            }
            $data['health_status'] = WxAppletModel::APPLET_HIGH_RISK;
            $this->repository->create($validate, $data);

            return app('json')->success('添加成功');
        } catch (\Exception $e) {
            // 这是进行异常捕获
            return json($e->getMessage());
        }

    }

    /**
     * 编辑小程序
     *
     * @param $id
     * @param AppletValidate $validate
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 12:03
     */
    public function save($id, AppletValidate $validate)
    {
        $applet = $this->repository->show($id);
        if (empty($applet)) {
            return app('json')->fail('小程序不存在');
        }
        $data = $this->request->params(['subject_id', 'name', 'original_id', 'original_appid','original_appsecret']);
        $this->repository->update($id, $validate, $data);

        return app('json')->success('编辑成功');
    }

    /**
     * 小程序详情
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 14:05
     */
    public function detail($id)
    {
        return app('json')->success($this->repository->show($id));
    }

    /**
     * 删除小程序
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 14:07
     */
    public function delete($id)
    {
        try {
            $this->repository->delete($id);
        } catch (DataNotFoundException | ModelNotFoundException | DbException $e) {
            return app('json')->fail('删除失败'.json_encode($e->getMessage()));
        }
        return app('json')->success('删除成功');
    }

    /**
     * 获取小程序列表
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 21:02
     */
    public function list()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['name', 'health_status', 'order_by']);

        $name = !empty($params['name']) ? $params['name'] : '';
        $orderBy = !empty($params['order_by']) ? $params['order_by'] : '';
        $healthStatus = empty($params['health_status']) ? 0 : $params['health_status'];

        return app('json')->success($this->repository->getApiList($page, $limit, $name, $healthStatus, $orderBy));
    }

    /**
     * 添加小程序主体
     *
     * @param AppletSubjectValidate $validate
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/28 21:46
     */
    public function createSubject(AppletSubjectValidate $validate)
    {
        $data = $this->request->params(['subject']);
        $subject = $this->subjectRepository->checkRepeat($data['subject']);
        if (!empty($subject)) {
            return app('json')->fail('小程序主体已存在');
        }
        $this->subjectRepository->create($validate, $data);

        return app('json')->success('添加成功');
    }

    /**
     * 编辑小程序主体
     *
     * @param $id
     * @param AppletSubjectValidate $validate
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 14:14
     */
    public function saveSubject($id, AppletSubjectValidate $validate)
    {
        $subject = $this->subjectRepository->show($id);
        if (empty($subject)) {
            return app('json')->fail('小程序主体不存在');
        }
        $data = $this->request->params(['subject']);
        $this->subjectRepository->update($id, $validate, $data);

        return app('json')->success('编辑成功');
    }

    /**
     * 小程序详情
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 14:05
     */
    public function detailSubject($id)
    {
        return app('json')->success($this->subjectRepository->show($id));
    }

    /**
     * 删除小程序
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 14:07
     */
    public function deleteSubject($id)
    {
        $applet = $this->subjectRepository->getAppletBySubjectId($id);
        if (!empty($applet)) {
            return app('json')->fail('小程序主体被占用，不能删除');
        }
        return app('json')->success($this->subjectRepository->delete($id));
    }

    /**
     * 小程序主体列表
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/28 22:07
     */
    public function subjectList()
    {
        $data = $this->request->params(['subject']);
        $subject = !empty($data['subject']) ? $data['subject'] : '';

        return app('json')->success($this->subjectRepository->getSubjectList($subject));
    }

    /**
     * 小程序汇总信息导出
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 21:51
     */
    public function excel()
    {
        $params = $this->request->params(['name', 'health_status']);
        $name = !empty($params['name']) ? $params['name'] : '';
        $healthStatus = !empty($params['health_status']) ? $params['health_status'] : 0;
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->appletExport($name, $healthStatus, $page, $limit);

        return app('json')->success($data);
    }


    /**
     * 分配授权小程序-树状列表
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function tree()
    {
        $where = $this->request->params(['name']);
        [$page, $limit] = $this->getPage();

        $data = $this->repository->appletListBySubject($where['name'] ?? '', $page, $limit);

        return app('json')->success($data);
    }

    /**
     * 小程序授权
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException|GuzzleException
     * @author  wzq
     * @date    2023/3/1 14:51
     */
    public function authorization()
    {
        try {
            $data = $this->openPlatformRepository->authorization();
            return app('json')->success($data);
        }  catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 小程序提审
     * @return mixed
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/6 17:29
     */
    public function submitAudit()
    {
        $appIds = $this->request->param('app_ids');
        if(!$appIds){
            return app('json')->fail('app_ids不能为空');
        }

        try {
            $data = $this->openPlatformRepository->submitAuditFlow($appIds);
            return app('json')->success($data);
        }  catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 撤回代码审核
     * @return mixed
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/7 10:24
     */
    public function undoCodeAudit()
    {
        $appIds = $this->request->post('app_ids', []);
        if(!$appIds){
            return app('json')->fail('app_ids不能为空');
        }

        try {
            foreach($appIds as $appId){
                Queue::push(WechatUndoCodeAuditJob::class, $appId);
            }
            return app('json')->success([]);
        }  catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 发布已通过审核的小程序
     * @return mixed
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/7 10:24
     */
    public function release()
    {
        $appIds = $this->request->post('app_ids', []);
        if(!$appIds){
            return app('json')->fail('app_ids不能为空');
        }
        try {
            foreach($appIds as $appId){
                Queue::push(WechatReleaseJob::class, $appId);
            }
            return app('json')->success([]);
        }  catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 小程序版本回退
     * @return mixed
     * @throws GuzzleException
     * @author  wzq
     * @date    2023/3/7 10:24
     */
    public function revertCodeRelease()
    {
        $appIds = $this->request->post('app_ids', []);
        if(!$appIds){
            return app('json')->fail('app_ids不能为空');
        }

        try {
            foreach($appIds as $appId){
                Queue::push(WechatRevertCodeReleaseJob::class, $appId);
            }
            return app('json')->success([]);
        }  catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }


    /**
     * 随机获取一个健康可以小程序
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/16 10:57
     */
    public function getApplet()
    {
        return app('json')->success($this->repository->healthyApplet());
    }

    public function getAuditstatus(){
        $appId = $this->request->param("appId",'');
        $auditid = $this->request->param("auditid",'');
        $data = $this->openPlatformRepository->getAuditstatus($appId,$auditid);

    }
}