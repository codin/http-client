<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

$psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

$client = new Codin\HttpClient\HttpClient($psr17Factory, $psr17Factory);

$payload = json_encode([
    'foo' => 'bar',
]);

$request = $psr17Factory->createRequest('POST', 'https://httpbin.org/post')
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('Content-Length', strlen($payload))
    ->withHeader('Accept', 'application/json')
    ->withBody($psr17Factory->createStream($payload));

$response = $client->sendRequest($request);

echo $response->getStatusCode()."\n";

$request = $psr17Factory->createRequest('PUT', 'https://httpbin.org/put')
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('Content-Length', strlen($payload))
    ->withHeader('Accept', 'application/json')
    ->withBody($psr17Factory->createStream($payload));

$response = $client->sendRequest($request);

echo $response->getStatusCode()."\n";

$request = $psr17Factory->createRequest('PATCH', 'https://httpbin.org/patch')
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('Content-Length', strlen($payload))
    ->withHeader('Accept', 'application/json')
    ->withBody($psr17Factory->createStream($payload));

$response = $client->sendRequest($request);

echo $response->getStatusCode()."\n";

$request = $psr17Factory->createRequest('GET', 'https://httpbin.org/get');

$response = $client->sendRequest($request);

echo $response->getStatusCode()."\n";

$request = $psr17Factory->createRequest('DELETE', 'https://httpbin.org/delete')
    ->withHeader('Accept', 'application/json');

$response = $client->sendRequest($request);

echo $response->getStatusCode()."\n";

// ----

$request = $psr17Factory->createRequest('GET', 'https://httpbin.org/status/400');

try {
    $response = $client->sendRequest($request);
} catch (Codin\HttpClient\Exceptions\ClientError $e) {
    echo $e->getMessage()."\n";
    echo $e->getResponse()->getStatusCode()."\n";
}

$request = $psr17Factory->createRequest('GET', 'https://httpbin.org/status/500');

try {
    $response = $client->sendRequest($request);
} catch (Codin\HttpClient\Exceptions\ServerError $e) {
    echo $e->getMessage()."\n";
    echo $e->getResponse()->getStatusCode()."\n";
}
