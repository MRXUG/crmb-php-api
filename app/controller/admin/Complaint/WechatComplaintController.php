<?php


namespace app\controller\admin\Complaint;


use app\common\repositories\wechat\MerchantComplaintRepository;
use app\validate\merchant\WechatComplaint\WechatComplaintOrderListValidate;
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
        return app('json')->success($this->repository->list($param));
    }

    public function detail($complaint_id){
        $mer_id = $this->request->param('mer_id');
        if(!$mer_id){
            return app('json')->fail('缺少商户id');
        }
        return app('json')->success($this->repository->detail($complaint_id, $mer_id));
    }

    public function statistics(){
        return app('json')->success($this->repository->statistics());
    }

}