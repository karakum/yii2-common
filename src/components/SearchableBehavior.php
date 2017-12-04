<?php

namespace karakum\common\components;


use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;

class SearchableBehavior extends Behavior
{
    /**
     * @var string the attribute that will receive search value
     */
    public $searchAttribute = 'search';

    /**
     * @var string search template
     */
    public $template = '#{id} {name}';

    private $_attributes = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (preg_match_all('/{(\w+)}/', $this->template, $matches)) {
            $this->_attributes = array_unique($matches[1]);
        } else {
            $this->_attributes = [];
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
        ];
    }

    private function generateSearchAttribute(ActiveRecord $model)
    {
        if ($this->_attributes) {
            $searchAttributes = [];
            $search = $this->template;
            foreach ($this->_attributes as $key) {
                $value = $model->$key;
                if (!empty($value)) {
                    $searchAttributes[] = $value;
                }
                $search = trim(str_replace('{' . $key . '}', $value ?: '', $search));
            }
            if ($searchAttributes) {
                $model->updateAttributes([$this->searchAttribute => $search]);
            }
        }
    }

    public function afterInsert(Event $event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        $model->refresh();
        $this->generateSearchAttribute($model);
    }

    public function afterUpdate(Event $event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        $changedAttributes = $event->changedAttributes;
        if (empty($model->getAttribute($this->searchAttribute)) || array_intersect_key($changedAttributes, array_combine($this->_attributes, $this->_attributes))) {
            $this->generateSearchAttribute($model);
        }
    }

}