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

Route::group(function () {

    // 小程序管理
    Route::group('applet', function () {
        // 小程序
        Route::post('create', '/create')->name('systemAppletCreate')->option([
            '_alias' => '小程序管理新增',
        ]);
        Route::post('save/:id', '/save')->name('systemAppletCreate')->option([
            '_alias' => '小程序管理编辑',
        ]);
        Route::get('detail/:id', '/detail')->name('groupDetail')->option([
            '_alias' => '小程序详情',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemAppletDelete')->option([
            '_alias' => '小程序删除',
        ]);
        Route::get('list', '/list')->name('systemAppletLst')->option([
            '_alias' => '小程序管理列表',
        ]);
        Route::get('excel', '/excel')->name('systemAppletExcel')->option([
            '_alias' => '小程序管理导出',
        ]);

        // 小程序主体
        Route::post('createSubject', '/createSubject')->name('systemAppletExcel')->option([
            '_alias' => '小程序主体新增',
        ]);
        Route::post('saveSubject/:id', '/saveSubject')->name('systemAppletSaveSubject')->option([
            '_alias' => '小程序主体编辑',
        ]);
        Route::get('detailSubject/:id', '/detailSubject')->name('groupDetail')->option([
            '_alias' => '小程序主体详情',
        ]);
        Route::delete('deleteSubject/:id', '/deleteSubject')->name('systemSubjectDelete')->option([
            '_alias' => '小程序主体删除',
        ]);
        Route::get('subjectList', '/subjectList')->name('systemAppletExcel')->option([
            '_alias' => '小程序主体列表',
        ]);



        Route::get('tree', '/tree')->name('systemAppletExcel')->option([
            '_alias' => '小程序分配树状列表',
        ]);

        Route::get('authorization', '/authorization')->name('systemAppletAuthorization')->option([
            '_alias' => '小程序授权',
        ]);

        Route::post('submit_audit', '/submitAudit')->name('systemAppletSubmitAudit')->option([
            '_alias' => '小程序提审',
        ]);

        Route::post('undo_code_audit', '/undoCodeAudit')->name('systemAppletUndoCodeAudit')->option([
            '_alias' => '小程序撤销审核',
        ]);

        Route::post('release', '/release')->name('systemAppletRelease')->option([
            '_alias' => '小程序发布',
        ]);

        Route::post('revert_code_release', '/revertCodeRelease')->name('systemAppletRevertCodeRelease')->option([
            '_alias' => '小程序版本回退',
        ]);

        Route::get('getApplet', '/getApplet')->name('systemGetApplet')->option([
            '_alias' => '随机获取一个健康可以小程序',
        ]);

        Route::post('getAuditstatus', '/getAuditstatus')->name('systemAppletGetAuditstatus')->option([
            '_alias' => '小程序审核状态',
        ]);

        Route::post('getprivacysetting', '/getprivacysetting')->name('systemAppletGetprivacysetting')->option([
            '_alias' => '小程序隐私查询',
        ]);

        Route::post('setPrivacySetting', '/setPrivacySetting')->name('systemAppletSetPrivacySetting')->option([
            '_alias' => '小程序隐私查询',
        ]);

        Route::post('getcallbackip', '/getcallbackip')->name('systemAppletGetcallbackip')->option([
            '_alias' => '小程序IP',
        ]);

    })->prefix('admin.applet.WxApplet')->option([
         '_path' => '/applet/wxApplet',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
