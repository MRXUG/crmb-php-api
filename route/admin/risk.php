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

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
//用户风险管控
Route::group(function () {

    Route::group('risk', function () {
        Route::post('setrisk', '/setRisk')->name('systemSetRisk')->option([
            '_alias' => '风险数据设置',
        ]);
        
        Route::get('getrisk','/getRisk')->name('systemGetRisk')->option([
            '_alias' => '风险数据获取',
        ]);
    })->prefix('admin.risk.Risk')->option([
        '_auth' => true,
    ]);







})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);