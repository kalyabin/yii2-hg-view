<?php
namespace tests;

use HgView\HgWrapper;
use HgView\Repository;
use PHPUnit_Framework_TestCase;
use Yii;
use VcsCommon\File;
use HgView\Commit;
use HgView\Diff;

/**
 * Test commit
 */
class CaseCommitTest extends PHPUnit_Framework_TestCase
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
        $this->variables = Yii::$app->params['commit'];

        $wrapper = new HgWrapper();
        $repoPath = Yii::$app->params['wrapper']['availRepository'];
        $this->repository = $wrapper->getRepository($repoPath);
    }

    /**
     * Tests commit variables
     */
    public function testCommitVariables()
    {
        $commit = $this->repository->getCommit($this->variables['diff']);

        $this->assertInstanceOf('DateTime', $commit->getDate());

        /* @var $wrapper HgWrapper */
        $wrapper = $this->repository->getWrapper();
        $this->assertGreaterThan(0, (int) $commit->getId());
        $this->assertNotEmpty($commit->contributorName);
        $this->assertNotEmpty($commit->message);
        $this->assertNotEmpty($commit->getParentsId());
        foreach ($commit->getParentsId() as $parentId) {
            $this->assertGreaterThan(0, (int) $parentId);
        }
        $this->assertNotEmpty($commit->getChangedFiles());
        $this->assertContainsOnly(File::className(), $commit->getChangedFiles());
        $lastFilePath = null;
        $lastFileStatus = null;
        foreach ($commit->getChangedFiles() as $item) {
            $this->assertInstanceOf(File::className(), $item);
            $this->assertInternalType('string', $item->getStatus());
            $this->assertInternalType('string', $item->getPath());
            $this->assertInternalType('string', $item->getPathname());
            $lastFilePath = $item->getPathname();
            $lastFileStatus = $item->getStatus();
        }

        $this->assertEquals($commit->getFileStatus($lastFilePath), $lastFileStatus);
        $lastFile = $commit->getFileByPath($lastFilePath);
        $this->assertInstanceOf(File::className(), $lastFile);
        $this->assertEquals($lastFilePath, $lastFile->getPathname());
        $this->assertEquals($lastFileStatus, $lastFile->getStatus());

        return $commit;
    }

    /**
     * Test commit diff
     *
     * @depends testCommitVariables
     * @param Commit $commit
     */
    public function testCommitDiff(Commit $commit)
    {
        $diffs = $commit->getDiff();
        $this->assertNotEmpty($diffs);
        $this->assertContainsOnlyInstancesOf(Diff::className(), $diffs);
        foreach ($diffs as $diff) {
            /* @var $diff Diff */
            $this->assertInternalType('string', $diff->getDescription());
            $this->assertInternalType('string', $diff->getNewFilePath());
            $this->assertNotEmpty($diff->getNewFilePath());
            $this->assertNotEmpty($diff->getPreviousFilePath());
            $this->assertContainsOnly('array', $diff->getLines());
            foreach ($diff->getLines() as $diffKey => $lines) {
                $this->assertInternalType('string', $diffKey);
                $this->assertRegExp('#^@@[\s]\-([\d]+),?([\d]+)?[\s]\+([\d]+),?([\d]+)?[\s]@@#i', $diffKey);
                $this->assertArrayHasKey('beginA', $lines);
                $this->assertArrayHasKey('beginB', $lines);
                $this->assertArrayHasKey('cntA', $lines);
                $this->assertArrayHasKey('cntB', $lines);
                $this->assertInternalType('integer', $lines['beginA']);
                $this->assertInternalType('integer', $lines['beginB']);
                $this->assertInternalType('integer', $lines['cntA']);
                $this->assertInternalType('integer', $lines['cntB']);
                $this->assertArrayHasKey('lines', $lines);
                $this->assertInternalType('array', $lines['lines']);
                $this->assertNotEmpty($lines['lines']);
                $this->assertContainsOnly('string', $lines['lines']);
                foreach ($lines['lines'] as $line) {
                    if (!empty($line)) {
                        $this->assertRegExp('#^([\s]|\+|\-|\\\\){1}#i', $line);
                    }
                }
            }
        }

        return $commit;
    }

    /**
     * Test commit raw file
     *
     * @depends testCommitDiff
     * @param Commit $commit
     */
    public function testCommitRawFile(Commit $commit)
    {
        $this->assertInternalType('string', $commit->getFileStatus($this->variables['rawFile']));
        $rawFile = $commit->getRawFile($this->variables['rawFile']);
        $this->assertInternalType('string', $rawFile);
    }

    /**
     * Test deleted raw file
     *
     * @depends testCommitRawFile
     */
    public function testCommitDeletedRawFile()
    {
        $commit = $this->repository->getCommit($this->variables['deletedCommitId']);
        $this->assertInstanceOf(Commit::className(), $commit);
        $this->assertEquals('D', $commit->getFileStatus($this->variables['deletedRawFile']));
        $rawFile = $commit->getPreviousRawFile($this->variables['deletedRawFile']);
        $this->assertInternalType('string', $rawFile);
    }

    /**
     * Tests binary binary file
     */
    public function testBinary()
    {
        $wrapper = new HgWrapper();

        $fileSize = $this->variables['binaryTest']['fileSize'];

        $repository = $wrapper->getRepository($this->variables['binaryTest']['projectPath']);
        $commit = $repository->getCommit($this->variables['binaryTest']['commitId']);
        $commit->getRawBinaryFile($this->variables['binaryTest']['filePath'], function($data) use (&$fileSize) {
            $fileSize -= strlen($data);
        });

        $this->assertEquals(0, $fileSize);
    }
}
