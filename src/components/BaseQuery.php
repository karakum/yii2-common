<?php

namespace karakum\common\components;


class BaseQuery extends \yii\db\ActiveQuery
{
    /**
     * @return $this
     */
    public function notMarked()
    {
        $modelClass = $this->modelClass;
        $tableName = $modelClass::tableName();

        return $this->andWhere([$tableName . '.deleted' => null]);
    }

    public function active()
    {
        $modelClass = $this->modelClass;
        $tableName = $modelClass::tableName();

        return $this->andWhere([$tableName . '.status' => StatusActiveRecord::STATUS_ACTIVE]);
    }

}