<?php

use samejack\PHP\ArgvParser;

function getConfig($key = null)
{
    $config = [];

    $argvParser = new ArgvParser();
    $config = $argvParser->parseConfigs();
    if (count($config) <= 1) {
        $iniFile = ROOT_PATH . "/key.ini";
        if (!file_exists($iniFile)) {
            return "";
        } else {
            $configStr = file_get_contents($iniFile);
            $config = json_decode($configStr, true);
        }
    }

    if (is_null($key)) {
        return $config;
    } else {
        return $config[$key] ?? "";
    }
}

function getAppConf()
{
    $iniFile = ROOT_PATH . "/app.ini";
    if (!file_exists($iniFile)) return [];

    $confStr = file_get_contents($iniFile);
    return json_decode($confStr, true) ?: [];
}

function setAppConf($conf)
{
    $iniFile = ROOT_PATH . "/app.ini";
    return file_put_contents($iniFile, json_encode($conf));
}