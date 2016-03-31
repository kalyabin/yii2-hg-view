<?php

namespace tests;

use HgView\Branch;
use HgView\Commit;
use HgView\HgWrapper;
use HgView\Repository;
use PHPUnit_Framework_TestCase;
use VcsCommon\exception\CommonException;
use VcsCommon\File;
use Yii;
use VcsCommon\Graph;
use HgView\Diff;

/**
 * Test repository
 */
class CaseRepositoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var array testing variables
     */
    protected $variables = [];

    /**
     * @var Repository repository model
     */
    protected $repository;

    /**
     * @inheritdoc
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->variables = Yii::$app->params['repository'];

        $wrapper = new HgWrapper();
        $repoPath = Yii::$app->params['wrapper']['availRepository'];
        $this->repository = $wrapper->getRepository($repoPath);
    }

    /**
     * Tests file list
     */
    public function testFileList()
    {
        $fileList = $this->repository->getFilesList();
        $this->assertNotEmpty($fileList);
        $this->assertContainsOnlyInstancesOf(File::className(), $fileList);
    }

    /**
     * Tests file list exception
     *
     * @expectedException VcsCommon\exception\CommonException
     */
    public function testFileListOutbandException()
    {
        $this->repository->getFilesList('/tmp/');
    }

    /**
     * Tests file list exception
     *
     * @expectedException VcsCommon\exception\CommonException
     */
    public function testFileListRepositoryException()
    {
        $this->repository->getFilesList($this->repository->getRepositoryPath());
    }

    /**
     * Tests check status
     */
    public function testCheckStatus()
    {
        $this->repository->checkStatus();
    }

    /**
     * Test branches
     */
    public function testBranches()
    {
        $branches = $this->repository->getBranches();
        $this->assertNotEmpty($branches);
        $this->assertContainsOnlyInstancesOf(Branch::className(), $branches);

        // check if current branch exists
        $currentBranchExists = false;
        foreach ($branches as $branch) {
            /* @var $branch Branch */
            if ($branch->getIsCurrent()) {
                $currentBranchExists = true;
                break;
            }
        }

        $this->assertTrue($currentBranchExists);
    }

    /**
     * Tests ignored and not ignored files
     */
    public function testIgnore()
    {
        // check full path ignored files
        $this->assertFalse($this->repository->pathIsNotIgnored($this->variables['ignoredPath']));
        $this->assertTrue($this->repository->pathIsNotIgnored($this->variables['notIgnoredPath']));
    }
}
