<?php

namespace shuliangfu\async;


use Yii;
use yii\base\Component;
use yii\helpers\Json;

/**
 * This is just an example.
 */

class AsyncWorker extends Component
{
    public $options = [];


    public function init()
    {
        parent::init();
    }

    /**
     * 执行后台任务
     * @param array $data
     */
    public function run(array $data)
    {
        if(get_extension_funcs("swoole") === false){
            exit('swoole扩展没有安装');
        }

        $client = new \swoole_client(SWOOLE_SOCK_TCP,SWOOLE_SOCK_ASYNC);

        $data = Json::encode($data);


        $client->on('Connect', function ($cli) use ($data) {
            $cli->send($data);
            $cli->close();
        });

        $client->on('Receive', function( $cli, $data ) {});
        $client->on('Close', function ($cli) {});
        $client->on('Error', function(){});

        if (!$client->connect($this->options['host'], $this->options['port'], $this->options['client_timeout'])){
            exit("Error: connect server failed. code[{$client->errCode}]\n");
        }
    }
}
