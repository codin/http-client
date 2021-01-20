<?php

require __DIR__ . '/vendor/autoload.php';

if ($_SERVER['REQUEST_URI'] === '/get/503') {
    header(sprintf('%s %u %s', $_SERVER['SERVER_PROTOCOL'], 503, 'Internal Server Error'));
} elseif ($_SERVER['REQUEST_URI'] === '/get/404') {
    header(sprintf('%s %u %s', $_SERVER['SERVER_PROTOCOL'], 404, 'Not Found'));
} else {
    header(sprintf('%s %u %s', $_SERVER['SERVER_PROTOCOL'], 200, 'OK'));
}

echo "Received {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} request\n";
