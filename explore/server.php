<?php

define('WEBPATH', __DIR__);

require __DIR__ . '/../libs/lib_config.php';

$protocal = new Swoole\Network\Protocol\HttpServer();
$protocal->loadSetting(__DIR__ . '/../examples/swoole.ini'); //加载配置文件
$protocal->setDocumentRoot(__DIR__);
$protocal->setLogger(new \Swoole\Log\EchoLog(true)); //Logger

$server = new Swoole\Network\Server('127.0.0.1', 9996);
$server->setProtocol($protocal);
$server->addListener('127.0.0.1', 9997);
//$server->addListener('192.168.1.104', 9997);

$server->run([
    'worker_num' => 1,
]);
