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

namespace app\controller\merchant\store\shipping;

use app\common\repositories\store\CityAreaRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\shipping\CityRepository as repository;
use think\facade\Db;
use think\facade\Log;

class City extends BaseController
{
    protected $repository;

    /**
     * City constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @Time: 14:40
     * @return mixed
     */
    public function lst()
    {
        return app('json')->success($this->repository->getFormatList([['is_show', '=', 1],['level','<',2]]));
    }

    public function lstV2($pid)
    {
        return app('json')->success(app()->make(CityAreaRepository::class)->getChildren(intval($pid)));
    }

    public function cityList()
    {
        $address = $this->request->param('address');
        if (!$address)
            return app('json')->fail('地址不存在');
        $make = app()->make(CityAreaRepository::class);
        $city = $make->search(compact('address'))->order('id DESC')->find();
        if (!$city){
            Log::info('用户定位对比失败，请在城市数据中增加:'.var_export($address,true));
            return app('json')->fail('地址不存在');
        }
        return app('json')->success($make->getCityList($city));
    }

    public function setWxAddress()
    {
        $data = $this->request->params(['p', 'c','d']);
        // 查询或插入第一级地址信息
        $province = Db::name('eb_city_area')->where('level', 1)->where('name', $data['p'])->find();
        if (!$province) {
            $province = ['name' => $data['p'], 'level' => 1];
            $province['id'] = Db::name('eb_city_area')->insertGetId($province);
        }

        // 查询或插入第二级地址信息
        $city = Db::name('eb_city_area')->where('level', 2)->where('name', $data['c'])->where('parent_id', $province['id'])->find();
        if (!$city) {
            $city = ['name' => $data['c'], 'level' => 2, 'parent_id' => $province['id']];
            $city['id'] = Db::name('eb_city_area')->insertGetId($city);
        }

        // 查询或插入第三级地址信息
        $district = Db::name('eb_city_area')->where('level', 3)->where('name', $data['d'])->where('parent_id', $city['id'])->find();
        if (!$district) {
            $district = ['name' => $data['d'], 'level' => 3, 'parent_id' => $city['id']];
            $district['id'] = Db::name('eb_city_area')->insertGetId($district);
        }

        // 返回结果
        $result = [
            'province' => $province,
            'city' => $city,
            'district' => $district
        ];
        return app('json')->success($result);
    }


    /**
     * @return mixed
     * @author Qinii
     */
    public function getlist()
    {
        return app('json')->success($this->repository->getFormatList(['is_show' => 1]));
    }
}
