<?php
use Messaging\sockets\Chat;
use Ratchet\Server\IoServer;

require dirname(__DIR__) . '/vendor/autoload.php';

$server = IoServer::factory(
    new Chat(),
    8787
);

$server->run();