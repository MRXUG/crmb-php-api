<?php

namespace app\common\repositories\applet;

use app\common\dao\applet\WxAppletDao;
use app\common\model\applet\WxAppletModel;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderStatisticsRepository;
use app\validate\admin\AppletValidate;
use crmeb\services\MiniProgramService;
use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use think\response\Json;

class WxAppletRepository extends BaseRepository
{
    /**
     * 日均支付订单量高风险，大于等于
     */
    const payment_Number_risk_high = '1000';
    /**
     * 日均支付订单量中风险，小于高风险，大于等于中风险
     */
    const payment_Number_risk_medium = '100';
    /**
     * 交易体验分高风险，小于等于
     */
    const currentScore_risk_high = '80';
    /**
     * 交易体验分中风险，大于高风险，小于等于中风险
     */
    const currentScore_risk_medium = '100';
    protected $dao;

    public function __construct(WxAppletDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 新增小程序
     *
     * @param AppletValidate $validate
     * @param $data
     *
     * @return Json|void
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 18:51
     */
    public function create(AppletValidate $validate, $data)
    {
        try {
            $this->checkParam($validate, $data);
            $currentScore = 0;
            $totalNum = 0;
            $data = [
                'subject_id'         => $data['subject_id'],
                'name'               => $data['name'],
                'original_id'        => $data['original_id'],
                'original_appid'     => $data['original_appid'],
                'original_appsecret' => $data['original_appsecret'],
                'current_score'      => $currentScore,
                'total_num'          => $totalNum,
            ];

            $res = $this->dao->create($data);

            // 小程序获取交易体验分违规记录
            $this->acquirePenaltyList($res->id, $data['original_appid'], $data['original_appsecret']);
        } catch (Exception $e) {
            // 这是进行异常捕获
            return json($e->getMessage());
        }
    }

    /**
     * 编辑小程序
     *
     * @param $id
     * @param AppletValidate $validate
     * @param $data
     *
     * @return Json|void
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:51
     */
    public function update($id, AppletValidate $validate, $data)
    {
        $this->checkParam($validate, $data);
        $applet = $this->dao->get($id);
        if ($applet) {
            $this->dao->update($id, $data);
        }
         if ($data['original_appid'] ?? '') {
             $this->clearAppidCache($data['original_appid']);
         }
    }

    /**
     * 获取小程序
     *
     * @param $id
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:51
     */
    public function show($id): array
    {
        return $this->dao->get($id)->toArray();
    }

    /**
     * 删除小程序
     *
     * @param $id
     *
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:51
     */
    public function delete($id)
    {
        $record = $this->dao->query([
            'id' => $id,
        ])->find();
        $this->dao->update($id, ['is_del' => WxAppletModel::DELETED_YES]);
        if ($record['original_appid'] ?? '') {
            $this->clearAppidCache($record['original_appid']);
        }
    }


    /**
     * 获取小程序列表
     *
     * @param $page
     * @param $limit
     * @param $name
     * @param $healthStatus
     * @param $orderBy
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/2/27 21:02
     */
    public function getApiList($page, $limit, $name, $healthStatus, $orderBy): array
    {
        $query = $this->dao->search($name, $healthStatus, $orderBy);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as &$item) {
            // 提审状态：0：从未提审，1：未提审，2：提审中，3：提审通过，4：提审失败，5：发布成功，6：发布失败
            $item['status'] = $item['submit']['status'] ?? 0;
            // 审核结果（提审失败原因）
            $item['submit_audit_result'] = $item['submit']['submit_audit_result'] ?? '';
            // 小程序版本号
            $item['user_version'] = $item['submit']['user_version'] ?? '';
            $subject = $item['subject']['subject'] ?? '';
            unset($item['subject']);
            unset($item['submit']);
            $item['subject'] = $subject;
            //$item['health_status'] = WxAppletModel::HEALTH_STATUS_NAME[$item['health_status']];
        }

        return compact('count', 'list');
    }


