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


namespace app\common\dao\system\merchant;


use app\common\dao\BaseDao;
use app\common\model\system\merchant\MerchantAdmin;
use app\common\model\system\merchant\MerchantAdminRelationModel;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Model;

/**
 * Class MerchantAdminDao
 * @package app\common\dao\system\merchant
 * @author xaboy
 * @day 2020-04-17
 */
class MerchantAdminDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-04-16
     */
    protected function getModel(): string
    {
        return MerchantAdmin::class;
    }

    const ALL_ADMIN_INFO_FIELD = 'account, real_name, phone,mar.*';

    /**
     * @param int $merId
     * @param array $where
     * @param int|null $level
     * @return BaseQuery
     * @author xaboy
     * @day 2020-04-18
     */
    public function search(int $merId, array $where = [], ?int $level = null)
    {
        $query = MerchantAdmin::getDB()
            ->alias('a')
            ->leftJoin('merchant_admin_relation mar', 'mar.merchant_admin_id = a.merchant_admin_id and mar.is_del = 0 and mar.mer_id = '.$merId)
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'mar.create_time');
            })
            ->when(!is_null($level), function ($query) use ($level) {
                $query->where('mar.level', $level);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('a.real_name|a.account', '%' . $where['keyword'] . '%');
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('mar.status', intval($where['status']));
            });

        return $query;
    }

    /**
     * @param int $merId
     * @return string
     * @author xaboy
     * @day 2020-04-16
     */
    public function merIdByAccount(int $merId): string
    {
        return MerchantAdmin::getDB()
            ->alias('a')
            ->join('merchant_admin_relation mar', 'mar.merchant_admin_id = a.merchant_admin_id and mar.is_del = 0 and mar.mer_id = '.$merId)
            ->where('mar.level', 0)
            ->value('account');
    }

    /**
     * @param int $merId
     * @return MerchantAdmin|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/7/7
     */
    public function merIdByAdmin(int $merId)
    {
        return MerchantAdmin::getInstance()
            ->alias('a')
            ->leftJoin('merchant_admin_relation mar', 'mar.merchant_admin_id = a.merchant_admin_id and mar.is_del = 0 and mar.mer_id = '.$merId)
            ->where('mar.level', 0)
            ->where('mar.mer_id', $merId)
            ->field(self::ALL_ADMIN_INFO_FIELD)
            ->find();
    }

    /**
     * @param string $account
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-20
     */
    public function accountByTopAdmin(string $account)
    {
        return MerchantAdmin::getInstance()
            ->alias('a')
            ->join('merchant_admin_relation mar', 'mar.merchant_admin_id = a.merchant_admin_id and mar.is_del = 0')
            ->where('a.account', $account)
            ->where('a.status', 1)
            ->field(self::ALL_ADMIN_INFO_FIELD)
            ->order('mar.last_time desc')
            ->find();
    }

    /**
     * @param string $account
     * @return mixed
     * @author xaboy
     * @day 2020-04-20
     */
    public function accountByMerchantId(string $account)
    {
        return MerchantAdmin::getInstance()->where('account', $account)->value('mer_id');
    }


    /**
     * @param int $id
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-17
     */
    public function get( $id)
    {
        return MerchantAdmin::getInstance()->where('is_del', 0)->find($id);
    }

    /**
     * @param int $id
     * @param int $merId
     * @param int|null $level
     * @return bool
     * @author xaboy
     * @day 2020-04-18
     */
    public function exists(int $id, int $merId = 0, ?int $level = null)
    {
        $query = MerchantAdmin::getDB()
            ->alias('a')
            ->join('merchant_admin_relation mar', 'mar.merchant_admin_id = a.merchant_admin_id and mar.is_del = 0 and mar.mer_id = '.$merId)
            ->where('mar.is_del', 0)
            ->where('a.merchant_admin_id', $id)
            ->where('mar.mer_id', $merId)
            ->when(!is_null($level), function ($query) use ($level){
                $query->where('mar.level', $level);
            });
        return $query->count() > 0;
    }

    /**
     * @param int $merId
     * @param $field
     * @param $value
     * @param int|null $except
     * @return bool
     * @author xaboy
     * @day 2020-04-18
     */
    public function merFieldExists(int $merId, $field, $value, ?int $except = null): bool
    {
        $query = MerchantAdmin::getDB()->where($field, $value)->where('mer_id', $merId);
        if (!is_null($except)) $query->where($this->getPk(), '<>', $except);
        return $query->count() > 0;
    }

    public function accountExists(int $merId, $account, $except){
        $query = MerchantAdmin::getDB()
            ->alias('a')
            ->join('merchant_admin_relation mar', 'mar.merchant_admin_id = a.merchant_admin_id and mar.is_del = 0 and mar.mer_id = '.$merId)
            ->where('a.account', $account)
            ->where('mar.mer_id', $merId)
            ->when(!is_null($except), function ($query) use ($except){
                $query->where('mar.merchant_admin_id', '<>', $except);
            });
        return $query->count() > 0;
    }

    public function create($data){
        $merAdmin = [
            'account' => $data['account'],
            'phone' => $data['phone'],
            'pwd' => $data['pwd'],
            'real_name' => $data['real_name'],
            'mer_id' => $data['mer_id'],
        ];

        $merAdminRelation = [
            'roles' => $data['roles'],
            'mer_id' => $data['mer_id'],
            'level' => $data['level'],
        ];

        Db::transaction(function ($merAdmin) use ($merAdminRelation) {
            if($id = MerchantAdmin::getDB()->where('account', $merAdmin['account'])->value('merchant_admin_id')){
                $merAdminRelation['merchant_admin_id'] = $id;
            }else{
                $merAdminRelation['merchant_admin_id'] = $this->getModelObj()->insertGetId($merAdmin);
            }
            MerchantAdminRelationModel::getDB()->insert($merAdminRelation);
        });
    }

    /**
     * @param int $id
     * @return bool
     * @author xaboy
     * @day 2020-04-18
     */
    public function topExists(int $id)
    {
        $query = MerchantAdmin::getDB()->where($this->getPk(), $id)->where('is_del', 0)->where('level', 0);
        return $query->count() > 0;
    }

    /**
     * @param int $merId
     * @return mixed
     * @author xaboy
     * @day 2020-04-17
     */
    public function merchantIdByTopAdminId(int $merId)
    {
        return MerchantAdminRelationModel::getDB()->where('mer_id', $merId)->where('is_del', 0)->where('level', 0)->value('merchant_admin_id');
    }

    public function deleteMer($merId)
    {
        MerchantAdmin::getDB()->where('mer_id', $merId)->update(['account' => Db::raw('CONCAT(`account`,\'$del\')')]);
    }
}
