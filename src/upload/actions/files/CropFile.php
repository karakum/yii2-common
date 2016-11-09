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

class CropFile extends Action
{
    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;
    public $fileClass;
    public $fileAttachmentAttr;
    public $fileThumbnailAttr;

    /**
     * @var string Атрибут Picture для связи с моделью
     */
    public $fileLinkTo;
    public $fileLinkToUser = false;
    public $pathNamespace;

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

        $response = Yii::$app->response;
        $request = Yii::$app->request;

        $width = (int)$request->post('width');
        $height = (int)$request->post('height');
        $left = (int)$request->post('x');
        $top = (int)$request->post('y');

        if (isset($width) and isset($height) and isset($left) and isset($top)) {
            $image = Attachment::findOne($file->getAttribute($this->fileAttachmentAttr));
            if ($image) {
                /**
                 * @param Attachment $image
                 * @return bool
                 */
                $avatarCallback = function ($image) use ($file, $model) {
                    if ($image->save()) {
                        $file->setAttribute($this->fileAttachmentAttr, $image->id);
                        if ($file->save()) {
                            return [
                                'f' => $file->id,
                                'photo' => $image->getUrl([$this->fileLinkTo => $model->id]),
                            ];
                        }
                    }
                    return false;
                };
                /** @var AttachManager $attachManager */
                $attachManager = Yii::$app->attachManager;
                $result = $attachManager->cropImage(
                    $this->pathNamespace,
                    [
                        'width' => (int)$width,
                        'height' => (int)$height,
                        'left' => (int)$left,
                        'top' => (int)$top,
                    ],
                    $image,
                    $avatarCallback
                );
            } else {
                $result = [
                    'error' => Yii::t('app', 'Original image empty'),
                ];
            }
        } else {
            $response->setStatusCode(400);
            $result = [
                'error' => Yii::t('app', 'Request parameters error'),
            ];
        }
        $response->format = \yii\web\Response::FORMAT_JSON;
        return $result;

    }
}