<?php
namespace tests;

use HgView\HgWrapper;
use HgView\Repository;
use PHPUnit_Framework_TestCase;
use Yii;

/**
 * Test wrapper
 */
class CaseWrapperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var array testing variables
     */
    protected $variables = [];

    /**
     * @inheritdoc
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->variables = Yii::$app->params['wrapper'];
    }

    /**
     * Wrapper constructor test
     *
     * @return HgWrapper
     */
    public function testConstructor()
    {
        $cmd = $this->variables['availCmd'];

        // set variables using constructor
        $wrapper = new HgWrapper([
            'cmd' => $cmd,
        ]);
        $this->assertInstanceOf(HgWrapper::class, $wrapper);
        $this->assertEquals($cmd, $wrapper->getCmd());

        // set variables without constructor
        $wrapper->setCmd($cmd);
        $this->assertEquals($cmd, $wrapper->getCmd());

        // check version
        $wrapper->checkVersion();
        $this->assertRegExp('/^([\d]+)\.?([\d]+)?\.?([\d]+)?$/', $wrapper->getVersion());

        return $wrapper;
    }

    /**
     * Tests wrapper constructor exceptions
     *
     * @param HgWrapper $wrapper
     *
     * @depends testConstructor
     * @expectedException VcsCommon\exception\CommonException
     */
    public function testContructorException(HgWrapper $wrapper)
    {
        $cmd = $this->variables['errorCmd'];
        $wrapper->setCmd($cmd);
    }

    /**
     * Tests command exceptions
     *
     * @param HgWrapper $wrapper
     *
     * @depends testConstructor
     * @expectedException VcsCommon\exception\CommonException
     */
    public function testCommandException(HgWrapper $wrapper)
    {
        $cmd = $this->variables['availCmd'];
        $wrapper->setCmd($cmd);
        $wrapper->execute(['random-command']);
    }

    /**
     * Test random command using repository
     *
     * @param HgWrapper $wrapper
     *
     * @depends testConstructor
     */
    public function testRandomCommand(HgWrapper $wrapper)
    {
        $cmd = $this->variables['availCmd'];
        $wrapper->setCmd($cmd);

        $command = ['log', '-r', 'tip:0', '--limit' => 10];
        $result = $cmd . ' log -r tip:0 --limit=10';

        $this->assertEquals($result, $wrapper->buildCommand($command));

        $stringResult = $wrapper->execute($command, $this->variables['availRepository'], false);
        $arrayResult = $wrapper->execute($command, $this->variables['availRepository'], true);
        $this->assertInternalType('string', $stringResult);
        $this->assertInternalType('array', $arrayResult);

        $this->assertNotEmpty($stringResult);
        $this->assertNotEmpty($arrayResult);
    }

    /**
     * Tests repository getter
     *
     * @param HgWrapper $wrapper
     * @depends testConstructor
     */
    public function testRepository(HgWrapper $wrapper)
    {
        $cmd = $this->variables['availCmd'];
        $repoPath = $this->variables['availRepository'];

        $wrapper->setCmd($cmd);
        $repository = $wrapper->getRepository($repoPath);
        $this->assertInstanceOf(Repository::class, $repository);
    }

    /**
     * Tests repository getter error
     *
     * @param HgWrapper $wrapper
     * @depends testConstructor
     * @expectedException VcsCommon\exception\CommonException
     */
    public function testRepositoryException(HgWrapper $wrapper)
    {
        $cmd = $this->variables['availCmd'];
        $repoPath = $this->variables['errorRepository'];

        $wrapper->setCmd($cmd);
        $wrapper->getRepository($repoPath);
    }
}
