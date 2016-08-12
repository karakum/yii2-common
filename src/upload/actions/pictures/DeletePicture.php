<?php
/**
 * Created by PhpStorm.
 * User: Андрейка
 * Date: 10.06.2016
 * Time: 17:15
 */

namespace app\components\upload\actions\pictures;


use app\components\upload\AttachManager;
use app\models\Pictures;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\NotFoundHttpException;

class DeletePicture extends Action
{
    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;

    /**
     * @var string Атрибут Picture для связи с моделью
     */
    public $pictureLinkTo;

    public function run($id, $p)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        /** @var Model $model */
        $model = call_user_func($this->getModel, $id);

        /** @var Pictures $picture */
        $picture = Pictures::find()->andWhere([
            'id' => $p,
            $this->pictureLinkTo => $model->id,
        ])->one();

        if (!$picture) {
            throw new NotFoundHttpException('Запрошенная страница не существует.');
        }

        $image = $picture->image;
        $thumb = $picture->thumb;

        $attachList = array_filter([$image, $thumb]);

        if (count($attachList)) {
            $transaction = $picture->getDb()->beginTransaction();
            $res = false;
            /** @var AttachManager $attachManager */
            $attachManager = Yii::$app->attachService;

            if ($attachManager->removeAttachments($attachList)) {
                $res = true;
                $transaction->commit();
            } else {
                $transaction->rollBack();
            }
            if ($res) {
                $result = [
                ];
            } else {
                $result = [
                    'error' => Yii::t('app', 'There are error occured while removing images'),
                ];
            }
        } else {
            $result = [
                'error' => Yii::t('app', 'There is no images'),
            ];
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result;
    }
}