<?php

namespace karakum\common\upload\actions\pictures;

use app\models\Pictures;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;

class DefaultPicture extends Action
{
    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;

    /**
     * @var string Атрибут Picture для связи с моделью
     */
    public $pictureLinkTo;

    /**
     * @var string Атрибут модели для связи с Picture
     */
    public $defaultLinkTo;

    public function run($id, $p)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        /** @var ActiveRecord $model */
        $model = call_user_func($this->getModel, $id);

        /** @var Pictures $picture */
        $picture = Pictures::find()->andWhere([
            'id' => $p,
            $this->pictureLinkTo => $model->id,
        ])->one();

        if (!$picture) {
            throw new NotFoundHttpException('Запрошенная страница не существует.');
        }

        if ($model->getAttribute($this->defaultLinkTo) == $picture->id) {
            $model->setAttribute($this->defaultLinkTo, null);
        } else {
            $model->setAttribute($this->defaultLinkTo, $picture->id);
        }
        if ($model->save()) {
            if ($model->getAttribute($this->defaultLinkTo) == $picture->id) {
                $result = [
                    'selected' => true,
                    'url' => $picture->image->getUrl(),
                    'thumbnailUrl' => $picture->thumb->getUrl(),
                ];
            } else {
                $result = [
                    'selected' => false,
                ];
            }
        } else {
            $result = [
                'error' => Yii::t('app', 'There are error occured while selecting default image'),
            ];
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result;
    }
}