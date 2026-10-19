<?php
return [
    'basePath'=>dirname(__DIR__), 'name'=>'Comment Room', 'timeZone'=>'UTC',
    'import'=>['application.models.*', 'application.components.*'],
    'defaultController'=>'site',
    'components'=>[
        'db'=>['connectionString'=>'sqlite:'.(getenv('DB_PATH') ?: '/data/comments.sqlite')],
        'request'=>['enableCsrfValidation'=>true, 'csrfCookie'=>['httpOnly'=>true, 'sameSite'=>'Lax']],
        'session'=>['cookieParams'=>['httponly'=>true, 'samesite'=>'Lax']],
        'user'=>['allowAutoLogin'=>false],
        'errorHandler'=>['errorAction'=>'site/error'],
    ],
    'onBeginRequest'=>function() { Database::initialize(); },
];
