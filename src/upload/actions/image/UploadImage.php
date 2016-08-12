<?php

namespace karakum\common\upload\actions\image;

use karakum\common\upload\actions\BaseAction;
use karakum\common\upload\models\Attachment;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\helpers\Url;

class UploadImage extends BaseAction
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

    public $url = [];
    public $urlWithId = true;
    public $pathNamespace;
    public $validatorProfile;
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

        $image = Attachment::findOne($model->getAttribute($this->imageIdAttribute));
        $thumb = Attachment::findOne($model->getAttribute($this->thumbIdAttribute));

        if (!$image) {
            $image = new Attachment([
                'user_id' => Yii::$app->user->id,
            ]);
        }
        if (!$thumb) {
            $thumb = new Attachment([
                'user_id' => Yii::$app->user->id,
            ]);
        }

        /**
         * @param $file
         * @param Attachment $image
         * @param Attachment $thumb
         * @return bool
         * @internal param PathOrganizer $pathOrganizer
         */
        $avatarCallback = function ($file, $image, $thumb) use ($model) {

            if ($image->save() and $thumb->save()) {
                $model->setAttribute($this->imageIdAttribute, $image->id);
                $model->setAttribute($this->thumbIdAttribute, $thumb->id);
                if ($model->save()) {

                    $file->url = $image->getUrl();
                    $file->thumbnailUrl = $thumb->getUrl();
                    foreach ($this->url as $key => $action) {
                        $file->$key = Url::to($this->urlWithId ? [$action, 'id' => $model->id] : [$action]);
                    }
                    unset($file->path);
                    if ($this->resizeThumbnail) {
                        $attachManager = $this->getAttachManager();
                        $file->thumbnailUrl = $attachManager->getThumbnail($thumb, $this->resizeThumbnail[0], $this->resizeThumbnail[1])->getUrl();
                    }
                    return true;
                }
            } else {
                Yii::error($image->getErrors());
                Yii::error($thumb->getErrors());
            }
            return false;
        };

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $attachManager = $this->getAttachManager();
        return $attachManager->uploadImage(
            $this->pathNamespace,
            $this->validatorProfile,
            $this->thumbnailProfile,
            $model->formName(),
            $image,
            $thumb,
            $avatarCallback
        );
    }
}