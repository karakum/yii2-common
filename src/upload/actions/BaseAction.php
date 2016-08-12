<?php

namespace karakum\common\upload\actions;


use karakum\common\upload\AttachManager;
use Yii;
use yii\base\Action;

class BaseAction extends Action
{
    protected function getAttachManager()
    {
        /** @var AttachManager $attachManager */
        $attachManager = Yii::$app->attachManager;
        return $attachManager;
    }
}