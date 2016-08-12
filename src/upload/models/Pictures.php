<?php

namespace karakum\common\upload\models;

use karakum\common\components\StatusActiveRecord;
use karakum\common\upload\AttachManager;
use Yii;

/**
 * This is the model class for table "{{%pictures}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $filename
 * @property integer $image_id
 * @property integer $thumb_id
 * @property integer $type
 * @property integer $sort
 * @property integer $status
 * @property string $created
 * @property string $updated
 * @property string $deleted
 *
 * @property Users $user
 * @property Attachment $image
 * @property Attachment $thumb
 */
class Pictures extends StatusActiveRecord
{

    const TYPE_SIMPLE = 1;
    const TYPE_CERT = 2;
    const TYPE_PORTFOLIO = 3;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%pictures}}';
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $max = Pictures::find()->amongThese($this)->max('sort');
                if (!is_numeric($max)) {
                    $max = 0;
                }
                $this->sort = $max + 1;
            }
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'image_id', 'thumb_id', 'type', 'sort', 'status'], 'integer'],
            [['filename', 'image_id', 'thumb_id'], 'required'],
            [['created', 'updated', 'deleted'], 'safe'],
            [['filename'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => Yii::$app->user->identityClass, 'targetAttribute' => ['user_id' => 'id']],
            [['image_id'], 'exist', 'skipOnError' => true, 'targetClass' => Attachment::className(), 'targetAttribute' => ['image_id' => 'id']],
            [['thumb_id'], 'exist', 'skipOnError' => true, 'targetClass' => Attachment::className(), 'targetAttribute' => ['thumb_id' => 'id']],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => array_keys(self::statusList())],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'filename' => 'Filename',
            'image_id' => 'Image ID',
            'thumb_id' => 'Thumb ID',
            'type' => 'Type',
            'sort' => 'Sort',
            'status' => 'Статус',
            'statusName' => 'Статус',
            'created' => 'Создан',
            'updated' => 'Изменен',
            'deleted' => 'Удален',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getImage()
    {
        return $this->hasOne(Attachment::className(), ['id' => 'image_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getThumb()
    {
        return $this->hasOne(Attachment::className(), ['id' => 'thumb_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Yii::$app->user->identityClass, ['id' => 'user_id']);
    }

    /**
     * @inheritdoc
     * @return PicturesQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new PicturesQuery(get_called_class());
    }

    public function getOriginal()
    {
        return $this->image->getUrl();
    }

    public function getThumbnail($width = null, $height = null)
    {
        if (is_null($width) || is_null($height)) {
            return $this->thumb->getUrl();
        } else {
            $attachManager = $this->getAttachManager();
            return $attachManager->getThumbnail($this->thumb, $width, $height)->getUrl();
        }
    }

    protected function getAttachManager()
    {
        /** @var AttachManager $attachManager */
        $attachManager = Yii::$app->attachManager;
        return $attachManager;
    }
}
