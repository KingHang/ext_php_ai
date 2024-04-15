<?php
function getConfig($key = null)
{
    $i = 0;
    $config = [];
    do {
        $i++;
        $iniFile = ROOT_PATH . "/key.ini";
        if (!file_exists($iniFile)) {
            sleep(2);
            continue;
        }

        $configStr = file_get_contents($iniFile);
        $config = json_decode($configStr, true);
    } while ($i < 10);

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
    return json_decode($confStr, true);
}

function setAppConf($conf)
{
    $iniFile = ROOT_PATH . "/app.ini";
    return file_put_contents($iniFile, json_encode($conf));
}