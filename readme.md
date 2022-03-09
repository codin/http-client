# Simple http client

Example

```php
$client = new Codin\HttpClient\HttpClient();
$request = $requestFactory->createRequest('get', '/health');
$response = $client->sendRequest($request);
```
