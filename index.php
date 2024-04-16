<?php

use app\Message;
use FormBuilder\Factory\Elm;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

define("ROOT_PATH", realpath(__DIR__));

// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("http://0.0.0.0:40444");

// 启动1个进程对外提供服务
$http_worker->count = 1;

$http_worker->onWorkerStart = function(Worker $worker) {
    try {
        $worker->msg = new Message();
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        Worker::stopAll();
    }
};

// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onMessage = function (TcpConnection $connection, Request $request) use ($http_worker) {
    $config = getAppConf();
    if ($request->method() == "POST") {
        setAppConf(array_merge($config, $request->post()));

        $http_worker->msg->refreshConfig();
        return $connection->send(json_encode(['code' => 200, "msg" => "设置成功"]));
    } else {
        $action = '';
        $method = 'POST';

        $model = Elm::select('model', '模型')->options(array_values(Message::$model))->value($config['model'] ?? "1");
        $key = Elm::input("key", "apiKey")->value($config['key'] ?? "");
        $proxy = Elm::input("proxy", "代理地址")->value($config['proxy'] ?? "");
        $tokens = Elm::input("tokens", "TOKENS")->value($config["tokens"] ?? 0)->min(0)->info("可用TOKEN数量，0不限制");
        $user = Elm::input("user", "用户列表")->info("填写可以使用机器人的微信ID，多个用英文逗号分隔")->value($config['user'] ?? "");
        $delayedReply = Elm::input("delayedReply", "延迟回复")->info("填写数字固定时间延迟或1-3随机延迟，0不延迟，单位：秒")->value($config['delayedReply'] ?? "0");

        //创建表单
        $form = Elm::createForm($action)->setMethod($method);
        $form->setTitle("AI助手设置");

        //添加组件
        $form->setRule([$model, $key, $proxy, $tokens, $user, $delayedReply]);

        //生成表单页面
        return $connection->send($form->view());
    }
};

Worker::runAll();