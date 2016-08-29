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

        $pattern = '#^([^\s]+)[\s]+([0-9]+)\:[\w]+#iU';

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
            'log', '--encoding' => 'utf-8', '--rev' => escapeshellcmd($id), '--template' => '"' . self::LOG_FORMAT . '"',
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
                'parent', '--encoding' => 'utf-8', '--template' => '"{rev}"',
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
            'log', '--encoding' => 'utf-8',
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
            'log', '--encoding' => 'utf-8', '-G', '--template' => ' ', '--limit' => (int) $limit,
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

    /**
     * Returns valid revision number:
     *
     * - if rev === tip - returns it;
     * - else returns valid integer;
     *
     * @param string|integer $rev Revision number
     * @return string|integer valid revision number
     */
    protected function getValidRevisionNumber($rev)
    {
        return $rev === 'tip' ? $rev : (int) $rev;
    }

    /**
     * Returns diff by specific command line params.
     *
     * Can receive everybody params for command line like this:
     *
     * ```php
     * $wrapper = new HgWrapper();
     * $repo = $wrapper->getRepository('/path/to/repository');
     *
     * // get revision diff:
     * print_r($repo->getDiff('commit', <rev>));
     *
     * // get commit compare
     * print_r($repo->getDiff('compare', <first_rev>, <last_rev>);
     *
     * // get file diff
     * print_r($repo->getDiff('file', '/path/to/file');
     *
     * // get file diff for specific revision
     * print_r($repo->getDiff('file', '/path/to/file', <rev>);
     *
     * // get full repo diff
     * print_r($repo->getDiff('repository');
     * ```
     *
     * @see \kalyabin\VcsCommon\BaseRepository::getDiff()
     * @return string[] line-by-line diffs
     * @throws CommonException
     */
    public function getDiff()
    {
        $command = ['diff', '--git', '--encoding' => 'utf-8',];

        $type = func_num_args() >= 1 ? func_get_arg(0) : null;
        $arg1 = func_num_args() >= 2 ? func_get_arg(1) : null;
        $arg2 = func_num_args() >= 3 ? func_get_arg(2) : null;

        if ($type === self::DIFF_COMMIT && !is_null($arg1)) {
             // commit diff command requires second param a revision number
            $command[] = '-c';
            $command[] = $this->getValidRevisionNumber($arg1);
        }
        else if ($type === self::DIFF_COMPARE && !is_null($arg1) && !is_null($arg2)) {
            // commits compare requires second and third params a revision numbers (first revision and last revision to compare)
            $command[] = '-r';
            $command[] = $this->getValidRevisionNumber($arg1) . ':' . $this->getValidRevisionNumber($arg2);
        }
        else if ($type == self::DIFF_PATH && is_string($arg1)) {
            // path diff requires second param a path of project file (or directory)
            // if this is not a valid path - HgWrapper throws CommonException
            if (!is_null($arg2)) {
                // specific revision number
                $command[] = '-c';
                $command[] = $this->getValidRevisionNumber($arg2);
            }
            // project path to compare
            $command[] = escapeshellcmd($arg1);
        }
        else if ($type == self::DIFF_REPOSITORY) {
            // full repo diff
            // nobody extended params in this case
        }
        else {
            // unknown params
            throw new CommonException('Type a valid command');
        }

        return $this->wrapper->execute($command, $this->projectPath, true);
    }
}
