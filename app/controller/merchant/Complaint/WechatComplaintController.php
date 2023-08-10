<?php


namespace app\controller\merchant\Complaint;


use app\common\repositories\wechat\MerchantComplaintRepository;
use app\validate\merchant\WechatComplaint\WechatComplaintResponseValidate;
use crmeb\basic\BaseController;
use think\App;

class WechatComplaintController extends BaseController
{

    /**
     * @var MerchantComplaintRepository
     */
    private $repository;

    public function __construct(
        App $app,
        MerchantComplaintRepository $repository
    ) {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function list(){
        $mer_id = $this->request->merId();
        return app('json')->success($this->repository->list());
    }

    public function detail($id){
        $mer_id = $this->request->merId();
    }

    public function statistics(){
        $mer_id = $this->request->merId();
    }

    public function response($complaint_id){
        $mer_id = $this->request->merId();
        $params = $this->request->params(['response_content', 'response_images']);
        app()->make(WechatComplaintResponseValidate::class)->check($params);
        //check complaint_id 是否存在
        if(!$this->repository->checkComplaintId($mer_id, $complaint_id )){
            return app('json')->fail('投诉单号不正确');
        }
        return app('json')->success($this->repository->response($complaint_id, $mer_id, $params));
    }

    public function refund($id){
        $mer_id = $this->request->merId();

    }

    public function complete($complaint_id){
        $mer_id = $this->request->merId();
        //check complaint_id 是否存在
        if(!$this->repository->checkComplaintId($mer_id, $complaint_id)){
            return app('json')->fail('投诉单号不正确');
        }
        return app('json')->success($this->repository->complete($complaint_id, $mer_id));
    }

    public function uploadImage(){
        $mer_id = $this->request->merId();
    }

}