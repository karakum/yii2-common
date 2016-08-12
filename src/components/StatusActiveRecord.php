<?php
namespace karakum\common\components;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;

class StatusActiveRecord extends ActiveRecord
{

    const STATUS_NOT_ACTIVE = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_MARK_DELETED = 99;

    const MESSAGE_MARK_DELETED = 0;
    const MESSAGE_DELETED = 1;

    public static function statusList()
    {
        return [
            self::STATUS_NOT_ACTIVE => 'Неактивен',
            self::STATUS_ACTIVE => 'Активен',
            self::STATUS_MARK_DELETED => 'Пометка на удаление',
        ];
    }

    protected function flashMessages()
    {
        return [
            self::MESSAGE_MARK_DELETED => 'Объект помечен на удаление',
            self::MESSAGE_DELETED => 'Объект удален',
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

    public function afterSave($insert, $changedAttributes)
    {
        $statusAttr = self::statusAttributeName();
        if (array_key_exists($statusAttr, $changedAttributes)) {
            if ($this->getAttribute($statusAttr) == self::STATUS_MARK_DELETED) {
                if ($this->hasAttribute('deleted')) {
                    $this->updateAttributes([
                        'deleted' => new Expression('NOW()'),
                    ]);
                    $this->refresh();
                }
            } elseif ($changedAttributes[$statusAttr] == self::STATUS_MARK_DELETED) {
                if ($this->hasAttribute('deleted')) {
                    $this->updateAttributes([
                        'deleted' => null,
                    ]);
                    $this->refresh();
                }
            }
        }

        parent::afterSave($insert, $changedAttributes);
    }

    public function beforeDelete()
    {
        $statusAttr = self::statusAttributeName();
        if ($this->getAttribute($statusAttr) != self::STATUS_MARK_DELETED) {
            $upd = ['status' => self::STATUS_MARK_DELETED];
            if ($this->hasAttribute('deleted')) {
                $upd['deleted'] = new Expression('NOW()');
            }
            $this->updateAttributes($upd);
            $this->refresh();
            Yii::$app->session->setFlash('success', $this->flashMessages()[self::MESSAGE_MARK_DELETED]);
            return false;
        }
        return parent::beforeDelete();
    }

    public function afterDelete()
    {
        Yii::$app->session->setFlash('warning', $this->flashMessages()[self::MESSAGE_DELETED]);
        parent::afterDelete();
    }

}