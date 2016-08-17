<?php
namespace karakum\common\components;

use Yii;
use yii\db\ActiveRecord;

class StatusActiveRecord extends ActiveRecord
{

    const STATUS_NOT_ACTIVE = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_MARK_DELETED = 99;

    public static function statusList()
    {
        return [
            self::STATUS_NOT_ACTIVE => Yii::t('app', 'Inactive'),
            self::STATUS_ACTIVE => Yii::t('app', 'Active'),
            self::STATUS_MARK_DELETED => Yii::t('app', 'Mark deleted'),
        ];
    }

    public function getStatusName()
    {
        return $this->statusList()[$this->getAttribute($this->statusAttributeName())];
    }

    protected static function statusAttributeName()
    {
        return 'status';
    }

}