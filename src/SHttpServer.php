<?php
/**
 * Swoole 实现的 http server,用来处理异步多进程任务
 */

namespace shuliangfu\async\src;

use yii\base\Exception;
use swoole_server;

class SHttpServer
{
    /**
     * swoole http-server 实例
     * @var null|swoole_server
     */
    private $server = null;

    /**
     * swoole 配置
     * @var array
     */
    private $setting = [];

    /**
     * Yii::$app 对象
     * @var array
     */
    private $app = null;

    /**
     * SHttpServer constructor.
     * @param array $setting
     * @param \Yii::$app $app
     */
    public function __construct($setting, $app)
    {
        $this->setting = $setting;
        $this->app = $app;
    }

    /**
     * 设置swoole进程名称
     * @param string $name
     * @return bool
     */
    private function setProcessName($name)
    {
        //MAC下进程名称设置不了
        if (PHP_OS == 'Darwin') {
            return false;
        }
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__ . " failed.require cli_set_process_title or swoole_set_process_name.");
            }
        }
    }

    /**
     * 运行服务
     * @return bool
     */
    public function run()
    {
        $this->server = new swoole_server($this->setting['host'], $this->setting['port']);
        $this->server->set($this->setting);
        //回调函数
        $call = [
            'start',
            'workerStart',
            'managerStart',
            'receive',
            'task',
            'finish',
            'workerStop',
            'shutdown',
        ];
        //事件回调函数绑定
        foreach ($call as $v) {
            $m = 'on' . ucfirst($v);
            if (method_exists($this, $m)) {
                $this->server->on($v, [$this, $m]);
            }
        }

        echo "服务成功启动" . PHP_EOL;
        echo "服务运行名称:{$this->setting['process_name']}" . PHP_EOL;
        echo "服务运行端口:{$this->setting['host']}:{$this->setting['port']}" . PHP_EOL;

        return $this->server->start();
    }

    /**
     * 服务启动事件
     * @param $server
     * @return bool
     */
    public function onStart($server)
    {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server master worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-master');
        //记录进程id,脚本实现自动重启
        $pid = "{$this->server->master_pid}\n{$this->server->manager_pid}";
        file_put_contents($this->setting['pidfile'], $pid);
        return true;
    }

    /**
     * 进程启动事件
     * @param swoole_server $server
     */
    public function onManagerStart($server)
    {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server manager worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-manager');
    }

    /**
     * 关闭事件
     */
    public function onClose()
    {
        unlink($this->setting['pidfile']);
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server shutdown\n";
    }

    /**
     * 开始工作事件
     * @param $server
     * @param $workerId
     */
    public function onWorkerStart($server, $workerId)
    {
        if ($workerId >= $this->setting['worker_num']) {
            $this->setProcessName($server->setting['process_name'] . '-task');
        } else {
            $this->setProcessName($server->setting['process_name'] . '-event');
        }
    }

    /**
     * 工作停止事件
     * @param $server
     * @param $workerId
     */
    public function onWorkerStop($server, $workerId)
    {
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server[{$server->setting['process_name']}  worker:{$workerId} shutdown\n";
    }

    /**
     * 处理请求
     * @param $server
     * @param $fd
     * @param $from_id
     * @param $data
     * @return bool
     */
    public function onReceive($server, $fd, $from_id, $data)
    {
        if ($data == 'stats') {
            return $this->server->send($fd, var_export($this->server->stats(), true), $from_id);
        }
        $this->server->task($data);
        return true;

    }

    /**
     * 任务处理
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return array|bool|mixed|void
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
        $this->logger('[task data] ' . $data);
        $data = $this->parseData($data);
        if ($data === false) {
            return false;
        }
        foreach ($data as $worker) {
            if (!isset($worker['route']) || empty($worker['route'])) {
                continue;
            }
            $action = $worker["route"];
            $params = [];
            if (isset($worker['argv'])) {
                $params = $worker['argv'];
                if (!is_array($params)) {
                    $params = [strval($params)];
                }
            }
            try {
                $parts = $this->app->createController($action);
                if (is_array($parts)) {
                    $res = $this->app->runAction($action, $params);
                    $this->logger('[task result] ' . var_export($res, true));
                }
            } catch (Exception $e) {
                $this->logger($e);
            }
        }
        return $data;
    }

    /**
     * 解析data对象
     * @param array $data
     * @return array|bool|mixed
     */
    private function parseData($data)
    {
        $data = json_decode($data, true);
        $data = $data ?: [];

        if(empty($data)){
            return false;
        }

        if(isset($data['route'])){
            $data = [$data];
        }
        return $data;
    }

    /**
     * 解析onfinish数据
     * @param $data
     * @return bool|string
     */
    private function genFinishData($data)
    {
        if (!isset($data['finish']) || !is_array($data['finish'])) {
            return false;
        }
        return json_encode(['data' => $data['finish']]);
    }

    /**
     * 任务结束回调函数
     * @param $server
     * @param $taskId
     * @param $data
     * @return bool
     */
    public function onFinish($server, $taskId, $data)
    {

        $data = $this->genFinishData($data);

        if ($data !== false) {
            return $this->server->task($data);
        }

        return true;

    }

    /**
     * 记录日志 日志文件名为当前年月（date("Y-m")）
     * @param string $msg
     * @param string $logfile
     */
    public function logger($msg, $logfile = '')
    {
        if (empty($msg)) {
            return;
        }
        if (!is_string($msg)) {
            $msg = var_export($msg, true);
        }
        //日志内容
        $msg = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        //日志文件大小
        $maxSize = $this->setting['log_size'];
        //日志文件位置
        $file = $logfile ?: $this->setting['log_file'];
        //切割日志
        if (file_exists($file) && filesize($file) >= $maxSize) {
            $bak = $file . '-' . time();
            if (!rename($file, $bak)) {
                error_log("rename file:{$file} to {$bak} failed", 3, $file);
            }
        }
        error_log($msg, 3, $file);
    }
}

