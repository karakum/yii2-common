<?php

namespace karakum\common\upload\models;

use karakum\common\components\StatusActiveRecord;
use karakum\PathRegistry\PathOrganizer;
use Yii;

/**
 * This is the model class for table "{{%attachment}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $path_id
 * @property string $name
 * @property string $file
 * @property string $mime_type
 * @property integer $size
 * @property integer $status
 * @property string $created
 * @property string $updated
 * @property string $deleted
 *
 * @property PathOrganizer $path
 * @property Users $user
 * @property Thumbnails[] $thumbnails
 * @property Users[] $users
 * @property Users[] $users0
 */
class Attachment extends StatusActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%attachment}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'path_id', 'name', 'file', 'mime_type'], 'required'],
            [['user_id', 'path_id', 'size', 'status'], 'integer'],
            [['name', 'file', 'mime_type'], 'string', 'max' => 255],
            [['path_id'], 'exist', 'skipOnError' => true, 'targetClass' => PathOrganizer::className(), 'targetAttribute' => ['path_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => Yii::$app->user->identityClass, 'targetAttribute' => ['user_id' => 'id']],
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
            'path_id' => 'Path ID',
            'name' => 'Name',
            'file' => 'File',
            'mime_type' => 'Mime Type',
            'size' => 'Size',
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
     * @return \yii\db\ActiveQuery
     */
    public function getThumbnails()
    {
        return $this->hasMany(Thumbnails::className(), ['attachment_id' => 'id']);
    }

    public function getFullName()
    {
        return $this->path->getBasePath($this->file);
    }

    public function getUrl($params = [])
    {
        return $this->path->getUrl($this->file, ['id' => $this->id, 'name' => $this->name] + $params);
    }

    /**
     * @inheritdoc
     * @return AttachmentQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new AttachmentQuery(get_called_class());
    }
}
