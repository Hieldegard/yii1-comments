<?php
require dirname(__DIR__, 2).'/vendor/yiisoft/yii/framework/yii.php';
Yii::createWebApplication(dirname(__DIR__).'/protected/config/main.php')->run();
