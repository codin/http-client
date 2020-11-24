Simple http client, zero dependencies


```php
$client = new Codin\HttpClient\HttpClient();
$request = $requestFactory->createRequest('get', '/health');
$response = $client->sendRequest($request);
```
