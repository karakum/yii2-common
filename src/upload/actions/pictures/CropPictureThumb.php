<?php

namespace karakum\common\upload\actions\pictures;

use karakum\common\upload\AttachManager;
use app\models\Attachment;
use app\models\Pictures;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\web\NotFoundHttpException;

class CropPictureThumb extends Action
{
    /**
     * @var callable Замыкание для получения модели по ID.
     */
    public $getModel;

    /**
     * @var string Атрибут Picture для связи с моделью
     */
    public $pictureLinkTo;
    public $pathNamespace;
    public $thumbnailProfile;

    public $resizeThumbnail = false;

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

        $response = Yii::$app->response;
        $request = Yii::$app->request;

        $width = (int)$request->post('width');
        $height = (int)$request->post('height');
        $left = (int)$request->post('x');
        $top = (int)$request->post('y');

        if (isset($width) and isset($height) and isset($left) and isset($top)) {
            $image = $picture->image;
            $thumb = $picture->thumb;
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
                $avatarCallback = function ($image, $thumb) use ($picture) {
                    if ($thumb->save()) {
                        $picture->thumb_id = $thumb->id;
                        if ($picture->save()) {
                            return [
                                'p' => $picture->id,
                                'thumb' => $thumb->getUrl(),
                            ];
                        }
                    }
                    return false;
                };
                /** @var AttachManager $attachManager */
                $attachManager = Yii::$app->attachService;
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
                    $picture->refresh();
                    $result['thumb'] = $attachManager->getThumbnail($picture->thumb, $this->resizeThumbnail[0], $this->resizeThumbnail[1])->getUrl();
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