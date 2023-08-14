<?php

namespace app\controller\api\server;

use app\common\repositories\user\UserVisitLogRepository;
use app\validate\Elasticsearch\UserVisitLogValidate;
use crmeb\basic\BaseController;
use crmeb\jobs\ElasticSearch\UserVisitLogJob;
use think\App;
use think\facade\Queue;

class UserLogController extends BaseController
{
    protected $merId;
    protected $repository;
    protected $service_id;

    public function __construct(App $app, UserVisitLogRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function visit()
    {
        $params = $this->request->param();
        $params['ip'] = $this->request->ip();
        if(!method_exists($this->request, 'userInfo')){
            $params['uid'] = 0;
        }else{
            $params['uid'] = $this->request->userInfo()->uid;
        }
        $params['user_type'] = UserVisitLogValidate::$WxAppletUserType;

        app()->make(UserVisitLogValidate::class)->check($params);
        // use QUEUE
         Queue::push(UserVisitLogJob::class, $params);
//        $this->repository->create($params);
        return app('json')->success('ok');
    }
}