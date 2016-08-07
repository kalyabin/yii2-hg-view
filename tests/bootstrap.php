<?php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

defined('VCS_DEBUG') or define('VCS_DEBUG', true);
defined('VCS_DEBUG_FILE') or define('VCS_DEBUG_FILE', __DIR__ . '/debug.log');

if (is_file(VCS_DEBUG_FILE)) {
    unlink(VCS_DEBUG_FILE);
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

Yii::setAlias('@tests', __DIR__);

new \yii\console\Application([
    'id' => 'unit',
    'basePath' => __DIR__,
    'params' => include __DIR__ . '/variables.local.php',
]);
