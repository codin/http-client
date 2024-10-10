<?php

declare(strict_types=1);

namespace Codin\HttpClient;

use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class RequestBuilder
{
    protected ServerRequestFactoryInterface $serverRequestFactory;

    protected StreamFactoryInterface $streamFactory;

    public function __construct(
        ServerRequestFactoryInterface $serverRequestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->serverRequestFactory = $serverRequestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function build(string $method, string $url, array $options = []): RequestInterface
    {
        $request = $this->serverRequestFactory->createServerRequest(strtoupper($method), $url);

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        $encoding = isset($options['encoding']) && is_int($options['encoding']) ? $options['encoding'] : PHP_QUERY_RFC1738;

        if (isset($options['query']) && is_array($options['query'])) {
            $queryString = http_build_query($options['query'], encoding_type: $encoding);
            $uri = $request->getUri()->withQuery($queryString);
            $request = $request->withUri($uri);
        }

        if (isset($options['stream']) && $options['stream'] instanceof StreamInterface) {
            $request = $request->withBody($options['stream']);
        }

        if (isset($options['body']) && is_string($options['body'])) {
            $body = $this->streamFactory->createStream($options['body']);
            $request = $request->withBody($body);
        }

        if (isset($options['json'])) {
            $json = json_encode($options['json'], JSON_THROW_ON_ERROR);
            if (!is_string($json)) {
                throw new JsonException(json_last_error_msg(), json_last_error());
            }
            $body = $this->streamFactory->createStream($json);
            $request = $request->withBody($body);
        }

        if (isset($options['multipart']) && is_array($options['multipart'])) {
            $multipart = new MultipartBuilder($this->streamFactory);

            foreach ($options['multipart'] as $name => $value) {
                $multipart->add($name, $value);
            }

            $request = $multipart->attach($request);
        }

        if (isset($options['form']) && is_array($options['form'])) {
            $queryString = http_build_query($options['form'], encoding_type: $encoding);
            $body = $this->streamFactory->createStream($queryString);
            $request = $request
                ->withBody($body)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ;
        }

        return $request;
    }
}
