<?php

namespace karakum\common\upload\actions\image;

use karakum\common\upload\actions\BaseAction;
use karakum\common\upload\AttachManager;
use karakum\common\upload\models\Attachment;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class CropImage extends BaseAction
{

    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;

    /**
     * @var string Атрибут Модели содержащий ID Attachment
     */
    public $imageIdAttribute;

    public $pathNamespace;

    public function run($id = null)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        if (is_null($this->imageIdAttribute)) {
            throw new InvalidConfigException('Action must have \'imageIdAttribute\' property');
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
            if ($image) {
                /**
                 * @param Attachment $image
                 * @return bool
                 */
                $avatarCallback = function ($image) use ($model) {
                    if ($image->save()) {
                        $model->setAttribute($this->imageIdAttribute, $image->id);
                        if ($model->save()) {
                            return [
                                'photo' => $image->getUrl(),
                            ];
                        }
                    }
                    return false;
                };
                $attachManager = $this->getAttachManager();
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