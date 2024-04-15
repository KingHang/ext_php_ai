<?php

namespace app;

use app\ai\openAi;
use Workerman\Connection\AsyncTcpConnection;

class Message
{
    public static array $model = [
        1 => ["value" => "1", "label" => "通义千问 qwen-turbo", 'model' => "qwen-turbo", "driver" => "Qwen"]
    ];

    protected mixed $ai;

    protected string $key = "";
    protected string $name = "";

    public function __construct()
    {
        $this->refreshConfig();
    }

    public function refreshConfig()
    {
        $config = getAppConf();
        $model = $config['model'] ?? 0;
        $key = $config['key'] ?? "";
        if ($config['model'] > 0 && !empty($key)) {
            try {
                $this->ai = new openAi([
                    "apiKey" => $key,
                    "proxy" => $config['proxy'],
                    "model" => self::$model[$model]['model'],
                ], self::$model[$model]['driver']);
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }

        $this->initWs();
    }

    public function onMessage($data, AsyncTcpConnection $conn)
    {
        echo $data . PHP_EOL;
        $msg = json_decode($data, true);
        $method = $msg['method'];

        $conf = getAppConf();
        $userList = isset($conf['user']) ? explode(",", $conf['user']) : [];
        $fromId = $msg['data']['fromid'] ?? "";
        $pid = $msg['pid'];
        if (empty($fromId) || !in_array($fromId, $userList)) {
            return false;
        }

        if ($method == "newmsg") {
            // 不适用群消息
            // 转发到ai获取结果

            $resData = $this->createAnswer($msg['data']['msg'] ?? "", $fromId);
            if ($resData == "") return false;

            sleep(rand(1, 3));

            $resMsg = $this->buildMsg($resData, $fromId, $pid);
            $this->sendMsg($resMsg, $conn);
        }
    }

    protected function buildMsg($content, $toId, $pid)
    {
        // { "method": "sendText", "wxid": "filehelper", "msg": "https://www.baidu.com/", "atid": "", "pid": 0 } 艾特消息 { "method": "sendText", "wxid": "23942162341@chatroom", "msg": "@昵称1 @昵称2 艾特消息", "atid": "wxid_xxx1|wxid_xxx2", "pid": 0 }
        return [
            "method" => "sendText",
            "wxid" => $toId,
            "msg" => $content,
            "pid" => $pid
        ];
    }

    public function sendMsg($data, AsyncTcpConnection $conn)
    {
        return $conn->send(json_encode($data));
    }

    private function createAnswer(mixed $param, $fromId)
    {
        $config = getAppConf();
        if (!isset($this->ai)) {
            return "请先设置相关参数";
        }
        if ($param == "tokens") {
            return $config['tokens'];
        }

        $res = $this->ai->getAi()->message($param);

        echo json_encode($res) . PHP_EOL;
        $answer = $tips = "";
        if ($res['code'] == 0) {
            $tokens = $config['tokens'] ?? 0;
            if ($tokens > 0) {
                $total_tokens = $res['data']['usage']['total_tokens'] ?? 0;
                $config['tokens'] = $tokens - $total_tokens;
                setAppConf($config);
                if ($config['tokens'] < 500) {
                    $tips = "tips:您的tokens已不足500，请及时充值！";
                }
            }
            $answer = $res['answer'] ?? "";
        } else {
            $tips = "tips:" . $res['msg'];
        }

        return $answer . $tips;
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
            $this->link->onWebSocketConnect = function (AsyncTcpConnection $con) {
                echo "连接成功" . PHP_EOL;
            };
            // 当收到消息时
            $this->link->onMessage = function (AsyncTcpConnection $con, $data) {
                self::onMessage($data, $con);
            };

            $this->link->onClose = function (AsyncTcpConnection $con) {
                $error = "被服务端取消了,可能是密钥或者应用状态不对,已有其他连接,请去个人中心查看应用" . PHP_EOL;
                echo $error;
                // throw new \Exception($error);
            };

            $this->link->connect();
        } else {
            echo "appKey不存在，请重试";
            // throw new \Exception("appKey不存在，请重试");
        }
    }

    public function getKey()
    {
        $config = getConfig();
        $this->key = $config['key'] ?? "";
        $this->name = $config['name'] ?? "";
    }
}