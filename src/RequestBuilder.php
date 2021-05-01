<?php

declare(strict_types=1);

namespace Codin\HttpClient;

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
     * @param array $options['headers']
     * @param array $options['query']
     * @param StreamInterface $options['stream']
     * @param string $options['body']
     * @param array $options['json']
     * @param array $options['multipart']
     * @param array $options['form']
     */
    public function build(string $method, string $url, array $options): ServerRequestInterface
    {
        $request = $this->serverRequestFactory->createServerRequest(strtoupper($method), $url);

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if (isset($options['query'])) {
            $uri = $request->getUri()
                ->withQuery(http_build_query($options['query']));
            $request = $request->withUri($uri);
        }

        if (isset($options['stream'])) {
            $request = $request->withBody($options['stream']);
        }

        if (isset($options['body'])) {
            $body = $this->streamFactory->createStream($options['body']);
            $request = $request->withBody($body);
        }

        if (isset($options['json'])) {
            $json = json_encode($options['json']);
            if (false === $json) {
                throw new ErrorException('Failed to encode json payload');
            }
            $body = $this->streamFactory->createStream($json);
            $request = $request->withBody($body);
        }

        if (isset($options['multipart'])) {
            $boundaryName = bin2hex(random_bytes(32));
            $boundaries = [];
            foreach ($options['multipart'] as $name => $value) {
                $boundaries[] = '--' . $boundaryName;
                $boundaries[] = 'Content-Disposition: form-data; name="' . $name . '"';
                $boundaries[] = $value;
            }
            $boundaries[] = '--' . $boundaryName . '--';
            $encodedFormData = implode("\r\n", $boundaries);
            $body = $this->streamFactory->createStream($encodedFormData);
            $request = $request
                ->withBody($body)
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ;
        }

        if (isset($options['form'])) {
            $body = $this->streamFactory->createStream(http_build_query($options['form']));
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ;
        }

        return $request;
    }
}
