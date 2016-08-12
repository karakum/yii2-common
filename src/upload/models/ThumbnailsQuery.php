<?php

namespace karakum\common\upload\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[Thumbnails]].
 *
 * @see Thumbnails
 */
class ThumbnailsQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return Thumbnails[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Thumbnails|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
