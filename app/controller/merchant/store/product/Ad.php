<?php
namespace app\controller\merchant\store\product;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantAdRepository;

/**
 *
 * @user: zhoukang
 * @data: 2023/3/6 11:19
 */
class Ad extends BaseController
{

    /**
     * @var MerchantAdRepository
     */
    private $repository;

    public function __construct(App $app, MerchantAdRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 广告详情
     *
     * @param $id
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 10:23
     */
    public function detail($id)
    {
        if (!$this->repository->adExists($id))
            return app('json')->fail('数据不存在');

        $info = $this->repository->getInfo($id);
        if($info['deliveryMethod']){
            $info['deliveryMethod'] = json_decode($info['deliveryMethod']);
        }
        return app('json')->success($info);
    }

    /**
     * 广告列表
     *
     * @param $goodsId
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 10:23
     */
    public function lst($goodsId)
    {
        [$page, $limit] = $this->getPage();
        $where['goods_id'] = $goodsId;
        $where = [
            'goods_id' => $goodsId,
        ];
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 编辑广告
     *
     * @param $id
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 10:23
     */
    public function update($id)
    {
        $data = $this->request->post();
        $this->validates($data);
        $coupon = $data['coupons'];
        unset($data['coupons']);

        if (!$this->repository->adExists($id))
            return app('json')->fail('数据不存在');

        $this->repository->updateData($id, $data, $coupon);

        return app('json')->success('编辑成功');
    }

    /**
     * 参数验证
     *
     * @param $data
     *
     * @return array|string|true
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 10:24
     */
    public function validates($data)
    {
        return $this->validate($data, [
            'mer_id|商户id'                          => 'require',
            'goods_id|商品id'                          => 'require',
            'ad_link_name|广告连接名称'                  => 'require',
            'ad_channel_id|广告渠道'                   => 'require',
//            'ad_account_id|广告商id'                  => 'require',
            'marketing_page_switch|营销页开关'          => 'in:1,0',
            'landing_page_type|落地页类型'              => 'requireIf:marketing_page_switch,1',
            'page_type|页面配置'                       => 'requireIf:marketing_page_switch,1|in:1,2',
            'marketing_discount_amount|优惠金额'       => 'requireIf:marketing_page_switch,1',
            'discount_fission_switch|优惠裂变开关'       => 'require|in:1,0',
            'fission_amount|涨红包金额'                 => 'requireIf:discount_fission_switch,1',
//            'discount_image|优惠后商品图'                => 'require',
            'page_popover_switch|商详页弹窗开关'          => 'require|in:1,0',
//            'marketing_page_main_chart|营销页头图'      => 'require',
//            'marketing_page_goods_chart|营销页商品图'    => 'require',
//            'marketing_page_bottom_chart|营销页底图'    => 'require',
//            'marketing_page_popup_chart|营销页弹窗'     => 'require',
//            'marketing_page_backcolor|营销页背景色'      => 'require',
            'reflow_coupons_switch|回流优惠券开关'        => 'require|in:1,0',
//            'coupon_popup_chart|领券弹窗'              => 'requireIf:reflow_coupons_switch,1',
            'coupons'                                   => 'requireIf:reflow_coupons_switch,1|array',
            'consume_coupon_switch|券核销方式'          => 'in:1',
            'pay_failure_discount_switch|支付失败优惠开关' => 'in:1,0',
            'pay_failure_discount_amount|支付失败优惠金额' => 'requireIf:pay_failure_discount_switch,1',
        ]);
    }

    /**
     * 广告创建
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 10:24
     */
    public function create()
    {
        $data = input();
        $this->validates($data);
        $coupon = $data['coupons'];
        unset($data['coupons']);

        if (isset($data['ad_id']))  unset($data['ad_id']);

        $this->repository->updateData(null, $data, $coupon);
        return app('json')->success('保存成功');
    }

    /**
     * 设置回传比例
     *
     * @param $id
     *
     * @return mixed
     * @throws \think\db\exception\DbException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/8 10:24
     */
    public function proportion($id)
    {
        $proportion = input('proportion');
        if (!$this->repository->adExists($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['postback_proportion' => $proportion]);
        return app('json')->success('保存成功');
    }

    /**
     * 预览太阳码
     *
     * @return mixed
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/10 17:45
     */
    public function qrcode()
    {
        $appid = input('appid');
        $path = input('path');
        $data = input('data');
        $id = uniqid();
        $url = get_preview_code($id, $appid, $path, $data);

        return app('json')->success(['url' => $url]);
    }
}