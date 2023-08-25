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

namespace app\controller\admin\system\merchant;

use app\common\dao\system\config\MerchantPayConfigDao;
use app\common\dao\system\config\SystemConfigValueDao;
use app\common\dao\system\merchant\MerchantDao;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\merchant\PlatformMerchantRepository;
use app\validate\admin\MerchantValidate;
use crmeb\basic\BaseController;
use crmeb\jobs\ChangeMerchantStatusJob;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Queue;

/**
 * Class Merchant
 * @package app\controller\admin\system\merchant
 * @author xaboy
 * @day 2020-04-16
 */
class Merchant extends BaseController
{
    /**
     * @var MerchantRepository
     */
    protected $repository;
    /**
     * @var SystemConfigValueDao
     */
    private $systemConfigValueDao;

    /**
     * Merchant constructor.
     * @param App                  $app
     * @param MerchantRepository   $repository
     * @param SystemConfigValueDao $systemConfigValueDao
     */
    public function __construct(App $app, MerchantRepository $repository, SystemConfigValueDao $systemConfigValueDao)
    {
        parent::__construct($app);
        $this->repository           = $repository;
        $this->systemConfigValueDao = $systemConfigValueDao;
    }

    public function count()
    {
        $where = $this->request->params(['keyword', 'date', 'status', 'statusTag', 'is_trader', 'category_id', 'type_id']);
        return app('json')->success($this->repository->count($where));
    }

    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-16
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where          = $this->request->params(['keyword', 'date', 'status', 'statusTag', 'is_trader', 'category_id', 'type_id', 'mchid']);
        return app('json')->success($this->repository->lst($where, $page, $limit));
    }

    /**
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-04-16
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form()));
    }

    /**
     * @param MerchantValidate $validate
     * @param MerchantCategoryRepository $merchantCategoryRepository
     * @param MerchantAdminRepository $adminRepository
     * @return mixed
     * @author xaboy
     * @day 2020/7/2
     */
    public function create(MerchantValidate $validate)
    {
        $data = $this->checkParam($validate);
        $this->repository->createMerchant($data);
        return app('json')->success('添加成功');
    }

    /**
     * @param int $id
     * @return mixed
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-16
     */
    public function updateForm($id)
    {
        if (!$this->repository->exists($id)) {
            return app('json')->fail('数据不存在');
        }

        return app('json')->success(formToData($this->repository->updateForm($id)));
    }

    /**
     * @param int $id
     * @param MerchantValidate $validate
     * @param MerchantCategoryRepository $merchantCategoryRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-06
     */
    public function update($id, MerchantValidate $validate, MerchantCategoryRepository $merchantCategoryRepository)
    {
        $data = $this->checkParam($validate, true);
        if (!$this->repository->exists($id)) {
            return app('json')->fail('数据不存在');
        }

        if ($this->repository->fieldExists('mer_name', $data['mer_name'], $id)) {
            return app('json')->fail('商户名已存在');
        }

        if ($data['mer_phone'] && isPhone($data['mer_phone'])) {
            return app('json')->fail('请输入正确的手机号');
        }

        if (!$data['category_id'] || !$merchantCategoryRepository->exists($data['category_id'])) {
            return app('json')->fail('商户分类不存在');
        }

        unset($data['mer_account'], $data['mer_password']);
        $margin            = $this->repository->checkMargin($id, $data['type_id']);
        $data['margin']    = $margin['margin'];
        $data['is_margin'] = $margin['is_margin'];
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-04-17
     */
    public function delete($id)
    {
        if (!$merchant = $this->repository->get(intval($id))) {
            return app('json')->fail('数据不存在');
        }

        if ($merchant->status) {
            return app('json')->fail('请先关闭该商户');
        }

        $this->repository->delete($id);
        return app('json')->success('编辑成功');
    }

    /**
     * @param MerchantValidate $validate
     * @param bool $isUpdate
     * @return array
     * @author xaboy
     * @day 2020-04-17
     */
    public function checkParam(MerchantValidate $validate, $isUpdate = false)
    {
        $data = $this->request->params([['category_id', 0], ['type_id', 0], 'business_license', 'mer_name', 'commission_rate', 'real_name', 'mer_phone', 'mer_keyword', 'mer_address', 'mark', ['sort', 0], ['status', 0], ['is_audit', 0], ['is_best', 0], ['is_bro_goods', 0], ['is_bro_room', 0], ['is_trader', 0], 'sub_mchid']);
        if (!$isUpdate) {
            $data += $this->request->params(['mer_account', 'mer_password']);
        } else {
            $validate->isUpdate();
            unset($data['status']);
        }
        $validate->check($data);
        return $data;
    }

    /**
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-31
     */
    public function switchStatus($id)
    {
        $is_best = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->exists($id)) {
            return app('json')->fail('数据不存在');
        }

        $this->repository->update($id, compact('is_best'));
        return app('json')->success('修改成功');
    }

    /**
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-31
     */
    public function switchClose($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->exists($id)) {
            return app('json')->fail('数据不存在');
        }

        $this->repository->update($id, compact('status'));
        Queue::push(ChangeMerchantStatusJob::class, $id);
        return app('json')->success('修改成功');
    }

    /**
     * @param $id
     * @param MerchantAdminRepository $adminRepository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/7/7
     */
    public function login($id, MerchantAdminRepository $adminRepository)
    {
        if (!$this->repository->exists($id)) {
            return app('json')->fail('数据不存在');
        }

        $adminInfo = $adminRepository->merIdByAdmin($id);
        $tokenInfo = $adminRepository->createToken($adminInfo);
        $admin     = $adminInfo->toArray();
        $data      = [
            'token' => $tokenInfo['token'],
            'exp'   => $tokenInfo['out'],
            'admin' => $admin,
            'url'   => '',
        ];

        return app('json')->success($data);
    }

    /**
     * TODO 修改复制次数表单
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-08-06
     */
    public function changeCopyNumForm($id)
    {
        return app('json')->success(formToData($this->repository->copyForm($id)));
    }

    /**
     * TODO 修改复制次数
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-08-06
     */
    public function changeCopyNum($id)
    {
        $data = $this->request->params(['type', 'num']);
        $num  = $data['num'];
        if ($num <= 0) {
            return app('json')->fail('次数必须为正整数');
        }

        if ($data['type'] == 2) {
            $mer_num = $this->repository->getCopyNum($id);
            if (($mer_num - $num) < 0) {
                return app('json')->fail('剩余次数不足');
            }

            $num = '-' . $data['num'];
        }
        $arr = [
            'type'    => 'sys',
            'num'     => $num,
            'message' => '平台修改「' . $this->request->adminId() . '」',
        ];
        app()->make(ProductCopyRepository::class)->add($arr, $id);
        return app('json')->success('修改成功');
    }

    /**
     * TODO 清理删除的商户内容
     * @return \think\response\Json
     * @author Qinii
     * @day 5/15/21
     */
    public function clearRedundancy()
    {
        $this->repository->clearRedundancy();
        return app('json')->success('清除完成');
    }

    /**
     * 获取商户支付资料
     *
     * @param $id
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 21:04
     */
    public function getMerchantPayInfo($id)
    {
        $data = merchantConfig($id, [
            'pay_routine_client_cert',
            'pay_routine_client_key',
            'pay_routine_serial_no',
            'pay_routine_v3_key',
            'pay_routine_key',
            'pay_routine_mchid',
        ]);

        return app('json')->success(formToData($this->repository->infoFrom($id, $data)));
    }

    /**
     * 更新商户支付资料
     *
     * @param $id
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 21:05
     */
    public function saveMerchantPayInfo($id)
    {
        $params = $this->request->params([
            'pay_routine_client_cert',
            'pay_routine_client_key',
            'pay_routine_serial_no',
            'pay_routine_v3_key',
            'pay_routine_key',
            'pay_routine_mchid',
        ]);

        $this->validate($params, [
            'pay_routine_mchid|商户号'      => 'require',
            'pay_routine_key'            => 'require',
            'pay_routine_serial_no|序列号'  => 'require',
            'pay_routine_v3_key'         => 'require',
            'pay_routine_client_cert|密钥' => 'require',
            'pay_routine_client_key|证书'  => 'require',
        ]);
        $cert     = file_get_contents(app()->getRootPath() . 'resources/certs/' . $params['pay_routine_client_cert']);
        $cert_key = file_get_contents(app()->getRootPath() . 'resources/certs/' . $params['pay_routine_client_key']);
        $mer = (new MerchantDao())->get($id);

        $s = (new MerchantPayConfigDao())->createOrUpdate(['mer_id' => $id], [
            'mer_id'        => $id,
            'mch_id'        => $params['pay_routine_mchid'],
            'pemkey'        => $cert_key,
            'pemcert'       => $cert,
            'serial_no'     => $params['pay_routine_serial_no'],
            'api_secret'    => $params['pay_routine_key'],
            'mch_name'      => $mer->mer_name,
            'apiv3_secret'  => $params['pay_routine_v3_key'],
        ]);
        app()->make(PlatformMerchantRepository::class)->checkMerchantId($params['pay_routine_mchid'], $id);

        foreach ($params as $k => $v) {
            $data = [
                'config_key' => $k,
                'value'      => json_encode($v),
                'mer_id'     => $id,
            ];
            $where = [
                'config_key' => $k,
                'mer_id'     => $id,
            ];
            $this->systemConfigValueDao->createOrUpdate($where, $data);
        }
        return app('json')->success('保存成功');
    }
}
