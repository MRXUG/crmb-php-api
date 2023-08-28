<?php


namespace app\common\model\store\shipping;


use app\common\model\BaseModel;
use think\model\relation\HasMany;

class PostageTemplateModel extends BaseModel
{
    protected $schema = [
        'template_id' => 'int',//编号
        'name' => 'varchar',//模板名称
        'type' => 'tinyint',//计件方式 0=数量
        'mer_id' => 'int',//商户 id
        'create_time' => 'timestamp',//添加时间

    ];

    /**
     * @inheritDoc
     */
    public static function tablePk(): ?string
    {
        return 'template_id';
    }

    /**
     * @inheritDoc
     */
    public static function tableName(): string
    {
        return 'postage_template';
    }

    public function rules(string $model, string $foreignKey = '', string $localKey = ''): HasMany
    {
        return $this->hasMany(PostageTemplateRuleModel::class, 'template_id', 'template_id');
    }
}