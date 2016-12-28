<?php
/**
 * 版权所有: © YIISHOPS(桂林心动了吧) 2016，并保留所有权利。
 * 网站地址: http://www.yiishops.com；
 * ----------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改
 * 和使用；不允许对程序代码以任何形式任何目的的再发布。如需要用作商业用
 * 途必须先购买版权，否则有可能会追究您的法律责任
 * ----------------------------------------------------------
 * 开发作者: 舒良府
 * 项目名称: yiishops
 * 文件名称: AsyncWorkerController.php
 * 编写时间: 2016/12/26 上午11:57
 * 开发工具: PhpStorm
 */

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