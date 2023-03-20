<?php


namespace app\common\repositories\delivery;


use app\common\dao\delivery\DeliveryProfitSharingStatusDao;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\PlatformMerchantRepository;
use crmeb\services\WechatService;
use think\exception\ValidateException;

class DeliveryProfitSharingStatusRepository extends BaseRepository
{
    public function __construct(DeliveryProfitSharingStatusDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取已发货待分佣的订单
     *
     * @param $limit
     * @param $where
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/6 20:24
     */
    public function getDeliveryPrepareProfitSharingOrder($limit, $where)
    {
        return $this->dao->getDeliveryPrepareProfitSharingOrder($limit, $where);
    }

    /**
     * 获取已发货分佣中的订单
     *
     * @param $where
     * @param $limit
     *
     * @return
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 10:01
     */
    public function getDeliveryProfitSharingOrder($limit, $where)
    {
        return $this->dao->getDeliveryProfitSharingOrder($limit, $where);
    }

    /**
     * 获取发货后是否分帐成功过
     *
     * @param $orderId
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 14:17
     */
    public function getProfitSharingStatus($orderId)
    {
        return $this->dao->getProfitSharingStatus($orderId);
    }
    
     /**
     * 获取分账接受商户ID
     *
     * @param $merIds
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 11:46
     */
    public function getProfitSharingAcceptMchId($merIds)
    {
        // 获取分账接受平台商户
        $mchIds = systemConfig('commission_merchant');
        // 检查分账商户是否是收款商户
        $mchIds = array_diff($mchIds, $merIds);
        if (empty($mchIds)) {
            throw new ValidateException('没有找到符合条件的分账接收商户');
        }
        
        $mchIdKey = array_rand($mchIds);
        $mchId = $mchIds[$mchIdKey];
        if (empty($mchId)) {
            throw new ValidateException('没有找到符合条件的分账接收商户');
        }
        
        return $mchId;
    }
    
    
     /**
     * 获取商户名称
     *
     * @param $mchId
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 11:53
     */
    public function getMchName($mchId)
    {
        /**
         * @var PlatformMerchantRepository
         */
        $platformMerchant = app()->make(PlatformMerchantRepository::class);
        $data = $platformMerchant->queryOne(['merchant_id' => $mchId],'mer_name');
        $merName = $data ? ($data->toArray())['mer_name'] : '';
        if (empty($merName)) {
            throw new ValidateException('分账接收商户简称不存在');
        }
        
        return $merName;
    }
    
     /**
     * 添加分账接收方
     *
     * @param $make
     * @param $mchId
     * @param $merName
     *
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/3 19:09
     */
    public function addProfitSharingReceivers(WechatService $make, $mchId, $merName)
    {
        if (empty($merName)) {
            \think\facade\Log::error('发货后，发起分账：添加分账接收人，商户名称为空');
            throw new ValidateException('发货后，发起分账：添加分账接收人，商户名称为空');
        }
        $make->profitSharing()->profitSharingReceiversAdd([
            'type' => 'MERCHANT_ID',
            'account' => $mchId,
            'relation_type' => 'STORE',
            'name' => $this->getEncrypt($merName, $make),
        ]);
    }
    
     /**
     * 加密参数
     *
     * @param $str
     * @param $make
     *
     * @return string
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/3 17:22
     */
    private function getEncrypt($str, $make)
    {
        $cert = $make->certficates()->get();
        //$str是待加密字符串
        $public_key = $cert['certificates'];
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码 
            $sign = base64_encode($encrypted);
        } else {
            throw new ValidateException('encrypt failed');
        }

        return $sign;
    }

    /**
     * 获取未解冻的资金
     *
     * @param $limit
     * @param $where
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 14:11
     */
    public function getUnfreezeOrders($limit, $where)
    {
        return $this->dao->query($where)->whereIn('unfreeze_status', [
            DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_DEFAULT,
            DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL,

        ])->where('is_del', 0)->limit($limit)->select()->toArray();
    }

    /**
     * 查询处于解冻中的订单
     *
     * @param $limit
     * @param $where
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 15:02
     */
    public function getUnfreezeIngOrders($limit, $where)
    {
         return $this->dao->query($where)->limit($limit)->select()->toArray();
    }

    /**
     * 获取分账回退中的订单
     *
     * @param $limit
     * @param $where
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 18:31
     */
    public function profitSharingReturnIng($limit, $where)
    {
        return $this->dao->query($where)->limit($limit)->select()->toArray();
    }
}