<?php

declare(strict_types=1);

namespace Codin\HttpClient;

use CurlHandle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

readonly class HttpClient implements ClientInterface
{
    public const VERSION = '1.0';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private array $options = [],
    ) {
    }

    private function parseHeaders(ResponseInterface $response, StreamInterface $headers): ResponseInterface
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

    private function buildResponse(StreamInterface $headers, StreamInterface $body): ResponseInterface
    {
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $response = $this->responseFactory->createResponse(200)->withBody($body);

        return $this->parseHeaders($response, $headers);
    }

    /**
     * @return array<int, mixed>
     */
    private function buildOptions(RequestInterface $request): array
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
            CURLOPT_COOKIEFILE => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
        ];

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

        if ($request->getBody()->getSize() > 0) {
            $size = $request->hasHeader('Content-Length')
                ? (int) $request->getHeaderLine('Content-Length')
                : null;

            $options[CURLOPT_UPLOAD] = true;

            // If the Expect header is not present, prevent curl from adding it
            if (!$request->hasHeader('Expect')) {
                $options[CURLOPT_HTTPHEADER][] = 'Expect:';
            }

            // cURL sometimes adds a content-type by default. Prevent this.
            if (!$request->hasHeader('Content-Type')) {
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type:';
            }

            if ($size !== null) {
                $options[CURLOPT_INFILESIZE] = $size;
                $request = $request->withoutHeader('Content-Length');
            }

            if ($request->getBody()->isSeekable() && $request->getBody()->tell() > 0) {
                $request->getBody()->rewind();
            }

            $options[CURLOPT_READFUNCTION] = static function ($session, $stream, int $chunk) use ($request): string {
                return $request->getBody()->read($chunk);
            };
        }

        return $options;
    }

    /**
     * @return array{0: StreamInterface, 1: StreamInterface}
     */
    private function prepareSession(RequestInterface $request, CurlHandle $session): array
    {
        curl_setopt_array($session, $this->buildOptions($request));

        $headers = $this->streamFactory->createStream('');

        curl_setopt(
            $session,
            CURLOPT_HEADERFUNCTION,
            static function ($session, string $data) use ($headers): int {
                return $headers->write($data);
            }
        );

        $body = $this->streamFactory->createStream('');

        curl_setopt(
            $session,
            CURLOPT_WRITEFUNCTION,
            static function ($session, string $data) use ($body): int {
                return $body->write($data);
            }
        );

        return [$headers, $body];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $session = curl_init();

        [$headers, $body] = $this->prepareSession($request, $session);

        $result = curl_exec($session);
        if (isset($this->options['metrics']) && is_callable($this->options['metrics'])) {
            $metrics = curl_getinfo($session);
            $this->options['metrics']($metrics);
        }
        $errorMessage = curl_error($session);
        $errorCode = curl_errno($session);
        curl_close($session);

        if (false === $result) {
            throw new Exceptions\TransportError($errorMessage, $errorCode, $request);
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
