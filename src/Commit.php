<?php
namespace HgView;

use DateTime;
use HgView\Diff;
use VcsCommon\BaseCommit;
use VcsCommon\exception\CommonException;
use VcsCommon\File;
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
        $previewFile = [];
        $ret = [];

        $appendFileDiff = function() use (&$previewFile, &$ret) {
            if (!empty($previewFile)) {
                $ret[] = new Diff($previewFile);
                $previewFile = [];
            }
        };

        $fullDiff = [];
        if (!is_null($file)) {
            $fullDiff = $this->repository->getDiff(Repository::DIFF_PATH, $file, $this->id);
        }
        else {
            $fullDiff = $this->repository->getDiff(Repository::DIFF_COMMIT, $this->id);
        }

        foreach ($fullDiff as $row) {
            if (StringHelper::startsWith($row, 'diff')) {
                // the new file diff, append to $ret
                $appendFileDiff();
            }
            $previewFile[] = $row;
        }

        // append last file diff to full array
        $appendFileDiff();

        return $ret;
    }

    /**
     * @inheritdoc
     */
    public function getRawFile($filePath)
    {
        $params = [
            'cat', '--encoding' => 'utf-8', '--rev' => $this->id, escapeshellcmd($filePath),
        ];
        return $this->repository->getWrapper()->execute($params, $this->repository->getProjectPath());
    }

    /**
     * @inheritdoc
     */
    public function getRawBinaryFile($filePath, $streamHandler)
    {
        throw new CommonException('This function is not supported');
    }

    /**
     * @inheritdoc
     */
    public function getPreviousRawFile($filePath)
    {
        if (!empty($this->parentsId)) {
            // get first parent to view old version of file
            $parentId = reset($this->parentsId);
            $params = [
                'cat', '--encoding' => 'utf-8', '--rev' => $parentId, escapeshellcmd($filePath),
            ];
            return $this->repository->getWrapper()->execute($params, $this->repository->getProjectPath());
        }

        // if hasnt parents - return as empty file
        return '';
    }

    /**
     * @inheritdoc
     */
    protected function getChangedFilesInternal()
    {
        $wrapper = $this->repository->wrapper;

        // get changed files
        $files = $wrapper->execute([
            'status', '--encoding' => 'utf-8', '--change' => escapeshellcmd($this->getId()),
        ], $this->repository->projectPath, true);
        foreach ($files as $file) {
            $pieces = preg_split('#[\s]+#', trim($file), 2);
            if (count($pieces) === 2) {
                $status = File::STATUS_UNKNOWN;
                switch ($pieces[0]) {
                    case 'M':
                        $status = File::STATUS_MODIFIED;
                        break;
                    case 'A':
                    case '?':
                        $status = File::STATUS_ADDITION;
                        break;
                    case 'R':
                    case '!':
                        $status = File::STATUS_DELETION;
                        break;
                }
                yield $pieces[1] => $status;
            }
        }
    }
}
