<?php

namespace app;

use app\ai\openAi;
use Workerman\Connection\AsyncTcpConnection;

class Message
{
    const MODEL_1 = "1";
    const MODEL_2 = "2";
    const MODEL_3 = "3";
    const MODEL_4 = "4";
    public static array $model = [
        self::MODEL_1 => ["value" => self::MODEL_1, "label" => "通义千问 qwen-turbo", 'model' => "qwen-turbo", "driver" => "Qwen"],
        self::MODEL_2 => ["value" => self::MODEL_2, "label" => "通义千问 qwen-plus", 'model' => "qwen-plus", "driver" => "Qwen"],
        self::MODEL_3 => ["value" => self::MODEL_3, "label" => "通义千问 qwen-max", 'model' => "qwen-max", "driver" => "Qwen"],
        self::MODEL_4 => ["value" => self::MODEL_4, "label" => "通义千问 qwen-max-longcontext", 'model' => "qwen-max-longcontext", "driver" => "Qwen"],
    ];

    const TALK_1 = "1";
    const TALK_2 = "2";
    public static array $talk = [
        self::TALK_1 => ["label" => "单轮会话", "value" => self::TALK_1],
        self::TALK_2 => ["label" => "多轮会话", "value" => self::TALK_2],
    ];

    const PACKAGE_1 = "1";
    const PACKAGE_2 = "2";
    const PACKAGE_3 = "3";
    const PACKAGE_4 = "4";
    public static array $package = [
        self::PACKAGE_1 => ["label" => "无限制", "value" => self::PACKAGE_1],
        self::PACKAGE_2 => ["label" => "限量套餐", "value" => self::PACKAGE_2],
        self::PACKAGE_3 => ["label" => "限时套餐", "value" => self::PACKAGE_3],
        self::PACKAGE_4 => ["label" => "限时限量套餐", "value" => self::PACKAGE_4],
    ];

    protected mixed $ai;

    protected string $key = "";
    protected string $name = "";

    public function __construct()
    {
        $this->initWs();
        $this->refreshConfig();
    }

    public function refreshConfig()
    {
        $config = getAppConf();
        $model = $config['model'] ?? 0;
        $key = $config['key'] ?? "";
        if ($model > 0 && !empty($key)) {
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

        $this->history = [];
    }

    public function onMessage($data, AsyncTcpConnection $conn)
    {
        // echo $data . PHP_EOL;
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

            $type = $msg['data']['type'] ?? 0;
            // 文字消息
            if ($type == 1) {
                $resData = $this->createAnswer($msg['data']['msg'] ?? "", $fromId);
                if ($resData == "") return false;

                $this->delayedReply($conf);

                $resMsg = $this->buildMsg($resData, $fromId, $pid);
                $this->sendMsg($resMsg, $conn);
            } elseif ($type == 3) {
                // 图片消息
            }
        }
    }

    protected function delayedReply($config)
    {
        if (!isset($config['delayedReply']) || $config['delayedReply'] == 0) {
            return true;
        }

        $delay = explode("-", $config['delayedReply']);
        if (count($delay) >= 2) {
            $rand = rand($delay[0], $delay[1]);
        } else {
            $rand = $delay[0];
        }

        sleep($rand);

        return true;
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

    public array $keywordReply = [
        "帮助" => ["type" => "func", "value" => "help", "desc" => "显示帮助信息"],
        "清空" => ["type" => "func", "value" => "clearHistory", "desc" => "清除多轮会话记录"],
        "余额" => ["type" => "func", "value" => "tokens", "desc" => "显示剩余可用tokens"],
    ];

    protected array $history = [];

    protected function clearHistory($message)
    {
        $this->history = [];
        return "已清除";
    }

    protected function help($message)
    {
        $text = "";
        foreach ($this->keywordReply as $key => $item) {
            $text .= $key . "  " . $item['desc'] . "\n";
        }

        return $text;
    }

    protected function tokens($message)
    {
        $config = getAppConf();

        return $config['tokens'] == 0 ? "未限制" : $config['tokens'];
    }

    private function createAnswer(mixed $param, $fromId)
    {
        $config = getAppConf();
        if (!isset($this->ai)) {
            return "请先设置相关参数";
        }

        // 关键词回复
        if (isset($this->keywordReply[$param])) {
            $reply = $this->keywordReply[$param];
            if ($reply['type'] == "func" && method_exists($this, $reply['value'])) {
                return $this->{$reply['value']}($param);
            }
        }

        $answer = $tips = "";

        // 套餐判断
        $package = $config['package'] ?? 1;
        if (in_array($package, [self::PACKAGE_2, self::PACKAGE_4])) {
            if ($config['tokens'] <= 0) {
                return "tips:您的套餐余额已不足，请充值！";
            }
            if ($config['tokens'] <= 500) {
                $tips = "tips:您的套餐余额已不足500，请及时充值！";
            }
        }
        if (in_array($package, [self::PACKAGE_3, self::PACKAGE_4])) {
            if (time() > strtotime($config['expire'])) {
                return "tips:您的套餐已过期，请续费！";
            }
        }

        // 会话处理
        $talk = $config['talk'];
        if ($talk == self::TALK_1) {
            $this->clearHistory("");
        }

        $res = $this->ai->getAi()->message($param, $this->history);

        if ($res['code'] == 0) {
            if (in_array($package, [self::PACKAGE_2, self::PACKAGE_4])) {
                $tokens = $config['tokens'] ?? 0;
                $total_tokens = $res['used'] ?? 0;
                $config['tokens'] = $tokens - $total_tokens;
                setAppConf($config);
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
            // echo "appKey不存在，请重试";
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