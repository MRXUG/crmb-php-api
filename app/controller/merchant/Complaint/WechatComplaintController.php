<?php


namespace app\controller\merchant\Complaint;


use app\common\repositories\wechat\MerchantComplaintRepository;
use app\validate\merchant\WechatComplaint\WechatComplaintOrderListValidate;
use app\validate\merchant\WechatComplaint\WechatComplaintRefundValidate;
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
        $param = $this->request->param(['complaint_id', 'transaction_id', 'out_trade_no',
            'problem_type', 'complaint_state', 'timeout_type', 'begin_time', 'end_time', 'page', 'limit']);
        app()->make(WechatComplaintOrderListValidate::class)->check($param);
        $param['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->list($param));
    }

    public function detail($complaint_id){
        $mer_id = $this->request->merId();
        return app('json')->success($this->repository->detail($complaint_id, $mer_id));
    }

    public function statistics(){
        $mer_id = $this->request->merId();
        return app('json')->success($this->repository->statistics($mer_id));
    }

    public function response($complaint_id){
        $mer_id = $this->request->merId();
        $params = $this->request->params(['response_content', 'response_images']);
        app()->make(WechatComplaintResponseValidate::class)->check($params);
        //check complaint_id 是否存在
        if(!$this->repository->checkComplaintId($mer_id, $complaint_id )){
            return app('json')->fail('投诉单号不正确');
        }
        if(!$this->repository->checkMedia($param['response_images'] ?? [])){
            return app('json')->fail('图片id不正确');
        }
        return app('json')->success($this->repository->response($complaint_id, $mer_id, $params));
    }

    public function refund($complaint_id){
        $param = $this->request->param(['action', 'launch_refund_day', 'reject_reason',
            'reject_media_list', 'remark']);
        app()->make(WechatComplaintRefundValidate::class)->check($param);
        $param['mer_id'] = $this->request->merId();
        //check complaint_id 是否存在
        if(!$this->repository->checkComplaintId($param['mer_id'], $complaint_id )){
            return app('json')->fail('投诉单号不正确');
        }
        if(!$this->repository->checkMedia($param['reject_media_list'] ?? [])){
            return app('json')->fail('图片id不正确');
        }
        return app('json')->success($this->repository->refund($complaint_id, $param));

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
        $file = $this->request->file('file');
        if (!$file)
            return app('json')->fail('请上传图片');
        $file = is_array($file) ? $file[0] : $file;
        $mer_id = $this->request->merId();
        $admin_id =  $this->request->adminId();
        $userType = $this->request->userType();
        validate(["file|图片" => [
            'fileSize' => $this->repository->getMaxImageSize(),
            'fileExt' => 'jpg,jpeg,png,bmp',
            'fileMime' => 'image/jpeg,image/bmp,image/png,image/x-ms-bmp',
        ]])->check(['file' => $file]);
        return app('json')->success($this->repository->uploadImage($mer_id, $admin_id, $userType, $file));
    }

}