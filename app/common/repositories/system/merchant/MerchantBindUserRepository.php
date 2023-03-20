<?php
namespace app\common\repositories\system\merchant;

use app\common\dao\system\merchant\MerchantBindUserDao;
use app\common\repositories\BaseRepository;
use app\common\model\system\merchant\MerchantBindUser;
use app\common\repositories\system\config\ConfigValueRepository;
use think\facade\Log;

/**
 * Class MerchantCategoryRepository
 * @package app\common\repositories\system\merchant
 * @author xaboy
 * @day 2020-05-06
 * @mixin MerchantBindUserDao
 */
class MerchantBindUserRepository extends BaseRepository
{
    /**
     * @var MerchantBindUserDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param MerchantBindUserDao $dao
     */
    public function __construct(MerchantBindUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 绑定商户和用户
     *
     * @param  int  $merId
     * @param  int  $wechatUserId
     * @param  int  $orderId
     * @return void
     */
    public function bindUserToMerchant(int $merId,int $wechatUserId,int $orderId):void
    {
        $isBindMer = $this->dao->existsWhere([
            'wechat_user_id' => $wechatUserId,
            'status'         => MerchantBindUser::STATUS_VALID
        ]);
        if ($isBindMer) {
            // 绑定了商户，不做处理
            Log::info( sprintf("'该用户已和其他商户绑定：商户id %s,用户id %s'",$merId,$wechatUserId));
            return;
        }
        /* @var $repo ConfigValueRepository*/
        $repo = app()->make(ConfigValueRepository::class);
        $bindDurationDay = $repo->get('profit_sharing_locking_duration',0);
        $bindDurationSec =  $bindDurationDay * 86400;
        $bindDurationSec = 1200;//todo-fw 2023/3/16 10:48: 仅供测试
        $this->dao->create([
            'mer_id'         => $merId,
            'wechat_user_id' => $wechatUserId,
            'order_id'       => $orderId,
            'expire_time'    => date('Y-m-d H:i:s', time() + $bindDurationSec)
        ]);
    }

    /**
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function removeBindingRelation(){
        $this->dao->setBindingRelationInvalid();
    }

    /**
     * @param $wechatUserId
     * @return mixed
     */
    public function getBindMerchantId($wechatUserId)
    {
        return $this->dao->query([
            'wechat_user_id'=> $wechatUserId,
            'status'=>MerchantBindUser::STATUS_VALID
        ])->value('mer_id');
    }
}