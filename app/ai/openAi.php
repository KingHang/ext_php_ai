<?php

namespace app\ai;

use Exception;

class openAi
{
    /**
     * @var
     */
    private mixed $ai;

    private mixed $driver;

    /**
     * @throws Exception
     */
    public function __construct($options = [], $driver = '')
    {
        $this->driver = $driver;
        $class = '\\app\\ai\\driver\\' . ucfirst(strtolower($this->driver));
        $this->ai = new $class($options);
        if (!$this->ai) {
            throw new Exception("不存在的AI驱动：{$driver}");
        }
    }

    public function getAi()
    {
        return $this->ai;
    }
}