yii2-async-worker
=================
基于swoole写的yii2后台任务处理

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist shuliangfu/yii2-async-worker "*"
```

or add

```
"shuliangfu/yii2-async-worker": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

配置
```php

'components' => [
    'worker' => [
            'class' => 'shuliangfu\async\AsyncWorker',
            'options' => [
                'host'              => '0.0.0.0',
                'port'              => 9510,
                'process_name'      => 'async-worker',
                'open_tcp_nodelay'  => 1,
                'daemonize'         => 1,
                'worker_num'        => 2,
                'task_worker_num'   => 2,
                'task_max_request'  => 10000,
                'pidfile'           => Yii::getAlias('@console/runtime/tmp/async-worker.pid'),
                'task_tmpdir'       => Yii::getAlias('@console/runtime/tmp/async-worker/task'),
                'log_file'          => Yii::getAlias('@console/runtime/tmp/async-worker.log'),
                'log_size'          => 204800000,
                'client_timeout'    => 30,
            ]
        ];
]

'controllerMap' => [
    'async-worker' => [
         'class' => 'shuliangfu\async\AsyncWorkerController',
    ]
]

```

启动服务
```
php yii async-worker start   #启动
php yii async-worker stop    #停止
php yii async-worker restart #重启
php yii async-worker list    #查看进程
php yii async-worker stats   #查看状态

```

执行后台作务
```
执行单个任务
Yii::$app->worker->run([
    'route' => 'async-worker/send-mail',
    'argv' => ['admin@shuliangfu.com', '我在使用您的扩展', '我在使用您的扩展，谢谢']
]);

执行多个任务
Yii::$app->worker->run([
    [
        'route' => 'async-worker/send-mail',
        'argv' => ['username@youemail.com', '我在使用您的扩展', '我在使用您的扩展，谢谢']
    ],
    ...
    ,
]);
```