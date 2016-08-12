<?php

namespace karakum\common\upload\actions\image;

use karakum\common\upload\actions\BaseAction;
use karakum\common\upload\AttachManager;
use karakum\common\upload\models\Attachment;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class CropImageThumb extends BaseAction
{

    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;

    /**
     * @var string Атрибут Модели содержащий ID Attachment
     */
    public $imageIdAttribute;

    /**
     * @var string Атрибут Модели содержащий ID Attachment для миниатюры
     */
    public $thumbIdAttribute;

    public $pathNamespace;
    public $thumbnailProfile;

    public $resizeThumbnail = false;

    public function run($id = null)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        if (is_null($this->imageIdAttribute)) {
            throw new InvalidConfigException('Action must have \'imageIdAttribute\' property');
        }
        if (is_null($this->thumbIdAttribute)) {
            throw new InvalidConfigException('Action must have \'thumbIdAttribute\' property');
        }

        /** @var ActiveRecord $model */
        $model = call_user_func($this->getModel, $id);

        $response = Yii::$app->response;
        $request = Yii::$app->request;

        $width = (int)$request->post('width');
        $height = (int)$request->post('height');
        $left = (int)$request->post('x');
        $top = (int)$request->post('y');

        if (isset($width) and isset($height) and isset($left) and isset($top)) {
            $image = Attachment::findOne($model->getAttribute($this->imageIdAttribute));
            $thumb = Attachment::findOne($model->getAttribute($this->thumbIdAttribute));
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
                $avatarCallback = function ($image, $thumb) use ($model) {
                    if ($thumb->save()) {
                        $model->setAttribute($this->thumbIdAttribute, $thumb->id);
                        if ($model->save()) {
                            return [
                                'thumb' => $thumb->getUrl(),
                            ];
                        }
                    }
                    return false;
                };
                $attachManager = $this->getAttachManager();
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
                if ($this->resizeThumbnail) {
                    $model->refresh();
                    $thumb = Attachment::findOne($model->getAttribute($this->thumbIdAttribute));
                    $result['thumb'] = $attachManager->getThumbnail($thumb, $this->resizeThumbnail[0], $this->resizeThumbnail[1])->getUrl();
                }
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