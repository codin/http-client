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

    public function __construct(StreamFactoryInterface $streamFactory)
    {
        $this->stream = $streamFactory->createStream('');
        $this->boundary = uniqid('', true);
    }

    /**
     * @param string|array<string>
     */
    protected function write($data, string $newline = "\r\n"): void
    {
        $this->stream->write((is_array($data) ? implode($newline, $data) : $data) . $newline);
    }

    public function add(string $name, string $data, array $headers = []): void
    {
        if (!array_key_exists('content-disposition', array_change_key_case($headers))) {
            $headers['Content-Disposition'] = sprintf('form-data; name="%s"', $name);
        }

        if (!array_key_exists('content-length', array_change_key_case($headers))) {
            $headers['Content-Length'] = mb_strlen($data);
        }

        $this->write('--' . $this->boundary);

        foreach ($headers as $key => $value) {
            $this->write(sprintf('%s: %s', $key, $value));
        }

        $this->write(['', $data]);
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
