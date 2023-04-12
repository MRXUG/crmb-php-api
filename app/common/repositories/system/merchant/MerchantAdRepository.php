<?php
namespace app\common\repositories\system\merchant;

use app\common\repositories\wechat\OpenPlatformRepository;
use think\facade\Db;
use think\db\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\common\dao\system\merchant\MerchantAdDao;
use app\common\repositories\BaseRepository;

/**
 * Class MerchantAdRepository
 * @package app\common\repositories\system\merchant
 * @author xaboy
 * @day 2020-05-06
 * @mixin MerchantAdDao
 */
class MerchantAdRepository extends BaseRepository
{
    /**
     * @var MerchantAdDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param MerchantAdDao $dao
     */
    public function __construct(MerchantAdDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param  int  $id
     * @return bool
     */
    public function adExists(int $id) : bool
    {
        return $this->dao->existsWhere([$this->getPk() => $id]);
    }

    /**
     *  广告详情
     *
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\DataNotFoundException
     */
    public function getInfo($id)
    {
       $info =  $this->dao->getInfo($id);

       if ($info){
           if (isset($info["couponInfo"]["transaction_minimum"]) && isset($info["couponInfo"]["discount_num"]) && ($info["couponInfo"]["transaction_minimum"] == 0)){
               $info["couponInfo"]["transaction_minimum"] = $info["couponInfo"]["discount_num"]+0.01;
           }
           if(isset($info['deliveryMethod']) && $info['deliveryMethod']){
               $info['deliveryMethod'] = json_decode($info['deliveryMethod']);
           }

       }

       return $info;
    }

    /**
     *  广告列表
     *
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function getList($where, $page, $limit): array
    {
        $baseQuery = $this->dao->getSearch($where);
        $count = $baseQuery->count($this->dao->getPk());
        $list = $baseQuery->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 更新创建广告
     *
     * @param $id
     * @param $data
     * @param $coupon
     *
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 20:22
     */
    public function updateData($id, $data, $coupon)
    {
        if (isset($data['create_time']))  unset($data['create_time']);
        if (isset($data['update_time']))  unset($data['update_time']);

        if(isset($data['deliveryMethod']) && $data['deliveryMethod']){
            $data['deliveryMethod'] = json_encode($data['deliveryMethod'],true);
        }

        Db::transaction(function () use ($id, $data, $coupon) {
            if ($id) {
                $this->dao->update($id, $data);
                app()->make(MerchantAdCouponRepository::class)->dels(['ad_id' => $id]);
            } else {
                $id = $this->dao->create($data)->getLastInsID();
            }
            if ($data['reflow_coupons_switch']) {
                $this->createAdCouponRelation($id, $coupon);
            }
        });
    }

    /**
     * 新增券与广告的关系
     *
     * @param $id
     * @param $coupon
     *
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 20:21
     */
    public function createAdCouponRelation($id, $coupon)
    {
        $arr = [];

        foreach ($coupon as $k=>$v){
            $arr[] = [
                'ad_id'=>$id,
                'stock_id'=>$v['stock_id']
            ];
        }
        if (count($arr) > 0){
            app()->make(MerchantAdCouponRepository::class)->insertAll($arr);
        }
    }

    public function getDeliveryMethod($id,$page,$query,$env_version){
        $deliveryMethod = $this->dao->getDeliveryMethod($id);
        if (!$deliveryMethod) return [];

        $params = [
            'jump_wxa'=>[
                "path"=>$page,
                "query"=>$query,
                "env_version"=>$env_version,
            ],
            'is_expire'=>true,
            'expire_type'=>1,
            'expire_interval'=>1,
        ];
        //获取scheme码
        $openPlatform = app()->make(OpenPlatformRepository::class);
        $appid = 'wx3ed327fd1af68e86';
        $scheme = $openPlatform->getScheme($appid,$params);

       return [
            'goType'=>$deliveryMethod['jumpMethod'],
            'title'=>$deliveryMethod['landingPageTitle'],
            'btnTitle'=>$deliveryMethod['buttonCopy'],
            'btn_bg_Color'=>$deliveryMethod['backgroundColor'],
            'btn_text_Color'=>$deliveryMethod['buttonCopyColor'],
            'btnPosition'=>$deliveryMethod['landingPageButton'],
            'bgImg'=>$deliveryMethod['landingBackgroundImage'],
            'openLink'=>$scheme,
        ];

    }
}