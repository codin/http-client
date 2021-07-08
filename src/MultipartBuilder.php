<?php

declare(strict_types=1);

namespace Codin\HttpClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class MultipartBuilder
{
    protected StreamInterface $stream;

    protected string $boundary;

    public function __construct(StreamFactoryInterface $streamFactory, ?string $boundary = null)
    {
        $this->stream = $streamFactory->createStream('');
        $this->boundary = null === $boundary ? uniqid('', true) : $boundary;
    }

    protected function write(string $data, string $newline = "\r\n"): void
    {
        $this->stream->write($data . $newline);
    }

    public function add(string $name, string $data, array $headers = []): void
    {
        $headers = array_change_key_case($headers);

        if (!array_key_exists('content-disposition', $headers)) {
            $headers['content-disposition'] = sprintf('form-data; name="%s"', $name);
        }

        if (!array_key_exists('content-length', $headers)) {
            $headers['content-length'] = mb_strlen($data);
        }

        $this->write('--' . $this->boundary);

        foreach ($headers as $key => $value) {
            $this->write(sprintf('%s: %s', $key, $value));
        }

        $this->write('');

        $this->write($data);
    }

    public function attach(RequestInterface $request): RequestInterface
    {
        $this->write('--' . $this->boundary . '--');
        return $request
            ->withBody($this->stream)
            ->withHeader('Content-Type', sprintf('multipart/form-data; boundary="%s"', $this->boundary))
        ;
    }
}
