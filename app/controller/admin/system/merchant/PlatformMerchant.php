<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/2 11:52
 */

namespace app\controller\admin\system\merchant;

use think\App;
use crmeb\basic\BaseController;
use think\db\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\validate\admin\PlatformMerchantValidate;
use app\common\dao\system\merchant\PlatformMerchantDao;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\merchant\PlatformMerchantRepository;

class PlatformMerchant extends BaseController
{
    /**
     * @var PlatformMerchantRepository
     */
    private $repository;
    /**
     * @var ConfigValueRepository
     */
    private $configValue;

    public function __construct(App $app, PlatformMerchantRepository $repository, ConfigValueRepository $configValue)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->configValue = $configValue;
    }

    /**
     * 商户号列表
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 20:56
     */
    public function lst()
    {
        return app('json')->success($this->repository->lst());
    }

    /**
     * 创建商户支付资料
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 20:57
     */
    public function create()
    {
        $data = $this->request->params([
            'merchant_id',
            'key',
            'v3_key',
            'serial_no',
            'mer_name',
            'cert_path',
            'key_path',
        ]);
        $this->validate($data , [
            'merchant_id|商户号' => 'require',
            'key'       => 'require',
            'v3_key'    => 'require',
            'serial_no|序列号' => 'require',
            'mer_name|商户简称' => 'require',
            'cert_path|密钥' => 'require',
            'key_path|证书'  => 'require',
        ]);
        $this->repository->checkMerchantId($data['merchant_id']);

        $this->repository->create($data);
        return app('json')->success('保存成功');
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function form($id = null)
    {
        if ($id) {
            $res = $this->repository->getWhere($id);
        }
        return app('json')->success(formToData($this->repository->form($id, $res ?? [])));
    }

    /**
     * 编辑商户号
     *
     * @param $id
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 20:59
     */
    public function save($id)
    {
        $data = $this->request->params([
            'merchant_id',
            'key',
            'v3_key',
            'serial_no',
            'mer_name',
            'cert_path',
            'key_path',
        ]);
       $this->validate($data , [
            'merchant_id|商户号' => 'require',
            'key'       => 'require',
            'v3_key'    => 'require',
            'serial_no|序列号' => 'require',
            'mer_name|商户简称' => 'require',
            'cert_path|密钥' => 'require',
            'key_path|证书'  => 'require',
        ]);
       $this->repository->checkMerchantId($data['merchant_id'], $id);

        try {
            $this->repository->save($id, $data);
        } catch (DbException $e) {
            return app('json')->fail('修改失败');
        }
        return app('json')->success('修改成功');
    }

    /**
     * 获取商户用途
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 21:00
     */
    public function getPlatformMerchantConfig()
    {
        $data = systemConfig([
            'commission_merchant',
            'build_bonds_merchant',
            'issue_bonds_merchant',
        ]);
        return app('json')->success($data);
    }

    /**
     * 删除平台商户
     *
     * @param $id
     * @param $merchantId
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 21:00
     */
    public function del($id, $merchantId)
    {
        $data = systemConfig([
            'commission_merchant',
            'build_bonds_merchant',
            'issue_bonds_merchant',
        ]);

        $this->repository->del($id, $data, $merchantId);
        return app('json')->success('删除成功');
    }

    /**
     * 编辑商户用途
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 21:00
     */
    public function savePlatformMerchantConfig()
    {
        $data = $this->request->params([
            'commission_merchant',
            'build_bonds_merchant',
            'issue_bonds_merchant',
        ]);
       $this->configValue->updateMerchant($data);
        return app('json')->success('保存成功');
    }
}