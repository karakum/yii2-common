<?php

use karakum\common\upload\AttachManager;
use karakum\PathRegistry\PathManager;
use yii\base\InvalidConfigException;
use yii\db\Migration;

/**
 * Handles the creation for table `attachment`.
 */
class m160607_101050_create_attachment extends Migration
{
    /**
     * @throws yii\base\InvalidConfigException
     * @return PathManager
     */
    protected function getPathManager()
    {
        try {
            $pathManager = Yii::$app->pathManager;
        } catch (Exception $e) {
            throw new InvalidConfigException('pathManager instantiate error:' . $e->getMessage());
        }
        if (!$pathManager instanceof PathManager) {
            throw new InvalidConfigException('You should configure "pathManager" component to use database before executing this migration.');
        }
        return $pathManager;
    }

    /**
     * @throws InvalidConfigException
     * @return AttachManager
     */
    protected function getAttachManager()
    {
        try {
            $attachManager = Yii::$app->attachManager;
        } catch (Exception $e) {
            throw new InvalidConfigException('attachManager instantiate error:' . $e->getMessage());
        }
        if (!$attachManager instanceof AttachManager) {
            throw new InvalidConfigException('You should configure "attachManager" component to use database before executing this migration.');
        }
        return $attachManager;
    }

    /**
     * @inheritdoc
     */
    public function up()
    {
        $pathManager = $this->getPathManager();
        $attachManager = $this->getAttachManager();

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%attachment}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'path_id' => $this->integer()->notNull(),

            'name' => $this->string()->notNull(),
            'file' => $this->string()->notNull(),
            'mime_type' => $this->string()->notNull(),
            'size' => $this->integer(),

            'status' => $this->smallInteger()->notNull()->defaultValue(0),
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
            'deleted' => 'TIMESTAMP NULL DEFAULT NULL',
        ], $tableOptions);


        $this->createTable('{{%thumbnails}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'path_id' => $this->integer()->notNull(),
            'attachment_id' => $this->integer()->notNull(),

            'width' => $this->smallInteger()->notNull(),
            'height' => $this->smallInteger()->notNull(),
            'file' => $this->string()->notNull(),
            'mime_type' => $this->string()->notNull(),
            'size' => $this->integer(),
        ], $tableOptions);

        $identityClass = $attachManager->identityClass;
        $userTable = $identityClass::tableName();

        $this->addForeignKey('fk-attachment-user_id', '{{%attachment}}', 'user_id', $userTable, 'id', 'RESTRICT', 'RESTRICT');
        $this->addForeignKey('fk-attachment-path_id', '{{%attachment}}', 'path_id', $pathManager->pathTable, 'id', 'RESTRICT', 'RESTRICT');

        $this->addForeignKey('fk-thumbnails-user_id', '{{%thumbnails}}', 'user_id', $userTable, 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk-thumbnails-path_id', '{{%thumbnails}}', 'path_id', $pathManager->pathTable, 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk-thumbnails-attachment_id', '{{%thumbnails}}', 'attachment_id', '{{%attachment}}', 'id', 'CASCADE', 'RESTRICT');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('{{%thumbnails}}');
        $this->dropTable('{{%attachment}}');
    }

    public function addAttachmentColumn($table, $column)
    {
        $t = $this->getDb()->getSchema()->getRawTableName($table);
        $this->addColumn($table, $column, $this->integer());
        $this->addForeignKey("fk-$t-$column-attachment-id", $table, $column, '{{%attachment}}', 'id', 'SET NULL', 'RESTRICT');
    }

    public function dropAttachmentColumn($table, $column)
    {
        $t = $this->getDb()->getSchema()->getRawTableName($table);
        $this->dropForeignKey("fk-$t-$column-attachment-id", $table);
        $this->dropColumn($table, $column);
    }
}
