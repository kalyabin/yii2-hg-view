<?php
namespace HgView;

use DateTime;
use HgView\Diff;
use VcsCommon\BaseCommit;
use yii\helpers\StringHelper;

/**
 * Represents Mercurial commit model
 */
class Commit extends BaseCommit
{
    const DATE_TIME_FORMAT = 'Y-m-d H:i O';

    /**
     * @inheritdoc
     */
    protected function parseDateInternal($value)
    {
        return DateTime::createFromFormat(self::DATE_TIME_FORMAT, $value);
    }

    /**
     * @inheritdoc
     */
    public function getDiff($file = null)
    {
        /**
         * @todo write a logic
         */
    }

    /**
     * @inheritdoc
     */
    public function getRawFile($filePath)
    {
        /**
         * @todo write a logic
         */
    }

    /**
     * @inheritdoc
     */
    public function getPreviousRawFile($filePath)
    {
        /**
         * @todo write a logic
         */
    }
}
