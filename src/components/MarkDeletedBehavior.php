<?php

namespace karakum\common\components;


use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\db\ActiveRecord;
use yii\db\Expression;

class MarkDeletedBehavior extends Behavior
{
    /**
     * @var string the attribute that will receive status value
     */
    public $statusAttribute = 'status';

    /**
     * @var integer the value of status for mark deletion
     */
    public $deletedStatus;

    /**
     * @var string the attribute that will receive timestamp value
     * Set this property to false if you do not want to record the creation time.
     */
    public $deletedAtAttribute = 'deleted_at';

    /**
     * @var string flash message that will be shown on mark for deletion.
     * Set this property to false if you do not want to show flash message.
     * Example: 'Object {id} is marked for deletion'
     */
    public $onMarkFlashMessage = false;

    /**
     * @var string flash message that will be shown on deletion.
     * Set this property to false if you do not want to show flash message.
     * Example: 'Object {id} deleted'
     */
    public $onDeleteFlashMessage = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->deletedStatus)) {
            throw new InvalidConfigException('MarkDeletedBehavior must have \'deletedStatus\' property value');
        }
    }


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function afterInsert(Event $event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        if ($model->getAttribute($this->statusAttribute) == $this->deletedStatus) {
            $model->updateAttributes([
                $this->deletedAtAttribute => new Expression('NOW()'),
            ]);
            $this->refresh();
        }
    }

    public function afterUpdate(Event $event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        $changedAttributes = $event->changedAttributes;

        if (array_key_exists($this->statusAttribute, $changedAttributes)) {
            if ($model->getAttribute($this->statusAttribute) == $this->deletedStatus) {
                $model->updateAttributes([
                    $this->deletedAtAttribute => new Expression('NOW()'),
                ]);
                $this->refresh();
            } elseif ($changedAttributes[$this->statusAttribute] == $this->deletedStatus) {
                $this->updateAttributes([
                    $this->deletedAtAttribute => null,
                ]);
                $this->refresh();
            }
        }
    }

    public function beforeDelete(ModelEvent $event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        if ($model->getAttribute($this->statusAttribute) != $this->deletedStatus) {
            $model->updateAttributes([
                $this->statusAttribute => $this->deletedStatus,
                $this->deletedAtAttribute => new Expression('NOW()'),
            ]);
            $model->refresh();
            if ($this->onMarkFlashMessage) {
                Yii::$app->session->addFlash('success', Yii::$app->getI18n()->format($this->onMarkFlashMessage, [
                    'id' => $model->primaryKey,
                ], Yii::$app->language));
            }
            $event->isValid = false;
            return false;
        }
        $event->isValid = true;
    }

    public function afterDelete(Event $event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        if ($this->onDeleteFlashMessage) {
            Yii::$app->session->addFlash('success', Yii::$app->getI18n()->format($this->onDeleteFlashMessage, [
                'id' => $model->primaryKey,
            ], Yii::$app->language));
        }
    }

}