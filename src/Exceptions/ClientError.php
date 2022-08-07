<?php

declare(strict_types=1);

namespace Codin\HttpClient\Exceptions;

use ErrorException;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ClientError extends ErrorException implements RequestExceptionInterface
{
    protected RequestInterface $request;

    protected ?ResponseInterface $response = null;

    public function __construct(RequestInterface $request, ?ResponseInterface $response = null)
    {
        $this->request = $request;
        $this->response = $response;
        parent::__construct($this->createMessage(), $response instanceof ResponseInterface ? $response->getStatusCode() : 0);
    }

    protected function createMessage(): string
    {
        if (!$this->response instanceof ResponseInterface) {
            return sprintf(
                'Http request "%s %s" failed to get a response',
                $this->request->getMethod(),
                $this->request->getUri()
            );
        }

        $message = sprintf(
            'Http request "%s %s" returned %u response',
            $this->request->getMethod(),
            $this->request->getUri(),
            $this->response->getStatusCode()
        );

        if (strpos($this->response->getHeaderLine('Content-Type'), '/json') !== false) {
            $data = json_decode((string) $this->response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data) && (isset($data['title']) || isset($data['detail']))) {
                $separator = isset($data['title'], $data['detail']) ? "\n\n" : '';
                $message = ($data['title'] ?? '').$separator.($data['detail'] ?? '');
            }
        }

        return $message;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
