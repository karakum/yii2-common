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

class CropThumb extends Action
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
    public $pathNamespace;
    public $thumbnailProfile;

    public $resizeThumbnail = false;

    public function run($id, $f)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        /** @var Model $model */
        $model = call_user_func($this->getModel, $id);

        $fileClass = $this->fileClass;
        /** @var ActiveRecord $file */
        $file = $fileClass::find()->andWhere([
            'id' => $f,
            $this->fileLinkTo => $model->id,
        ])->one();

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
            $thumb = Attachment::findOne($file->getAttribute($this->fileThumbnailAttr));
            if (!$thumb) {
                $thumb = new Attachment([
                    'user_id' => Yii::$app->user->id,
                ]);
            }
            if ($image) {
                /**
                 * @param Attachment $image
                 * @param Attachment $thumb
                 * @return bool
                 */
                $avatarCallback = function ($image, $thumb) use ($file, $model) {
                    if ($thumb->save()) {
                        $file->setAttribute($this->fileAttachmentAttr, $image->id);
                        $file->setAttribute($this->fileThumbnailAttr, $thumb->id);
                        if ($file->save()) {

                            if ($this->resizeThumbnail) {
                                /** @var AttachManager $attachManager */
                                $attachManager = Yii::$app->attachManager;
                                $th = $attachManager->getThumbnail($thumb, $this->resizeThumbnail[0], $this->resizeThumbnail[1])->getUrl([$this->fileLinkTo => $model->id]);
                            } else {
                                $th = $thumb->getUrl([$this->fileLinkTo => $model->id]);
                            }
                            return [
                                'f' => $file->id,
                                'thumb' => $th,
                            ];
                        }
                    }
                    return false;
                };
                /** @var AttachManager $attachManager */
                $attachManager = Yii::$app->attachManager;
                $result = $attachManager->cropThumb(
                    $this->pathNamespace,
                    $this->thumbnailProfile,
                    [
                        'width' => (int)$width,
                        'height' => (int)$height,
                        'left' => (int)$left,
                        'top' => (int)$top,
                    ],
                    $image,
                    $thumb,
                    $avatarCallback
                );
            } else {
                $result = [
                    'error' => Yii::t('app', 'Image empty'),
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