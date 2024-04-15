<?php

namespace app\controller;

use app\services\Message;
use app\WechatListen;
use support\Request;
use  FormBuilder\Factory\Elm;
use Workerman\Worker;

class IndexController
{
    public function index(Request $request)
    {
        $config = getAppConf();
        if ($request->method() == "POST") {
            setAppConf(array_merge($config, $request->post()));

            return json(['code' => 200, "msg" => "设置成功，请重启应用"]);
        } else {
            $action = '';
            $method = 'POST';

            $model = Elm::select('model', '模型')->options(array_values(Message::$model))->value($config['model'] ?? "1");
            $key = Elm::input("key", "apiKey")->value($config['key'] ?? "");
            $proxy = Elm::input("proxy", "代理地址")->value($config['proxy'] ?? "");
            $tokens = Elm::input("tokens", "TOKENS")->value($config["tokens"] ?? 0)->min(0)->info("可用TOKEN数量，0不限制");
            $user = Elm::input("user", "用户列表")->info("填写可以使用机器人的微信ID，多个英文逗号分隔")->value($config['user'] ?? "");

            //创建表单
            $form = Elm::createForm($action)->setMethod($method);
            $form->setTitle("AI助手设置");

            //添加组件
            $form->setRule([$model, $key, $proxy, $tokens, $user]);

            //生成表单页面
            return $form->view();
        }
    }
}
