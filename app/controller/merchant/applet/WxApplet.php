<?php

namespace app\controller\merchant\applet;

use app\common\model\applet\WxAppletModel;
use app\common\repositories\applet\WxAppletRepository;
use app\common\repositories\applet\WxAppletSubjectRepository;
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


    public function __construct(App $app, WxAppletRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 获取小程序列表
     *
     * @return mixed
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

}