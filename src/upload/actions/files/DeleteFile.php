<?php

namespace karakum\common\upload\actions\files;


use karakum\common\upload\AttachManager;
use karakum\common\upload\models\Attachment;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;

class DeleteFile extends Action
{
    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;
    public $fileClass;
    public $fileAttachmentAttr;
    public $fileThumbnailAttr;

    /**
     * @var string Атрибут для связи с моделью
     */
    public $fileLinkTo;
    public $fileLinkToUser = false;

    public function run($id, $f)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        /** @var Model $model */
        $model = call_user_func($this->getModel, $id);

        $fileClass = $this->fileClass;
        $query = $fileClass::find()->andWhere([
            'id' => $f,
            $this->fileLinkTo => $model->id,
        ]);
        if ($this->fileLinkToUser) {
            $query->andWhere([
                $this->fileLinkToUser => Yii::$app->user->id,
            ]);
        }
        /** @var ActiveRecord $file */
        $file = $query->one();

        if (!$file) {
            throw new NotFoundHttpException('Запрошенная страница не существует.');
        }

        $image = Attachment::findOne($file->getAttribute($this->fileAttachmentAttr));
        if ($this->fileThumbnailAttr) {
            $thumb = Attachment::findOne($file->getAttribute($this->fileThumbnailAttr));
        } else {
            $thumb = null;
        }

        $attachList = array_filter([$image, $thumb]);

        if (count($attachList)) {
            $transaction = $file->getDb()->beginTransaction();
            $res = false;
            /** @var AttachManager $attachManager */
            $attachManager = Yii::$app->attachManager;

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
                    'error' => Yii::t('app', 'There are error occured while removing files'),
                ];
            }
        } else {
            $result = [
                'error' => Yii::t('app', 'There is no files'),
            ];
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result;
    }
}