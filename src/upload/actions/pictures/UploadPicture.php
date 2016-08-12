<?php
/**
 * Created by PhpStorm.
 * User: Андрейка
 * Date: 10.06.2016
 * Time: 16:01
 */

namespace app\components\upload\actions\pictures;


use app\components\upload\AttachManager;
use app\models\Attachment;
use app\models\Pictures;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Url;

class UploadPicture extends Action
{

    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;

    /**
     * @var string Атрибут Picture для связи с моделью
     */
    public $pictureLinkTo;
    public $pictureType = Pictures::TYPE_SIMPLE;
    public $pictureStatus = Pictures::STATUS_ACTIVE;
    public $url = [];
    public $pathNamespace;
    public $validatorProfile;
    public $thumbnailProfile;

    public $resizeThumbnail = false;

    public function run($id)
    {
        if (!is_callable($this->getModel)) {
            throw new InvalidConfigException('Action must have \'getModel\' callable property');
        }
        /** @var Model $model */
        $model = call_user_func($this->getModel, $id);

        $image = new Attachment([
            'user_id' => Yii::$app->user->id,
        ]);
        $thumb = new Attachment([
            'user_id' => Yii::$app->user->id,
        ]);

        /**
         * @param $file
         * @param Attachment $image
         * @param Attachment $thumb
         * @return bool
         * @internal param PathOrganizer $pathOrganizer
         */
        $avatarCallback = function ($file, $image, $thumb) use ($model) {

            if ($image->save() and $thumb->save()) {

                $picture = new Pictures();
                $picture->setAttribute($this->pictureLinkTo, $model->id);
                $picture->image_id = $image->id;
                $picture->thumb_id = $thumb->id;
                $picture->type = $this->pictureType;
                $picture->status = $this->pictureStatus;
                $picture->filename = $image->name;
                if ($picture->save()) {

                    $file->p = $picture->id;
                    $file->name = $image->name;
                    $file->url = $image->getUrl();
                    $file->thumbnailUrl = $thumb->getUrl();
                    foreach ($this->url as $key => $action) {
                        $file->$key = Url::to([$action, 'id' => $model->id, 'p' => $picture->id]);
                    }
                    if ($this->resizeThumbnail) {
                        /** @var AttachManager $attachManager */
                        $attachManager = Yii::$app->attachService;
                        $file->thumbnailUrl = $attachManager->getThumbnail($thumb, $this->resizeThumbnail[0], $this->resizeThumbnail[1])->getUrl();
                    }
                    unset($file->path);
                    return true;
                }
            } else {
                Yii::error($image->getErrors());
                Yii::error($thumb->getErrors());
            }
            return false;
        };

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        /** @var AttachManager $attachManager */
        $attachManager = Yii::$app->attachService;
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