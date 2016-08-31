<?php
namespace karakum\common\upload\actions\files;


use karakum\common\upload\AttachManager;
use karakum\common\upload\models\Attachment;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Url;
use yii\web\UploadedFile;

class UploadFile extends Action
{

    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;
    public $uploadAttribute;

    /**
     * @var string Атрибут для связи с моделью
     */
    public $fileLinkTo;
    public $fileClass;
    public $fileAttachmentAttr;
    public $fileThumbnailAttr;
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

            if ($image->save() && (!$thumb || $thumb->save())) {

                $picture = new $this->fileClass;
                $picture->setAttribute($this->fileLinkTo, $model->id);
                $picture->setAttribute($this->fileAttachmentAttr, $image->id);
                if ($thumb) {
                    $picture->setAttribute($this->fileThumbnailAttr, $thumb->id);
                }
                if ($picture->save()) {

                    /** @var AttachManager $attachManager */
                    $attachManager = Yii::$app->attachManager;
                    $file->f = $picture->id;
                    $file->name = $image->name;
                    $file->url = $image->getUrl([$this->fileLinkTo => $model->id]);
                    if ($thumb) {
                        if ($this->resizeThumbnail) {
                            $file->thumbnailUrl = $attachManager->getThumbnail($thumb, $this->resizeThumbnail[0], $this->resizeThumbnail[1])->getUrl([$this->fileLinkTo => $model->id]);
                        } else {
                            $file->thumbnailUrl = $thumb->getUrl([$this->fileLinkTo => $model->id]);
                        }
                    } else {
                        $file->icon = $attachManager->getIcon($image->mime_type);
                    }
                    foreach ($this->url as $key => $action) {
                        if (in_array($key, ['cropPhotoUrl', 'cropThumbUrl'])) {
                            if ($thumb) {
                                $file->$key = Url::to([$action, 'id' => $model->id, 'f' => $picture->id]);
                            }
                        } else {
                            $file->$key = Url::to([$action, 'id' => $model->id, 'f' => $picture->id]);
                        }
                    }
                    unset($file->path);
                    return true;
                }
            } else {
                if ($image && $image->errors) {
                    Yii::error($image->errors);
                }
                if ($thumb && $thumb->errors) {
                    Yii::error($thumb->errors);
                }
            }
            return false;
        };

        $uploads = [];
        foreach (UploadedFile::getInstances($model, $this->uploadAttribute) as $index => $f) {
            $uploads['name'][$index] = $f->name;
            $uploads['tmp_name'][$index] = $f->tempName;
            $uploads['type'][$index] = $f->type;
            $uploads['size'][$index] = $f->size;
            $uploads['error'][$index] = $f->error;
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        /** @var AttachManager $attachManager */
        $attachManager = Yii::$app->attachManager;
        return $attachManager->uploadImage(
            $uploads,
            $this->pathNamespace,
            $this->validatorProfile,
            $this->thumbnailProfile,
            $image,
            $thumb,
            $avatarCallback
        );
    }
}