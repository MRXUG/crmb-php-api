<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\controller\merchant\system;


use app\common\repositories\store\MerchantTakeRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\merchant\RelatedBusinessRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\common\repositories\user\UserBillRepository;
use app\validate\merchant\MerchantTakeValidate;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\merchant\MerchantUpdateValidate;
use crmeb\jobs\ChangeMerchantStatusJob;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Queue;

/**
 * Class Merchant
 * @package app\controller\merchant\system
 * @author xaboy
 * @day 2020/6/25
 */
class Merchant extends BaseController
{
    /**
     * @var MerchantRepository
     */
    protected $repository;
    /**
     * @var ConfigValueRepository
     */
    private $configValueRepository;
    /**
     * @var RelatedBusinessRepository
     */
    private $relatedBusinessRepository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param MerchantRepository $repository
     */
    public function __construct(
        App $app,
        MerchantRepository $repository,
        ConfigValueRepository $configValueRepository,
        RelatedBusinessRepository $relatedBusinessRepository
    )
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->configValueRepository = $configValueRepository;
        $this->relatedBusinessRepository = $relatedBusinessRepository;
    }

    /**
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020/6/25
     */
    public function updateForm()
    {
        return app('json')->success(formToData($this->repository->merchantForm($this->request->merchant()->toArray())));
    }

    /**
     * @param MerchantUpdateValidate $validate
     * @return mixed
     * @author xaboy
     * @day 2020/6/25
     */
    public function update(MerchantUpdateValidate $validate, MerchantTakeValidate $takeValidate, MerchantTakeRepository $repository)
    {

        $type = $this->request->param('type',1);
        $merchant = $this->request->merchant();
        if ($type == 2) {
            $data = $this->request->params([
                'mer_info',
                'mer_certificate',
                'service_phone',
                'mer_avatar',
                'mer_banner',
                'mer_state',
                'mini_banner',
                'mer_keyword',
                'mer_address',
                'long',
                'lat',
                ['delivery_way',[2]],
            ]);
            $validate->check($data);
            $sys_bases_status = systemConfig('sys_bases_status') === '0' ? 0 : 1;
            if ($sys_bases_status && empty($data['mer_certificate']))
                return app('json')->fail('店铺资质不可为空');

            app()->make(ConfigValueRepository::class)->setFormData([
                'mer_certificate' => $data['mer_certificate']
            ], $this->request->merId());
            unset($data['mer_certificate']);

            foreach ($data['delivery_way'] as $datum) {
                if ($datum == 1) {
                    $takeData = $this->request->params(['mer_take_status', 'mer_take_name', 'mer_take_phone', 'mer_take_address', 'mer_take_location', 'mer_take_day', 'mer_take_time']);
                    $takeValidate->check($takeData);
                    $repository->set($this->request->merId(), $takeData);
                    break;
                }
            }
            $delivery_way = implode(',',$data['delivery_way']);
            if (count($data['delivery_way']) == 1 && $data['delivery_way'] != $merchant->delivery_way) {
                app()->make(ProductRepository::class)->getSearch([])
                    ->where('mer_id',$merchant->mer_id)
                    ->update(['delivery_way' => $delivery_way]);
            }

            $data['delivery_way'] = $delivery_way;

        } else {
            $data = $this->request->params(['mer_state']);
            if ($data['mer_state'] == 1 && !merchantConfig($merchant->mer_id, 'pay_routine_mchid')) {
                return app('json')->fail('请配置微信商户号');
            }

            if ($merchant->is_margin == 1 && $data['mer_state'] == 1)
                return app('json')->fail('开启店铺前请先支付保证金');

            if ($data['mer_state'] && !$merchant->sub_mchid && systemConfig('open_wx_combine'))
                return app('json')->fail('开启店铺前请先完成微信子商户入驻');
        }
        $merchant->save($data);

        Queue::push(ChangeMerchantStatusJob::class, $this->request->merId());
        return app('json')->success('修改成功');
    }


    /**
     * @return mixed
     * @author xaboy
     * @day 2020/7/21
     */
    public function info(MerchantTakeRepository $repository)
    {
        $merchant = $this->request->merchant();
        $append = ['merchantCategory', 'merchantType', 'mer_certificate'];
        if ($merchant->is_margin == -10)
            $append[] = 'refundMarginOrder';

        $data = $merchant->append($append)->hidden(['mark', 'reg_admin_id', 'sort'])->toArray();
        $delivery = $repository->get($this->request->merId()) + systemConfig(['tx_map_key']);
        $data = array_merge($data,$delivery);
        $data['sys_bases_status'] = systemConfig('sys_bases_status') === '0' ? 0 : 1;
        // 关联主体
        $data['related_business'] = $this->relatedBusinessRepository->getAll($merchant->mer_id)->toArray();
        // 微信支付商户号
        $data['pay_routine_mchid'] =  merchantConfig($merchant->mer_id, 'pay_routine_mchid');
        // 分佣设置
        $data['profit_sharing'] = $this->configValueRepository->getProfitSharingSetting();
        return app('json')->success($data);
    }

    /**
     * 添加关联店铺主体（弹窗）
     *
     * @param int $id
     * @return mixed
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/11 9:39
     */
    public function correlationForm(int $id)
    {
        return app('json')->success(formToData($this->repository->correlationForm($id)));
    }

    /**
     * 编辑关联主体
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/13 17:25
     */
    public function saveCorrelation($id)
    {
        $data = $this->request->params([
            'name',
            'business_license'
        ]);
        if ($this->relatedBusinessRepository->uniqueName($data['name'], $id)) {
            return app('json')->fail('保存失败，企业名称重复');
        }
        $this->relatedBusinessRepository->save($id, $data);

        return app('json')->success('编辑成功');
    }

    /**
     *  提交关联主体
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/11 9:40
     */
    public function createCorrelation()
    {
        $data = $this->request->params([
            'name',
            'business_license'
        ]);

        $data['mer_id'] = $this->request->merId();
        if ($this->relatedBusinessRepository->uniqueName($data['name'])) {
            return app('json')->fail('保存失败，企业名称重复');
        }
        $this->relatedBusinessRepository->createRelatedBusiness($data);

        return app('json')->success('添加成功');
    }

    /**
     * 删除关联主体
     *
     * @param $id
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/13 17:31
     */
    public function deleteCorrelation($id)
    {
        $this->relatedBusinessRepository->del($id);

        return app('json')->success('删除成功');
    }



    /**
     * 商户信息-基本信息
     *
     * @param MerchantTakeRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/8/1
     */
    public function takeInfo(MerchantTakeRepository $repository, MerchantRepository $merchantRepository)
    {
        $merId = $this->request->merId();
        // 商户详情

        return app('json')->success($repository->get($merId) + systemConfig(['tx_map_key']) + $merchantRepository->merchantInfo($merId));
    }

    /**
     * @param MerchantTakeValidate $validate
     * @param MerchantTakeRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/8/1
     */
    public function take(MerchantTakeValidate $validate, MerchantTakeRepository $repository)
    {
        $data = $this->request->params(['mer_take_status', 'mer_take_name', 'mer_take_phone', 'mer_take_address', 'mer_take_location', 'mer_take_day', 'mer_take_time']);
        $validate->check($data);
        $repository->set($this->request->merId(), $data);
        return app('json')->success('设置成功');
    }


    public function getMarginQrCode()
    {
        $data['pay_type'] = 1;
        $res = app()->make(ServeOrderRepository::class)->QrCode($this->request->merId(), 'margin', $data);
        return app('json')->success($res);
    }

    public function getMarginLst()
    {
        [$page, $limit] = $this->getPage();
        $where = [
            'mer_id' => $this->request->merId(),
            'category' => 'mer_margin'
        ];
        $data = app()->make(UserBillRepository::class)->getLst($where, $page, $limit);
        return app('json')->success($data);
    }


}
