Simple http client, zero dependencies, better performance than Guzzle and HttpPlug with PSR-15

Client 0.29ms avg 99th
HttpPlug 0.43ms avg 99th
Guzzle 0.82ms avg 99th


```php

$auth = new Uri('https://api.oneutilitybill.co/oauth/token');
$api = new Uri('https://api.oneutilitybill.co/');

$client = new Shovel();

$request = $requestFactory->createRequest('get', '/health');
$response = $client->sendRequest($request);
$response = $client->get('/health');