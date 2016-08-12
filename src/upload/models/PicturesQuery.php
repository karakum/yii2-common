<?php

namespace karakum\common\upload\models;

use karakum\common\components\BaseQuery;

/**
 * This is the ActiveQuery class for [[Pictures]].
 *
 * @see Pictures
 */
class PicturesQuery extends BaseQuery
{
    /**
     * @inheritdoc
     * @return Pictures[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Pictures|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
