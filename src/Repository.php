<?php
namespace HgView;

use VcsCommon\BaseRepository;
use VcsCommon\exception\CommonException;
use VcsCommon\File;
use yii\helpers\FileHelper;
use VcsCommon\Graph;

/**
 * Repository class
 * Provides access control to project repository
 */
class Repository extends BaseRepository
{
    /**
     * Revision template at log representation
     */
    const LOG_FORMAT = '{rev}\n{parents}\n{author|person}\n{author|email}\n{date|isodate}\n{desc|firstline}\n\n';

    /**
     * @var HgWrapper Common Mercurial interface
     */
    protected $wrapper;

    /**
     * Returns true if repository exists in project path.
     *
     * @return boolean
     * @throws CommonException
     */
    protected function checkRepository()
    {
        // the command returns path to real repository
        // this path must equals current project path
        $result = $this->wrapper->execute(['--cwd', $this->projectPath, 'root']);
        return str_replace("\n", '', $result) === $this->projectPath;
    }

    /**
     * Check repository status and returns it.
     *
     * @return string
     * @throws CommonException
     */
    public function checkStatus()
    {
        $result = $this->wrapper->execute(['status'], $this->projectPath);
        return $result;
    }

    public function getBranches()
    {
        $ret = [];

        // detect current selected branch
        $currentBranch = str_replace("\n", '', $this->wrapper->execute(['branch'], $this->projectPath));

        // get all opened branches
        $result = $this->wrapper->execute(['branches'], $this->projectPath, true);

        $pattern = '#^([^\s]+)[\s]+([0-9]+)\:[\d]+#iU';

        foreach ($result as $row) {
            $matches = [];
            if (preg_match($pattern, $row, $matches)) {
                $ret[] = new Branch($this, [
                    'id' => $matches[1],
                    'head' => $matches[2],
                    'isCurrent' => $matches[1] === $currentBranch,
                ]);
            }
        }

        return $ret;
    }

    /**
     * @inheritdoc
     */
    public function pathIsNotIgnored($filePath)
    {
        $path = ltrim(FileHelper::normalizePath($filePath), DIRECTORY_SEPARATOR);

        $command = [
            'status', '-i', escapeshellcmd($path),
        ];

        $result = $this->wrapper->execute($command, $this->projectPath, true, true);

        foreach ($result as $row) {
            $row = preg_replace('#^I[\s]+(.*)#i', '$1', $row);
            if ($row === $filePath) {
                return false;
            }
        }

        return true;
    }

    /**
     * From string with parents revisions splitting by space like this:
     *
     * ```
     * 1:a2324ab 2:2adfe 3:12345 4:etc
     * ```
     *
     * Generate array like this:
     * ```php
     * array(
     *     '1', '2', '3', '4', // etc
     * )
     * ```
     *
     * Resulting array contains only revisions numbers.
     *
     * @param string $parents Parents ids splitting by space
     *
     * @return array Parents revisions numbers
     */
    protected function prepareParentsIds($parents)
    {
        $result = explode(' ', $parents);

        foreach ($result as $k => $parent) {
            $result[$k] = preg_replace('#^([\d]+)\:.*#i', '$1', $parent);
            if (!trim($result[$k])) {
                unset ($result[$k]);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getCommit($id)
    {
        $command = [
            'log', '--rev' => escapeshellcmd($id), '--template' => '"' . self::LOG_FORMAT . '"',
        ];

        $result = $this->wrapper->execute($command, $this->projectPath, true);
        list ($id, $parents, $contributorName, $contributorEmail, $date, $message) = $result;
        // explode parents
        $parents = $this->prepareParentsIds($parents);

        $commit = new Commit($this, [
            'id' => $id,
            'parentsId' => $parents,
            'contributorName' => $contributorName,
            'contributorEmail' => $contributorName === $contributorEmail ? '' : $contributorEmail,
            'date' => $date,
            'message' => $message,
        ]);

        // get changed files
        $files = $this->wrapper->execute([
            'status', '--change' => escapeshellcmd($commit->getId()),
        ], $this->projectPath, true);
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
                $commit->appendChangedFile(new File(
                    $this->projectPath . DIRECTORY_SEPARATOR . $pieces[1],
                    $this,
                    $status
                ));
            }
        }

        return $commit;
    }

    /**
     * Returns beginner revision number by skipped revisions.
     *
     * Returns -1 if no revisions to be returned and null if no requirements to skip.
     *
     * @param integer $skip revisions to be skipped
     * @return integer|null
     */
    protected function calculateBeginRevisionLog($skip)
    {
        if ($skip) {
            $currentRevisionNumber = (int) $this->wrapper->execute([
                'parent', '--template' => '"{rev}"',
            ], $this->projectPath);
            if ($currentRevisionNumber <= $skip) {
                return -1;
            }
            return $currentRevisionNumber - $skip;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getHistory($limit, $skip, $path = null)
    {
        $ret = [];

        $skip = (int) $skip;
        $limit = (int) $limit;

        if ($limit <= 0 || $skip < 0) {
            throw new CommonException();
        }

        $command = [
            'log',
        ];

        // detect begin revision number
        $fromRevision = $this->calculateBeginRevisionLog($skip);
        if ($fromRevision === -1) {
            // the end of search
            return $ret;
        }
        else if (!is_null($fromRevision)) {
            $command['--rev'] = $fromRevision . ':0';
        }

        $command['--limit'] = $limit;
        $command['--template'] = '"' . self::LOG_FORMAT . '"';

        if (!empty($path)) {
            $command[] = escapeshellcmd($path);
        }

        $result = $this->wrapper->execute($command, $this->projectPath, true);

        $commit = [];
        foreach ($result as $row) {
            if (count($commit) < 6) {
                $commit[] = $row;
            }
            else {
                list ($id, $parents, $contributorName, $contributorEmail, $date, $message) = $commit;
                // explode parents
                $parents = $this->prepareParentsIds($parents);
                $ret[] = new Commit($this, [
                    'id' => $id,
                    'parentsId' => $parents,
                    'contributorName' => $contributorName,
                    'contributorEmail' => $contributorName === $contributorEmail ? '' : $contributorEmail,
                    'date' => $date,
                    'message' => $message,
                ]);
                $commit = [];
            }
        }

        return $ret;
    }

    /**
     * @inheritdoc
     */
    public function getGraphHistory($limit, $skip, $path = null)
    {
        $ret = new Graph();

        $rawHistory = $this->getHistory($limit, $skip);

        $command = [
            'log', '-G', '--template' => ' ', '--limit' => (int) $limit,
        ];

        // detect begin revision number
        $fromRevision = $this->calculateBeginRevisionLog((int) $skip);
        if ($fromRevision === -1) {
            // the end of search
            return $ret;
        }
        else if (!is_null($fromRevision)) {
            $command['--rev'] = $fromRevision . ':0';
        }

        $result = $this->wrapper->execute($command, $this->projectPath, true);

        $cursor = 0;
        foreach ($result as $row) {
            $row = str_replace(' ', '', $row);
            if (strpos($row, 'o') !== false && isset($rawHistory[$cursor])) {
                $rawHistory[$cursor]->graphLevel = strpos($row, '*');
                $ret->pushCommit($rawHistory[$cursor]);
                $cursor++;
            }
        }

        return $ret;
    }

    public function getDiff()
    {
        /**
         * @todo write a logic
         */
    }
}
