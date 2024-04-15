<?php

namespace app;

use app\services\Message;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Worker;

class WechatListen
{
    protected string $key = "";
    protected string $name = "";

    protected AsyncTcpConnection $link;
    public static Message $msg;

    public function onWorkerStart($worker)
    {
        try {
            self::$msg = new Message();

            $this->initWs();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function initWs()
    {
        if ($this->key == "") {
            $this->getKey();
        }

        if (isset($this->link)) {
            $this->link->close();
        }

        if (!empty($this->key)) {
            $this->link = new AsyncTcpConnection("ws://127.0.0.1:8202/wx?name={$this->name}&key={$this->key}");
            $this->link->onWebSocketConnect = function(AsyncTcpConnection $con) {
                echo "可以开始干饭了";
            };
            // 当收到消息时
            $this->link->onMessage = function(AsyncTcpConnection $con, $data) {
                self::$msg->onMessage($data, $con);
            };

            $this->link->onClose = function(AsyncTcpConnection $con) {
                $error = "被服务端取消了,可能是密钥或者应用状态不对,已有其他连接,请去个人中心查看应用";
                // echo $error;
                throw new \Exception($error);
            };

            $this->link->connect();
        } else {
            throw new \Exception("appKey不存在，请重试");
        }
    }

    public function getKey()
    {
        $config = getConfig();
        $this->key = $config['key'] ?? "";
        $this->name = $config['name'] ?? "";
    }
}