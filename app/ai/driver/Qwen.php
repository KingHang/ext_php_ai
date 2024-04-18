<?php

namespace app\ai\driver;

use app\ai\AiException;
use app\ai\Driver;

class Qwen extends Driver
{
    protected string $baseUrl = "https://dashscope.aliyuncs.com";
    protected string $model = "qwen-turbo";
    protected array $modelList = [
        "qwen-turbo" => "/api/v1/services/aigc/text-generation/generation",
        "qwen-plus" => "/api/v1/services/aigc/text-generation/generation",
        "qwen-max" => "/api/v1/services/aigc/text-generation/generation",
        "qwen-max-longcontext" => "/api/v1/services/aigc/text-generation/generation",
    ];

    public function message(string $data, array &$history = [], bool $sse = false): array
    {
        try {
            $modelApi = $this->modelList[$this->model] ?? null;
            if (!$modelApi) {
                throw new AiException("模型不存在");
            }
            $messages = [[
                "role" => "system",
                "content" => $this->role,
            ]];

            foreach ($history as $item) {
                $messages[] = [
                    "role" => $item['role'],
                    "content" => $item['content']
                ];
            }

            $question = [
                "role" => "user",
                "content" => $data
            ];
            $messages[] = $question;
            $history[] = $question;
            $headers = [
                "Authorization" => "Bearer {$this->apiKey}"
            ];
            if ($sse) {
                $headers['Accept'] = "text/event-stream";
            }

            $resData = $this->httpPostJson($this->baseUrl . $modelApi, [
                "model" => $this->model,
                "input" => [
                    "messages" => $messages
                ]
            ], $headers);

            if (isset($resData['output']['text'])) {
                $history[] = ["role" => "system", "content" => $resData['output']['text']];

                return [
                    "code" => 0,
                    "data" => $resData,
                    "answer" => $resData['output']['text'],
                    "used" => $resData['usage']['total_tokens']
                ];
            } else {
                return [
                    "code" => 1,
                    "msg" => $resData['message'] ?? "错误"
                ];
            }
        } catch (AiException $ai) {
            return [
                "code" => 1,
                "msg" => $ai->getMessage()
            ];
        }
    }
}