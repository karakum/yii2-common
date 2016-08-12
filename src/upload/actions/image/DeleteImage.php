<?php

namespace karakum\common\upload\actions\image;


use karakum\common\upload\actions\BaseAction;
use karakum\common\upload\AttachManager;
use karakum\common\upload\models\Attachment;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class DeleteImage extends BaseAction
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

    public $noImageUrl;
    public $noThumbUrl;

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

        $model->setAttribute($this->imageIdAttribute, null);
        $model->setAttribute($this->thumbIdAttribute, null);

        $attachList = array_filter([$image, $thumb]);

        if (count($attachList)) {
            $transaction = $model->getDb()->beginTransaction();
            $res = false;
            if ($model->save()) {
                $attachManager = $this->getAttachManager();
                if ($attachManager->removeAttachments($attachList)) {
                    $res = true;
                    $transaction->commit();
                } else {
                    $transaction->rollBack();
                }
            } else {
                $transaction->rollBack();
            }
            if ($res) {
                $result = [];
                if ($this->noImageUrl) {
                    $result['url'] = $this->noImageUrl;
                }
                if ($this->noThumbUrl) {
                    $result['thumbnailUrl'] = $this->noThumbUrl;
                }
            } else {
                $result = [
                    'error' => Yii::t('app', 'There are error occured while removing images'),
                ];
            }
        } else {
            $result = [
                'error' => Yii::t('app', 'There is no image'),
            ];
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result;
    }
}