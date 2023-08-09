<?php

namespace app\models\od;

use app\components\db\ContragentsActiveRecord;
use app\components\tree\AdjacencyListBehavior;
use app\components\tree\AutoTreeTrait;
use app\components\tree\NestedSetsBehavior;
use app\models\od\query\SourceQuery;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * @property int $id [int]
 * @property int $tree_id [int]
 * @property int $parent_id [int]
 * @property int $depth [int]
 * @property int $lft [int]
 * @property int $rgt [int]
 * @property string $path [varchar(255)]
 * @property string $mime [varchar(255)]
 * @property int $type [int]
 * @property string $crc [varchar(255)]
 * @property bool $temporary [tinyint(1)]
 * @property string $created_at [datetime]
 * @property string $updated_at [datetime]
 * @property string $parsed_at [datetime]
 * @property string $started_at [datetime]
 *
 * @property-read null|static $parent
 *
 * @property-read string $fullPath
 */
class Source extends ContragentsActiveRecord
{
    use AutoTreeTrait;

    public const TYPE_ZIP = 'application/zip';

    public const TYPE_DIR = 'DIR';

    public static function tableName(): string
    {
        return 'source';
    }

    public function behaviors(): array
    {
        return [
            'AdjacencyList' => [
                'class' => AdjacencyListBehavior::class,
                'parentAttribute' => 'parent_id',
            ],
            'NestedSets' => [
                'class' => NestedSetsBehavior::class,
                'leftAttribute' => 'lft',
                'rightAttribute' => 'rgt',
                'treeAttribute' => 'tree_id',
                'depthAttribute' => 'depth',
            ],
            'TimeStamp' => [
                'class' => TimestampBehavior::class,
                'value' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['path'], 'required'],
            ['parent_id', 'integer'],
            [['path', 'crc'], 'string', 'max' => 255],
            ['temporary', 'boolean'],
            ['temporary', 'default', 'value' => false],
        ];
    }

    public function getInfo(): string
    {
        $date = date('d.m.Y', strtotime($this->parsed_at ?? time()));
        return "$this->path (Обновлено: $date)";
    }

    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'tree_id' => Yii::t('app', 'Parent ID'),
            'parent_id' => Yii::t('app', 'Parent ID'),
            'depth' => Yii::t('app', 'Depth'),
            'lft' => Yii::t('app', 'Lft'),
            'rgt' => Yii::t('app', 'Rgt'),
            'path' => Yii::t('app', 'Источник'),
            'info' => Yii::t('app', 'Источник'),
            'crc' => Yii::t('app', 'Контрольная сумма'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'parsed_at' => Yii::t('app', 'Обработано'),
            'started_at' => Yii::t('app', 'Started At'),
        ];
    }

    /**
     * @return SourceQuery
     */
    public static function find(): SourceQuery
    {
        return new SourceQuery(get_called_class());
    }

    public static function factory(int $treeId, ?int $parentId, string $path): static
    {
        return static::findOne(['tree_id' => $treeId, 'parent_id' => $parentId, 'path' => $path])
            ?? new static(['tree_id' => $treeId, 'parent_id' => $parentId, 'path' => $path]);
    }

    /**
     * @return string
     */
    public function getFullPath(): string
    {
        $response = [Yii::getAlias('@sources')];
        $parents = $this->getParents()->select('path')->all();
        foreach ($parents as $parent) {
            $response[] = $parent->path;
        }
        return implode(DIRECTORY_SEPARATOR, $response) . DIRECTORY_SEPARATOR . $this->path;
    }

    public function isArchive(): bool
    {
        return $this->mime == self::TYPE_ZIP;
    }

    public function isDir(): bool
    {
        return $this->mime == self::TYPE_DIR;
    }
}
