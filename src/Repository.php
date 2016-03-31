<?php
namespace HgView;

use VcsCommon\BaseRepository;

/**
 * Repository class
 * Provides access control to project repository
 */
class Repository extends BaseRepository
{
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
        $command = [
            'status', '-i', ltrim(\yii\helpers\FileHelper::normalizePath($filePath), DIRECTORY_SEPARATOR),
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

    public function getCommit($id)
    {
        /**
         * @todo write a logic
         */
    }

    public function getHistory($limit, $skip, $path = null)
    {
        /**
         * @todo write a logic
         */
    }

    public function getGraphHistory($limit, $skip)
    {
        /**
         * @todo write a logic
         */
    }

    public function getDiff()
    {
        /**
         * @todo write a logic
         */
    }
}
