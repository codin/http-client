<?php

declare(strict_types=1);

namespace Codin\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class HttpClient implements ClientInterface
{
    public const VERSION = '1.0';

    /**
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    protected $streamFactory;

    /**
     * @var resource
     */
    protected $session;

    /**
     * @var int
     */
    protected $timeout = 4;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->session = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->session);
    }

    protected function buildOptions(RequestInterface $request): array
    {
        $options = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => sprintf('HttpClient/%s php/%s curl/%s', self::VERSION, phpversion(), curl_version()['version']),
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [],
        ];

        $headers = [];
        foreach ($request->getHeaders() as $name => $group) {
            $headers[] = sprintf('%s: %s', $name, implode('; ', $group));
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        if (in_array($request->getMethod(), ['PUT', 'POST', 'PATCH'])) {
            $body = (string) $request->getBody();
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        } elseif ($request->getMethod() === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }

        return $options;
    }

    protected function parseHeaders(ResponseInterface $response, string $headers): ResponseInterface
    {
        $headerLines = explode("\r\n", trim($headers));
        $statusLine = array_shift($headerLines);

        if (preg_match('#HTTP/(?<version>[0-9\.]+) (?<status>[0-9]{3}) (?<message>.+)#i', $statusLine, $match)) {
            $response = $response->withProtocolVersion($match['version'])->withStatus($match['status'], $match['message']);
        }

        return array_reduce($headerLines, function (ResponseInterface $carry, string $line) {
            [$name, $value] = explode(':', $line);
            return $carry->withHeader($name, $value);
        }, $response);
    }

    protected function buildResponse(string $result, array $info): ResponseInterface
    {
        $headers = mb_substr($result, 0, $info['header_size']);
        $body = mb_substr($result, $info['header_size']);
        $stream = $this->streamFactory->createStream($body);

        $response = $this->responseFactory->createResponse($info['http_code'])
            ->withBody($stream);

        return $this->parseHeaders($response, $headers);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        curl_setopt_array($this->session, $this->buildOptions($request));

        $result = curl_exec($this->session);

        if (!is_string($result)) {
            throw new Exceptions\CurlError(sprintf('%s %s', curl_errno($this->session), curl_error($this->session)));
        }

        $info = curl_getinfo($this->session);

        return $this->buildResponse($result, $info);
    }
}
