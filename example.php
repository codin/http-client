<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

$psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

$client = new Codin\HttpClient\HttpClient($psr17Factory, $psr17Factory);

$times = [];

for ($i = 1; $i < 100; $i++) {
    $request = $psr17Factory->createRequest('get', 'https://madebykieron.co.uk/');
    $start = microtime(true);
    $response = $client->sendRequest($request);
    $times[] = microtime(true) - $start;
}

$duration = round(array_sum($times)/count($times) * 1000, 2);
var_dump($duration);
