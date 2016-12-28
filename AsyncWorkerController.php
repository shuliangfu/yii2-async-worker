<?php

namespace shuliangfu\async;

use Yii;
use shuliangfu\async\src\SwooleService;
use yii\console\Controller;


class AsyncWorkerController extends Controller
{

    public $defaultAction = 'run';
    public $options = [];


    public function init()
    {
        parent::init();

    }

    /**
     * 启动服务action
     * @param string $mode
     */
    public function actionRun($mode='start'){
        $service = new SwooleService(Yii::$app->worker->options,Yii::$app);
        switch ($mode) {
            case 'start':
                $service->serviceStart();
                break;
            case 'restart':
                $service->serviceRestart();
                break;
            case 'stop':
                $service->serviceStop();
                break;
            case 'stats':
                $service->serviceStats();
                break;
            case 'list':
                $service->serviceList();
                break;
            default:
                exit('error:参数错误');
                break;
        }
    }


    public function actionSendMail($to, $title, $message)
    {
        $mail = Yii::$app->mailer->compose();
        $mail->setTo($to);
        $mail->setSubject($title);
        $mail->setHtmlBody($message);
        $mail->send();
    }
}