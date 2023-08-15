<?php


declare (strict_types = 1);
namespace app\command;


use app\common\model\system\merchant\Merchant;
use crmeb\services\WechatService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;

class batchCreateNotifyUrl extends Command
{

    protected function configure()
    {
        // 指令配置
        $this->setName('notify_url:create')
            ->setDescription('创建商户微信投诉回调');
    }

    /**
     * TODO
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function execute(Input $input, Output $output)
    {
        $where = [
            ['mer_state', '=', 1],
            ['is_del', '=', 0],
            ['status', '=', 1],
        ];
        $merList = Merchant::getInstance()
            ->where($where)
            ->where('wechat_complaint_notify_url is null')
            ->select();
        foreach ($merList as $v){
            $url = env('APP.HOST'). '/api/notice/wechat_complaint_notify/'.$v->mer_id;
            $updateInfo = [
                'wechat_complaint_notify_url' => $url,
                'wechat_complaint_notify_status' => 1
            ];

            $wechatService = WechatService::getMerPayObj($v->mer_id)->MerchantComplaint();
            $wechatService->createNotification($url);
            $v->where('mer_id', '=', $v->mer_id)->update($updateInfo);
        }



        $output->writeln('执行成功');
    }

}