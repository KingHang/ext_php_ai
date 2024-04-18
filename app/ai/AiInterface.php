<?php

namespace app\ai;

interface AiInterface
{
    public function message(string $data, array &$history = [], bool $sse = false): array;
}