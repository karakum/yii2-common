<?php

namespace karakum\common\upload\models;

use karakum\PathRegistry\PathOrganizer;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%thumbnails}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $path_id
 * @property integer $attachment_id
 * @property integer $width
 * @property integer $height
 * @property string $file
 * @property string $mime_type
 * @property integer $size
 *
 * @property Attachment $attachment
 * @property PathOrganizer $path
 * @property Users $user
 */
class Thumbnails extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%thumbnails}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'path_id', 'attachment_id', 'width', 'height', 'file', 'mime_type'], 'required'],
            [['user_id', 'path_id', 'attachment_id', 'width', 'height', 'size'], 'integer'],
            [['file', 'mime_type'], 'string', 'max' => 255],
            [['attachment_id'], 'exist', 'skipOnError' => true, 'targetClass' => Attachment::className(), 'targetAttribute' => ['attachment_id' => 'id']],
            [['path_id'], 'exist', 'skipOnError' => true, 'targetClass' => PathOrganizer::className(), 'targetAttribute' => ['path_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => Yii::$app->user->identityClass, 'targetAttribute' => ['user_id' => 'id']],
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
            'path_id' => 'Path ID',
            'attachment_id' => 'Attachment ID',
            'width' => 'Width',
            'height' => 'Height',
            'file' => 'File',
            'mime_type' => 'Mime Type',
            'size' => 'Size',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAttachment()
    {
        return $this->hasOne(Attachment::className(), ['id' => 'attachment_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPath()
    {
        return $this->hasOne(PathOrganizer::className(), ['id' => 'path_id']);
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
     * @return ThumbnailsQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ThumbnailsQuery(get_called_class());
    }

    public function getFullName()
    {
        return $this->path->getBasePath($this->file);
    }

    public function getUrl()
    {
        return $this->path->getUrl($this->file, ['id' => $this->id, 'name' => $this->name]);
    }

}
