<?php

namespace karakum\common\upload\models;
use karakum\common\components\BaseQuery;

/**
 * This is the ActiveQuery class for [[Attachment]].
 *
 * @see Attachment
 */
class AttachmentQuery extends BaseQuery
{
    /**
     * @inheritdoc
     * @return Attachment[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Attachment|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
