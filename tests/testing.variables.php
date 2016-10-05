<?php
/**
 * Local variables to test
 */

return [
    // repository wich to be tested
    'repositoryUrl' => 'https://kalyabin@bitbucket.org/kalyabin/yii2-hg-view-testing',
    'repositoryPath' => __DIR__ . '/repo/testing-repo',

    // variables for HgWrapper tests
    'wrapper' => [
        'availCmd' => 'hg',
        'errorCmd' => 'wrong-hg',
        'availRepository' => __DIR__ . '/repo/testing-repo',
        'errorRepository' => '/tmp',
    ],

    // variables for Repository tests
    'repository' => [
        'commitDiff' => '5',
        'commitCompare' => [
            '1',
            '5',
        ],
        'commitFileDiff' => [
            '5', 'testing.txt',
        ],
        'pathHistory' => 'testing.txt',
        'ignoredPath' => 'ignored.txt',
        'notIgnoredPath' => 'contributors.txt',
    ],

    // variables for Commit tests
    'commit' => [
        'diff' => '3',
        'rawFileNew' => [
            'commitId' => '6',
            'file' => 'file_to_remove.txt',
        ],
        'rawFileDeleted' => [
            'commitId' => '7',
            'file' => 'file_to_remove.txt',
        ],
        'rawFileUpdated' => [
            'commitId' => '3',
            'file' => 'testing.txt',
        ],
        'rawFileNotUpdated' => [
            'commitId' => '8',
            'file' => 'testing.txt',
        ],
        'binaryTest' => [
            'commitId' => '9',
            'filePath' => 'binary_file.png',
            'fileSize' => 42969,
        ],
    ],
];
