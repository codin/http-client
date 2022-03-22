# Simple http client

![version](https://img.shields.io/github/v/tag/codin/http-client)
![workflow](https://img.shields.io/github/workflow/status/codin/http-client/Composer)
![license](https://img.shields.io/github/license/codin/http-client)

Example

```php
$client = new Codin\HttpClient\HttpClient();
$request = $requestFactory->createRequest('get', '/health');
$response = $client->sendRequest($request);
```
