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
namespace app\common\repositories\store\order;

use app\common\dao\store\order\StoreOrderDao;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\store\product\ProductGroupBuyingRepository;
use app\common\repositories\store\product\ProductPresellSkuRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\StoreDiscountRepository;
use app\common\repositories\store\shipping\ExpressRepository;
use app\common\repositories\store\StorePrinterRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\serve\ServeDumpRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserBrokerageRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\PayGiveCouponJob;
use crmeb\jobs\SendSmsJob;
use crmeb\jobs\UserBrokerageLevelJob;
use crmeb\services\CombinePayService;
use crmeb\services\CrmebServeServices;
use crmeb\services\ExpressService;
use crmeb\services\PayService;
use crmeb\services\printer\Printer;
use crmeb\services\QrcodeService;
use crmeb\services\SpreadsheetExcelService;
use crmeb\services\SwooleTaskService;
use Exception;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Route;
use think\Model;

/**
 * Class StoreOrderRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/6/9
 * @mixin StoreOrderDao
 */
class StoreOrderStatisticsRepository extends BaseRepository
{
    /**
     * 支付类型
     */
    const PAY_TYPE = ['balance', 'weixin', 'routine', 'h5', 'alipay', 'alipayQr', 'weixinQr'];

    /**
     * StoreOrderRepository constructor.
     * @param StoreOrderDao $dao
     */
    public function __construct(StoreOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取appid近30天内日均付款订单量
     *
     * @param $appid
     *
     * @return int|string|null
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/3 10:29
     */
    public function averageDailyPaymentByAppId($appid)
    {

        $payTimeStart = date('Y-m-d  H:i:s', strtotime('-30 day'));
        $payTimeEnd = date('Y-m-d H:i:s', time());
        $where = [
            'appid' => $appid,
            'pay_time_start' => $payTimeStart,
            'pay_time_end' => $payTimeEnd,
        ];
        $count = $this->dao->search($where)->count();

        return $count > 0 ? bcdiv($count, '30') : 0;
    }

   
}
