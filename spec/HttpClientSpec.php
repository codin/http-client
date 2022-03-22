<?php

namespace spec\Codin\HttpClient;

use Codin\HttpClient\Exceptions;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Process\Process;

class HttpClientSpec extends ObjectBehavior
{
    public function it_should_send_requests()
    {
        $url = 'http://localhost:8000';

        $process = new Process(['php', '-S', 'localhost:8000', 'mock.php']);
        $process->enableOutput();
        $process->start();
        $process->waitUntil(function (string $type, string $data): bool {
            return strpos($data, 'started') !== false;
        });

        if (!$process->isRunning()) {
            throw new \RuntimeException('Failed to start test server');
        }

        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        $this->beConstructedWith($psr17Factory, $psr17Factory);

        $request = $psr17Factory->createRequest('GET', $url.'/');
        $response = $this->sendRequest($request);
        $response->getStatusCode()->shouldReturn(200);

        $request = $psr17Factory->createRequest('GET', $url.'/get/503');
        $this->shouldThrow(Exceptions\ServerError::class)->duringSendRequest($request);

        $request = $psr17Factory->createRequest('GET', $url.'/get/404');
        $this->shouldThrow(Exceptions\ClientError::class)->duringSendRequest($request);

        $request = $psr17Factory->createRequest('GET', 'http://localhost:3001/');
        $this->shouldThrow(Exceptions\TransportError::class)->duringSendRequest($request);

        $request = $psr17Factory->createRequest('HEAD', $url);
        $response = $this->sendRequest($request);
        $response->getStatusCode()->shouldReturn(200);

        $payload = json_encode([
            'foo' => 'bar',
        ]);

        $request = $psr17Factory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($psr17Factory->createStream($payload));
        $response = $this->sendRequest($request);
        $response->getStatusCode()->shouldReturn(200);

        $request = $psr17Factory->createRequest('PUT', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', strlen($payload))
            ->withBody($psr17Factory->createStream($payload));
        $response = $this->sendRequest($request);
        $response->getStatusCode()->shouldReturn(200);

        $process->stop();
    }
}
