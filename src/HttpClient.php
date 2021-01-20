<?php

declare(strict_types=1);

namespace Codin\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class HttpClient implements ClientInterface
{
    public const VERSION = '1.0';

    protected ResponseFactoryInterface $responseFactory;

    protected StreamFactoryInterface $streamFactory;

    protected array $options;

    /**
     * @var resource
     */
    protected $session;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        array $options = []
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->options = $options;
        $this->session = curl_init();
    }

    public function __destruct()
    {
        if (is_resource($this->session)) {
            curl_close($this->session);
        }
    }

    protected function buildOptions(RequestInterface $request): array
    {
        $options = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => sprintf(
                'HttpClient/%s php/%s curl/%s',
                self::VERSION,
                phpversion(),
                (curl_version() ?: ['version' => null])['version']
            ),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ('POST' === $request->getMethod()) {
            $options[CURLOPT_POST] = true;
        } elseif ('HEAD' === $request->getMethod()) {
            $options[CURLOPT_NOBODY] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }

        if ($request->getProtocolVersion() === '1.1') {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        } elseif ($request->getProtocolVersion() === '2.0') {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
        } else {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
        }

        $headers = [];
        foreach ($request->getHeaders() as $name => $group) {
            $headers[] = sprintf('%s: %s', $name, implode('; ', $group));
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        // Prevent curl from sending its default Accept and Expect headers
        foreach (['Accept', 'Expect'] as $name) {
            if (!$request->hasHeader($name)) {
                $options[CURLOPT_HTTPHEADER][] = $name.':';
            }
        }

        if (in_array($request->getMethod(), ['PUT', 'POST', 'PATCH'])) {
            if ('POST' !== $request->getMethod()) {
                $options[CURLOPT_UPLOAD] = true;
            }

            if ($request->hasHeader('Content-Length')) {
                $options[CURLOPT_INFILESIZE] = $request->getHeader('Content-Length')[0];
            } elseif (!$request->hasHeader('Transfer-Encoding')) {
                $options[CURLOPT_HTTPHEADER][] = 'Transfer-Encoding: chunked';
            }

            if ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }

            $options[CURLOPT_READFUNCTION] = static function ($session, $stream, int $chunk) use ($request): string {
                return $request->getBody()->read($chunk);
            };
        }

        return $this->options + $options;
    }

    protected function parseHeaders(ResponseInterface $response, StreamInterface $headers): ResponseInterface
    {
        $data = rtrim((string) $headers);
        $parts = explode("\r\n\r\n", $data);
        $last = array_pop($parts);
        $lines = explode("\r\n", $last);
        $status = array_shift($lines);

        if (is_string($status) && strpos($status, 'HTTP/') === 0) {
            [$version, $status, $message] = explode(' ', substr($status, strlen('http/')), 3);
            $response = $response->withProtocolVersion($version)->withStatus((int) $status, $message);
        }

        return array_reduce($lines, static function (ResponseInterface $response, string $line): ResponseInterface {
            [$name, $value] = explode(':', $line, 2);
            return $response->withHeader($name, $value);
        }, $response);
    }

    protected function buildResponse(StreamInterface $headers, StreamInterface $body): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(200)->withBody($body);

        return $this->parseHeaders($response, $headers);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        curl_setopt_array($this->session, $this->buildOptions($request));

        $headers = $this->streamFactory->createStream('');

        curl_setopt(
            $this->session,
            CURLOPT_HEADERFUNCTION,
            static function ($session, string $data) use ($headers): int {
                return $headers->write($data);
            }
        );

        $body = $this->streamFactory->createStream('');

        curl_setopt(
            $this->session,
            CURLOPT_WRITEFUNCTION,
            static function ($session, string $data) use ($body): int {
                return $body->write($data);
            }
        );

        $result = curl_exec($this->session);
        curl_reset($this->session);

        if (false === $result) {
            throw new Exceptions\CurlError(sprintf(
                'cURL error (%s): %s',
                curl_errno($this->session),
                curl_error($this->session)
            ));
        }

        $response = $this->buildResponse($headers, $body);

        if ($response->getStatusCode() >= 500) {
            throw new Exceptions\ServerError($request, $response);
        }

        if ($response->getStatusCode() >= 400) {
            throw new Exceptions\ClientError($request, $response);
        }

        return $response;
    }
}
