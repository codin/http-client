<?php

declare(strict_types=1);

namespace Codin\HttpClient\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ClientError extends TransportError
{
    protected RequestInterface $request;

    protected ResponseInterface $response;

    public function __construct(RequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;

        $code = $response->getStatusCode();
        $message = sprintf(
            'Http request "%s %s" returned %u response',
            $request->getMethod(),
            $request->getUri(),
            $code
        );

        if (strpos($this->response->getHeaderLine('Content-Type'), '/json') !== false) {
            $data = json_decode((string) $this->response->getBody(), true);
            if (is_array($data) && (isset($data['title']) || isset($data['detail']))) {
                $separator = isset($data['title'], $data['detail']) ? "\n\n" : '';
                $message = ($data['title'] ?? '').$separator.($data['detail'] ?? '');
            }
        }

        parent::__construct($message, $code);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