    /**
     * 参数校验
     *
     * @param AppletValidate $validate
     * @param $data
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:58
     */
    public function checkParam(AppletValidate $validate, $data)
    {
        $validate->check($data);

        return $data;
    }

    /**
     * 校验小程序是否存在
     *
     * @param $originalAppid
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/1 11:58
     */
    public function checkRepeat($originalAppid)
    {
        $data = $this->dao->checkRepeat($originalAppid);

        return $data ? $data->toArray() : [];
    }

    /**
     * @param string|null $appletName
     * @param int $page
     * @param int $limit
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function appletListBySubject(?string $appletName, int $page = 1, int $limit = 10): array
    {
        $where[] = ['is_del', '=', WxAppletModel::DELETED_NO];
        if ($appletName) {
            $where[] = ['name', 'like', "%$appletName%"];
        }

        $query = $this->dao->appletList($where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->select()->map(function ($item) {
            $item['label'] = $item['subject'];

            return [
                'label'    => $item['name'] . $item['id'],
                'children' => [$item],
            ];
        });

        return compact('count', 'list');
    }

    /**
     * 小程序获取交易体验分违规记录
     *
     * @param $id
     * @param $appId
     * @param $secret
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/2 21:31
     */
    public function acquirePenaltyList($id, $appId, $secret)
    {
        try {
            $config['mini_program']['app_id'] = $appId;
            $config['mini_program']['secret'] = $secret;
            $make = new MiniProgramService($config);

            $penaltyList = $make->miniProgram()->applet->getPenaltyList();

            if ($penaltyList) {
                $currentScore = $penaltyList->currentScore;
                $totalNum = $penaltyList->totalNum;
                $orderRepository = app()->make(StoreOrderStatisticsRepository::class);
                $paymentNumber = $orderRepository->averageDailyPaymentByAppId($appId);

                $this->dao->update($id, [
                    'current_score' => $currentScore,
                    'total_num' => $totalNum,
                    'health_status' => $this->calculateHealth($paymentNumber, $currentScore),
                ]);
            }
        } catch (Exception $e) {
            // 这是进行异常捕获
            Log::info('小程序获取交易体验分违规记录失败：' . $e->getMessage());
        }
    }


    /**
     * 计算小程序健康值
     *
     * @param $paymentNumber
     * @param $currentScore
     *
     * @return int
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/3 10:58
     */
    public function calculateHealth($paymentNumber, $currentScore): int
    {
        // 默认健康
        $healthStatus = WxAppletModel::APPLET_HEALTHY;
        // 高风险
        if ($paymentNumber >= self::payment_Number_risk_high || $currentScore <= self::currentScore_risk_high) {
            $healthStatus = WxAppletModel::APPLET_HIGH_RISK;
        }
        // 中风险
        if ((self::payment_Number_risk_high > $paymentNumber && $paymentNumber >= self::payment_Number_risk_medium) ||
            ($currentScore > self::currentScore_risk_high && $currentScore < self::currentScore_risk_medium)) {
            $healthStatus = WxAppletModel::APPLET_MEDIUM_RISK;
        }

        return $healthStatus;
    }


    /**
     * @param $appid
     * @return mixed|string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAppSecretByAppid($appid)
    {
        $record = $this->dao->query([
            'original_appid' => $appid,
            'is_del'         => WxAppletModel::DELETED_NO
        ])->find();
        if (!$record) {
            return '';
        }
        return $record['original_appsecret'];
    }

    /**
     * 删除appid和secret映射的缓存
     * @param $originalAppid
     * @return void
     */
    public function clearAppidCache($originalAppid)
    {
        $redis = Cache::store('redis')->handler();
        $redis->del(sprintf(WxAppletModel::CACHE_APPID_TO_SECRET, $originalAppid));
    }

    /**
     * 获取健康小程序
     *
     * @return array
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/3 12:05
     */
    public function healthyApplet(): array
    {
        return $this->dao->healthyApplet();
    }
}