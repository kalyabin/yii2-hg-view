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
        'diff' => '1194',
        'rawFile' => 'Directory.php',
        'deletedCommitId' => '1183',
        'deletedRawFile' => 'personal/ordercheck/cancel/index.php',
    ],
];
