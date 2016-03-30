<?php
namespace HgView;

use HgView\Repository;
use VcsCommon\BaseWrapper;
use VcsCommon\exception\CommonException;

/**
 * This class provides access to hg console command and implements common methods.
 */
class HgWrapper extends BaseWrapper
{
    /**
     * @var string path to console hg command
     */
    protected $cmd = 'hg';

    /**
     * Returns repository path name like .git, .hg, etc.
     *
     * @return string
     */
    public function getRepositoryPathName()
    {
        return '.hg';
    }

    /**
     * Checks mercurial version and set it to version property.
     *
     * @throws CommonException
     */
    public function checkVersion()
    {
        $pattern = '#version[\s]([\d]+\.?([\d]+)?\.([\d]+)?)#';

        $result = $this->execute('version');
        if (!preg_match($pattern, $result, $matches)) {
            throw new CommonException('HG command not found');
        }
        $this->version = $matches[1];
    }

    /**
     * Create repository instance by provided directory.
     * Directory must be a path of project (not a .hg path).
     *
     * @param string $dir project directory
     * @return Repository
     * @throws CommonException
     */
    public function getRepository($dir)
    {
        /**
         * @todo create method logic
         */
    }
}

