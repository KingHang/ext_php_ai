<?php

namespace app\ai;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

abstract class Driver implements AiInterface
{
    protected Client $http;

    protected string $baseUrl;
    protected string $model;
    protected array $modelList;
    protected string $role = "You are a helpful assistant.";
    protected string $apiKey;

    public function __construct(array $options)
    {
        $this->http = new Client();
        if (!isset($options['apiKey']) || empty($options['apiKey'])) {
            throw new \Exception("请先设置大模型参数");
        }
        $this->apiKey = $options['apiKey'];
        if (isset($options['model']) && !empty($options['model'])) {
            $this->model = $options['model'];
        }
        if (isset($options['role']) && !empty($options['role'])) {
            $this->model = $options['role'];
        }
        if (isset($options['proxy']) && !empty($options['proxy'])) {
            $this->model = $options['proxy'];
        }
    }

    public function httpPostJson($uri, $data, $headers = [])
    {
        try {
            $resData = $this->http->request("POST", $uri, [
                "headers" => $headers,
                "json" => $data,
                'verify' => false
            ]);

            return json_decode($resData->getBody(), true);
        } catch (GuzzleException $e) {
            echo $e->getMessage();
        }
    }

    public function httpGet($uri, $query = [], $headers = [])
    {
        try {
            $resData = $this->http->request("GET", $uri, [
                "headers" => $headers,
                "query" => $query,
                'verify' => false
            ]);

            return json_decode($resData->getBody(), true);
        } catch (GuzzleException $e) {
            echo $e->getMessage();
        }
    }
}