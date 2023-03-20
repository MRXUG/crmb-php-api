<?php
namespace app\common\model\applet;

use app\common\model\BaseModel;

class WxAppletSubjectModel extends BaseModel
{


    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'applet_subject';
    }

}