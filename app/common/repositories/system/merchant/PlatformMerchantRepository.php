<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/2 11:51
 */

namespace app\common\repositories\system\merchant;

use think\exception\ValidateException;
use app\common\model\system\merchant\PlatformMerchant;
use crmeb\jobs\CouponEntrustJob;
use think\facade\Db;
use think\Collection;
use FormBuilder\Form;
use think\facade\Queue;
use think\facade\Route;
use FormBuilder\Factory\Elm;
use think\db\exception\DbException;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\common\dao\system\config\SystemConfigValueDao;
use app\common\dao\system\merchant\PlatformMerchantDao;
use think\Model;
use app\common\repositories\system\config\ConfigValueRepository;

class PlatformMerchantRepository  extends BaseRepository
{
    /**
     * 开启分佣
     */
    const ENABLE_COMMISSION = 1;
    /**
     * @var SystemConfigValueDao
     */
    private $systemConfigValueDao;

    public function __construct(PlatformMerchantDao $dao,SystemConfigValueDao $systemConfigValueDao)
    {
        $this->dao = $dao;
        $this->systemConfigValueDao = $systemConfigValueDao;
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function lst($where = [], $field = '*'): Collection
    {
        $where['is_del'] = PlatformMerchant::DELETED_NO;
        return $this->dao->selectWhere($where, $field);
    }

    /**
     * 格式化平台商户信息
     *
     * @param $mchId
     *
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function formatMerchantByMchId($mchId = '')
    {
        $field = 'merchant_id, merchant_id pay_routine_mchid, `key` pay_routine_key, v3_key as pay_routine_v3_key,
            cert_path pay_routine_client_cert, key_path as pay_routine_client_key, serial_no pay_routine_serial_no';
        $where['is_del'] = PlatformMerchant::DELETED_NO;
        $merchantList = $this->dao->selectWhere($where, $field)->toArray();
        $merchantById = array_column($merchantList, null, 'merchant_id');

        foreach ($merchantById as &$item) {
            $item['site_url'] = systemConfig('site_url');
        }

        if (empty($mchId)) {
            return $merchantById;
        } else {
            return $merchantById[$mchId] ?? [];
        }
    }

    /**
     * @throws DbException
     */
    public function save($id, $data)
    {
        $this->dao->update($id, $data);

        // 批次委托、新商户会设置领券回调
        Queue::push(CouponEntrustJob::class, ['mch_id' => $data['merchant_id']]);
    }

    public function del($id, $data, $merchantId)
    {
        foreach ($data as $k=>$v)
        {
            $data[$k] = json_encode(array_values(array_diff($v, [$merchantId])));
        }

        Db::transaction(function () use ($id, $data) {
            $this->dao->delete($id);
            app()->make(ConfigValueRepository::class)->updateMerchant($data);
        });
    }

    public function create($data)
    {
        $this->dao->create($data);
        // 批次委托、新商户会设置领券回调
        Queue::push(CouponEntrustJob::class, ['mch_id' => $data['merchant_id']]);
    }

    /**
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws DbException
     */
    public function getWhere($id): array
    {
        return $this->dao->get($id)->toArray();
    }

    /**
     * 查询单条
     *
     * @param $where
     * @param string $field
     *
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/6 21:43
     */
    public function queryOne($where, $field = '*')
    {
        return $this->dao->getWhere($where, $field);
    }

    public function form($id = null, $formData = []): Form
    {
        $form = Elm::createForm(Route::buildUrl(
            is_null($id) ? 'systemCreatePlatformMerchant' : 'systemSavePlatformMerchant',
            is_null($id) ? [] : ['id' => $id])
            ->build());

        $form->setRule([
            Elm::input('merchant_id', '微信商户号', $formData['merchant_id'] ?? '')->required(),
            Elm::input('key', 'v2key', $formData['key'] ?? '')->required(),
            Elm::input('v3_key', 'v3key', $formData['v3_key'] ?? '')->required(),
            Elm::input('serial_no', '证书序列号', $formData['serial_no'] ?? '')->required(),
            Elm::input('mer_name', '商户号全称', $formData['mer_name'] ?? '')->required(),
            Elm::uploadFile('cert_path', 'apiclient_cert.pem',
                rtrim(systemConfig('site_url'), '/').Route::buildUrl('configUploadCert', [
                    'field' => 'file',
                ])->build())->headers(['X-Token' => request()->token()])->required(),
            Elm::uploadFile('key_path', 'apiclient_key.pem',
                rtrim(systemConfig('site_url'), '/').Route::buildUrl('configUploadCert', [
                    'field' => 'file',
                ])->build())->headers(['X-Token' => request()->token()])->required(),
        ]);

        return $form->formData($formData)->setTitle('设置商户号');
    }

    /**
     * 获取一条商户信息
     *
     * @param $id
     * @param array|null $where
     * @param string $fields
     *
     * @return array|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getOne($id, ?array $where = [], string $fields = '*')
    {
        intval($id) > 0 && $where['id'] = $id;
        return $this->dao->getWhere($where, $fields);
    }

    /**
     * 检测商户号唯一性
     *
     * @param $merchantId
     * @param  int  $id
     *
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/16 10:36
     */
    public function checkMerchantId($merchantId, int $id = 0)
    {
        // 平台商户
        $plMerId = $this->dao->getValue(['merchant_id' => $merchantId], 'id');
        // 非平台商户
        $merId = $this->systemConfigValueDao->getValue([
            'config_key' => "pay_routine_mchid",
            'value'      => json_encode($merchantId),
        ], 'mer_id');
        if (($plMerId && $plMerId != $id) || $merId && ($merId != $id) || ($plMerId && $merId)) {
            throw new ValidateException('该商户号已绑定过其它商户！');
        }

    }
}