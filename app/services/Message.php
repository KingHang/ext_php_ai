<?php

namespace app\services;

use extend\OpenAI\Client as Ai;
use Workerman\Connection\AsyncTcpConnection;

class Message
{
    public static array $model = [
        1 => ["value" => "1", "label" => "通义千问 qwen-turbo", 'model' => "qwen-turbo", "driver" => "Qianwen"]
    ];

    protected mixed $ai;

    public function __construct()
    {
        $config = getAppConf();
        $model = $config['model'] ?? 0;
        $key = $config['key'] ?? "";
        if ($config['model'] > 0 && !empty($key)) {
            $this->ai = Ai::getInstance([
                "appid" => $key,
                "proxy" => "",
                "model" => self::$model[$model]['model'],
            ], self::$model[$model]['driver']);
        }
    }

    public function onMessage($data, AsyncTcpConnection $conn)
    {
        echo $data . PHP_EOL;
        $msg = json_decode($data, true);
        $method = $msg['method'];
//        if (isset($msg['req'])) return $this->req['callback']($msg);
//        if (isset($msg['cb'])) {
//            $cbId = $msg['cb'];
//
//
//            $res = $this->onRequest($msg);
//            $res['cb'] = $cbId;
//
//            return $this->sendMsg($res, $conn);
//        }

//        if ($msg['type'] == 724) {
//            //登录消息,启动插件之后微信才登录进来就会有
//            return $this->sayHello();
//        }
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

//        //有人加群，加群type=702 但是没有邀请人id，用群成员信息更新来实现
//        if (obj.type == 701) {
//            for (let user of obj.data.member) {
//                let r
//                if (user.new) {
//                    //这是新用户
//                    r = await Send({ method: 'sendText', wxid: obj.data.wxid, msg: `欢迎新伙伴${user.wxid}[${user.nickName}],来自${user.invite}[${user.inviteName2 || user.inviteName}]的邀请` })
//                } else {
//                    r = await Send({ method: 'sendText', atid: user.wxid, wxid: obj.data.wxid, msg: '来聊聊啊' })
//                }
//                console.log('通知结果', r)
//            }
//            return '成员信息更新完毕'
//        }
//        //有人退群
//        if (obj.type == 703) {
//            for (let user of obj.data.member) {
//                let r = await Send({ method: 'sendText', wxid: obj.data.wxid, msg: `小伙伴${user.wxid}[${user.nickName2 || user.nickName}]离开了我们` })
//                console.log('通知结果', r)
//            }
//            return '成员退出更新完毕'
//        }

        //处理收到消息的
//        if ($msg['data']['from'])
//        if (obj.data.fromid == obj.myid) {
//            obj.data.fromid = obj.data.toid
//            console.log('收到自己的消息,已转换fromid为toid')
//        }
//        let fd = await FuDuJi(obj)//复读吧

    }

    public function sayHello()
    {
        return ['method' => 'sendText', 'wxid' => 'filehelper', 'msg' => '您的私人复读机已上线'];
    }

    public function onRequest($obj)
    {
        return ['msg' => '收到了'];
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

//    protected array $rule = [
//        "帮助" => ["type" => "func", "func" => "help"],
//        "模型" => ["type" => "func", "func" => "help"],
//    ];

    protected array $history = [];

    private function createAnswer(mixed $param, $fromId)
    {
        $config = getAppConf();
        if (!isset($this->ai)) {
            return "请先设置相关参数";
        }
        if ($param == "tokens") {
            return $config['tokens'];
        }
//        if ($param == "清理消息") {
//            unset($this->history[$fromId]);
//            return "清理成功";
//        }
//        $this->history[$fromId][] = ["role" => "user", "content" => $param];

        $res = $this->ai->getAi()->smart([
            // "context" => $this->history[$fromId] ?? [],
            "msg" => $param
        ]);

        echo json_encode($res) . PHP_EOL;
        $tokens = $config['tokens'] ?? 0;
        $tips = "";
        if ($tokens > 0) {
            $total_tokens = $res['usage']['total_tokens'] ?? 0;
            $config['tokens'] = $tokens - $total_tokens;
            setAppConf($config);
            if ($config['tokens'] < 500) {
                $tips = "tips:您的tokens已不足500，请及时充值！";
            }
        }

        $answer = $res['answer'] ?? "";
//        if ($answer) {
//            $this->history[$fromId][] = ["role" => "system", "content" => $answer];
//        }

        return $answer . $tips;
    }
}