<?php
/**
 * Local variables to test
 */

return [
    'wrapper' => [
        'availCmd' => 'hg',
        'errorCmd' => 'wrong-hg',
        'availRepository' => '/path/to/mercurial/repository',
        'errorRepository' => '/tmp',
    ],
    'repository' => [
        'commitDiff' => 'tip',
        'commitCompare' => [
            '2200',
            'tip',
        ],
        'commitFileDiff' => [
            'tip', 'test.txt',
        ],
        'pathHistory' => '/path/to/single/file',
        'ignoredPath' => '/path/to/ignored/file',
        'notIgnoredPath' => '/path/to/not/ignored/file',
    ],
    'commit' => [
        'diff' => '1244f5a7409604e4027e9d97538f65d32767fb14',
        'rawFile' => [
            'commitId' => '95c5af88b6b83e66f67e09962d92a289d7916a18',
            'file' => 'Directory.php'
        ],
    ],
];
